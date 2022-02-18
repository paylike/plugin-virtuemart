#Testing with Cypress

As you can see the plugin is bundled with Cypress testing on this repository. You can use the tests, if you have some experience with testing.

***DO NOT USE IN PRODUCTION, THE TESTS MODIFY SETTINGS AND CREATE ORDERS***

## Requirements

* A framework/shop installation is required, in which you need to have the sample theme installed and products displayed on the homepage.
* You need to have Paylike module installed and configured (**test keys** required)
* You need to have some other currencies configured in store, then set them in `cypress.env.json` file (these will be used to make payments with every currency specified)
* You also need to have a test client account with previous purchases and an admin account for which you set the credentials in the `cypress.env.json` file
* *For testing purpose, product stock management and sending order emails need to be disabled.*

## Getting started

1. Run following commands into plugin folder (as in this repo)

    ```bash
    npm install cypress --save-dev
    ```

2. Copy and rename `cypress.env.json.example` file in the root folder and fill the data as explained bellow:
```json
{
    "ENV_HTTP_AUTH_ENABLED": false, // 'true' if you have HTTP auth when accessing website
    "ENV_HTTP_USER": "", // if you have HTTP auth when accessing website
    "ENV_HTTP_PASS": "",
    "ENV_ADMIN_URL": "", // like http(s)://baseUrl/administrator
    "ENV_CLIENT_USER": "", // frontend user
    "ENV_CLIENT_PASS": "",
    "ENV_ADMIN_USER": "", // admin user
    "ENV_ADMIN_PASS": "",
    "REMOTE_LOG_URL": "", // if you want to send log information about framework/shop & paylike module versions
    "ENV_CURRENCY_TO_CHANGE_WITH": "USD",
    "ENV_CURRENCIES_TO_TEST": ["USD", "EUR"], // currencies used to make payments with in Full test
    "ENV_CARD_NUMBER": 4100000000000000,
    "ENV_CARD_EXPIRY": 1226,
    "ENV_CARD_CVV": 654
}
```

3. Start the Cypress testing server.
    ```bash
    npx cypress open
    ```
4. In the interface, we can choose which test to run

## Getting Problems?

Since this is a frontend test, its not always consistent, due to delays or some glitches regarding overlapping elements. If you can't get over an issue please open an issue and we'll take a look.