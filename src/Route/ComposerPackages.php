<?php
/**
 * Composer packages.json rendering.
 *
 * @package PixelgradeLT
 * @license GPL-2.0-or-later
 * @since 0.1.0
 */

declare ( strict_types = 1 );

namespace PixelgradeLT\Retailer\Route;

use PixelgradeLT\Retailer\Capabilities;
use PixelgradeLT\Retailer\Exception\HttpException;
use PixelgradeLT\Retailer\HTTP\Request;
use PixelgradeLT\Retailer\HTTP\Response;
use PixelgradeLT\Retailer\HTTP\ResponseBody\JsonBody;
use PixelgradeLT\Retailer\Repository\PackageRepository;
use PixelgradeLT\Retailer\Transformer\PackageRepositoryTransformer;
use WP_Http as HTTP;

/**
 * Class for rendering a Composer packages.json for a given repository.
 *
 * @since 0.1.0
 */
class ComposerPackages implements Route {
	/**
	 * Solution repository.
	 *
	 * @var PackageRepository
	 */
	protected PackageRepository $repository;

	/**
	 * Repository transformer.
	 *
	 * @var PackageRepositoryTransformer
	 */
	protected PackageRepositoryTransformer $transformer;

	/**
	 * Constructor.
	 *
	 * @since 0.1.0
	 *
	 * @param PackageRepository            $repository  Solution repository.
	 * @param PackageRepositoryTransformer $transformer Solution repository transformer.
	 */
	public function __construct( PackageRepository $repository, PackageRepositoryTransformer $transformer ) {
		$this->repository  = $repository;
		$this->transformer = $transformer;
	}

	/**
	 * Handle a request to the packages.json endpoint.
	 *
	 * @since 0.1.0
	 *
	 * @param Request $request HTTP request instance.
	 * @throws HTTPException If the user doesn't have permission to view packages.
	 * @return Response
	 */
	public function handle( Request $request ): Response {
		if ( ! current_user_can( Capabilities::VIEW_SOLUTIONS ) ) {
			throw HttpException::forForbiddenResource();
		}

		return new Response(
			new JsonBody( $this->transformer->transform( $this->repository ) ),
			HTTP::OK,
			[ 'Content-Type' => 'application/json; charset=' . get_option( 'blog_charset' ) ]
		);
	}
}
