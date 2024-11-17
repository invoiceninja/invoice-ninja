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

use Tests\TestCase;
use App\Models\User;
use App\Models\Quote;
use App\Models\Client;
use App\Models\Account;
use App\Models\Company;
use App\Models\Country;
use App\Models\Invoice;
use App\Models\ClientContact;
use App\Utils\Traits\MakesHash;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Session;
use App\Utils\Traits\GeneratesConvertedQuoteCounter;
use Illuminate\Foundation\Testing\DatabaseTransactions;

/**
 * 
 *   App\Utils\Traits\GeneratesConvertedQuoteCounter
 */
class GeneratesConvertedQuoteCounterTest extends TestCase
{
    use GeneratesConvertedQuoteCounter;
    use DatabaseTransactions;
    use MakesHash;

    protected $account;
    protected $faker;
    protected $client;
    protected $company;

    protected function setUp(): void
    {
        parent::setUp();

        Session::start();
        $this->faker = \Faker\Factory::create();
        Model::reguard();

        if (\App\Models\Country::count() == 0) {
            \Illuminate\Support\Facades\Artisan::call('db:seed', ['--force' => true]);
        }

    }

    public function testCounterExtraction()
    {
        $this->account = Account::factory()->create([
            'hosted_client_count' => 1000,
            'hosted_company_count' => 1000,
        ]);

        $this->account->num_users = 3;
        $this->account->save();

        $fake_email = $this->faker->email();

        $user = User::whereEmail($fake_email)->first();

        if (! $user) {
            $user = User::factory()->create([
                'account_id' => $this->account->id,
                'confirmation_code' => $this->createDbHash(config('database.default')),
                'email' => $fake_email,
            ]);
        }

        $user_id = $user->id;

        $this->company = Company::factory()->create([
            'account_id' => $this->account->id,
        ]);

        $this->client = Client::factory()->create([
            'user_id' => $user_id,
            'company_id' => $this->company->id,
        ]);

        $contact = ClientContact::factory()->create([
            'user_id' => $user_id,
            'client_id' => $this->client->id,
            'company_id' => $this->company->id,
            'is_primary' => 1,
            'send_email' => true,
        ]);

        $settings = $this->client->getMergedSettings();
        $settings->invoice_number_counter = 1;
        $settings->invoice_number_pattern = '{$year}-I{$counter}';
        $settings->quote_number_pattern = '{$year}-Q{$counter}';
        $settings->shared_invoice_quote_counter = 1;
        $settings->timezone_id = '31';
        $this->company->settings = $settings;

        $this->company->save();

        $this->client->settings = $settings;
        $this->client->save();

        $quote = Quote::factory()->create([
            'user_id' => $this->client->user_id,
            'company_id' => $this->client->company_id,
            'client_id' => $this->client->id,
        ]);

        $quote = $quote->service()->markSent()->convert()->save();

        $invoice = Invoice::find($quote->invoice_id);

        $this->assertNotNull($invoice);

        $this->assertEquals(now()->format('Y'). '-Q0001', $quote->number);
        $this->assertEquals(now()->format('Y'). '-I0001', $invoice->number);

        $settings = $this->client->getMergedSettings();
        $settings->invoice_number_counter = 100;
        $settings->invoice_number_pattern = 'I{$counter}';
        $settings->quote_number_pattern = 'Q{$counter}';
        $settings->shared_invoice_quote_counter = 1;
        $settings->timezone_id = '31';

        $this->company->settings = $settings;

        $this->company->save();

        $this->client->settings = $settings;
        $this->client->save();

        $quote = Quote::factory()->create([
            'user_id' => $this->client->user_id,
            'company_id' => $this->client->company_id,
            'client_id' => $this->client->id,
        ]);

        $quote = $quote->service()->markSent()->convert()->save();

        $invoice = Invoice::find($quote->invoice_id);

        $this->assertNotNull($invoice);

        $this->assertEquals('Q0100', $quote->number);
        $this->assertEquals('I0100', $invoice->number);
    }
}
