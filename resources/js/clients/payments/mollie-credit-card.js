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

class _Mollie {
    constructor() {
        this.mollie = Mollie(
            document.querySelector('meta[name=mollie-profileId]')?.content,
            {
                testmode: document.querySelector('meta[name=mollie-testmode]')
                    ?.content,
                locale: 'en_US',
            }
        );
    }

    createCardHolderInput() {
        let cardHolder = this.mollie.createComponent('cardHolder');
        cardHolder.mount('#card-holder');

        let cardHolderError = document.getElementById('card-holder-error');

        cardHolder.addEventListener('change', function (event) {
            if (event.error && event.touched) {
                cardHolderError.textContent = event.error;
            } else {
                cardHolderError.textContent = '';
            }
        });

        return this;
    }

    createCardNumberInput() {
        let cardNumber = this.mollie.createComponent('cardNumber');
        cardNumber.mount('#card-number');

        let cardNumberError = document.getElementById('card-number-error');

        cardNumber.addEventListener('change', function (event) {
            if (event.error && event.touched) {
                cardNumberError.textContent = event.error;
            } else {
                cardNumberError.textContent = '';
            }
        });

        return this;
    }

    createExpiryDateInput() {
        let expiryDate = this.mollie.createComponent('expiryDate');
        expiryDate.mount('#expiry-date');

        let expiryDateError = document.getElementById('expiry-date-error');

        expiryDate.addEventListener('change', function (event) {
            if (event.error && event.touched) {
                expiryDateError.textContent = event.error;
            } else {
                expiryDateError.textContent = '';
            }
        });

        return this;
    }

    createCvvInput() {
        let verificationCode = this.mollie.createComponent('verificationCode');
        verificationCode.mount('#cvv');

        let verificationCodeError = document.getElementById('cvv-error');

        verificationCode.addEventListener('change', function (event) {
            if (event.error && event.touched) {
                verificationCodeError.textContent = event.error;
            } else {
                verificationCodeError.textContent = '';
            }
        });

        return this;
    }

    handlePayNowButton() {
        document.getElementById('pay-now').disabled = true;

        if (document.querySelector('input[name=token]').value !== '') {
            document.querySelector('input[name=gateway_response]').value = '';

            return document.getElementById('server-response').submit();
        }

        this.mollie.createToken().then(function (result) {
            let token = result.token;
            let error = result.error;

            if (error) {
                document.getElementById('pay-now').disabled = false;

                let errorsContainer = document.getElementById('errors');
                errorsContainer.innerText = error.message;
                errorsContainer.hidden = false;

                return;
            }

            let tokenBillingCheckbox = document.querySelector(
                'input[name="token-billing-checkbox"]:checked'
            );

            if (tokenBillingCheckbox) {
                document.querySelector('input[name="store_card"]').value =
                    tokenBillingCheckbox.value;
            }

            document.querySelector('input[name=gateway_response]').value =
                token;
            document.querySelector('input[name=token]').value = '';

            document.getElementById('server-response').submit();
        });
    }

    handle() {
        this.createCardHolderInput()
            .createCardNumberInput()
            .createExpiryDateInput()
            .createCvvInput();

        Array.from(
            document.getElementsByClassName('toggle-payment-with-token')
        ).forEach((element) =>
            element.addEventListener('click', (element) => {
                document
                    .getElementById('mollie--payment-container')
                    .classList.add('hidden');
                document.getElementById('save-card--container').style.display =
                    'none';
                document.querySelector('input[name=token]').value =
                    element.target.dataset.token;
            })
        );

        document
            .getElementById('toggle-payment-with-credit-card')
            .addEventListener('click', (element) => {
                document
                    .getElementById('mollie--payment-container')
                    .classList.remove('hidden');
                document.getElementById('save-card--container').style.display =
                    'grid';
                document.querySelector('input[name=token]').value = '';
            });

        document
            .getElementById('pay-now')
            .addEventListener('click', () => this.handlePayNowButton());
    }
}

function boot() {
    new _Mollie().handle();

    /** @type {NodeListOf<HTMLInputElement>} */
    const tokens = document.querySelectorAll('input.toggle-payment-with-token');

    if (tokens.length > 0) {
        tokens[0].click();
    }
}

instant() ? boot() : wait('#mollie-credit-card-payment').then(() => boot());
