<?php
/**
 * Select packages from a Composer repository.
 *
 * @since   0.1.0
 * @license GPL-2.0-or-later
 * @package Pressody
 */

/*
 * This file is part of a Pressody module.
 *
 * This Pressody module is free software: you can redistribute it and/or modify it under the terms of the
 * GNU General Public License as published by the Free Software Foundation, either version 2 of the License,
 * or (at your option) any later version.
 *
 * This Pressody module is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
 * without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with this Pressody module.
 * If not, see <https://www.gnu.org/licenses/>.
 *
 * Copyright (c) 2021, 2022 Vlad Olaru (vlad@thinkwritecode.com)
 */

declare( strict_types=1 );

/*
 * This file is part of composer/satis.
 *
 * (c) Composer <https://github.com/composer>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Pressody\Retailer\Client;

use Composer\Composer;
use Composer\DependencyResolver\DefaultPolicy;
use Composer\DependencyResolver\Pool;
use Composer\DependencyResolver\Request;
use Composer\DependencyResolver\Solver;
use Composer\DependencyResolver\SolverProblemsException;
use Composer\IO\IOInterface;
use Composer\Json\JsonFile;
use Composer\Package\AliasPackage;
use Composer\Package\BasePackage;
use Composer\Package\Link;
use Composer\Package\Loader\ArrayLoader;
use Composer\Package\PackageInterface;
use Composer\Package\Version\VersionSelector;
use Composer\Repository\ComposerRepository;
use Composer\Repository\ConfigurableRepositoryInterface;
use Composer\Repository\PlatformRepository;
use Composer\Repository\RepositoryInterface;
use Composer\Repository\RepositorySet;
use Composer\Semver\Constraint\MatchAllConstraint;
use Composer\Semver\VersionParser;
use Composer\Util\Filesystem;

class ComposerPackageSelection {
	/** @var IOInterface The output Interface. */
	protected $output;
	protected $io;

	/** @var bool Skips Exceptions if true. */
	protected $skipErrors;

	/** @var string packages.json file name. */
	private $filename;

	/** @var array Array of additional repositories for dependencies */
	private $depRepositories;

	/** @var bool Selects All Packages if true. */
	private $requireAll;

	/** @var bool Add required dependencies if true. */
	private $requireDependencies;

	/** @var bool required dev-dependencies if true. */
	private $requireDevDependencies;

	/** @var bool do not build packages only dependencies */
	private $onlyDependencies;

	/** @var bool only resolve best candidates in dependencies */
	private $onlyBestCandidates;

	/** @var bool Filter dependencies if true. */
	private $requireDependencyFilter;

	/** @var string Minimum stability accepted for Packages in the list. */
	private $minimumStability;

	/** @var array Minimum stability accepted by Package. */
	private $minimumStabilityPerPackage;

	/** @var bool https://getcomposer.org/doc/04-schema.md#prefer-stable */
	private $preferStable;

	/** @var bool This is useful for testing purposes when you want to select the lowest possible packages that match the requirements. */
	private $preferLowest;

	/**
	 * If this is set to true, all platform requirements are ignored
	 * If this is set to false, no platform requirements are ignored
	 * If this is set to string[], those packages will be ignored
	 *
	 * @var string[]|bool
	 */
	private $ignorePlatformReqs;

	/** @var array The active package filter to merge. */
	private $packagesFilter = [];

	/** @var string|null The active repository filter to merge. */
	private $repositoryFilter;

	/** @var bool Apply the filter also for resolving dependencies. */
	private $repositoryFilterDep;

	/** @var PackageInterface[] The selected packages from config */
	private $selected = [];

	/** @var array A list of packages marked as abandoned */
	private $abandoned = [];

	/** @var array A list of blacklisted package/constraints. */
	private $blacklist = [];

	/** @var array|null A list of package types. If set only packages with one of these types will be selected */
	private $includeTypes;

	/** @var array A list of package types that will not be selected */
	private $excludeTypes = [];

	/** @var array|bool Patterns from strip-hosts. */
	private $stripHosts = false;

	/** @var string The prefix of the distURLs when using archive. */
	private $archiveEndpoint;

	/** @var string The homepage - needed to get the relative paths of the providers */
	private $homepage;

	public function __construct( IOInterface $io, string $outputDir, array $config, bool $skipErrors ) {
		$this->io         = $io;
		$this->skipErrors = $skipErrors;
		$this->filename   = $outputDir . '/packages.json';

		$this->fetchOptions( $config );
	}

	public function setRepositoryFilter( ?string $repositoryFilter, bool $forDependencies = false ): void {
		$this->repositoryFilter    = $repositoryFilter;
		$this->repositoryFilterDep = (bool) $forDependencies;
	}

	public function hasRepositoryFilter(): bool {
		return null !== $this->repositoryFilter;
	}

	public function hasBlacklist(): bool {
		return count( $this->blacklist ) > 0;
	}

	public function hasTypeFilter(): bool {
		return null !== $this->includeTypes || count( $this->excludeTypes ) > 0;
	}

	public function setPackagesFilter( array $packagesFilter = [] ): void {
		$this->packagesFilter = $packagesFilter;
	}

	public function hasFilterForPackages(): bool {
		return count( $this->packagesFilter ) > 0;
	}

	/**
	 * @param Composer $composer
	 * @param bool     $verbose
	 *
	 * @throws \Exception
	 * @return PackageInterface[]
	 */
	public function select( Composer $composer, bool $verbose ): array {
		// run over all packages and store matching ones
		$this->io->write( '<info>Scanning packages</info>' );

		$repos = $initialRepos = $composer->getRepositoryManager()->getRepositories();

		$stabilityFlags = array_map( function ( $value ) {
			return BasePackage::$stabilities[ $value ];
		}, $this->minimumStabilityPerPackage );

		$repositorySet = new RepositorySet( $this->minimumStability, $stabilityFlags );

		if ( $this->hasRepositoryFilter() ) {
			$repos = $this->filterRepositories( $repos );

			if ( 0 === count( $repos ) ) {
				throw new \InvalidArgumentException( sprintf( 'Specified repository url "%s" does not exist.', $this->repositoryFilter ) );
			}

			if ( count( $repos ) > 1 ) {
				throw new \InvalidArgumentException( sprintf( 'Found more than one repository for url "%s".', $this->repositoryFilter ) );
			}
		}

		if ( $this->hasFilterForPackages() ) {
			$repos = $this->filterPackages( $repos );

			if ( 0 === count( $repos ) ) {
				throw new \InvalidArgumentException( sprintf( 'Could not find any repositories config with "name" matching your package(s) filter: %s', implode( ', ', $this->packagesFilter ) ) );
			}
		}

		foreach ( $repos as $repo ) {
			$repositorySet->addRepository( $repo );
		}

		// determine the required packages
		$rootLinks = $this->requireAll ? $this->getAllLinks( $repos, $this->minimumStability, $verbose ) : $this->getFilteredLinks( $composer );

		// creating requirements request
		$request = new Request();
		// Add the root links to the list of required packages.
		foreach ( $rootLinks as $link ) {
			$request->requireName( $link->getTarget(), $link->getConstraint() );
		}
		// Create the pool of required packages.
		$pool = $repositorySet->createPool( $request, $this->io );

		// We will try and solve the dependencies to see if all is well.
		if ( $this->requireDependencies || $this->requireDevDependencies ) {
			$policy = new DefaultPolicy( $this->preferStable, $this->preferLowest );
			// solve dependencies
			$solver = new Solver( $policy, $pool, $this->io );
			try {
				$lockTransaction = $solver->solve( $request, $this->ignorePlatformReqs );
				$solver          = null;
				// @todo Investigate if we could be much more elegant by using the solver result instead of selecting links for root and deps.
			} catch ( SolverProblemsException $e ) {
				$message = 'Could not solve the root package dependencies. Here are the reasons: ' . PHP_EOL;
				$message .= $e->getPrettyString( $repositorySet, $request, $pool, $this->io->isVerbose() );

				throw new \Exception( $message, 0, $e );
			}
		}

		// select the required packages and determine dependencies
		$depsLinks = $this->selectLinks( $pool, $repositorySet, $rootLinks, true, $verbose );

		if ( $this->requireDependencies || $this->requireDevDependencies ) {
			// dependencies of required packages might have changed and be part of filtered repos
			if ( $this->hasRepositoryFilter() && true !== $this->repositoryFilterDep ) {
				$this->addRepositories( $pool, \array_filter( $initialRepos, function ( $r ) use ( $repos ) {
					return false === \in_array( $r, $repos );
				} ) );
			}

			// additional repositories for dependencies
			if ( ! $this->hasRepositoryFilter() || true !== $this->repositoryFilterDep ) {
				$this->addRepositories( $pool, $this->getDepRepos( $composer ) );
			}

			// select dependencies
			$this->selectLinks( $pool, $repositorySet, $depsLinks, false, $verbose );
		}

		$this->setSelectedAsAbandoned();

		$this->pruneBlacklisted( $pool, $verbose );
		$this->pruneByType( $verbose );

		ksort( $this->selected, SORT_STRING );

		return $this->selected;
	}

	/**
	 * @return PackageInterface[]
	 */
	public function clean(): array {
		$this->applyStripHosts();

		return $this->selected;
	}

	/**
	 * @return PackageInterface[]
	 */
	public function load(): array {
		$packages     = [];
		$rootJsonFile = new JsonFile( $this->filename );
		$dirname      = dirname( $this->filename );

		if ( ! $rootJsonFile->exists() ) {
			return $packages;
		}

		$loader     = new ArrayLoader();
		$rootConfig = $rootJsonFile->read();
		$includes   = [];

		if ( isset( $rootConfig['includes'] ) && is_array( $rootConfig['includes'] ) ) {
			$includes = $rootConfig['includes'];
		}

		if ( isset( $rootConfig['providers'] ) && is_array( $rootConfig['providers'] ) && isset( $rootConfig['providers-url'] ) ) {
			$baseUrl       = $this->homepage ? parse_url( rtrim( $this->homepage, '/' ), PHP_URL_PATH ) . '/' : null;
			$baseUrlLength = strlen( $baseUrl );

			foreach ( $rootConfig['providers'] as $package => $provider ) {
				$file = str_replace( [ '%package%', '%hash%' ], [
					$package,
					$provider['sha256'],
				], $rootConfig['providers-url'] );

				if ( $baseUrl && substr( $file, 0, $baseUrlLength ) === $baseUrl ) {
					$file = substr( $file, $baseUrlLength );
				}

				$includes[ $file ] = $provider;
			}
		}

		foreach ( array_keys( $includes ) as $file ) {
			$includedJsonFile = new JsonFile( $dirname . '/' . $file );

			if ( ! $includedJsonFile->exists() ) {
				$this->io->write( sprintf(
					'<error>File \'%s\' does not exist, defined in "includes" in \'%s\'</error>',
					$includedJsonFile->getPath(),
					$rootJsonFile->getPath()
				) );

				continue;
			}

			$includedConfig = $includedJsonFile->read();

			if ( ! isset( $includedConfig['packages'] ) || ! is_array( $includedConfig['packages'] ) ) {
				continue;
			}

			$includedPackages = $includedConfig['packages'];

			foreach ( $includedPackages as $name => $versions ) {
				if ( ! is_array( $versions ) ) {
					continue;
				}

				foreach ( $versions as $package ) {
					if ( ! is_array( $package ) ) {
						continue;
					}

					if ( isset( $package['name'] ) && in_array( $package['name'], $this->packagesFilter ) ) {
						continue;
					}

					$package = $loader->load( $package );

					if ( $package instanceof AliasPackage ) {
						$package = $package->getAliasOf();
					}

					$packages[ $package->getUniqueName() ] = $package;
				}
			}
		}

		return $packages;
	}

	private function fetchOptions( array $config ): void {
		$this->depRepositories = $config['repositories-dep'] ?? [];

		$this->requireAll              = isset( $config['require-all'] ) && true === $config['require-all'];
		$this->requireDependencies     = isset( $config['require-dependencies'] ) && true === $config['require-dependencies'];
		$this->requireDevDependencies  = isset( $config['require-dev-dependencies'] ) && true === $config['require-dev-dependencies'];
		$this->onlyDependencies        = isset( $config['only-dependencies'] ) && true === $config['only-dependencies'];
		$this->onlyBestCandidates      = isset( $config['only-best-candidates'] ) && true === $config['only-best-candidates'];
		$this->requireDependencyFilter = (bool) ( $config['require-dependency-filter'] ?? true );

		if ( ! $this->requireAll && ! isset( $config['require'] ) ) {
			$this->io->write( 'No explicit requires defined, enabling require-all' );
			$this->requireAll = true;
		}

		$this->minimumStability           = $config['minimum-stability'] ?? 'dev';
		$this->minimumStabilityPerPackage = $config['minimum-stability-per-package'] ?? [];
		$this->preferStable               = $config['prefer-stable'] ?? true;
		$this->preferLowest               = $config['prefer-lowest'] ?? false;
		$this->ignorePlatformReqs         = $config['ignore-platform-reqs'] ?? false;
		$this->abandoned                  = $config['abandoned'] ?? [];
		$this->blacklist                  = $config['blacklist'] ?? [];
		$this->includeTypes               = $config['include-types'] ?? null;
		$this->excludeTypes               = $config['exclude-types'] ?? [];

		$this->stripHosts      = $this->createStripHostsPatterns( $config['strip-hosts'] ?? false );
		$this->archiveEndpoint = isset( $config['archive']['directory'] ) ? ( $config['archive']['prefix-url'] ?? $config['homepage'] ) . '/' : null;

		$this->homepage = $config['homepage'] ?? null;
	}

	/**
	 * @param array|false $stripHostsConfig
	 *
	 * @return array|false
	 */
	private function createStripHostsPatterns( $stripHostsConfig ) {
		if ( ! is_array( $stripHostsConfig ) ) {
			return $stripHostsConfig;
		}

		$patterns = [];

		foreach ( $stripHostsConfig as $entry ) {
			if ( ! strlen( $entry ) ) {
				continue;
			}

			if ( '/private' === $entry || '/local' === $entry ) {
				$patterns[] = [ $entry ];
				continue;
			} elseif ( false !== strpos( $entry, ':' ) ) {
				$type = 'ipv6';
				if ( ! defined( 'AF_INET6' ) ) {
					$this->io->write( '<error>Unable to use IPv6.</error>' );
					continue;
				}
			} elseif ( 0 === preg_match( '#[^/.\\d]#', $entry ) ) {
				$type = 'ipv4';
			} else {
				$type       = 'name';
				$host       = '#^(?:.+\.)?' . preg_quote( $entry, '#' ) . '$#ui';
				$patterns[] = [ $type, $host ];
				continue;
			}

			@list( $host, $mask ) = explode( '/', $entry, 2 );

			$host = @inet_pton( $host );

			if ( false === $host || (int) $mask != $mask ) {
				$this->io->write( sprintf( '<error>Invalid subnet "%s"</error>', $entry ) );
				continue;
			}

			$host = unpack( 'N*', $host );

			if ( null === $mask ) {
				$mask = 'ipv4' === $type ? 32 : 128;
			} else {
				$mask = (int) $mask;

				if ( $mask < 0 || ( 'ipv4' === $type && $mask > 32 ) || ( 'ipv6' === $type && $mask > 128 ) ) {
					continue;
				}
			}

			$patterns[] = [ $type, $host, $mask ];
		}

		return $patterns;
	}

	private function applyStripHosts(): void {
		if ( false === $this->stripHosts ) {
			return;
		}

		foreach ( $this->selected as $uniqueName => $package ) {
			$sources = [];

			if ( $package->getSourceType() ) {
				$sources[] = 'source';
			}

			if ( $package->getDistType() ) {
				$sources[] = 'dist';
			}

			foreach ( $sources as $index => $type ) {
				$url = 'source' === $type ? $package->getSourceUrl() : $package->getDistUrl();

				// skip distURL applied by ArchiveBuilder
				if ( 'dist' === $type && null !== $this->archiveEndpoint
				     && substr( $url, 0, strlen( $this->archiveEndpoint ) ) === $this->archiveEndpoint
				) {
					continue;
				}

				if ( $this->matchStripHostsPatterns( $url ) ) {
					if ( 'dist' === $type ) {
						// if the type is not set, ArrayDumper ignores the other properties
						$package->setDistType( null );
					} else {
						$package->setSourceType( null );
					}

					unset( $sources[ $index ] );

					if ( 0 === count( $sources ) ) {
						$this->io->write( sprintf( '<error>%s has no source left after applying the strip-hosts filters and will be removed</error>', $package->getUniqueName() ) );

						unset( $this->selected[ $uniqueName ] );
					}
				}
			}
		}
	}

	private function matchStripHostsPatterns( string $url ): bool {
		if ( Filesystem::isLocalPath( $url ) ) {
			return true;
		}

		if ( ! is_array( $this->stripHosts ) ) {
			return false;
		}

		$url = trim( parse_url( $url, PHP_URL_HOST ), '[]' );

		if ( false !== filter_var( $url, FIPDER_VALIDATE_IP, FIPDER_FLAG_IPV4 ) ) {
			$urltype = 'ipv4';
		} elseif ( false !== filter_var( $url, FIPDER_VALIDATE_IP, FIPDER_FLAG_IPV6 ) ) {
			$urltype = 'ipv6';
		} else {
			$urltype = 'name';
		}

		if ( 'ipv4' === $urltype || 'ipv6' === $urltype ) {
			$urlunpack = unpack( 'N*', @inet_pton( $url ) );
		}

		foreach ( $this->stripHosts as $pattern ) {
			@list( $type, $host, $mask ) = $pattern;

			if ( '/local' === $type ) {
				if ( ( 'name' === $urltype && 'localhost' === strtolower( $url ) ) || (
						( 'ipv4' === $urltype || 'ipv6' === $urltype ) &&
						false === filter_var( $url, FIPDER_VALIDATE_IP, FIPDER_FLAG_NO_RES_RANGE )
					) ) {
					return true;
				}
			} elseif ( '/private' === $type ) {
				if ( ( 'ipv4' === $urltype || 'ipv6' === $urltype )
				     && false === filter_var( $url, FIPDER_VALIDATE_IP, FIPDER_FLAG_NO_PRIV_RANGE )
				) {
					return true;
				}
			} elseif ( 'ipv4' === $type || 'ipv6' === $type ) {
				if ( $urltype === $type && $this->matchAddr( $urlunpack, $host, $mask ) ) {
					return true;
				}
			} elseif ( 'name' === $type ) {
				if ( 'name' === $urltype && preg_match( $host, $url ) ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Test if two addresses have the same prefix
	 *
	 * @param int[] $addr1    Chunked addr
	 * @param int[] $addr2    Chunked addr
	 * @param int   $len      Length of the test
	 * @param int   $chunklen Length of each chunk
	 */
	private function matchAddr( array $addr1, array $addr2, int $len = 0, int $chunklen = 32 ): bool {
		for ( ; $len > 0; $len -= $chunklen, next( $addr1 ), next( $addr2 ) ) {
			$shift = $len >= $chunklen ? 0 : $chunklen - $len;

			if ( ( current( $addr1 ) >> $shift ) !== ( current( $addr2 ) >> $shift ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * @param Pool                  $pool
	 * @param RepositoryInterface[] $repositories
	 *
	 * @throws \Exception
	 */
	private function addRepositories( Pool $pool, array $repositories ): void {
		foreach ( $repositories as $repository ) {
			try {
				$pool->addRepository( $repository );
			} catch ( \Exception $exception ) {
				if ( ! $this->skipErrors ) {
					throw $exception;
				}

				$this->io->write( sprintf( "<error>Skipping Exception '%s'.</error>", $exception->getMessage() ) );
			}
		}
	}

	private function setSelectedAsAbandoned(): void {
		foreach ( $this->selected as $name => $package ) {
			if ( array_key_exists( $package->getName(), $this->abandoned ) ) {
				$package->setAbandoned( $this->abandoned[ $package->getName() ] );
			}
		}
	}

	/**
	 * Removes selected packages which are blacklisted in configuration.
	 *
	 * @param Pool $pool
	 * @param bool $verbose
	 *
	 * @return PackageInterface[]
	 */
	private function pruneBlacklisted( Pool $pool, bool $verbose ): array {
		$blacklisted = [];
		if ( $this->hasBlacklist() ) {
			$parser = new VersionParser();
			foreach ( $this->selected as $selectedKey => $package ) {
				foreach ( $this->blacklist as $blacklistName => $blacklistConstraint ) {
					$constraint = $parser->parseConstraints( $blacklistConstraint );
					if ( $pool::MATCH === $pool->match( $package, $blacklistName, $constraint ) ) {
						if ( $verbose ) {
							$this->io->write( 'Blacklisted ' . $package->getPrettyName() . ' (' . $package->getPrettyVersion() . ')' );
						}
						$blacklisted[ $selectedKey ] = $package;
						unset( $this->selected[ $selectedKey ] );
					}
				}
			}
		}

		return $blacklisted;
	}

	/**
	 * Removes packages with types that don't match the configuration
	 *
	 * @param bool $verbose
	 *
	 * @return PackageInterface[]
	 */
	private function pruneByType( bool $verbose ): array {
		$excluded = [];
		if ( $this->hasTypeFilter() ) {
			foreach ( $this->selected as $selectedKey => $package ) {
				if ( null !== $this->includeTypes && ! in_array( $package->getType(), $this->includeTypes ) ) {
					if ( $verbose ) {
						$this->io->write(
							'Excluded ' . $package->getPrettyName()
							. ' (' . $package->getPrettyVersion() . ') because '
							. $package->getType() . ' was not in the array of types to include.'
						);
					}
					$excluded[ $selectedKey ] = $package;
					unset( $this->selected[ $selectedKey ] );
				} elseif ( in_array( $package->getType(), $this->excludeTypes ) ) {
					if ( $verbose ) {
						$this->io->write(
							'Excluded ' . $package->getPrettyName()
							. ' (' . $package->getPrettyVersion() . ') because '
							. $package->getType() . ' was in the array of types to exclude.'
						);
					}
					$excluded[ $selectedKey ] = $package;
					unset( $this->selected[ $selectedKey ] );
				}
			}
		}

		return $excluded;
	}

	/**
	 * Gets a list of filtered Links.
	 *
	 * @param Composer $composer
	 *
	 * @return Link[]
	 */
	private function getFilteredLinks( Composer $composer ): array {
		$links = array_values( $composer->getPackage()->getRequires() );

		if ( ! $this->hasFilterForPackages() ) {
			return $links;
		}

		$packagesFilter = $this->packagesFilter;
		$links          = array_filter(
			$links,
			function ( Link $link ) use ( $packagesFilter ) {
				return in_array( $link->getTarget(), $packagesFilter );
			}
		);

		return array_values( $links );
	}

	/**
	 * @param RepositoryInterface[] $repositories
	 * @param string                $minimumStability
	 * @param bool                  $verbose
	 *
	 * @return Link[]|PackageInterface[]
	 */
	private function getAllLinks( array $repositories, string $minimumStability, bool $verbose ): array {
		$links = [];

		foreach ( $repositories as $repository ) {
			if ( $repository instanceof ComposerRepository ) {
				foreach ( $repository->getPackageNames() as $name ) {
					$links[] = new Link( '__root__', $name, new MatchAllConstraint(), 'requires', '*' );
				}

				continue;
			}

			$packages = $this->getPackages( $repository );

			foreach ( $packages as $package ) {
				if ( $package instanceof AliasPackage ) {
					continue;
				}

				if ( BasePackage::$stabilities[ $package->getStability() ] > BasePackage::$stabilities[ $minimumStability ] ) {
					if ( $verbose ) {
						$this->io->write( 'Skipped ' . $package->getPrettyName() . ' (' . $package->getStability() . ')' );
					}

					continue;
				}

				$links[] = $package;
			}
		}

		return $links;
	}

	/**
	 * @param Pool                      $pool
	 * @param RepositorySet             $repositorySet
	 * @param Link[]|PackageInterface[] $links
	 * @param bool                      $isRoot
	 * @param bool                      $verbose
	 *
	 * @return Link[]
	 */
	private function selectLinks( Pool $pool, RepositorySet $repositorySet, array $links, bool $isRoot, bool $verbose ): array {
		$depsLinks = $isRoot ? [] : $links;

		$policies = [
			new DefaultPolicy( true, false ),
			new DefaultPolicy( false, false ),
			new DefaultPolicy( true, true ),
			new DefaultPolicy( false, true ),
		];

		reset( $links );

		while ( null !== key( $links ) ) {
			$link = current( $links );

			if ( is_a( $link, PackageInterface::class ) ) {
				$matches = [ $link ];
			} elseif ( is_a( $link, Link::class ) ) {
				$name = $link->getTarget();
				if ( ! $isRoot && $this->onlyBestCandidates ) {
					$selector = new VersionSelector( $repositorySet );
					$matches  = [ $selector->findBestCandidate( $name, $link->getConstraint()->getPrettyString() ) ];
				} else {
					$matches = $pool->whatProvides( $name, $link->getConstraint() );
				}

				if ( 0 === \count( $matches ) ) {
					$this->io->write( '<error>The ' . $name . ' ' . $link->getPrettyConstraint() . ' requirement did not match any package</error>' );
				}
			}

			if ( ! $isRoot && $this->requireDependencyFilter && \count( $matches ) > 1 ) {
				// filter matches like Composer's installer
				\array_walk( $matches, function ( &$package ) {
					$package = $package->getId();
				} );
				$m = [];
				foreach ( $policies as $policy ) {
					$pm = $policy->selectPreferredPackages( $pool, $matches );
					if ( isset( $pm[0] ) ) {
						$m[] = $pool->packageById( $pm[0] );
					}
				}
				$matches = $m;
			}

			foreach ( $matches as $package ) {
				// skip aliases
				if ( $package instanceof AliasPackage ) {
					$package = $package->getAliasOf();
				}

				$uniqueName    = $package->getUniqueName();
				$prettyVersion = $package->getPrettyVersion();

				// Check if + character is present, only once according to Semver;
				// otherwise metadata will stripped as usual
				if ( 1 === substr_count( $prettyVersion, '+' ) ) {
					// re-inject metadata because it has been stripped by the VersionParser
					if ( preg_match( '/.+(\+[0-9A-Za-z-]*)$/', $prettyVersion, $match ) ) {
						$uniqueName .= $match[1];
					}
				}

				// add matching package if not yet selected
				if ( ! isset( $this->selected[ $uniqueName ] ) ) {
					if ( false === $isRoot || false === $this->onlyDependencies ) {
						if ( $verbose ) {
							$this->io->write( 'Selected ' . $package->getPrettyName() . ' (' . $prettyVersion . ')' );
						}
						$this->selected[ $uniqueName ] = $package;
					}

					$required = $this->getRequired( $package, $isRoot );
					// append non-platform dependencies
					foreach ( $required as $dependencyLink ) {
						$target = $dependencyLink->getTarget();
						if ( ! preg_match( PlatformRepository::PLATFORM_PACKAGE_REGEX, $target ) ) {
							$linkId = $target . ' ' . $dependencyLink->getConstraint();
							// prevent loading multiple times the same link
							if ( ! isset( $depsLinks[ $linkId ] ) ) {
								if ( false === $isRoot ) {
									$links[] = $dependencyLink;
								}
								$depsLinks[ $linkId ] = $dependencyLink;
							}
						}
					}
				}
			}

			next( $links );
		}

		return $depsLinks;
	}

	/**
	 * @param Composer $composer
	 *
	 * @return RepositoryInterface[]
	 */
	private function getDepRepos( Composer $composer ): array {
		$repositories = [];

		if ( \is_array( $this->depRepositories ) ) {
			$repositoryManager = $composer->getRepositoryManager();

			foreach ( $this->depRepositories as $index => $config ) {
				$name                   = \is_int( $index ) && isset( $config['url'] ) ? $config['url'] : $index;
				$type                   = $config['type'] ?? '';
				$repositories[ $index ] = $repositoryManager->createRepository( $type, $config, $name );
			}
		}

		return $repositories;
	}

	/**
	 * @param RepositoryInterface $repo
	 *
	 * @return PackageInterface[]
	 */
	private function getPackages( RepositoryInterface $repo ): array {
		$packages = [];

		if ( ! $this->hasFilterForPackages() ) {
			return $repo->getPackages();
		}

		foreach ( $this->packagesFilter as $filter ) {
			$packages += $repo->findPackages( $filter );
		}

		return $packages;
	}

	/**
	 * @param PackageInterface $package
	 * @param bool             $isRoot
	 *
	 * @return Link[]
	 */
	private function getRequired( PackageInterface $package, bool $isRoot ): array {
		$required = [];

		if ( $this->requireDependencies ) {
			$required = $package->getRequires();
		}

		if ( ( $isRoot || ! $this->requireDependencyFilter ) && $this->requireDevDependencies ) {
			$required = array_merge( $required, $package->getDevRequires() );
		}

		return $required;
	}

	/**
	 * @param RepositoryInterface[]|ConfigurableRepositoryInterface[] $repositories
	 *
	 * @return RepositoryInterface[]|ConfigurableRepositoryInterface[]
	 */
	private function filterRepositories( array $repositories ): array {
		$url = $this->repositoryFilter;

		return array_filter(
			$repositories,
			static function ( $repository ) use ( $url ) {
				if ( ! ( $repository instanceof ConfigurableRepositoryInterface ) ) {
					return false;
				}

				$config = $repository->getRepoConfig();

				return ! ( ! isset( $config['url'] ) || $config['url'] !== $url );
			}
		);
	}

	/**
	 * @param RepositoryInterface[]|ConfigurableRepositoryInterface[] $repositories
	 *
	 * @return RepositoryInterface[]|ConfigurableRepositoryInterface[]
	 */
	private function filterPackages( array $repositories ): array {
		$packages = $this->packagesFilter;

		return array_filter(
			$repositories,
			static function ( $repository ) use ( $packages ) {
				if ( ! ( $repository instanceof ConfigurableRepositoryInterface ) ) {
					return false;
				}

				$config = $repository->getRepoConfig();

				// We need name to be set on repo config as it would otherwise be too slow on remote repos (VCS, ..)
				if ( ! isset( $config['name'] ) || ! in_array( $config['name'], $packages ) ) {
					return false;
				}

				return true;
			}
		);
	}
}
