// ***********************************************
// For more comprehensive examples of custom
// commands please read more here:
// https://on.cypress.io/custom-commands
// ***********************************************
// -- This is a parent command --
// Cypress.Commands.add('login', (email, password) => { ... })
// -- This is a child command --
// Cypress.Commands.add('drag', { prevSubject: 'element'}, (subject, options) => { ... })
// -- This is a dual command --
// Cypress.Commands.add('dismiss', { prevSubject: 'optional'}, (subject, options) => { ... })
// -- This will overwrite an existing command --
// Cypress.Commands.overwrite('visit', (originalFn, url, options) => { ... })


/**
 * Parent commands
 */

/**
 * Go to specified Url
 * Enhanced with auth for HTTP protected websites
 */
 Cypress.Commands.add('goToPage', (pageUrl) => {
     /** Check if pageUrl is an URI for admin, then add admin url to it. */
    if (pageUrl.match(/(\/index\.php\?)/g)) {
        pageUrl = Cypress.env('ENV_ADMIN_URL') + pageUrl;
    }

    if (Cypress.env('ENV_HTTP_AUTH_ENABLED')) {
        cy.visit(pageUrl, {
            auth: {
                username: Cypress.env('ENV_HTTP_USER'),
                password: Cypress.env('ENV_HTTP_PASS'),
            },
        });
    } else {
        cy.visit(pageUrl);
    }
});

/**
 * Login into an account
 * {String} usernameInputSelector
 * {String} passwordInputSelector
 * {String} type
 */
 Cypress.Commands.add('loginIntoAccount', (usernameInputSelector, passwordInputSelector, type) => {
    /** Select username & password inputs, then press enter. */
    if ('client' === type || 'user' === type) {
        var username = Cypress.env('ENV_CLIENT_USER');
        var password = Cypress.env('ENV_CLIENT_PASS');
    } else if ('admin' === type) {
        var username = Cypress.env('ENV_ADMIN_USER');
        var password = Cypress.env('ENV_ADMIN_PASS');
    }

    cy.get(usernameInputSelector).type(username);
    cy.get(passwordInputSelector).type(`${password}{enter}`);

    cy.wait(2000);
});

/**
 * Remove display:none from element
 * {String} elementSelector
 */
 Cypress.Commands.add('removeDisplayNoneFrom', (elementSelector) => {
    cy.get(elementSelector).then(($selector) => {
        $selector.attr('style', '{display: block}');
    });
});

/**
 * Select an option containing part of string in its text body
 * {String} elementSelector
 * {String} optionTextPart
 */
 Cypress.Commands.add('selectOptionContaining', (elementSelector, optionTextPart) => {
    cy.get(elementSelector)
    .find('option')
    .contains(optionTextPart)
    .then($option => {
        cy.get(elementSelector).select($option.text());
    });
});