/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2024. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

import { wait } from '../wait';

wait('#stripe-ach-payment').then(() => ach());

function ach() {
    let payNow = document.getElementById('pay-now');

    if (payNow) {
        Array.from(
            document.getElementsByClassName('toggle-payment-with-token')
        ).forEach((element) =>
            element.addEventListener('click', (element) => {
                document.querySelector('input[name=source]').value =
                    element.target.dataset.token;
            })
        );
        payNow.addEventListener('click', function () {
            let payNowButton = document.getElementById('pay-now');
            payNowButton.disabled = true;
            payNowButton.querySelector('svg').classList.remove('hidden');
            payNowButton.querySelector('span').classList.add('hidden');
            document.getElementById('server-response').submit();
        });
    }

    const first = document.querySelector('input[name="payment-type"]');

    if (first) {
        first.click();
    }

    document.getElementById('new-bank').addEventListener('click', (ev) => {
        if (!document.getElementById('accept-terms').checked) {
            errors.textContent =
                'You must accept the mandate terms prior to making payment.';
            errors.hidden = false;
            return;
        }

        errors.hidden = true;

        let stripe;

        let publishableKey = document.querySelector(
            'meta[name="stripe-publishable-key"]'
        ).content;

        let stripeConnect = document.querySelector(
            'meta[name="stripe-account-id"]'
        )?.content;

        if (stripeConnect) {
            stripe = Stripe(publishableKey, { stripeAccount: stripeConnect });
        } else {
            stripe = Stripe(publishableKey);
        }

        let newBankButton = document.getElementById('new-bank');
        newBankButton.disabled = true;
        newBankButton.querySelector('svg').classList.remove('hidden');
        newBankButton.querySelector('span').classList.add('hidden');

        ev.preventDefault();
        const accountHolderNameField = document.getElementById(
            'account-holder-name-field'
        );
        const emailField = document.getElementById('email-field');
        const clientSecret = document.querySelector(
            'meta[name="client_secret"]'
        )?.content;
        // Calling this method will open the instant verification dialog.
        stripe
            .collectBankAccountForPayment({
                clientSecret: clientSecret,
                params: {
                    payment_method_type: 'us_bank_account',
                    payment_method_data: {
                        billing_details: {
                            name: accountHolderNameField.value,
                            email: emailField.value,
                        },
                    },
                },
                expand: ['payment_method'],
            })
            .then(({ paymentIntent, error }) => {
                if (error) {
                    console.error(error.message);
                    errors.textContent = error.message;
                    errors.hidden = false;
                    resetButtons();

                    // PaymentMethod collection failed for some reason.
                } else if (paymentIntent.status === 'requires_payment_method') {
                    // Customer canceled the hosted verification modal. Present them with other
                    // payment method type options.

                    errors.textContent =
                        'We were unable to process the payment with this account, please try another one.';
                    errors.hidden = false;
                    resetButtons();
                    return;
                } else if (paymentIntent.status === 'requires_confirmation') {
                    let bank_account_response = document.getElementById(
                        'bank_account_response'
                    );
                    bank_account_response.value = JSON.stringify(paymentIntent);

                    confirmPayment(stripe, clientSecret);
                }

                resetButtons();
                return;
            });
    });

    function confirmPayment(stripe, clientSecret) {
        stripe
            .confirmUsBankAccountPayment(clientSecret)
            .then(({ paymentIntent, error }) => {
                console.log(paymentIntent);
                if (error) {
                    console.error(error.message);
                    // The payment failed for some reason.
                } else if (paymentIntent.status === 'requires_payment_method') {
                    // Confirmation failed. Attempt again with a different payment method.

                    errors.textContent =
                        'We were unable to process the payment with this account, please try another one.';
                    errors.hidden = false;
                    resetButtons();
                } else if (paymentIntent.status === 'processing') {
                    // Confirmation succeeded! The account will be debited.

                    let gateway_response =
                        document.getElementById('gateway_response');
                    gateway_response.value = JSON.stringify(paymentIntent);
                    document.getElementById('server-response').submit();
                } else if (
                    paymentIntent.next_action?.type ===
                        'verify_with_microdeposits' ||
                    paymentIntent.next_action?.type === 'requires_source_action'
                ) {
                    errors.textContent =
                        'You will receive an email with details on how to verify your bank account and process payment.';
                    errors.hidden = false;
                    document.getElementById('new-bank').style.visibility =
                        'hidden';

                    let gateway_response =
                        document.getElementById('gateway_response');
                    gateway_response.value = JSON.stringify(paymentIntent);
                    document.getElementById('server-response').submit();
                }
            });
    }

    function resetButtons() {
        let newBankButton = document.getElementById('new-bank');
        newBankButton.disabled = false;
        newBankButton.querySelector('svg').classList.add('hidden');
        newBankButton.querySelector('span').classList.remove('hidden');
    }
}
