/// <reference types="cypress" />

'use strict';

import { PaylikeTestHelper } from './test_helper.js';

export var TestMethods = {

    /** Admin & frontend user credentials. */
    StoreUrl: (Cypress.env('ENV_ADMIN_URL').match(/^(?:http(?:s?):\/\/)?(?:[^@\n]+@)?(?:www\.)?([^:\/\n?]+)/im))[0],
    AdminUrl: Cypress.env('ENV_ADMIN_URL'),
    RemoteVersionLogUrl: Cypress.env('REMOTE_LOG_URL'),
    CaptureMode: 'Delayed',

    /** Construct some variables to be used bellow. */
    ShopName: 'virtuemart',
    PaylikeName: 'paylike',
    VirtuemartConfigAdminUrl: '/index.php?option=com_virtuemart&view=config',
    ModulesAdminUrl: '/index.php?option=com_installer&view=manage',
    PaymentMethodsAdminUrl: '/index.php?option=com_virtuemart&view=paymentmethod',
    ShopAdminUrl: '/index.php?option=com_virtuemart&view=user&task=editshop', // used for change currency
    OrdersPageAdminUrl: '/index.php?option=com_virtuemart&view=orders',

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
     * Modify Paylike settings
     */
    changePaylikeCaptureMode() {
        /** Go to modules page, and select Paylike. */
        cy.goToPage(this.PaymentMethodsAdminUrl);

        /** Select paylike & config its settings. */
        cy.get('.adminlist tbody tr td:nth-child(2) a').contains(this.PaylikeName, {matchCase: false}).click();
        cy.get('#admin-ui-tabs ul li span').contains('Configuration').click();

        /** Make select visible. */
        cy.removeDisplayNoneFrom('#params_capture_mode');

        /** Change capture mode & save. */
        cy.get('#params_capture_mode').select(this.CaptureMode);

        PaylikeTestHelper.setPositionRelativeOn('.navbar-fixed-top');

        cy.get('#toolbar-save > .button-save').click();
    },

    /**
     * Make an instant payment
     * @param {String} currency
     */
    makePaymentFromFrontend(currency) {
        /** Go to store frontend. */
        cy.goToPage(this.StoreUrl);

        /**
         * Disabled for the moment.
         * Now, we change currency from admin shop section by changeShopCurrencyFromAdmin()
         */
        // this.changeCurrencyFromFrontend(currency);

        /** Add to cart random product. */
        var randomInt = PaylikeTestHelper.getRandomInt(/*max*/ 6);
        cy.get('div.browse-view input.addtocart-button').eq(randomInt).click();
        cy.wait(1000);

        /** Proceed to checkout. */
        cy.get('#fancybox-wrap').should('be.visible');
        cy.get('.vm-btn-primary.showcart').click();

        /** Choose Paylike. */
        cy.get('.vm-payment-plugin-single').contains(this.PaylikeName, {matchCase: false}).then(($div) => {
            $div.children('input').trigger('click');
        });

        /** Accept terms of services. */
        cy.get('#tos').click();

        /** Get order total amount. */
        cy.get('span.PricebillTotal').then($totalAmount => {
            var orderTotalAmount = PaylikeTestHelper.filterAndGetAmountInMinor($totalAmount, currency);
            cy.wrap(orderTotalAmount).as('orderTotalAmount');
        });

        cy.wait(2000);

        /** Confirm checkout. */
        cy.get('#checkoutFormSubmit').click();

        /**
         * Fill in Paylike popup.
         */
        PaylikeTestHelper.fillAndSubmitPaylikePopup();

        cy.wait(1000);

        /** Check if order was paid. */
        cy.get('#paylike-after-info').should('be.visible');

        /** Verify amount. */
        cy.get('.post_payment_order_total').then(($totalAmount) => {
            var expectedAmount = PaylikeTestHelper.filterAndGetAmountInMinor($totalAmount, currency);
            cy.get('@orderTotalAmount').then(orderTotalAmount => {
                expect(expectedAmount).to.eq(orderTotalAmount);
            });
        });
    },

    /**
     * Make payment with specified currency and process order
     *
     * @param {String} currency
     * @param {String} paylikeAction
     */
     payWithSelectedCurrency(currency, paylikeAction) {
        /** Change shop currency from admin. */
        it(`Change shop currency from admin to "${currency}"`, () => {
            TestMethods.changeShopCurrencyFromAdmin(currency);
        });

        /** Make an instant payment. */
        it(`makes a Paylike payment with "${currency}"`, () => {
            this.makePaymentFromFrontend(currency);
        });

        /** Process last order from admin panel. */
        it(`process (${paylikeAction}) an order from admin panel`, () => {
            this.processOrderFromAdmin(paylikeAction);
        });
    },

    /**
     * Process last order from admin panel
     * @param {String} paylikeAction
     */
    processOrderFromAdmin(paylikeAction) {
        /** Go to admin orders page. */
        cy.goToPage(this.OrdersPageAdminUrl);

        /** Go to admin & get order statuses to be globally used. */
        this.getPaylikeOrderStatuses();

        cy.wait(1000);

        /** Go to orders page. */
        cy.goToPage(this.OrdersPageAdminUrl);

        /** Click on first (latest in time) order from orders table. */
        cy.get('.adminlist tbody tr td:nth-child(2) a').first().click();

        /**
         * If CaptureMode='Delayed', set shipped on order status & make 'capture'
         * If CaptureMode='Instant', set refunded on order status & make 'refund'
         */
        this.paylikeActionOnOrderAmount(paylikeAction);
    },

    /**
     * Capture an order amount
     * @param {String} paylikeAction
     */
     paylikeActionOnOrderAmount(paylikeAction) {
        cy.get('a.show_element.btn.btn-small').click();
        cy.removeDisplayNoneFrom('#order_items_status');

        /** Select proper order status using global saved statuses. */
        cy.get('@paylikeOrderStatusForCapture').then(paylikeOrderStatusForCapture => {
            cy.get('@paylikeOrderStatusForRefund').then(paylikeOrderStatusForRefund => {
                /** Default to capture. */
                var statusForOrder = paylikeOrderStatusForCapture;

                if ('refund' === paylikeAction || 'void' === paylikeAction) {
                    statusForOrder = paylikeOrderStatusForRefund;
                }

                cy.selectOptionContaining('#order_items_status', statusForOrder);
                cy.get('a.orderStatFormSubmit').click();
                cy.wait(1000);
            })
        })

    },

    /**
     * Get Paylike order statuses from settings
     */
    getPaylikeOrderStatuses() {
        /** Go to modules page, and select Paylike. */
        cy.goToPage(this.PaymentMethodsAdminUrl);

        /** Select paylike & config its settings. */
        cy.get('.adminlist tbody tr td:nth-child(2) a').contains(this.PaylikeName, {matchCase: false}).click();
        cy.get('#admin-ui-tabs ul li span').contains('Configuration').click();

        /** Get order status for capture and refund. */
        cy.get('#params_status_capture_chzn a span').then($captureStatus => {
            cy.wrap($captureStatus.text()).as('paylikeOrderStatusForCapture');
        });
        cy.get('#params_status_refunded_chzn a span').then($refundStatus => {
            cy.wrap($refundStatus.text()).as('paylikeOrderStatusForRefund');
        });
    },

    /**
     * Change shop currency from admin
     * (temporary solution until the plugin will process chosen frontend currency)
     */
    changeShopCurrencyFromAdmin(currency) {
        /** Go to edit shop page. */
        cy.goToPage(this.ShopAdminUrl);

        PaylikeTestHelper.setPositionRelativeOn('.navbar-fixed-top');

        /** Make select visible. */
        cy.removeDisplayNoneFrom('#vendor_currency');

        /** Currency name. */
        var currentCurrencyName = PaylikeTestHelper.getCurrencyName(currency);

        /** Select currency & save. */
        cy.get('#vendor_currency').select(currentCurrencyName);

        cy.get('#toolbar-save > .button-save').click();
    },

    /**
     * Change shop currency from frontend
     */
    changeCurrencyFromFrontend(currency) {
        // /** Click on currencies. */
        // cy.get('.moduletable_js h3').contains('Currencies Selector', {matchCase: false}).then(($heading) => {
        //     $heading.children('a').trigger('click');
        // })

        // /** Make select visible. */
        // cy.removeDisplayNoneFrom('#virtuemart_currency_id');

        // /** Get currency name. */
        // var currencyName = PaylikeTestHelper.getCurrencyName(currency);

        // /** Select currency by option text. */
        // cy.selectOptionContaining('#virtuemart_currency_id', currencyName);
        // cy.get('input[value="Change Currency"]').click();
        // cy.wait(1000);
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
            cy.wrap(frameworkVersion[0]).as('frameworkVersion');
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
}