/// <reference types="cypress" />

'use strict';

import { TestMethods } from '../support/test_methods.js';

describe('paylike plugin full test', () => {
    /**
     * Login into admin and frontend to store cookies.
     */
    before(() => {
        cy.goToPage(Cypress.env('ENV_ADMIN_URL'));
        TestMethods.loginIntoAdminBackend();
        cy.goToPage(TestMethods.StoreUrl);
        TestMethods.loginIntoClientAccount();
    });

    /**
     * Run this on every test case bellow
     * - preserve cookies between tests
     */
    beforeEach(() => {
        Cypress.Cookies.defaults({
            preserve: (cookie) => {
              return true;
            }
        });
    });

    let captureModes = ['Instant', 'Delayed'];
    let currenciesToTest = Cypress.env('ENV_CURRENCIES_TO_TEST');

    context(`make payments in "${captureModes[0]}" mode`, () => {
        /** Modify Paylike settings. */
        it(`change Paylike capture mode to "${captureModes[0]}"`, () => {
            TestMethods.CaptureMode = captureModes[0];
            TestMethods.changePaylikeCaptureMode();
        });

        /** Make Instant payments */
        for (var currency of currenciesToTest) {
            TestMethods.payWithSelectedCurrency(currency, 'refund');

            /** Send log if currency = DKK. */
            /**
             * HARDCODED currency
             */
            if ('DKK' == currency) {
                it('log shop & paylike versions remotely', () => {
                    this.logVersions();
                });
            }
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
                TestMethods.payWithSelectedCurrency(currency, 'capture');
                /** In "delayed" mode we check "void" action too. */
                TestMethods.payWithSelectedCurrency(currency, 'void');
            }
        }
    });

}); // describe