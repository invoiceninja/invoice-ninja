/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license 
 */

class AuthorizeACH {
    constructor() {
        this.errors = document.getElementById('errors');
        this.key = document.querySelector(
            'meta[name="stripe-publishable-key"]'
        ).content;
        this.stripe_connect = document.querySelector(
            'meta[name="stripe-account-id"]'
        )?.content;
    }

    setupStripe = () => {

        if (this.stripeConnect){
           
           this.stripe = Stripe(this.key, {
              stripeAccount: this.stripeConnect,
            }); 
           
        }
        else {
            this.stripe = Stripe(this.key);
        }


        return this;
    };

    getFormData = () => {
        return {
            country: document.getElementById('country').value,
            currency: document.getElementById('currency').value,
            routing_number: document.getElementById('routing-number').value,
            account_number: document.getElementById('account-number').value,
            account_holder_name: document.getElementById('account-holder-name')
                .value,
            account_holder_type: document.querySelector(
                'input[name="account-holder-type"]:checked'
            ).value,
        };
    };

    handleError = (message) => {
        document.getElementById('save-button').disabled = false;
        document.querySelector('#save-button > svg').classList.add('hidden');
        document.querySelector('#save-button > span').classList.remove('hidden');

        this.errors.textContent = '';
        this.errors.textContent = message;
        this.errors.hidden = false;
    };

    handleSuccess = (response) => {
        document.getElementById('gateway_response').value = JSON.stringify(
            response
        );

        document.getElementById('server_response').submit();
    };

    handleSubmit = (e) => {

        if (!document.getElementById('accept-terms').checked) {
                errors.textContent = "You must accept the mandate terms prior to making payment.";
                errors.hidden = false;
                return;
        }

        document.getElementById('save-button').disabled = true;
        document.querySelector('#save-button > svg').classList.remove('hidden');
        document.querySelector('#save-button > span').classList.add('hidden');

        e.preventDefault();

        this.errors.textContent = '';
        this.errors.hidden = true;

        this.stripe
            .createToken('bank_account', this.getFormData())
            .then((result) => {
                if (result.hasOwnProperty('error')) {
                    return this.handleError(result.error.message);
                }

                return this.handleSuccess(result);
            });
    };

    handle() {
        document
            .getElementById('save-button')
            .addEventListener('click', (e) => this.handleSubmit(e));
    }
}

new AuthorizeACH().setupStripe().handle();
