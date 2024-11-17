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

class CheckoutCreditCard {
    constructor() {
        this.tokens = [];
    }

    handlePaymentUsingToken(e) {
        document.getElementById('checkout--container').classList.add('hidden');
        document
            .getElementById('pay-now-with-token--container')
            .classList.remove('hidden');
        document.getElementById('save-card--container').style.display = 'none';

        document.querySelector('input[name=token]').value =
            e.target.dataset.token;
    }

    handlePaymentUsingCreditCard(e) {
        document
            .getElementById('checkout--container')
            .classList.remove('hidden');
        document
            .getElementById('pay-now-with-token--container')
            .classList.add('hidden');
        document.getElementById('save-card--container').style.display = 'grid';

        document.querySelector('input[name=token]').value = '';

        const payButton = document.getElementById('pay-button');

        const publicKey =
            document.querySelector('meta[name="public-key"]').content ?? '';
        const form = document.getElementById('payment-form');

        Frames.init(publicKey);

        Frames.addEventHandler(
            Frames.Events.CARD_VALIDATION_CHANGED,
            function (event) {
                payButton.disabled = !Frames.isCardValid();
            }
        );

        Frames.addEventHandler(
            Frames.Events.CARD_TOKENIZATION_FAILED,
            function (event) {
                payButton.disabled = false;
            }
        );

        Frames.addEventHandler(Frames.Events.CARD_TOKENIZED, function (event) {
            payButton.disabled = true;

            document.querySelector('input[name="gateway_response"]').value =
                JSON.stringify(event);

            document.querySelector('input[name="store_card"]').value =
                document.querySelector(
                    'input[name=token-billing-checkbox]:checked'
                ).value;

            document.getElementById('server-response').submit();
        });

        form.addEventListener('submit', function (event) {
            event.preventDefault();
            payButton.disabled = true;
            Frames.submitCard();
        });
    }

    completePaymentUsingToken(e) {
        let btn = document.getElementById('pay-now-with-token');

        btn.disabled = true;
        btn.querySelector('svg').classList.remove('hidden');
        btn.querySelector('span').classList.add('hidden');

        document.getElementById('server-response').submit();
    }

    handle() {
        this.handlePaymentUsingCreditCard();

        Array.from(
            document.getElementsByClassName('toggle-payment-with-token')
        ).forEach((element) =>
            element.addEventListener('click', this.handlePaymentUsingToken)
        );

        document
            .getElementById('toggle-payment-with-credit-card')
            .addEventListener('click', this.handlePaymentUsingCreditCard);

        document
            .getElementById('pay-now-with-token')
            .addEventListener('click', this.completePaymentUsingToken);
    }
}

function boot() {
    new CheckoutCreditCard().handle();

    /** @type {NodeListOf<HTMLInputElement>} */
    const tokens = document.querySelectorAll('input.toggle-payment-with-token');

    if (tokens.length > 0) {
        tokens[0].click();
    }
}

instant() ? boot() : wait('#checkout-credit-card-payment').then(() => boot());
