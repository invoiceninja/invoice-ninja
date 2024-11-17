<?php

namespace Tests\Feature\EInvoice\RequestValidation;

use App\Http\Requests\EInvoice\UpdateEInvoiceConfiguration;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Tests\TestCase;
use Illuminate\Support\Facades\Validator;

class UpdateEInvoiceConfigurationTest extends TestCase
{
    protected UpdateEInvoiceConfiguration $request;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(
            ThrottleRequests::class
        );

        $this->request = new UpdateEInvoiceConfiguration();
    }

    public function testConfigValidationFails()
    {
        $data = [
            'entddity' => 'invoice',
        ];

        $this->request->initialize($data);
        $validator = Validator::make($data, $this->request->rules());

        $this->assertFalse($validator->passes());
    }

    public function testConfigValidation()
    {
        $data = [
            'entity' => 'invoice',
        ];

        $this->request->initialize($data);
        $validator = Validator::make($data, $this->request->rules());

        $this->assertTrue($validator->passes());
    }

    public function testConfigValidationInvalidcode()
    {
        $data = [
            'entity' => 'invoice',
            'payment_means' => [[
                'code' => 'invalidcodehere'
            ]]
        ];

        $this->request->initialize($data);
        $validator = Validator::make($data, $this->request->rules());

        $this->assertFalse($validator->passes());
    }

    public function testValidatesPaymentMeansForBankTransfer()
    {
        $data = [
            'entity' => 'invoice',
            'payment_means' => [[
                'code' => '30',
                'iban' => '123456789101112254',
                'bic_swift' => 'DEUTDEFF',
                'account_holder' => 'John Doe Company Limited'
            ]]
        ];

        $this->request->initialize($data);
        $validator = Validator::make($data, $this->request->rules());

        $this->assertTrue($validator->passes());
    }

    public function testValidatesPaymentMeansForCardPayment()
    {
        $data = [
            'entity' => 'invoice',
            'payment_means' => [[
                'code' => '48',
                'card_type' => 'VISA',
                'iban' => '12345678'
            ]]
        ];

        $this->request->initialize($data);
        $validator = Validator::make($data, $this->request->rules());

        $this->assertFalse($validator->passes());
    }

    public function testValidatesPaymentMeansForCreditCard()
    {
        $data = [
            'entity' => 'invoice',
            'payment_means' => [[
                'code' => '54',
                'card_type' => 'VISA',
                'card_number' => '************1234',
                'card_holder' => 'John Doe'
            ]]
        ];

        $this->request->initialize($data);
        $validator = Validator::make($data, $this->request->rules());

        $this->assertTrue($validator->passes());
    }

    public function testFailsValidationWhenRequiredFieldsAreMissing()
    {
        $data = [
            'entity' => 'invoice',
            'payment_means' => [[
                'code' => '30',
            ]]
        ];

        $this->request->initialize($data);
        $validator = Validator::make($data, $this->request->rules());
        $this->assertFalse($validator->passes());

        $this->assertTrue($validator->errors()->has('payment_means.0.bic_swift'));

    }

    public function testFailsValidationWithInvalidPaymentMeansCode()
    {
        $data = [
            'entity' => 'invoice',
            'payment_means' => [[
                'code' => '999',
            ]]
        ];

        $this->request->initialize($data);
        $validator = Validator::make($data, $this->request->rules());

        $this->assertFalse($validator->passes());
        $this->assertTrue($validator->errors()->has('payment_means.0.code'));
    }

    public function testValidatesPaymentMeansForDirectDebit()
    {
        $data = [
            'entity' => 'invoice',
            'payment_means' => [[
                'code' => '49',
                'payer_bank_account' => '12345678',
                'bic_swift' => 'DEUTDEFF'
            ]]
        ];

        $this->request->initialize($data);
        $validator = Validator::make($data, $this->request->rules());

        $this->assertTrue($validator->passes());
    }

    public function testValidatesPaymentMeansForBookEntry()
    {
        $data = [
            'entity' => 'invoice',
            'payment_means' => [[
                'code' => '15',
                'account_holder' => 'John Doe Company Limited',
                'bsb_sort' => '123456'
            ]]
        ];

        $this->request->initialize($data);
        $validator = Validator::make($data, $this->request->rules());

        $this->assertTrue($validator->passes());
    }
}
