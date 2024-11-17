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

class BraintreeCreditCard {
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

    mountBraintreePaymentWidget() {
        window.braintree.dropin.create(
            {
                authorization: document.querySelector('meta[name=client-token]')
                    .content,
                container: '#dropin-container',
                threeDSecure:
                    document
                        .querySelector('input[name=threeds_enable]')
                        .value.toLowerCase() === 'true',
            },
            this.handleCallback
        );
    }

    handleCallback(error, dropinInstance) {
        if (error) {
            console.error(error);
            return;
        }

        let payNow = document.getElementById('pay-now');
        let params = JSON.parse(
            document.querySelector('input[name=threeds]').value
        );

        payNow.addEventListener('click', () => {
            dropinInstance.requestPaymentMethod(
                {
                    threeDSecure: {
                        challengeRequested: true,
                        amount: params.amount,
                        email: params.email,
                        billingAddress: {
                            givenName: params.billingAddress.givenName, // ASCII-printable characters required, else will throw a validation error
                            surname: params.billingAddress.surname, // ASCII-printable characters required, else will throw a validation error
                            phoneNumber: params.billingAddress.phoneNumber,
                            streetAddress: params.billingAddress.streetAddress,
                            extendedAddress:
                                params.billingAddress.extendedAddress,
                            locality: params.billingAddress.locality,
                            region: params.billingAddress.region,
                            postalCode: params.billingAddress.postalCode,
                            countryCodeAlpha2:
                                params.billingAddress.countryCodeAlpha2,
                        },
                    },
                },
                function (err, payload) {
                    if (err) {
                        console.log(err);
                        dropin.clearSelectedPaymentMethod();
                        alert(
                            'There was a problem verifying this card, please contact your merchant'
                        );
                        return;
                    }

                    if (
                        document.querySelector('input[name=threeds_enable]')
                            .value === 'true' &&
                        !payload.liabilityShifted
                    ) {
                        console.log('Liability did not shift', payload);
                        alert(
                            'There was a problem verifying this card, please contact your merchant'
                        );
                        return;
                    }

                    payNow.disabled = true;

                    payNow.querySelector('svg').classList.remove('hidden');
                    payNow.querySelector('span').classList.add('hidden');

                    document.querySelector(
                        'input[name=gateway_response]'
                    ).value = JSON.stringify(payload);

                    let tokenBillingCheckbox = document.querySelector(
                        'input[name="token-billing-checkbox"]:checked'
                    );

                    if (tokenBillingCheckbox) {
                        document.querySelector(
                            'input[name="store_card"]'
                        ).value = tokenBillingCheckbox.value;
                    }

                    document.getElementById('server-response').submit();
                }
            );
        });
    }

    handle() {
        this.initBraintreeDataCollector();
        this.mountBraintreePaymentWidget();

        Array.from(
            document.getElementsByClassName('toggle-payment-with-token')
        ).forEach((element) =>
            element.addEventListener('click', (element) => {
                document
                    .getElementById('dropin-container')
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

        document
            .getElementById('toggle-payment-with-credit-card')
            .addEventListener('click', (element) => {
                document
                    .getElementById('dropin-container')
                    .classList.remove('hidden');
                document.getElementById('save-card--container').style.display =
                    'grid';
                document.querySelector('input[name=token]').value = '';

                document
                    .getElementById('pay-now-with-token')
                    .classList.add('hidden');
                document.getElementById('pay-now').classList.remove('hidden');
            });

        let payNowWithToken = document.getElementById('pay-now-with-token');

        payNowWithToken.addEventListener('click', (element) => {
            payNowWithToken.disabled = true;
            payNowWithToken.querySelector('svg').classList.remove('hidden');
            payNowWithToken.querySelector('span').classList.add('hidden');

            document.getElementById('server-response').submit();
        });
    }
}

function boot() {
    new BraintreeCreditCard().handle();

    /** @type {NodeListOf<HTMLInputElement>} */
    const tokens = document.querySelectorAll('input.toggle-payment-with-token');

    if (tokens.length > 0) {
        tokens[0].click();
    }
}

instant()
    ? boot()
    : wait('#braintree-credit-card-payment', 'meta[name=client-token]').then(
          () => boot()
      );
