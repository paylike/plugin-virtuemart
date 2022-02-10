/// <reference types="cypress" />

'use strict';

import { PaylikeTestHelper } from './test_helper.js';

export var TestMethods = {

    /** Admin & frontend user credentials. */
    StoreUrl: Cypress.env('ENV_STORE_URL'),
    AdminUrl: Cypress.env('ENV_ADMIN_URL'),
    RemoteVersionLogUrl: Cypress.env('REMOTE_LOG_URL'),
    CaptureMode: Cypress.env('ENV_CAPTURE_MODE'),

    /**
     * Constant used to make or skip some tests.
     */
    NeedToAdminLogin: true === Cypress.env('ENV_STOP_EMAIL') ||
                      true === Cypress.env('ENV_LOG_VERSION') ||
                      true === Cypress.env('ENV_SETTINGS_CHECK'),

    /** Construct some variables to be used bellow. */
    ShopName: 'VirtueMart',
    PaylikeName: 'paylike',
    FrontendCurrency: '',
    VirtuemartConfigAdminUrl: '/index.php?option=com_virtuemart&view=config',
    // ModulesAdminUrl: '/index.php?option=com_installer&view=manage',
    PaymentMethodsAdminUrl: '/index.php?option=com_virtuemart&view=paymentmethod',
    ManageEmailSettingUrl: '/index.php?controller=AdminEmails',
    OrdersPageAdminUrl: '/index.php?controller=AdminOrders',

    /**
     * Login to admin backend account
     */
     loginIntoAdminBackend() {
        cy.loginIntoAccount('input[name=username]', 'input[name=passwd]', 'admin');
    },
    /**
     * Login to client|user frontend account
     */
     loginIntoClientAccount() {
        cy.loginIntoAccount('input[name=username]', 'input[name=password]', 'client');
    },

    /**
     * Get Shop & Paylike versions and send log data.
     */
    logVersions() {
        /** Go to Virtuemart config page. */
        cy.goToPage(this.VirtuemartConfigAdminUrl);

        /** Get Framework version. */
        cy.get('#status.navbar').then(($frameworkVersionFromPage) => {
            var versionText = $frameworkVersionFromPage.text();
            var frameworkVersion = versionText.match(/\d*\.\d*((\.\d*)?)*/g);
            cy.wrap(frameworkVersion).as('frameworkVersion');
        });

        /** Get shop version. */
        cy.get('.vm-installed-version').first().then(($shopVersionFromPage) => {
            var versionText = $shopVersionFromPage.text();
            var shopVersion = versionText.replace('VirtueMart ', '');
            cy.wrap(shopVersion).as('shopVersion');
        });

        /** Go to extensions admin page. */
        cy.goToPage(this.ModulesAdminUrl);

        /** Search for paylike. */
        cy.get('#filter_search').clear().type(`${this.PaylikeName}{enter}`);

        cy.get('#manageList tbody tr:nth-child(1) td:nth-child(6)').then($paylikeVersionFromPage => {
            var paylikeVersion = ($paylikeVersionFromPage.text()).replace(/[^0-9.]/g, '');
            /** Make global variable to be accessible bellow. */
            cy.wrap(paylikeVersion).as('paylikeVersion');
        });

        /** Get global variables and make log data request to remote url. */
        cy.get('@frameworkVersion').then(frameworkVersion => {
            cy.get('@shopVersion').then(shopVersion => {
                cy.get('@paylikeVersion').then(paylikeVersion => {

                    cy.request('GET', this.RemoteVersionLogUrl, {
                        key: shopVersion,
                        tag: this.ShopName,
                        view: 'html',
                        framework: frameworkVersion,
                        ecommerce: shopVersion,
                        plugin: paylikeVersion
                    }).then((resp) => {
                        expect(resp.status).to.eq(200);
                    });
                });
            });
        });
    },

    /**
     * Modify email settings (disable notifications)
     */
    deactivateEmailNotifications() {
        /** Go to email settings page. */
        cy.goToPage(this.ManageEmailSettingUrl);

        cy.get('#PS_MAIL_METHOD_3').click();
        cy.get('#mail_fieldset_email .panel-footer button').click();
    },

    /**
     * Modify email settings (disable notifications)
     */
    activateEmailNotifications() {
        /** Go to email settings page. */
        cy.goToPage(this.ManageEmailSettingUrl);

        cy.get('#PS_MAIL_METHOD_1').click();
        cy.get('#mail_fieldset_email .panel-footer button').click();
    },

    /**
     * Modify Paylike settings
     */
    changePaylikeCaptureMode() {
        /** Go to modules page, and select Paylike. */
        cy.goToPage(this.PaymentMethodsAdminUrl);

        /** Select paylike & config its settings. */
        cy.get('.adminlist tbody tr td:nth-child(2) a').contains(this.PaylikeName, {matchCase: false}).click();
        cy.get('#admin-ui-tabs ul li span').contains('Configuration').click();

        /** Make select visible. */
        cy.get('#params_capture_mode').then(($select) => {
            $select.attr('style', '{display: block}');
        });

        /** Change capture mode & save. */
        cy.get('#params_capture_mode').select(this.CaptureMode);
        cy.get('#toolbar-save').click();
    },

    /**
     * Make an instant payment
     * @param {String} currency
     */
    makePaymentFromFrontend(currency) {
        /** Go to store frontend. */
        cy.goToPage(this.StoreUrl);

        /** Change currency & wait for products price to finish update. */
        cy.get('#blockcurrencies .dropdown-toggle').click();
        cy.get('#blockcurrencies ul a').each(($listLink) => {
            if ($listLink.text().includes(currency)) {
                cy.get($listLink).click();
                /** Make this currency globally available. */
                this.FrontendCurrency = currency;
            }
        });
        cy.wait(1000);

        /** Make all add-to-cart buttons visible. */
        PaylikeTestHelper.setVisibleOn('.product_list.grid .button-container');

        /** Add to cart random product. */
        var randomInt = PaylikeTestHelper.getRandomInt(/*max*/ 6);
        cy.get('.ajax_add_to_cart_button').eq(randomInt).click();

        /** Proceed to checkout. */
        cy.get('.next a').click();
        cy.get('.standard-checkout').click();

        /**
         * Client frontend login.
         */
        this.loginIntoClientAccount();

        /** Continue checkout. */
        cy.get('button[name=processAddress]').click();
        cy.get('#cgv').click();
        cy.get('.standard-checkout').click();

        /** Verify amount. */
        cy.get('#total_price').then(($totalAmount) => {
            var expectedAmount = PaylikeTestHelper.filterAndGetAmountInMinor($totalAmount, currency);
            cy.window().then(($win) => {
                expect(expectedAmount).to.eq(Number($win.amount))
            })
        });

        /** Click on Paylike. */
        cy.get('#paylike-btn').click();

        /**
         * Fill in Paylike popup.
         */
        PaylikeTestHelper.fillAndSubmitPaylikePopup();

        /** Check if order was paid. */
        cy.get('.alert-success').should('contain.text', 'Congratulations, your payment has been approved');
    },

    /**
     * Process last order from admin panel
     */
    processOrderFromAdmin(contextFlag = false) {
        /** Login & go to admin orders page. */
        if (false === this.NeedToAdminLogin && !contextFlag) {
            cy.goToPage(this.OrdersPageAdminUrl);
            this.loginIntoAdminBackend();
        } else {
            cy.goToPage(this.OrdersPageAdminUrl);
        }

        /** Click on first (latest in time) order from orders table. */
        cy.get('.table.order tbody tr').first().click();

        /**
         * If CaptureMode='Delayed', set shipped on order status & make 'capture'
         * If CaptureMode='Instant', set refunded on order status & make 'refund'
         */
        if ('Delayed' === this.CaptureMode) {
            this.paylikeActionOnOrderAmount('capture');
        } else {
            this.paylikeActionOnOrderAmount('refund', this.FrontendCurrency);
        }
    },

    /**
     * Make payment with specified currency and process order
     */
    payWithSelectedCurrency(currency, contextFlag = false) {

        /** Make an instant payment. */
        it(`makes a Paylike payment with "${currency}"`, () => {
            this.makePaymentFromFrontend(currency);
        });

        /** Process last order from admin panel. */
        it('process (capture/refund/void) an order from admin panel', () => {
            this.processOrderFromAdmin(contextFlag);
        });

        /** Send log if currency = DKK. */
        /**
         * HARDCODED currency
         */
        if ('DKK' == currency) {
            it('log shop & paylike versions remotely', () => {
                this.logVersions();
            });
        }
    },

    /**
     * Capture an order amount
     * @param {String} paylikeAction
     * @param {String} currency
     * @param {Boolean} partialRefund
     */
     paylikeActionOnOrderAmount(paylikeAction, currency = '', partialRefund = false) {
        cy.get('#paylike_action').select(paylikeAction);

        /** Enter full amount for refund. */
        if ('refund' === paylikeAction) {
            cy.get('#total_order  .amount').then(($totalAmount) => {
                var minorAmount = PaylikeTestHelper.filterAndGetAmountInMajorUnit($totalAmount, currency);
                cy.get('input[name=paylike_amount_to_refund]').clear().type(`${minorAmount}`);
                cy.get('input[name=paylike_refund_reason]').clear().type('automatic refund');
            });
        }

        cy.get('#submit_paylike_action').click();
        cy.wait(1000);
        cy.get('#alert.alert-info').should('not.exist');
        cy.get('#alert.alert-warning').should('not.exist');
        cy.get('#alert.alert-danger').should('not.exist');
    },

}