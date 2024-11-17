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

namespace Tests\Feature\Bank;

use Tests\TestCase;
use App\Models\Payment;
use Tests\MockAccountData;
use App\Models\BankIntegration;
use App\Models\BankTransaction;
use App\Models\BankTransactionRule;
use App\Models\Invoice;
use App\Services\Bank\ProcessBankRules;
use Illuminate\Validation\ValidationException;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Str;

class BankTransactionRuleTest extends TestCase
{
    use DatabaseTransactions;
    use MockAccountData;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();

        $this->withoutMiddleware(
            ThrottleRequests::class
        );

        $this->withoutExceptionHandling();

        $this->markTestSkipped('ignore');
    }

    public function testNewCreditMatchingRulesInvoiceStartsWith() 
    {

        $bi = BankIntegration::factory()->create([
                'company_id' => $this->company->id,
                'user_id' => $this->user->id,
                'account_id' => $this->account->id,
            ]);

        $hash = Str::random(32);
        $rand_amount = rand(1000,10000000);

        $bt = BankTransaction::factory()->create([
            'bank_integration_id' => $bi->id,
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'description' => $hash,
            'base_type' => 'CREDIT',
            'amount' => $rand_amount
        ]);

        $this->assertNull($bt->payment_id);

        $br = BankTransactionRule::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'matches_on_all' => false,
            'auto_convert' => false,
            'applies_to' => 'CREDIT',
            'rules' => [
                [
                    'search_key' => '$invoice.number',
                    'operator' => 'starts_with',
                ]
            ]
        ]);

        $i = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'amount' => $rand_amount,
            'balance' => $rand_amount,
            'number' => $hash,
            'status_id' => 2,
            'custom_value1' => substr($hash, 0, 8)
        ]);

        $this->assertEquals(BankTransaction::STATUS_UNMATCHED, $bt->status_id);

        (new ProcessBankRules($bt))->run();

        $bt = $bt->fresh();

        $this->assertEquals(BankTransaction::STATUS_MATCHED, $bt->status_id);
        $this->assertNotNull($i->id);
        $this->assertNotNull($bt->invoice_ids);
        $this->assertEquals($i->hashed_id, $bt->invoice_ids);
    }

    public function testNewCreditMatchingRulesInvoiceContains()
    {

        $bi = BankIntegration::factory()->create([
                'company_id' => $this->company->id,
                'user_id' => $this->user->id,
                'account_id' => $this->account->id,
            ]);

        $hash = Str::random(32);
        $rand_amount = rand(1000,10000000);

        $bt = BankTransaction::factory()->create([
            'bank_integration_id' => $bi->id,
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'description' => $hash,
            'base_type' => 'CREDIT',
            'amount' => $rand_amount
        ]);

        $this->assertNull($bt->payment_id);

        $br = BankTransactionRule::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'matches_on_all' => false,
            'auto_convert' => false,
            'applies_to' => 'CREDIT',
            'rules' => [
                [
                    'search_key' => '$invoice.number',
                    'operator' => 'contains',
                ]
            ]
        ]);

        $i = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'amount' => $rand_amount,
            'balance' => $rand_amount,
            'number' => $hash,
            'status_id' => 2,
            'custom_value1' => substr($hash, 0, 8)
        ]);

        $this->assertEquals(BankTransaction::STATUS_UNMATCHED, $bt->status_id);

        (new ProcessBankRules($bt))->run();

        $bt->fresh();

        $this->assertEquals(BankTransaction::STATUS_MATCHED, $bt->status_id);
        $this->assertNotNull($i->id);
        $this->assertNotNull($bt->invoice_ids);
        $this->assertEquals($i->hashed_id, $bt->invoice_ids);
    }

    public function testNewCreditMatchingRulesInvoiceNumber()
    {

        $bi = BankIntegration::factory()->create([
                'company_id' => $this->company->id,
                'user_id' => $this->user->id,
                'account_id' => $this->account->id,
            ]);

        $hash = Str::random(32);
        $rand_amount = rand(1000,10000000);

        $bt = BankTransaction::factory()->create([
            'bank_integration_id' => $bi->id,
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'description' => $hash,
            'base_type' => 'CREDIT',
            'amount' => $rand_amount
        ]);

        $this->assertNull($bt->payment_id);

        $br = BankTransactionRule::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'matches_on_all' => false,
            'auto_convert' => false,
            'applies_to' => 'CREDIT',
            'rules' => [
                [
                    'search_key' => '$invoice.number',
                    'operator' => 'is',
                ]
            ]
        ]);

        $i = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'amount' => $rand_amount,
            'balance' => $rand_amount,
            'number' => $hash,
            'status_id' => 2,
            'custom_value1' => substr($hash, 0, 8)
        ]);

        $this->assertEquals(BankTransaction::STATUS_UNMATCHED, $bt->status_id);

        (new ProcessBankRules($bt))->run();

        $bt = $bt->fresh();

        $this->assertEquals(BankTransaction::STATUS_MATCHED, $bt->status_id);
        $this->assertNotNull($i->id);
        $this->assertNotNull($bt->invoice_ids);
        $this->assertEquals($i->hashed_id, $bt->invoice_ids);
    }


    public function testNewCreditMatchingRulesInvoiceAmount()
    {

        $bi = BankIntegration::factory()->create([
                'company_id' => $this->company->id,
                'user_id' => $this->user->id,
                'account_id' => $this->account->id,
            ]);

        $hash = Str::random(32);
        $rand_amount = rand(1000,10000000);

        $bt = BankTransaction::factory()->create([
            'bank_integration_id' => $bi->id,
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'description' => $hash,
            'base_type' => 'CREDIT',
            'amount' => $rand_amount
        ]);

        $this->assertNull($bt->payment_id);

        $br = BankTransactionRule::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'matches_on_all' => false,
            'auto_convert' => false,
            'applies_to' => 'CREDIT',
            'rules' => [
                [
                    'search_key' => '$invoice.amount',
                    'operator' => '=',
                ]
            ]
        ]);

        $i = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'amount' => $rand_amount,
            'balance' => $rand_amount,
            'status_id' => 2,
            'custom_value1' => substr($hash, 0, 8)
        ]);

        $this->assertEquals(BankTransaction::STATUS_UNMATCHED, $bt->status_id);

        (new ProcessBankRules($bt))->run();

        $bt->fresh();

        $this->assertEquals(BankTransaction::STATUS_MATCHED, $bt->status_id);
        $this->assertNotNull($i->id);
        $this->assertNotNull($bt->invoice_ids);
        $this->assertEquals($i->hashed_id, $bt->invoice_ids);
    }

    public function testNewCreditMatchingRulesPaymentCustomValue()
    {

        $bi = BankIntegration::factory()->create([
                'company_id' => $this->company->id,
                'user_id' => $this->user->id,
                'account_id' => $this->account->id,
            ]);

        $hash = Str::random(32);
        $rand_amount = rand(1000,10000000);

        $bt = BankTransaction::factory()->create([
            'bank_integration_id' => $bi->id,
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'description' => $hash,
            'base_type' => 'CREDIT',
            'amount' => $rand_amount
        ]);

        $this->assertNull($bt->payment_id);

        $br = BankTransactionRule::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'matches_on_all' => false,
            'auto_convert' => false,
            'applies_to' => 'CREDIT',
            'rules' => [
                [
                    'search_key' => '$payment.custom1',
                    'operator' => 'starts_with',
                ]
            ]
        ]);

        $p = Payment::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'amount' => $rand_amount,
            'custom_value1' => substr($hash, 0, 8)
        ]);

        $this->assertEquals(BankTransaction::STATUS_UNMATCHED, $bt->status_id);

        (new ProcessBankRules($bt))->run();

        $bt = $bt->fresh();

        $this->assertEquals(BankTransaction::STATUS_MATCHED, $bt->status_id);
        $this->assertNotNull($p->id);
        $this->assertNotNull($bt->payment_id);
        $this->assertEquals($p->id, $bt->payment_id);
    }

    public function testNewCreditMatchingRulesPaymentStartsWith()
    {

        $bi = BankIntegration::factory()->create([
                'company_id' => $this->company->id,
                'user_id' => $this->user->id,
                'account_id' => $this->account->id,
            ]);

        $hash = Str::random(32);
        $rand_amount = rand(1000,10000000);

        $bt = BankTransaction::factory()->create([
            'bank_integration_id' => $bi->id,
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'description' => $hash,
            'base_type' => 'CREDIT',
            'amount' => $rand_amount
        ]);

        $this->assertNull($bt->payment_id);

        $br = BankTransactionRule::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'matches_on_all' => false,
            'auto_convert' => false,
            'applies_to' => 'CREDIT',
            'rules' => [
                [
                    'search_key' => '$payment.transaction_reference',
                    'operator' => 'starts_with',
                ]
            ]
        ]);

        $p = Payment::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'amount' => $rand_amount,
            'transaction_reference' => substr($hash, 0, 8)
        ]);

        $this->assertEquals(BankTransaction::STATUS_UNMATCHED, $bt->status_id);

        (new ProcessBankRules($bt))->run();


