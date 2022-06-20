# Using Composer with Pressody Retailer

Once Pressody Retailer is installed and configured you can include the Pressody Retailer repository in the list of repositories in your `composer.json` or `satis.json`, then require the packages using `pressody_retailer` (or your custom setting) as the vendor:

```json
{
	"repositories": [
		{
			"type": "composer",
			"url": "https://retailer.getpressody.com/pdpackagist/"
		}
	],
	"require": {
		"composer/installers": "^1.0",
		"pressody_retailer/atomic-blocks": "*",
		"pressody_retailer/genesis": "*",
		"pressody_retailer/gravityforms": "*"
	}
}
```

_The `pressody_retailer` vendor name can be changed on the [Settings page](settings.md)._

## Installing Packages

When you install a package from a Pressody Retailer repository for the first time, Composer will notify you that authentication is required. Use your API Key for the username and `pressody_retailer` as the password. Composer will then ask if you want to store the credentials, which should be fine.

```sh
$ ls -1
composer.json

$ composer install
Loading composer repositories with package information

    Authentication required (retailer.getpressody.com):
      Username: aUEZYqq6pXlMjdg8swe0rQgMCZAPJNaR
      Password:
Do you want to store credentials for local.test in /Users/vladolaru/.composer/auth.json ? [Yn] y
Updating dependencies (including require-dev)
Package operations: 4 installs, 0 updates, 0 removals
  - Installing composer/installers (v1.5.0):
  - Installing pressody_retailer/genesis (2.6.1):
  - Installing pressody_retailer/gravityforms (2.3.2):
  - Installing pressody_retailer/atomic-blocks (1.2.1):
Writing lock file
Generating autoload files

$ ls -1
composer.json
composer.lock
vendor
wp-content

$ ls -1 wp-content/plugins
gravityforms
```

## Configuring Authentication

It's also possible to configure Composer to use your API Key by running the `config` command:

```sh
$ composer config http-basic.retailer.getpressody.com \
   aUEZYqq6pXlMjdg8swe0rQgMCZAPJNaR pressody_retailer
```

After running that command, you should end up with an `auth.json` in your project alongside the `composer.json` that looks like this:

```json
{
    "http-basic": {
        "retailer.getpressody.com": {
            "username": "aUEZYqq6pXlMjdg8swe0rQgMCZAPJNaR",
            "password": "pressody_retailer"
        }
    }
}
```

The [Composer documentation explains the benefit](https://getcomposer.org/doc/articles/http-basic-authentication.md) of using a local `auth.json`:
 
> The main advantage of the `auth.json` file is that it can be gitignored so that every developer in your team can place their own credentials in there, which makes revocation of credentials much easier than if you all share the same.

[Back to Index](index.md)
