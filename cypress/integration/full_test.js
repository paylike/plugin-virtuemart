/// <reference types="cypress" />

'use strict';

import { TestMethods } from '../support/test_methods.js';

describe('paylike plugin quick test', () => {
    /**
     * Go to backend site admin if necessary
     */
    before(() => {
        cy.goToPage(Cypress.env('ENV_ADMIN_URL'));
    });

    /**
     * Run this on every test case bellow
     * - preserve cookies between tests
     */
    beforeEach(() => {
        Cypress.Cookies.preserveOnce(Cypress.env('ENV_COOKIE_HASH'));
    });

    /**
     * Login into admin if necessary.
     */
    it('login into admin backend', () => {
        TestMethods.loginIntoAdminBackend();
    });

    let captureModes = ['Instant', 'Delayed'];
    let currenciesToTest = Cypress.env('ENV_CURRENCIES_TO_TEST');
    let contextFlag = true;

    context(`make payments in "${captureModes[0]}" mode`, () => {
        /** Modify Paylike settings. */
        it(`change Paylike capture mode to "${captureModes[0]}"`, () => {
            TestMethods.CaptureMode = captureModes[0];
            TestMethods.changePaylikeCaptureMode();
        });

        /** Make Instant payments */
        for (var currency of currenciesToTest) {
            TestMethods.payWithSelectedCurrency(currency, contextFlag);
        }
    });

    context(`make payments in "${captureModes[1]}" mode`, () => {
        /** Modify Paylike settings. */
        it(`change Paylike capture mode to "${captureModes[1]}"`, () => {
            TestMethods.CaptureMode = captureModes[1];
            TestMethods.changePaylikeCaptureMode();
        });

        for (var currency of currenciesToTest) {
            /**
             * HARDCODED currency
             */
            if ('USD' == currency || 'RON' == currency) {
                TestMethods.payWithSelectedCurrency(currency, contextFlag);
            }
        }
    });

}); // describe