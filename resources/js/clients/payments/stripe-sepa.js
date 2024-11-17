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

class ProcessSEPA {
    constructor(key, stripeConnect) {
        this.key = key;
        this.errors = document.getElementById('errors');
        this.stripeConnect = stripeConnect;
    }

    setupStripe = () => {
        if (this.stripeConnect) {
            this.stripe = Stripe(this.key, {
                stripeAccount: this.stripeConnect,
            });
        } else {
            this.stripe = Stripe(this.key);
        }

        const elements = this.stripe.elements();
        var style = {
            base: {
                color: '#32325d',
                fontFamily:
                    '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif',
                fontSmoothing: 'antialiased',
                fontSize: '16px',
                '::placeholder': {
                    color: '#aab7c4',
                },
                ':-webkit-autofill': {
                    color: '#32325d',
                },
            },
            invalid: {
                color: '#fa755a',
                iconColor: '#fa755a',
                ':-webkit-autofill': {
                    color: '#fa755a',
                },
            },
        };
        var options = {
            style: style,
            supportedCountries: ['SEPA'],
            // If you know the country of the customer, you can optionally pass it to
            // the Element as placeholderCountry. The example IBAN that is being used
            // as placeholder reflects the IBAN format of that country.
            placeholderCountry: document.querySelector('meta[name="country"]')
                .content,
        };
        this.iban = elements.create('iban', options);
        this.iban.mount('#sepa-iban');

        document.getElementById('sepa-name').value = document.querySelector(
            'meta[name=client_name]'
        ).content;
        document.getElementById('sepa-email-address').value =
            document.querySelector('meta[name=client_email]').content;

        return this;
    };

    handle = () => {
        let errors = document.getElementById('errors');

        Array.from(
            document.getElementsByClassName('toggle-payment-with-token')
        ).forEach((element) =>
            element.addEventListener('click', (element) => {
                document
                    .getElementById('stripe--payment-container')
                    .classList.add('hidden');
                document.getElementById('save-card--container').style.display =
                    'none';
                document.querySelector('input[name=token]').value =
                    element.target.dataset.token;
            })
        );

        document
            .getElementById('toggle-payment-with-new-bank-account')
            .addEventListener('click', (element) => {
                document
                    .getElementById('stripe--payment-container')
                    .classList.remove('hidden');
                document.getElementById('save-card--container').style.display =
                    'grid';
                document.querySelector('input[name=token]').value = '';
            });

        document.getElementById('pay-now').addEventListener('click', (e) => {
            if (
                document.querySelector('input[name=token]').value.length !== 0
            ) {
                document.getElementById('pay-now').disabled = true;
                document
                    .querySelector('#pay-now > svg')
                    .classList.remove('hidden');
                document
                    .querySelector('#pay-now > span')
                    .classList.add('hidden');

                this.stripe
                    .confirmSepaDebitPayment(
                        document.querySelector('meta[name=pi-client-secret')
                            .content,
                        {
                            payment_method:
                                document.querySelector('input[name=token]')
                                    .value,
                        }
                    )
                    .then((result) => {
                        if (result.error) {
                            return this.handleFailure(result.error.message);
                        }

                        return this.handleSuccess(result);
                    });
            } else {
                if (document.getElementById('sepa-name').value === '') {
                    document.getElementById('sepa-name').focus();
                    errors.textContent = document.querySelector(
                        'meta[name=translation-name-required]'
                    ).content;
                    errors.hidden = false;
                    return;
                }

                if (
                    document.getElementById('sepa-email-address').value === ''
                ) {
                    document.getElementById('sepa-email-address').focus();
                    errors.textContent = document.querySelector(
                        'meta[name=translation-email-required]'
                    ).content;
                    errors.hidden = false;
                    return;
                }

                if (
                    !document.getElementById('sepa-mandate-acceptance').checked
                ) {
                    errors.textContent = document.querySelector(
                        'meta[name=translation-terms-required]'
                    ).content;
                    errors.hidden = false;

                    return;
                }

                document.getElementById('pay-now').disabled = true;
                document
                    .querySelector('#pay-now > svg')
                    .classList.remove('hidden');
                document
                    .querySelector('#pay-now > span')
                    .classList.add('hidden');

                this.stripe
                    .confirmSepaDebitPayment(
                        document.querySelector('meta[name=pi-client-secret')
                            .content,
                        {
                            payment_method: {
                                sepa_debit: this.iban,
                                billing_details: {
                                    name: document.getElementById('sepa-name')
                                        .value,
                                    email: document.getElementById(
                                        'sepa-email-address'
                                    ).value,
                                },
                            },
                        }
                    )
                    .then((result) => {
                        if (result.error) {
                            return this.handleFailure(result.error.message);
                        }

                        return this.handleSuccess(result);
                    });
            }
        });
    };

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

        if (document.querySelector('input[name=token]').value.length > 2) {
            document.querySelector('input[name="store_card"]').value = false;
        }

        document.getElementById('server-response').submit();
    }

    handleFailure(message) {
        let errors = document.getElementById('errors');

        errors.textContent = '';
        errors.textContent = message;
        errors.hidden = false;

        document.getElementById('pay-now').disabled = false;
        document.querySelector('#pay-now > svg').classList.add('hidden');
        document.querySelector('#pay-now > span').classList.remove('hidden');
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
}

function boot() {
    const publishableKey =
        document.querySelector('meta[name="stripe-publishable-key"]')
            ?.content ?? '';

    const stripeConnect =
        document.querySelector('meta[name="stripe-account-id"]')?.content ?? '';

    new ProcessSEPA(publishableKey, stripeConnect).setupStripe().handle();

    /** @type {NodeListOf<HTMLInputElement>} */
    const tokens = document.querySelectorAll('input.toggle-payment-with-token');

    if (tokens.length > 0) {
        tokens[0].click();
    }
}

instant() ? boot() : wait('#stripe-sepa-payment').then(() => boot());
