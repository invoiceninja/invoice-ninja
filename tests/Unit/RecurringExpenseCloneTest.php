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

use App\Factory\RecurringExpenseFactory;
use App\Factory\RecurringExpenseToExpenseFactory;
use App\Models\Account;
use App\Models\Client;
use App\Models\Company;
use App\Models\User;
use App\Utils\Traits\AppSetup;
use Tests\TestCase;

/**
 * 
 */
class RecurringExpenseCloneTest extends TestCase
{
    use AppSetup;

    public $faker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->faker = \Faker\Factory::create();
        
        if (\App\Models\Country::count() == 0) {
            \Illuminate\Support\Facades\Artisan::call('db:seed', ['--force' => true]);
        }

    }

    public function testBadBase64String()
    {
        $account = Account::factory()->create();
        $user = User::factory()->create(['account_id' => $account->id, 'email' => $this->faker->unique()->safeEmail()]);
        $company = Company::factory()->create(['account_id' => $account->id]);

        $client = Client::factory()->create([
            'user_id' => $user->id,
            'company_id' => $company->id,
        ]);

        $recurring_expense = RecurringExpenseFactory::create($company->id, $user->id);
        $recurring_expense->date = now();
        $recurring_expense->amount = 10;
        $recurring_expense->foreign_amount = 20;
        $recurring_expense->exchange_rate = 0.5;
        $recurring_expense->private_notes = 'private';
        $recurring_expense->public_notes = 'public';
        $recurring_expense->custom_value4 = 'custom4';
        $recurring_expense->should_be_invoiced = true;

        $recurring_expense->save();

        $expense = RecurringExpenseToExpenseFactory::create($recurring_expense);
        $expense->save();

        $this->assertNotNull($expense);
        $this->assertEquals(20, $expense->foreign_amount);
        $this->assertEquals(10, $expense->amount);
    }
}
