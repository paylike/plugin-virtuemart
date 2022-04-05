import { PaylikeCurrencies } from './currencies.js';

export let PaylikeTestHelper = {
    /**
     * Fill Paylike popup and submit the form
     */
    fillAndSubmitPaylikePopup() {
        cy.get('#card-number').type(`${Cypress.env('ENV_CARD_NUMBER')}`);
        cy.get('#card-expiry').type(`${Cypress.env('ENV_CARD_EXPIRY')}`);
        cy.get('#card-code').type(`${Cypress.env('ENV_CARD_CVV')}{enter}`);
    },

    /**
     * Filter amount text with symbols
     * Get it in currency minor unit
     *
     * @param {Object} $unfilteredAmount
     * @param {String} currency
     *
     * @return {Number}
     */
     filterAndGetAmountInMinor($unfilteredAmount, currency) {
        var formattedAmount = this.filterAndGetAmountInMajorUnit($unfilteredAmount);

       /** Get multiplier based on currency code. */
       var multiplier = PaylikeCurrencies.get_paylike_currency_multiplier(currency);

       return Math.ceil(Math.round(formattedAmount * multiplier));
   },

   /**
    * Filter amount text with symbols
    * Get it in currency major unit
    *
    * @param {Object} $unfilteredAmount
    *
    * @return {Number}
    */
    filterAndGetAmountInMajorUnit($unfilteredAmount) {
       /** Replace any character except numbers, commas, points */
       var filtered = ($unfilteredAmount.text()).replace(/[^0-9,.][a-z.]*/g, '')
       var matchPointFirst = filtered.match(/\..*,/g);
       var matchCommaFirst = filtered.match(/,.*\./g);

       if (matchPointFirst) {
           var amountAsText = (filtered.replace('.', '')).replace(',', '.');
       } else if (matchCommaFirst) {
           var amountAsText = filtered.replace(',', '');
       } else {
           var amountAsText = filtered.replace(',', '.');
       }

       return parseFloat(amountAsText);
   },

    /**
     * Get currency name based on code provided
     *
     * @param {String} currencyCode
     */
     getCurrencyName(currencyCode) {
        var currencyObject = PaylikeCurrencies.get_paylike_currency(currencyCode);
        return currencyObject['currency'];
    },

    /**
     * Set position=relative on selected element
     * Useful when an element cover another element
     *
     * @param {String} selector
     */
     setPositionRelativeOn(selector) {
        cy.get(selector).then(($selectedElement) => {
            $selectedElement.attr('style', 'position:relative;');
        });
    },

    /**
     * Set visibility=visible on selected element
     * Useful when an element must be clicked but is hidden
     *
     * @param {String} selector
     */
     setVisibleOn(selector) {
        cy.get(selector).each(($selector) => {
            $selector.css({'visibility':'visible', 'opacity': '100'})
        });
    },

    /**
     * Get a random int/float between 0 and provided max
     * @param {int|float} max
     * @returns int|float
     */
    getRandomInt(max) {
        return Math.floor(Math.random() * max);
    },

};
