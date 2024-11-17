/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

import { wait, instant } from '../wait';

class StripeCreditCard {
    constructor(key, secret, onlyAuthorization, stripeConnect) {
        this.key = key;
        this.secret = secret;
        this.onlyAuthorization = onlyAuthorization;
        this.stripeConnect = stripeConnect;
    }

    setupStripe() {
        if (this.stripeConnect) {
            this.stripe = Stripe(this.key, {
                stripeAccount: this.stripeConnect,
            });
        } else {
            this.stripe = Stripe(this.key);
        }

        this.elements = this.stripe.elements();

        return this;
    }

    createElement() {
        this.cardElement = this.elements.create('card', {
            hidePostalCode:
                document.querySelector('meta[name=stripe-require-postal-code]')
                    ?.content === '0',
            value: {
                postalCode: document.querySelector(
                    'meta[name=client-postal-code]'
                ).content,
            },
            hideIcon: false,
        });

        return this;
    }

    mountCardElement() {
        this.cardElement.mount('#card-element');

        return this;
    }

    completePaymentUsingToken() {
        let token = document.querySelector('input[name=token]').value;

        let payNowButton = document.getElementById('pay-now');
        this.payNowButton = payNowButton;

        this.payNowButton.disabled = true;

        this.payNowButton.querySelector('svg').classList.remove('hidden');
        this.payNowButton.querySelector('span').classList.add('hidden');

        this.stripe
            .handleCardPayment(this.secret, {
                payment_method: token,
            })
            .then((result) => {
                if (result.error) {
                    return this.handleFailure(result.error.message);
                }

                return this.handleSuccess(result);
            });
    }

    completePaymentWithoutToken() {
        let payNowButton = document.getElementById('pay-now');
        this.payNowButton = payNowButton;

        this.payNowButton.disabled = true;

        this.payNowButton.querySelector('svg').classList.remove('hidden');
        this.payNowButton.querySelector('span').classList.add('hidden');

        let cardHolderName = document.getElementById('cardholder-name');

        this.stripe
            .handleCardPayment(this.secret, this.cardElement, {
                payment_method_data: {
                    billing_details: { name: cardHolderName.value },
                },
            })
            .then((result) => {
                if (result.error) {
                    return this.handleFailure(result.error.message);
                }

                return this.handleSuccess(result);
            });
    }

    handleSuccess(result) {
        document.querySelector('input[name="gateway_response"]').value =
            JSON.stringify(result.paymentIntent);

        let tokenBillingCheckbox = document.querySelector(
            'input[name="token-billing-checkbox"]:checked'
        );

        if (tokenBillingCheckbox) {
            document.querySelector('input[name="store_card"]').value =
                tokenBillingCheckbox.value;
        }

        document.getElementById('server-response').submit();
    }

    handleFailure(message) {
        let errors = document.getElementById('errors');

        errors.textContent = '';
        errors.textContent = message;
        errors.hidden = false;

        this.payNowButton.disabled = false;
        this.payNowButton.querySelector('svg').classList.add('hidden');
        this.payNowButton.querySelector('span').classList.remove('hidden');
    }

    handleAuthorization() {
        let cardHolderName = document.getElementById('cardholder-name');

        let payNowButton = document.getElementById('authorize-card');

        this.payNowButton = payNowButton;
        this.payNowButton.disabled = true;

        this.payNowButton.querySelector('svg').classList.remove('hidden');
        this.payNowButton.querySelector('span').classList.add('hidden');

        this.stripe
            .handleCardSetup(this.secret, this.cardElement, {
                payment_method_data: {
                    billing_details: { name: cardHolderName.value },
                },
            })
            .then((result) => {
                if (result.error) {
                    return this.handleFailure(result.error.message);
                }

                return this.handleSuccessfulAuthorization(result);
            });
    }

    handleSuccessfulAuthorization(result) {
        document.getElementById('gateway_response').value = JSON.stringify(
            result.setupIntent
        );

        document.getElementById('server_response').submit();
    }

    handle() {
        this.setupStripe();

        if (this.onlyAuthorization) {
            this.createElement().mountCardElement();

            document
                .getElementById('authorize-card')
                .addEventListener('click', () => {
                    return this.handleAuthorization();
                });
        } else {
            Array.from(
                document.getElementsByClassName('toggle-payment-with-token')
            ).forEach((element) =>
                element.addEventListener('click', (element) => {
                    document
                        .getElementById('stripe--payment-container')
                        .classList.add('hidden');
                    document.getElementById(
                        'save-card--container'
                    ).style.display = 'none';
                    document.querySelector('input[name=token]').value =
                        element.target.dataset.token;
                })
            );

            document
                .getElementById('toggle-payment-with-credit-card')
                .addEventListener('click', (element) => {
                    document
                        .getElementById('stripe--payment-container')
                        .classList.remove('hidden');
                    document.getElementById(
                        'save-card--container'
                    ).style.display = 'grid';
                    document.querySelector('input[name=token]').value = '';
                });

            this.createElement().mountCardElement();

            document.getElementById('pay-now').addEventListener('click', () => {
                try {
                    let tokenInput =
                        document.querySelector('input[name=token]');

                    if (tokenInput.value) {
                        return this.completePaymentUsingToken();
                    }

                    return this.completePaymentWithoutToken();
                } catch (error) {
                    console.log(error.message);
                }
            });
        }
    }
}

function boot() {
    const publishableKey =
        document.querySelector('meta[name="stripe-publishable-key"]')
            ?.content ?? '';

    const secret =
        document.querySelector('meta[name="stripe-secret"]')?.content ?? '';

    const onlyAuthorization =
        document.querySelector('meta[name="only-authorization"]')?.content ??
        '';

    const stripeConnect =
        document.querySelector('meta[name="stripe-account-id"]')?.content ?? '';

    let s = new StripeCreditCard(
        publishableKey,
        secret,
        onlyAuthorization,
        stripeConnect
    );

    s.handle();

    /** @type {NodeListOf<HTMLInputElement>} */
    const tokens = document.querySelectorAll('input.toggle-payment-with-token');

    if (tokens.length > 0) {
        tokens[0].click();
    }
}

instant() ? boot() : wait('#stripe-credit-card-payment').then(() => boot());
