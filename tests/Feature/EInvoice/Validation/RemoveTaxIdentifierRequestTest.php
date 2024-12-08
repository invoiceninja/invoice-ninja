<?php

namespace Tests\Feature\EInvoice\Validation;

use Tests\TestCase;
use App\Http\Requests\EInvoice\Peppol\RemoveTaxIdentifierRequest;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Validator;
use Tests\MockAccountData;

class RemoveTaxIdentifierRequestTest extends TestCase
{
    use MockAccountData;

    protected RemoveTaxIdentifierRequest $request;

    protected function setUp(): void
    {
        parent::setUp();

        $this->request = new RemoveTaxIdentifierRequest();

        $this->withoutMiddleware(
            ThrottleRequests::class
        );

        $this->makeTestData();
    }

    public function testMissingCountry(): void
    {
        $this->actingAs($this->user);

        $data = [
            'vat_number' => 'DE123456789',
        ];

        $this->request->initialize($data);

        $validator = Validator::make($data, $this->request->rules());

        $this->assertFalse($validator->passes());
    }

    public function testInvalidCountry(): void
    {
        $this->actingAs($this->user);

        $data = [
            'country' => 'US',
            'vat_number' => 'DE123456789',
        ];

        $this->request->initialize($data);

        $validator = Validator::make($data, $this->request->rules());

        $this->assertFalse($validator->passes());
    }

    public function testMissingVat(): void
    {
        $this->actingAs($this->user);

        $data = [
            'country' => 'DE',
            // 'vat_number' => 'DE123456789',
        ];

        $this->request->initialize($data);

        $validator = Validator::make($data, $this->request->rules());

        if(!$validator->passes())
            nlog($validator->errors());

        $this->assertFalse($validator->passes());
    }
}
