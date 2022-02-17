/// <reference types="cypress" />

'use strict';

import { TestMethods } from '../support/test_methods.js';

describe('paylike plugin quick test', () => {
    /**
     * Go to backend site admin if necessary
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

    /**
     * Modify Paylike capture mode
     */
    it('modify Paylike settings for capture mode', () => {
        TestMethods.changePaylikeCaptureMode();
    });

    /**
     * Change shop currency
     */
    it('Change shop currency', () => {
        TestMethods.changeShopCurrencyFromAdmin(Cypress.env('ENV_CURRENCY_TO_CHANGE_WITH'));
    });

    /** Pay and process order. */
    /** Capture */
    TestMethods.payWithSelectedCurrency(Cypress.env('ENV_CURRENCY_TO_CHANGE_WITH'), 'capture');

    /** Refund last created order (previously captured). */
    it('Process last order captured from admin panel to be refunded', () => {
        TestMethods.processOrderFromAdmin('refund');
    });

    /** Void */
    TestMethods.payWithSelectedCurrency(Cypress.env('ENV_CURRENCY_TO_CHANGE_WITH'), 'void');

}); // describe