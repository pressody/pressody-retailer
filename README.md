# Pressody Retailer

Custom WordPress plugin to hold the RETAILER-entity logic for the Pressody (PD for short) system (infrastructure-side).

## About

Pressody Retailer is part of the Pressody infrastructure.

It is the part that is closest to the end user (paying customer or not) since it **manages PD Solutions** that are tied to a **purchasable product** (even if it's free). We are using WooCommerce for the e-commerce functionality, so **PD Retailer integrates with WooCommerce,** although we could integrate with other e-commerce solutions if that need arises.

Besides managing PD Solutions, **PD Retailer also stores (and manages) PD Compositions details** to be used in creating the final `composer.json` contents deployed to actual WordPress sites.

### PD Solutions

PD Solutions are **Composer metapackages** that are focused on fulfilling all the functional needs of **a specific customer problem** (e.g. contact form, hosting, portfolio, selling subscription digital products, etc.).

PD Solutions can require other PD Solutions, but **their main focus is on exposing PD Parts** (managed via the [Pressody Records](https://github.com/pressody/pressody-records) plugin) in a meaningful, understandable, relatable way to the end-users. While _PD Parts_ should be focused on grouping functionality in ways that are _easy to manage by us,_ **_PD Solutions_ should strive to be easily managed and understood by end-users.**

To allow for flexibility, PD Solutions can _exclude_ other PD Solutions from being part of the same composition. **The purchasing experience should reflect the constraints** between various products tied to PD Solutions.

### PD Compositions

PD Compositions are **the final entity before deploying code** to actual WordPress sites.

End-users manage **PD Solutions for a specific site** (hence a specific PD Composition). They can add other PD Solutions to a composition, they can detach and re-attach them or delete them completely.

Since we are focused on products and purchases (even if free), **each PD Solution added to an PD Composition is tied to an order and a specific item in that order.** This way we have the utmost flexibility in allowing end-users to move purchased solutions from one site to another, while _maintaining the orders (and recurring payments) intact._ 

## Contributing

Contributions are more than welcomed! In **any shape or form:** open up [Github issues](https://github.com/pressody/pressody-retailer/issues/new), give your input to [existing issues](https://github.com/pressody/pressody-retailer/issues), propose changes through [pull requests](https://github.com/pressody/pressody-retailer/pulls), or [send a though or two](vladpotter85@gmail.com) may way. Either way, you are welcomed!

### Start developing

To start developing, clone this repository (or a fork of it) into your WordPress' installation `wp-content/plugins` directory. Open a console in the newly cloned repository directory (`wp-content/plugins/pressody-retailer/`) and run:

```
composer install
npm install
```

After you've made your changes or additions, please [open a pull-request](https://docs.github.com/en/pull-requests/collaborating-with-pull-requests/proposing-changes-to-your-work-with-pull-requests) and we'll work through it.

### Running Tests

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
