/// <reference types="cypress" />

'use strict';

import { TestMethods } from '../support/test_methods.js';

describe('paylike plugin quick test', () => {
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

    let currency = Cypress.env('ENV_CURRENCY_TO_CHANGE_WITH');

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
        TestMethods.changeShopCurrencyFromAdmin(currency);
    });

    /** Pay and process order. */
    /** Capture */
    TestMethods.payWithSelectedCurrency(currency, 'capture');

    /** Refund last created order (previously captured). */
    it('Process last order captured from admin panel to be refunded', () => {
        TestMethods.processOrderFromAdmin('refund');
    });

    /** Void */
    TestMethods.payWithSelectedCurrency(currency, 'void');

}); // describe