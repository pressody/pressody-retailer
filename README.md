# PixelgradeLT Retailer

Custom WordPress plugin to hold the RETAILER-entity logic for the Pixelgrade LT system (infrastructure-side).

## About

PixelgradeLT Retailer is part of the PixelgradeLT infrastructure. 
It is the part that is closest to the end user (paying customer or not) since it **manages LT Solutions** that are tied to a **purchasable product** (even if it's free). We are using WooCommerce for the e-commerce functionality, so **LT Retailer integrates with WooCommerce,** although we could integrate with other e-commerce solutions if that need arises.

Besides managing LT Solutions, **LT Retailer also stores (and manages) LT Compositions details** to be used in creating the final `composer.json` contents deployed to actual WordPress sites.

### LT Solutions

LT Solutions are **Composer metapackages** that are focused on fulfilling all the functional needs of **a specific customer problem** (e.g. contact form, hosting, portfolio, selling subscription digital products, etc.).

LT Solutions can require other LT Solutions, but **their main focus is on exposing LT Parts** (managed via the [PixelgradeLT Records](https://github.com/pixelgradelt/pixelgradelt-records) plugin) in a meaningful, understandable, relatable way to the end-users. While LT Parts should be focused on grouping functionality in ways that are easy to manage by us, **LT Solutions should strive to be easily managed and understood by end-users.**

To allow for flexibility, LT Solutions can exclude other LT Solutions from being part of the same composition.

### LT Compositions

LT Compositions are **the final entity before deploying code** to actual WordPress sites.

End-users manage **LT Solutions for a specific site** (hence a specific LT Composition). They can add other LT Solution to a composition, they can detach and re-attach them or delete them completely.

Since we are focused on products and purchases (even if free), **each LT Solution added to an LT Composition is tied to an order and a specific item in that order.** This way we have the utmost flexibility in allowing end-users to move purchased solutions from one site to another, while maintaining the orders (and recurring payments) intact. 

## Running Tests

To run the PHPUnit tests, in the root directory of the plugin, run something like:

```
./vendor/bin/phpunit --testsuite=Unit --colors=always
```
or
```
composer run tests
```

Bear in mind that there are **simple unit tests** (hence the `--testsuite=Unit` parameter) that are very fast to run, and there are **integration tests** (`--testsuite=Integration`) that need to load the entire WordPress codebase, recreate the db, etc. Choose which ones you want to run depending on what you are after.

You can run either the unit tests or the integration tests with the following commands:

```
composer run tests-unit
```
or
```
composer run tests-integration
```

**Important:** Before you can run the tests, you need to create a `.env` file in `tests/phpunit/` with the necessary data. You can copy the already existing `.env.example` file. Further instructions are in the `.env.example` file.

## Credits

...