$bt = $bt->fresh();

        $this->assertEquals(BankTransaction::STATUS_MATCHED, $bt->status_id);
        $this->assertNotNull($p->id);
        $this->assertNotNull($bt->payment_id);
        $this->assertEquals($p->id, $bt->payment_id);
    }

    public function testNewCreditMatchingRulesPaymentAmount()
    {

        $bi = BankIntegration::factory()->create([
                'company_id' => $this->company->id,
                'user_id' => $this->user->id,
                'account_id' => $this->account->id,
            ]);

        $hash = md5(time());
        $rand_amount = rand(1000,10000000);

        $bt = BankTransaction::factory()->create([
            'bank_integration_id' => $bi->id,
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'description' => $hash,
            'base_type' => 'CREDIT',
            'amount' => $rand_amount
        ]);

        $this->assertNull($bt->payment_id);

        $br = BankTransactionRule::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'matches_on_all' => false,
            'auto_convert' => false,
            'applies_to' => 'CREDIT',
            'rules' => [
                [
                    'search_key' => '$payment.amount',
                    'operator' => '=',
                ]
            ]
        ]);

        $p = Payment::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'amount' => $rand_amount,
            'transaction_reference' => 'nein'
        ]);

        $this->assertEquals(BankTransaction::STATUS_UNMATCHED, $bt->status_id);

        (new ProcessBankRules($bt))->run();


