<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace Tests\Unit;

use App\DataMapper\EInvoice\TaxEntity;
use Tests\TestCase;
use App\Models\User;
use App\Models\Design;
use App\Models\License;
use App\Models\Payment;
use Illuminate\Support\Str;
use Illuminate\Foundation\Testing\DatabaseTransactions;

/**
 * 
 */
class LicenseTest extends TestCase
{
   use DatabaseTransactions;
   
    protected function setUp(): void
    {
        parent::setUp();
    }

    public function stubLicense($tes = [])
    {
        $entities = [];

        foreach($tes as $te)
        {
            $te = new TaxEntity([
                'legal_entity_id' => $te['legal_entity_id'] ?? 1,
                'company_key' => $te['company_key'] ?? '',
                'received_documents' => $te['received_documents'] ?? []
            ]);

            $entities[] = $te;
        }

        $l = new License();
        $l->license_key = Str::random(32);
        $l->email = 'test@gmail.com';
        $l->transaction_reference = Str::random(10);
        $l->e_invoice_quota = 0;
        $l->entities = $entities;
        $l->save();

        return $l;

    }

    public function testTaxEntiyFind()
    {
        $tes = [
            [
                'legal_entity_id' => 22,
                'company_key' => \Illuminate\Support\Str::random(32),
                'received_documents' => []
            ],
            [
                'legal_entity_id' => 33,
                'company_key' => \Illuminate\Support\Str::random(32),
                'received_documents' => []
            ],
            [
                'legal_entity_id' => 50,
                'company_key' => 'abcd',
                'received_documents' => []
            ]
        ];

        $l = $this->stubLicense($tes);

        $this->assertCount(3, $l->entities);

        $search = $l->findEntity('legal_entity_id', 50);

        $this->assertEquals(50, $search->legal_entity_id);
        $this->assertEquals('abcd', $search->company_key);

        $search = $l->findEntity('company_key', 'abcd');

        $this->assertEquals(50, $search->legal_entity_id);
        $this->assertEquals('abcd', $search->company_key);

    }

    public function testTaxEntityAddRemove()
    {
        $l = $this->stubLicense();

        $this->assertCount(0, $l->entities);

        $te = new TaxEntity([
            'legal_entity_id' => 123,
            'company_key' => 'qqqq',
            'received_documents' => []
        ]);

        $l->addEntity($te);
        $l->refresh();

        $this->assertCount(1, $l->entities);

        $l->removeEntity($te);
        $l->refresh();

        $this->assertCount(0, $l->entities);


    }


    public function testTaxEntityAddUpdate()
    {
        $l = $this->stubLicense();

        $this->assertCount(0, $l->entities);

        $te = new TaxEntity([
            'legal_entity_id' => 123,
            'company_key' => 'qqqq',
            'received_documents' => []
        ]);

        $l->addEntity($te);
        $l->refresh();

        $this->assertCount(1, $l->entities);

        $entity = $l->findEntity('legal_entity_id', 123);

        $this->assertNotNull($entity);

        $entity->legal_entity_id = 555;

        $l->updateEntity($entity,'company_key');
        $l->refresh();

        $entity = $l->findEntity('company_key', 'qqqq');

        $this->assertNotNull($entity);

        $this->assertEquals('qqqq', $entity->company_key);
        $this->assertEquals(555, $entity->legal_entity_id);
        
    }



    public function testLicenseValidity()
    {
        $l = new License();

        $l->license_key = Str::random(32);  
        $l->email = 'test@gmail.com';
        $l->transaction_reference = Str::random(10);
        $l->e_invoice_quota = 0;
        $l->save();

        $this->assertInstanceOf(License::class, $l);

        $this->assertTrue($l->isValid());
    }


    public function testLicenseValidityExpired()
    {
        $l = new License();

        $l->license_key = Str::random(32);
        $l->email = 'test@gmail.com';
        $l->transaction_reference = Str::random(10);
        $l->e_invoice_quota = 0;
        $l->save();

        $l->created_at = now()->subYears(2);
        $l->save();

        $this->assertInstanceOf(License::class, $l);

        $this->assertFalse($l->isValid());
    }


    public function testPopDocs()
    {

        $docs = [
            '8f47aa3c-9c51-4f4a-b45d-c275945d6284',
            '2e6d7168-43b9-4f92-9c3a-b8d6f3e9c5a1',
            'f9c12d4b-6e8a-4d7f-b3c5-a2e9f8d1b7c4',
            '7a3b5c9d-2e4f-4a6b-8c1d-9e7f5a3b2d4c',
            '1d9e8f7c-6b5a-4c3d-2e1f-9a8b7c6d5e4f',
            '4b8c2d6e-9f5a-3d7b-1c4e-8a2b9d7c6f5e',
            '3e2d1c9b-8a7f-6d5e-4c3b-2d1e9f8a7b6c',
            '5f4e3d2c-1b9a-8c7d-6e5f-4d3c2b1a9e8d',
            '9d8c7b6a-5e4f-3d2c-1b9a-8c7d6e5f4d3c',
            '6c5d4e3f-2b1a-9d8c-7e6f-5d4c3b2a1e9d',
        ];

        $processed_docs = [
            '8f47aa3c-9c51-4f4a-b45d-c275945d6284', 
            '2e6d7168-43b9-4f92-9c3a-b8d6f3e9c5a1',
            'f9c12d4b-6e8a-4d7f-b3c5-a2e9f8d1b7c4',
            '7a3b5c9d-2e4f-4a6b-8c1d-9e7f5a3b2d4c',
            '1d9e8f7c-6b5a-4c3d-2e1f-9a8b7c6d5e4f',
            '4b8c2d6e-9f5a-3d7b-1c4e-8a2b9d7c6f5e',
            '3e2d1c9b-8a7f-6d5e-4c3b-2d1e9f8a7b6c',
            '5f4e3d2c-1b9a-8c7d-6e5f-4d3c2b1a9e8d',
            '9d8c7b6a-5e4f-3d2c-1b9a-8c7d6e5f4d3c',
        ];

        $tes = [
            [
                'legal_entity_id' => rand(1, 100),
                'company_key' => \Illuminate\Support\Str::random(32),
                'received_documents' => []
            ],
            [
                'legal_entity_id' => rand(1, 100),
                'company_key' => \Illuminate\Support\Str::random(32),
                'received_documents' => []
            ],
            [
                'legal_entity_id' => 50,
                'company_key' => 'abcd',
                'received_documents' => $docs
            ]
        ];

        $l = $this->stubLicense($tes);

        $tax_entity = $l->findEntity('legal_entity_id', 50);

        $this->assertNotNull($tax_entity);
        $this->assertEquals(50, $tax_entity->legal_entity_id);
        $this->assertCount(10, $tax_entity->received_documents);

        $tax_entity->received_documents = array_values(
            array_diff($tax_entity->received_documents, $processed_docs)
        );

        $l->updateEntity($tax_entity);
        $l->refresh();

        $tax_entity = $l->findEntity('legal_entity_id', 50);

        $this->assertEquals(50, $tax_entity->legal_entity_id);
        $this->assertCount(1, $tax_entity->received_documents);

        $this->assertEquals('6c5d4e3f-2b1a-9d8c-7e6f-5d4c3b2a1e9d', $tax_entity->received_documents[0]);
    
    }
   

}
