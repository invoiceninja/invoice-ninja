/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

import { instant, wait } from '../wait';

class PayTraceCreditCard {
    constructor() {
        this.clientKey = document.querySelector(
            'meta[name=paytrace-client-key]'
        )?.content;
    }

    get creditCardStyles() {
        return {
            font_color: '#000',
            border_color: '#a1b1c9',
            border_style: 'dotted',
            font_size: '13pt',
            input_border_radius: '3px',
            input_border_width: '1px',
            input_font: 'Times New Roman, arial, fantasy',
            input_font_weight: '400',
            input_margin: '5px 0px 5px 0px',
            input_padding: '0px 5px 0px 5px',
            label_color: '#a0aec0',
            label_size: '16px',
            label_width: '150px',
            label_font: 'Times New Roman, sans-serif, serif',
            label_font_weight: 'light',
            label_margin: '5px 0px 0px 0px',
            label_padding: '0px 5px 0px 5px',
            background_color: 'white',
            height: '30px',
            width: '370px',
            padding_bottom: '0px',
        };
    }

    get codeStyles() {
        return {
            font_color: '#000',
            border_color: '#a1b1c9',
            border_style: 'dotted',
            font_size: '13pt',
            input_border_radius: '2px',
            input_border_width: '1px',
            input_font: 'serif, cursive, fantasy',
            input_font_weight: '700',
            input_margin: '5px 0px 5px 20px',
            input_padding: '0px 5px 0px 5px',
            label_color: '#a0aec0',
            label_size: '16px',
            label_width: '150px',
            label_font: 'sans-serif, arial, serif',
            label_font_weight: 'bold',
            label_margin: '5px 0px 0px 20px',
            label_padding: '2px 5px 2px 5px',
            background_color: 'white',
            height: '30px',
            width: '150px',
            padding_bottom: '2px',
        };
    }

    get expStyles() {
        return {
            font_color: '#000',
            border_color: '#a1b1c9',
            border_style: 'dashed',
            font_size: '12pt',
            input_border_radius: '0px',
            input_border_width: '2px',
            input_font: 'arial, cursive, fantasy',
            input_font_weight: '400',
            input_margin: '5px 0px 5px 0px',
            input_padding: '0px 5px 0px 5px',
            label_color: '#a0aec0',
            label_size: '16px',
            label_width: '150px',
            label_font: 'arial, fantasy, serif',
            label_font_weight: 'normal',
            label_margin: '5px 0px 0px 0px',
            label_padding: '2px 5px 2px 5px',
            background_color: 'white',
            height: '30px',
            width: '85px',
            padding_bottom: '2px',
            type: 'dropdown',
        };
    }

    updatePayTraceLabels() {
        window.PTPayment.getControl('securityCode').label.text(
            document.querySelector('meta[name=ctrans-cvv]').content
        );

        window.PTPayment.getControl('creditCard').label.text(
            document.querySelector('meta[name=ctrans-card_number]').content
        );

        window.PTPayment.getControl('expiration').label.text(
            document.querySelector('meta[name=ctrans-expires]').content
        );
    }

    setupPayTrace() {
        return window.PTPayment.setup({
            styles: {
                code: this.codeStyles,
                cc: this.creditCardStyles,
                exp: this.expStyles,
            },
            authorization: {
                clientKey: this.clientKey,
            },
        });
    }

    handlePaymentWithCreditCard(event) {
        const button = document.getElementById('pay-now');

        button.querySelector('svg').classList.remove('hidden');
        button.querySelector('span').classList.add('hidden');

        event.target.parentElement.disabled = true;
        document.getElementById('errors').hidden = true;

        window.PTPayment.validate((errors) => {
            if (errors.length >= 1) {
                let errorsContainer = document.getElementById('errors');

                errorsContainer.textContent = errors[0].description;
                errorsContainer.hidden = false;

                button.querySelector('svg').classList.add('hidden');
                button.querySelector('span').classList.remove('hidden');

                return (event.target.parentElement.disabled = false);
            }

            this.ptInstance
                .process()
                .then((response) => {
                    document.getElementById('HPF_Token').value =
                        response.message.hpf_token;
                    document.getElementById('enc_key').value =
                        response.message.enc_key;

                    let tokenBillingCheckbox = document.querySelector(
                        'input[name="token-billing-checkbox"]:checked'
                    );

                    if (tokenBillingCheckbox) {
                        document.querySelector(
                            'input[name="store_card"]'
                        ).value = tokenBillingCheckbox.value;
                    }

                    document.getElementById('server_response').submit();
                })
                .catch((error) => {
                    document.getElementById('errors').textContent =
                        JSON.stringify(error);
                    document.getElementById('errors').hidden = false;

                    button.querySelector('svg').classList.add('hidden');
                    button.querySelector('span').classList.remove('hidden');

                    console.log(error);
                });
        });
    }

    handlePaymentWithToken(event) {
        event.target.parentElement.disabled = true;

        const button = document.getElementById('pay-now');

        button.querySelector('svg').classList.remove('hidden');
        button.querySelector('span').classList.add('hidden');

        document.getElementById('server_response').submit();
    }

    handle() {
        Array.from(
            document.getElementsByClassName('toggle-payment-with-token')
        ).forEach((element) =>
            element.addEventListener('click', (element) => {
                document
                    .getElementById('paytrace--credit-card-container')
                    .classList.add('hidden');
                document.getElementById('save-card--container').style.display =
                    'none';
                document.querySelector('input[name=token]').value =
                    element.target.dataset.token;
            })
        );

        document
            .getElementById('toggle-payment-with-credit-card')
            ?.addEventListener('click', (element) => {
                document
                    .getElementById('paytrace--credit-card-container')
                    .classList.remove('hidden');
                document.getElementById('save-card--container').style.display =
                    'grid';
                document.querySelector('input[name=token]').value = '';

                this.setupPayTrace().then((instance) => {
                    this.ptInstance = instance;
                    this.updatePayTraceLabels();
                });
            });

        document.getElementById('pay-now').addEventListener('click', (e) => {
            if (document.querySelector('input[name=token]').value === '') {
                return this.handlePaymentWithCreditCard(e);
            }

            return this.handlePaymentWithToken(e);
        });

        if (
            Array.from(
                document.getElementsByClassName('toggle-payment-with-token')
            ).length === 0 &&
            !instant()
        ) {
            document.getElementById('toggle-payment-with-credit-card').click();
        }
    }
}

function boot() {
    new PayTraceCreditCard().handle();

    /** @type {NodeListOf<HTMLInputElement>} */
    const tokens = document.querySelectorAll('input.toggle-payment-with-token');

    if (tokens.length > 0) {
        tokens[0].click();
    }
}

instant() ? boot() : wait('#paytrace-credit-card-payment').then(() => boot());