$bt = $bt->fresh();

        $this->assertEquals(BankTransaction::STATUS_MATCHED, $bt->status_id);
        $this->assertNotNull($p->id);
        $this->assertNotNull($bt->payment_id);
        $this->assertEquals($p->id, $bt->payment_id);
    }

    public function testNewCreditMatchingRulesPaymentTransactionReferenceExactMatch()
    {

        $bi = BankIntegration::factory()->create([
                'company_id' => $this->company->id,
                'user_id' => $this->user->id,
                'account_id' => $this->account->id,
            ]);

        $hash = md5(time());
        $rand_amount = rand(1000,10000000);

        $bt = BankTransaction::factory()->create([
            'bank_integration_id' => $bi->id,
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'description' => $hash,
            'base_type' => 'CREDIT',
            'amount' => $rand_amount
        ]);

        $this->assertNull($bt->payment_id);

        $br = BankTransactionRule::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'matches_on_all' => false,
            'auto_convert' => false,
            'applies_to' => 'CREDIT',
            'rules' => [
                [
                    'search_key' => '$payment.transaction_reference',
                    'operator' => 'is',
                ]
            ]
        ]);

        $p = Payment::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'amount' => $rand_amount,
            'transaction_reference' => $hash
        ]);

        $this->assertEquals(BankTransaction::STATUS_UNMATCHED, $bt->status_id);

        (new ProcessBankRules($bt))->run();


$bt = $bt->fresh();

        $this->assertEquals(BankTransaction::STATUS_MATCHED, $bt->status_id);
        $this->assertNotNull($p->id);
        $this->assertNotNull($bt->payment_id);
        $this->assertEquals($p->id, $bt->payment_id);
    }

    public function testNewCreditMatchingRulesPaymentTransactionReferenceContains()
    {

        $bi = BankIntegration::factory()->create([
                'company_id' => $this->company->id,
                'user_id' => $this->user->id,
                'account_id' => $this->account->id,
            ]);

        $hash = Str::random(32);
        $rand_amount = rand(1000,10000000);

        $bt = BankTransaction::factory()->create([
            'bank_integration_id' => $bi->id,
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'description' => $hash,
            'base_type' => 'CREDIT',
            'amount' => $rand_amount
        ]);

        $this->assertNull($bt->payment_id);

        $br = BankTransactionRule::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'matches_on_all' => false,
            'auto_convert' => false,
            'applies_to' => 'CREDIT',
            'rules' => [
                [
                    'search_key' => '$payment.transaction_reference',
                    'operator' => 'contains',
                ]
            ]
        ]);

        $p = Payment::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'amount' => $rand_amount,
            'transaction_reference' => substr($hash, 3, 13)
        ]);

        $this->assertEquals(BankTransaction::STATUS_UNMATCHED, $bt->status_id);

        (new ProcessBankRules($bt))->run();


