# WooCommerce End to End Tests

Automated end-to-end tests for WooCommerce Services.

## Table of contents

- [Pre-requisites](#pre-requisites)
  - [Install NodeJS](#install-nodejs)
  - [Install dependencies](#install-dependencies)
  - [Configuration](#configuration)
      - [Test Configuration](#test-configuration)
      - [Environment Variables](#environment-variables)
- [Running tests](#running-tests)
  - [How to run tests](#how-to-run-tests) 
- [Writing tests](#writing-tests) 

## Pre-requisites

### Install NodeJS

```bash
brew install node #MacOS
```

### Install dependencies

```bash
npm install
```

### Configuration

#### Test Configuration

The tests use environment variables to specify login test data needed to run tests. 

In order to run the tests, create 2 users as follows on your test site:

1) Store Owner with `Admin` role
2) Customer with `Customer` role   

Specify test site URL and users details using environment variables. 

#### Environment variables

Set environmental variables as shown below. Note that you don't need to add the trailing slash ('/') at the end of the site URL:

- `export WP_BASE_URL={your test site URL}`
- `export WP_ADMIN_USER_NAME={your Admin user username}`
- `export WP_ADMIN_USER_PW={your Admin user password}`
- `export WP_CUSTOMER_USER_NAME={your Admin user password}`
- `export WP_CUSTOMER_USER_PW={your Admin user password}`
- `export WP_MYSQL_E2E_USERNAME={your mysql test database username}`
- `export WP_MYSQL_E2E_PASSWORD={your mysql test database password}`
- `export WP_MYSQL_E2E_HOST={your mysql test database host}`

You can unset the variables when you are done:

- `unset WP_BASE_URL`
- `unset WP_ADMIN_USER_NAME`
- `unset WP_ADMIN_USER_PW`
- `unset WP_CUSTOMER_USER_NAME`
- `unset WP_CUSTOMER_USER_PW`
- `unset WP_MYSQL_E2E_USERNAME`
- `unset WP_MYSQL_E2E_PASSWORD`
- `unset WP_MYSQL_E2E_HOST`

## Running tests

### How to run tests

To run e2e tests use the following command:

```bash
npm run test:e2e
```

Tests are being run headless by default. However, sometimes it's useful to observe the browser while running tests. To do so, `Development mode` can be enabled by passing `--dev` flag to the `test:e2e` script when running tests as follows:

```bash
npm run test:e2e -- --dev
```

The `Development mode` also enables SlowMo mode. SlowMo slows down Puppeteer’s operations so we can better see what is happening in the browser. You can adjust the SlowMo value by editing `PUPPETEER_SLOWMO` variable in `./tests/e2e-tests/config/jest-puppeteer.dev.config.js` file. The default `PUPPETEER_SLOWMO=50` means test actions will be slowed down by 50 milliseconds.

To run an individual test, use the direct path to the spec. For example:

```bash
npm run test:e2e ./tests/e2e-tests/specs/admin/activate-extension.test.js
``` 

## Writing tests

We use the following tools to write e2e tests:

- [Puppeteer](https://github.com/GoogleChrome/puppeteer) – a Node library which provides a high-level API to control Chrome or Chromium over the DevTools Protocol
- [jest-puppeteer](https://github.com/smooth-code/jest-puppeteer) – provides all required configuration to run tests using Puppeteer
- [expect-puppeteer](https://github.com/smooth-code/jest-puppeteer/tree/master/packages/expect-puppeteer) – assertion library for Puppeteer

Tests are kept in `./tests/e2e-tests/specs` folder. 

The following packages are being used to write tests:

- `e2e-test-utils` - End-To-End (E2E) test utils for WordPress. You can find the full list of utils [here](https://github.com/WordPress/gutenberg/tree/master/packages/e2e-test-utils). 
