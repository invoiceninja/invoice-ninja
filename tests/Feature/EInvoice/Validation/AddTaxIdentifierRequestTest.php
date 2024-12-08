<?php

namespace Tests\Feature\EInvoice\Validation;

use Tests\TestCase;
use Illuminate\Support\Facades\Validator;
use App\Http\Requests\EInvoice\Peppol\AddTaxIdentifierRequest;
use App\Models\Company;
use App\Models\CompanyToken;
use App\Models\CompanyUser;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Tests\MockAccountData;

class AddTaxIdentifierRequestTest extends TestCase
{
    use MockAccountData;

    protected AddTaxIdentifierRequest $request;

    protected function setUp(): void
    {
        parent::setUp();

        $this->request = new AddTaxIdentifierRequest();


        $this->withoutMiddleware(
            ThrottleRequests::class
        );

        $this->makeTestData();
    }

    public function testValidInput()
    {
        $this->actingAs($this->user);

        $data = [
            'country' => 'DE',
            'vat_number' => 'DE123456789',
        ];

        $this->request->initialize($data);
        $validator = Validator::make($data, $this->request->rules());

        $this->assertTrue($validator->passes());
    }

    public function testInvalidCountry()
    {
        $this->actingAs($this->user);

        $data = [
            'country' => 'US',
            'vat_number' => 'DE123456789',
        ];

        $this->request->initialize($data);

        $validator = Validator::make($data, $this->request->rules());

        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('country', $validator->errors()->toArray());
    }

    public function testInvalidVatNumber()
    {
        $this->actingAs($this->user);

        $data = [
            'country' => 'DE',
            'vat_number' => 'DE12345', // Too short
        ];

        $this->request->initialize($data);
        $validator = Validator::make($data, $this->request->rules());

        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('vat_number', $validator->errors()->toArray());
    }

    public function testMissingCountry()
    {
        $this->actingAs($this->user);

        $data = [
            'vat_number' => 'DE123456789',
        ];

        $this->request->initialize($data);
        $validator = Validator::make($data, $this->request->rules());

        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('country', $validator->errors()->toArray());
    }

    public function testMissingVatNumber()
    {
        $this->actingAs($this->user);

        $data = [
            'country' => 'DE',
        ];

        $this->request->initialize($data);
        $validator = Validator::make($data, $this->request->rules());

        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('vat_number', $validator->errors()->toArray());
    }

    public function testSameCountryFails()
    {
        $this->actingAs($this->user);

        $this->user->setCompany($this->company);

        $settings = $this->company->settings;
        $settings->country_id = 276; // DE

        $this->company->settings = $settings;
        $this->company->save();

        $data = [
            'country' => $settings->country_id,
            'vat_number' => 'DE123456789',
        ];

        $this->request->initialize($data);
        $this->request->prepareForValidation();

        $validator = Validator::make($data, $this->request->rules());

        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('country', $validator->errors()->toArray());
    }
}