$bt = $bt->fresh();

        $this->assertEquals(BankTransaction::STATUS_MATCHED, $bt->status_id);
        $this->assertNotNull($p->id);
        $this->assertNotNull($bt->payment_id);
        $this->assertEquals($p->id, $bt->payment_id);
    }



    public function testMatchCreditOnInvoiceNumber()
    {

        $bi = BankIntegration::factory()->create([
                'company_id' => $this->company->id,
                'user_id' => $this->user->id,
                'account_id' => $this->account->id,
            ]);

        $hash = md5(time());

        $bt = BankTransaction::factory()->create([
            'bank_integration_id' => $bi->id,
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'description' => $hash,
            'base_type' => 'CREDIT',
            'amount' => 100
        ]);

        $br = BankTransactionRule::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'matches_on_all' => false,
            'auto_convert' => true,
            'applies_to' => 'CREDIT',
            'rules' => [
                [
                    'search_key' => '$invoice.number',
                    'operator' => 'is',
                ]
            ]
        ]);

        $bt = $bt->refresh();

        $debit_rules = $bt->company->debit_rules();

        $bt->service()->processRules();

        $bt = $bt->fresh();

    }

    public function testMatchingWithStripos()
    {
        $bt_value = strtolower(str_replace(" ", "", 'hello soldier'));
        $rule_value = strtolower(str_replace(" ", "", 'solider'));
        $rule_length = iconv_strlen($rule_value);

        $this->assertFalse(stripos($rule_value, $bt_value) !== false);
        $this->assertFalse(stripos($bt_value, $rule_value) !== false);
    }

    public function testBankRuleBulkActions()
    {
        $data = [
            'action' => 'archive',
            'ids' => [$this->bank_transaction_rule]
        ];

        $response = $this->withHeaders([
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/bank_transaction_rules/bulk', $data)
          ->assertStatus(200);


        $data = [
            'ids' => [$this->bank_transaction_rule->hashed_id],
            'action' => 'restore'
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/bank_transaction_rules/bulk', $data)
          ->assertStatus(200);

        $data = [
            'ids' => [$this->bank_transaction_rule->hashed_id],
            'action' => 'delete'
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/bank_transaction_rules/bulk', $data)
          ->assertStatus(200);
    }

    public function testValidationContainsRule()
    {
        $bi = BankIntegration::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
        ]);

        $bt = BankTransaction::factory()->create([
            'bank_integration_id' => $bi->id,
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'description' => 'HellO ThErE CowBoY',
            'base_type' => 'DEBIT',
            'amount' => 100
        ]);

        $br = BankTransactionRule::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'matches_on_all' => false,
            'auto_convert' => true,
            'applies_to' => 'DEBIT',
            'client_id' => $this->client->id,
            'vendor_id' => $this->vendor->id,
            'category_id' => $this->expense_category->id,
            'rules' => [
                [
                    'search_key' => 'description',
                    'operator' => 'contains',
                    'value' => 'hello',
                ]
            ]
        ]);

        $bt = $bt->refresh();

        $debit_rules = $bt->company->debit_rules();

        $bt->service()->processRules();

        $bt = $bt->fresh();

        $this->assertNotNull($bt->expense_id);
        // $this->assertNotNull($bt->expense->category_id);
        // $this->assertNotNull($bt->expense->vendor_id);

        $bt = null;
    }


    public function testUpdateValidationRules()
    {
        $br = BankTransactionRule::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'matches_on_all' => false,
            'auto_convert' => true,
            'applies_to' => 'DEBIT',
            'client_id' => $this->client->id,
            'vendor_id' => $this->vendor->id,
            'rules' => [
                [
                    'search_key' => 'amount',
                    'operator' => '<=',
                    'value' => 100,
                ]
            ]
        ]);


        $data = [
            "applies_to" => "DEBIT",
            "archived_at" => 0,
            "auto_convert" => false,
            "category_id" => $this->expense_category->hashed_id,
            "is_deleted" => false,
            "isChanged" => true,
            "matches_on_all" => true,
            "name" => "TEST 22",
            "updated_at" => 1669060432,
            "vendor_id" => $this->vendor->hashed_id
            ];

        $response = null;

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->putJson('/api/v1/bank_transaction_rules/'. $br->hashed_id. '?include=expense_category', $data);

        $response->assertStatus(200);


    }

    public function testMatchingBankTransactionExpenseAmountLessThanEqualTo()
    {
        $bi = BankIntegration::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
        ]);

        $bt = BankTransaction::factory()->create([
            'bank_integration_id' => $bi->id,
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'description' => 'xx',
            'base_type' => 'DEBIT',
            'amount' => 100
        ]);

        $br = BankTransactionRule::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'matches_on_all' => false,
            'auto_convert' => true,
            'applies_to' => 'DEBIT',
            'client_id' => $this->client->id,
            'vendor_id' => $this->vendor->id,
            'rules' => [
                [
                    'search_key' => 'amount',
                    'operator' => '<=',
                    'value' => 100,
                ]
            ]
        ]);


        $bt->company->refresh();

        $bt->refresh()->service()->processRules();

        $bt = $bt->fresh();

        $this->assertNotNull($bt->expense_id);

        $bt = null;
    }


    public function testMatchingBankTransactionExpenseAmountLessThan()
    {
        $br = BankTransactionRule::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'matches_on_all' => false,
            'auto_convert' => true,
            'applies_to' => 'DEBIT',
            'client_id' => $this->client->id,
            'vendor_id' => $this->vendor->id,
            'rules' => [
                [
                    'search_key' => 'amount',
                    'operator' => '<',
                    'value' => 100,
                ]
            ]
        ]);

        $bi = BankIntegration::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
        ]);

        $bt = BankTransaction::factory()->create([
            'bank_integration_id' => $bi->id,
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'description' => '',
            'base_type' => 'DEBIT',
            'amount' => 99
        ]);


        $bt->service()->processRules();

        $bt = $bt->fresh();

        $this->assertNotNull($bt->expense_id);
    }

    public function testMatchingBankTransactionExpenseAmountGreaterThan()
    {
        $br = BankTransactionRule::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'matches_on_all' => false,
            'auto_convert' => true,
            'applies_to' => 'DEBIT',
            'client_id' => $this->client->id,
            'vendor_id' => $this->vendor->id,
            'rules' => [
                [
                    'search_key' => 'amount',
                    'operator' => '>',
                    'value' => 100,
                ]
            ]
        ]);

        $bi = BankIntegration::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
        ]);

        $bt = BankTransaction::factory()->create([
            'bank_integration_id' => $bi->id,
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'description' => '',
            'base_type' => 'DEBIT',
            'amount' => 101
        ]);


        $bt->refresh()->service()->processRules();

        $bt = $bt->fresh();

        $this->assertNotNull($bt->expense_id);
    }


    public function testMatchingBankTransactionExpenseAmountMiss()
    {
        $br = BankTransactionRule::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'matches_on_all' => false,
            'auto_convert' => true,
            'applies_to' => 'DEBIT',
            'client_id' => $this->client->id,
            'vendor_id' => $this->vendor->id,
            'rules' => [
                [
                    'search_key' => 'amount',
                    'operator' => '=',
                    'value' => 100,
                ]
            ]
        ]);

        $bi = BankIntegration::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
        ]);

        $bt = BankTransaction::factory()->create([
            'bank_integration_id' => $bi->id,
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'description' => '',
            'base_type' => 'DEBIT',
            'amount' => 101
        ]);


        $bt->service()->processRules();

        $bt = $bt->fresh();

        $this->assertEmpty($bt->expense_id);
    }

    public function testMatchingBankTransactionExpenseAmount()
    {
        $br = BankTransactionRule::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'matches_on_all' => false,
            'auto_convert' => true,
            'applies_to' => 'DEBIT',
            'client_id' => $this->client->id,
            'vendor_id' => $this->vendor->id,
            'rules' => [
                [
                    'search_key' => 'amount',
                    'operator' => '=',
                    'value' => 100,
                ]
            ]
        ]);

        $bi = BankIntegration::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
        ]);

        $bt = BankTransaction::factory()->create([
            'bank_integration_id' => $bi->id,
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'description' => '',
            'base_type' => 'DEBIT',
            'amount' => 100
        ]);


        $bt->service()->processRules();

        $bt = $bt->fresh();

        $this->assertNotNull($bt->expense_id);
    }


    public function testMatchingBankTransactionExpenseIsEmpty()
    {
        $br = BankTransactionRule::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'matches_on_all' => false,
            'auto_convert' => true,
            'applies_to' => 'DEBIT',
            'client_id' => $this->client->id,
            'vendor_id' => $this->vendor->id,
            'rules' => [
                [
                    'search_key' => 'description',
                    'operator' => 'is_empty',
                    'value' => '',
                ]
            ]
        ]);

        $bi = BankIntegration::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
        ]);

        $bt = BankTransaction::factory()->create([
            'bank_integration_id' => $bi->id,
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'description' => '',
            'base_type' => 'DEBIT',
            'amount' => 100
        ]);

        $bt = $bt->refresh();

        $bt->service()->processRules();

        $bt = $bt->fresh();

        $this->assertNotNull($bt->expense_id);
    }

    public function testMatchingBankTransactionExpenseIsEmptyMiss()
    {
        $bi = BankIntegration::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
        ]);

        $bt = BankTransaction::factory()->create([
            'bank_integration_id' => $bi->id,
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'description' => 'asdadsa',
            'base_type' => 'DEBIT',
            'amount' => 100
        ]);


        $br = BankTransactionRule::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'matches_on_all' => false,
            'auto_convert' => true,
            'applies_to' => 'DEBIT',
            'client_id' => $this->client->id,
            'vendor_id' => $this->vendor->id,
            'rules' => [
                [
                    'search_key' => 'description',
                    'operator' => 'is_empty',
                    'value' => '',
                ]
            ]
        ]);


        $bt->load('company');

        $bt->service()->processRules();

        $bt = $bt->fresh();

        $this->assertEmpty($bt->expense_id);
    }


    public function testMatchingBankTransactionExpenseStartsWithMiss()
    {
        $br = BankTransactionRule::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'matches_on_all' => false,
            'auto_convert' => true,
            'applies_to' => 'DEBIT',
            'client_id' => $this->client->id,
            'vendor_id' => $this->vendor->id,
            'rules' => [
                [
                    'search_key' => 'description',
                    'operator' => 'starts_with',
                    'value' => 'chesst',
                ]
            ]
        ]);

        $bi = BankIntegration::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
        ]);

        $bt = BankTransaction::factory()->create([
            'bank_integration_id' => $bi->id,
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'description' => 'ChESSSty coughs are terrible',
            'base_type' => 'DEBIT',
            'amount' => 100
        ]);


        $bt->service()->processRules();

        $bt = $bt->fresh();

        $this->assertEmpty($bt->expense_id);
    }



    public function testMatchingBankTransactionExpenseStartsWith()
    {
        $br = BankTransactionRule::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'matches_on_all' => false,
            'auto_convert' => true,
            'applies_to' => 'DEBIT',
            'client_id' => $this->client->id,
            'vendor_id' => $this->vendor->id,
            'rules' => [
                [
                    'search_key' => 'description',
                    'operator' => 'starts_with',
                    'value' => 'chess',
                ]
            ]
        ]);

        $bi = BankIntegration::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
        ]);

        $bt = BankTransaction::factory()->create([
            'bank_integration_id' => $bi->id,
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'description' => 'ChESSSty coughs are terrible',
            'base_type' => 'DEBIT',
            'amount' => 100
        ]);


        $bt->service()->processRules();

        $bt = $bt->fresh();

        $this->assertNotNull($bt->expense_id);
    }


    public function testMatchingBankTransactionExpenseContainsMiss()
    {
        $br = BankTransactionRule::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'matches_on_all' => false,
            'auto_convert' => true,
            'applies_to' => 'DEBIT',
            'client_id' => $this->client->id,
            'vendor_id' => $this->vendor->id,
            'rules' => [
                [
                    'search_key' => 'description',
                    'operator' => 'contains',
                    'value' => 'asdddfd',
                ]
            ]
        ]);

        $bi = BankIntegration::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
        ]);

        $bt = BankTransaction::factory()->create([
            'bank_integration_id' => $bi->id,
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'description' => 'Something asd bizarre',
            'base_type' => 'DEBIT',
            'amount' => 100
        ]);


        $bt->service()->processRules();

        $bt = $bt->fresh();

        $this->assertEmpty($bt->expense_id);
    }


    public function testMatchingBankTransactionExpenseContains()
    {
        $br = BankTransactionRule::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'matches_on_all' => false,
            'auto_convert' => true,
            'applies_to' => 'DEBIT',
            'client_id' => $this->client->id,
            'vendor_id' => $this->vendor->id,
            'rules' => [
                [
                    'search_key' => 'description',
                    'operator' => 'contains',
                    'value' => 'asd',
                ]
            ]
        ]);

        $bi = BankIntegration::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
        ]);

        $bt = BankTransaction::factory()->create([
            'bank_integration_id' => $bi->id,
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'description' => 'Something asd bizarre',
            'base_type' => 'DEBIT',
            'amount' => 100
        ]);


        $bt->service()->processRules();

        $bt = $bt->fresh();

        $this->assertNotNull($bt->expense_id);
    }

    public function testMatchingBankTransactionExpenseMiss()
    {
        $br = BankTransactionRule::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'matches_on_all' => false,
            'auto_convert' => true,
            'applies_to' => 'DEBIT',
            'client_id' => $this->client->id,
            'vendor_id' => $this->vendor->id,
            'rules' => [
                [
                    'search_key' => 'description',
                    'operator' => 'is',
                    'value' => 'wallaby',
                ]
            ]
        ]);

        $bi = BankIntegration::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
        ]);

        $bt = BankTransaction::factory()->create([
            'bank_integration_id' => $bi->id,
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'description' => 'Wall',
            'base_type' => 'DEBIT',
        ]);


        $bt->service()->processRules();

        $bt = $bt->fresh();

        $this->assertEmpty($bt->expense_id);
    }

    public function testMatchingBankTransactionExpense()
    {
        $br = BankTransactionRule::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'matches_on_all' => false,
            'auto_convert' => true,
            'applies_to' => 'DEBIT',
            'client_id' => $this->client->id,
            'vendor_id' => $this->vendor->id,
            'rules' => [
                [
                    'search_key' => 'description',
                    'operator' => 'is',
                    'value' => 'wallaby',
                ]
            ]
        ]);

        $bi = BankIntegration::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
        ]);

        $bt = BankTransaction::factory()->create([
            'bank_integration_id' => $bi->id,
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'description' => 'WallABy',
            'base_type' => 'DEBIT',
        ]);


        $bt->service()->processRules();

        $bt = $bt->fresh();

        $this->assertNotNull($bt->expense_id);
    }


    // public function testMatchingBankTransactionInvoice()
    // {
    //     $this->invoice->number = "MUHMUH";
    //     $this->invoice->save();

    //     $br = BankTransactionRule::factory()->create([
    //         'company_id' => $this->company->id,
    //         'user_id' => $this->user->id,
    //         'matches_on_all' => false,
    //         'auto_convert' => true,
    //         'applies_to' => 'CREDIT',
    //         'client_id' => $this->client->id,
    //         'vendor_id' => $this->vendor->id,
    //         'rules' => [
    //             [
    //                 'search_key' => 'description',
    //                 'operator' => 'is',
    //                 'value' => 'MUHMUH',
    //             ]
    //         ]
    //     ]);

    //     $bi = BankIntegration::factory()->create([
    //         'company_id' => $this->company->id,
    //         'user_id' => $this->user->id,
    //         'account_id' => $this->account->id,
    //     ]);

    //     $bt = BankTransaction::factory()->create([
    //         'bank_integration_id' => $bi->id,
    //         'company_id' => $this->company->id,
    //         'user_id' => $this->user->id,
    //         'description' => 'MUHMUH',
    //         'base_type' => 'CREDIT',
    //         'amount' => 100
    //     ]);


    //     $bt->service()->processRules();

    //     $bt = $bt->fresh();

    //     $this->assertEquals(BankTransaction::STATUS_MATCHED, $bt->status_id);
    // }
}
