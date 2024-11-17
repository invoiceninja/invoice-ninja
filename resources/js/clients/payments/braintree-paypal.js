/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

import { wait, instant } from '../wait';

class BraintreePayPal {
    initBraintreeDataCollector() {
        window.braintree.client.create(
            {
                authorization: document.querySelector('meta[name=client-token]')
                    .content,
            },
            function (err, clientInstance) {
                window.braintree.dataCollector.create(
                    {
                        client: clientInstance,
                        paypal: true,
                    },
                    function (err, dataCollectorInstance) {
                        if (err) {
                            return;
                        }

                        document.querySelector(
                            'input[name=client-data]'
                        ).value = dataCollectorInstance.deviceData;
                    }
                );
            }
        );
    }

    static getPaymentDetails() {
        return {
            flow: 'vault',
        };
    }

    static handleErrorMessage(message) {
        let errorsContainer = document.getElementById('errors');

        errorsContainer.innerText = message;
        errorsContainer.hidden = false;
    }

    handlePaymentWithToken() {
        Array.from(
            document.getElementsByClassName('toggle-payment-with-token')
        ).forEach((element) =>
            element.addEventListener('click', (element) => {
                document
                    .getElementById('paypal-button')
                    .classList.add('hidden');
                document.getElementById('save-card--container').style.display =
                    'none';
                document.querySelector('input[name=token]').value =
                    element.target.dataset.token;

                document
                    .getElementById('pay-now-with-token')
                    .classList.remove('hidden');
                document.getElementById('pay-now').classList.add('hidden');
            })
        );

        let payNowWithToken = document.getElementById('pay-now-with-token');

        payNowWithToken.addEventListener('click', (element) => {
            payNowWithToken.disabled = true;
            payNowWithToken.querySelector('svg').classList.remove('hidden');
            payNowWithToken.querySelector('span').classList.add('hidden');

            document.getElementById('server-response').submit();
        });
    }

    handle() {
        this.initBraintreeDataCollector();
        this.handlePaymentWithToken();

        braintree.client
            .create({
                authorization: document.querySelector('meta[name=client-token]')
                    .content,
            })
            .then(function (clientInstance) {
                return braintree.paypalCheckout.create({
                    client: clientInstance,
                });
            })
            .then(function (paypalCheckoutInstance) {
                return paypalCheckoutInstance
                    .loadPayPalSDK({
                        vault: true,
                    })
                    .then(function (paypalCheckoutInstance) {
                        return paypal
                            .Buttons({
                                fundingSource: paypal.FUNDING.PAYPAL,

                                createBillingAgreement: function () {
                                    return paypalCheckoutInstance.createPayment(
                                        BraintreePayPal.getPaymentDetails()
                                    );
                                },

                                onApprove: function (data, actions) {
                                    return paypalCheckoutInstance
                                        .tokenizePayment(data)
                                        .then(function (payload) {
                                            document
                                                .querySelector('#paypal-button')
                                                ?.classList.add('hidden');
                                            document
                                                .querySelector(
                                                    '#paypal-spinner'
                                                )
                                                ?.classList.remove('hidden');

                                            let tokenBillingCheckbox =
                                                document.querySelector(
                                                    'input[name="token-billing-checkbox"]:checked'
                                                );

                                            if (tokenBillingCheckbox) {
                                                document.querySelector(
                                                    'input[name="store_card"]'
                                                ).value =
                                                    tokenBillingCheckbox.value;
                                            }

                                            document.querySelector(
                                                'input[name=gateway_response]'
                                            ).value = JSON.stringify(payload);
                                            document
                                                .getElementById(
                                                    'server-response'
                                                )
                                                .submit();
                                        });
                                },

                                onCancel: function (data) {
                                    // ..
                                },

                                onError: function (err) {
                                    console.log(err.message);

                                    BraintreePayPal.handleErrorMessage(
                                        err.message
                                    );
                                },
                            })
                            .render('#paypal-button');
                    });
            })
            .catch(function (err) {
                console.log(err.message);

                BraintreePayPal.handleErrorMessage(err.message);
            });
    }
}

function boot() {
    new BraintreePayPal().handle();

    /** @type {NodeListOf<HTMLInputElement>} */
    const tokens = document.querySelectorAll('input.toggle-payment-with-token');

    if (tokens.length > 0) {
        tokens[0].click();
    }
}

instant() ? boot() : wait('#braintree-paypal-payment').then(() => boot());
