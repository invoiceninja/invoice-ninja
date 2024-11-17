<?php

namespace Tests\Feature\EInvoice\Validation;

use Tests\TestCase;
use Illuminate\Support\Facades\Validator;
use Modules\Admin\Http\Requests\EInvoice\Peppol\AddTaxIdentifierRequest;

class AddTaxIdentifierRequestTest extends TestCase
{
    protected AddTaxIdentifierRequest $request;

    private array $vat_regex_patterns = [
        'DE' => '/^DE\d{9}$/',
        'AT' => '/^ATU\d{8}$/',
        'BE' => '/^BE0\d{9}$/',
        'BG' => '/^BG\d{9,10}$/',
        'CY' => '/^CY\d{8}L$/',
        'HR' => '/^HR\d{11}$/',
        'DK' => '/^DK\d{8}$/',
        'ES' => '/^ES[A-Z0-9]\d{7}[A-Z0-9]$/',
        'EE' => '/^EE\d{9}$/',
        'FI' => '/^FI\d{8}$/',
        'FR' => '/^FR\d{2}\d{9}$/',
        'EL' => '/^EL\d{9}$/',
        'HU' => '/^HU\d{8}$/',
        'IE' => '/^IE\d{7}[A-Z]{1,2}$/',
        'IT' => '/^IT\d{11}$/',
        'LV' => '/^LV\d{11}$/',
        'LT' => '/^LT(\d{9}|\d{12})$/',
        'LU' => '/^LU\d{8}$/',
        'MT' => '/^MT\d{8}$/',
        'NL' => '/^NL\d{9}B\d{2}$/',
        'PL' => '/^PL\d{10}$/',
        'PT' => '/^PT\d{9}$/',
        'CZ' => '/^CZ\d{8,10}$/',
        'RO' => '/^RO\d{2,10}$/',
        'SK' => '/^SK\d{10}$/',
        'SI' => '/^SI\d{8}$/',
        'SE' => '/^SE\d{12}$/',
    ];

    protected function setUp(): void
    {
        
        if (!class_exists(\Modules\Admin\Http\Requests\EInvoice\Peppol\AddTaxIdentifierRequest::class)) {
            $this->markTestSkipped('Skip test for GH Actions');
        }

        parent::setUp();
        $this->request = new AddTaxIdentifierRequest();
    }

    public function testValidInput()
    {
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
        $data = [
            'country' => 'DE',
        ];

        $this->request->initialize($data);
        $validator = Validator::make($data, $this->request->rules());

        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('vat_number', $validator->errors()->toArray());
    }
}
