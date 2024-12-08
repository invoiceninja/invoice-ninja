<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2024. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Services\Quickbooks;

use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Product;
use App\Factory\ClientFactory;
use App\Factory\InvoiceFactory;
use App\Factory\ProductFactory;
use App\DataMapper\QuickbooksSync;
use App\Factory\ClientContactFactory;
use QuickBooksOnline\API\Core\CoreConstants;
use App\Services\Quickbooks\Models\QbInvoice;
use App\Services\Quickbooks\Models\QbProduct;
use QuickBooksOnline\API\DataService\DataService;
use App\Services\Quickbooks\Jobs\QuickbooksImport;
use App\Services\Quickbooks\Models\QbClient;
use App\Services\Quickbooks\Transformers\ClientTransformer;
use App\Services\Quickbooks\Transformers\InvoiceTransformer;
use App\Services\Quickbooks\Transformers\PaymentTransformer;
use App\Services\Quickbooks\Transformers\ProductTransformer;

class QuickbooksService
{
    public DataService $sdk;

    public QbInvoice $invoice;

    public QbProduct $product;

    public QbClient $client;

    public QuickbooksSync $settings;

    private bool $testMode = true;

    private bool $try_refresh = true;

    public function __construct(public Company $company)
    {
        $this->init();
    }

    private function init(): self
    {

        $config = [
            'ClientID' => config('services.quickbooks.client_id'),
            'ClientSecret' => config('services.quickbooks.client_secret'),
            'auth_mode' => 'oauth2',
            'scope' => "com.intuit.quickbooks.accounting",
            'RedirectURI' => $this->testMode ? 'https://grok.romulus.com.au/quickbooks/authorized' : 'https://invoicing.co/quickbooks/authorized',
            'baseUrl' => $this->testMode ? CoreConstants::SANDBOX_DEVELOPMENT : CoreConstants::QBO_BASEURL,
        ];

        $merged = array_merge($config, $this->ninjaAccessToken());

        $this->sdk = DataService::Configure($merged);

        // $this->sdk->setLogLocation(storage_path("logs/quickbooks.log"));
        $this->sdk->enableLog();

        $this->sdk->setMinorVersion("73");
        $this->sdk->throwExceptionOnError(true);

        $this->checkToken();

        $this->invoice = new QbInvoice($this);

        $this->product = new QbProduct($this);

        $this->client = new QbClient($this);

        $this->settings = $this->company->quickbooks->settings;

        $this->checkDefaultAccounts();

        return $this;
    }

    private function checkDefaultAccounts(): self
    {

        $accountQuery = "SELECT * FROM Account WHERE AccountType IN ('Income', 'Cost of Goods Sold')";

        if (strlen($this->settings->default_income_account) == 0 || strlen($this->settings->default_expense_account) == 0) {

            nlog("Checking default accounts for company {$this->company->company_key}");
            $accounts = $this->sdk->Query($accountQuery);

            nlog($accounts);

            $find_income_account = true;
            $find_expense_account = true;

            foreach ($accounts as $account) {
                if ($account->AccountType->value == 'Income' && $find_income_account) {
                    $this->settings->default_income_account = $account->Id->value;
                    $find_income_account = false;
                } elseif ($account->AccountType->value == 'Cost of Goods Sold' && $find_expense_account) {
                    $this->settings->default_expense_account = $account->Id->value;
                    $find_expense_account = false;
                }
            }

            nlog($this->settings);

            $this->company->quickbooks->settings = $this->settings;
            $this->company->save();
        }


        return $this;
    }

    private function checkToken(): self
    {

        if ($this->company->quickbooks->accessTokenExpiresAt == 0 || $this->company->quickbooks->accessTokenExpiresAt > time()) {
            return $this;
        }

        if ($this->company->quickbooks->accessTokenExpiresAt && $this->company->quickbooks->accessTokenExpiresAt < time() && $this->try_refresh) {
            $this->sdk()->refreshToken($this->company->quickbooks->refresh_token);
            $this->company = $this->company->fresh();
            $this->try_refresh = false;
            $this->init();

            return $this;
        }

        nlog('Quickbooks token expired and could not be refreshed => ' .$this->company->company_key);
        throw new \Exception('Quickbooks token expired and could not be refreshed');

    }

    private function ninjaAccessToken(): array
    {
        return $this->company->quickbooks->accessTokenExpiresAt > 0 ? [
            'accessTokenKey' => $this->company->quickbooks->accessTokenKey,
            'refreshTokenKey' => $this->company->quickbooks->refresh_token,
            'QBORealmID' => $this->company->quickbooks->realmID,
        ] : [];
    }

    public function sdk(): SdkWrapper
    {
        return new SdkWrapper($this->sdk, $this->company);
    }

    /**
     *
     *
     * @return void
     */
    public function syncFromQb(): void
    {
        QuickbooksImport::dispatch($this->company->id, $this->company->db);
    }

    public function findEntityById(string $entity, string $id): mixed
    {
        return $this->sdk->FindById($entity, $id);
    }

    public function query(string $query)
    {
        return $this->sdk->Query($query);
    }

    /**
     * Flag to determine if a sync is allowed in either direction
     *
     * @param  string $entity
     * @param  \App\Enum\SyncDirection $direction
     * @return bool
     */
    public function syncable(string $entity, \App\Enum\SyncDirection $direction): bool
    {
        return $this->settings->{$entity}->direction === $direction || $this->settings->{$entity}->direction === \App\Enum\SyncDirection::BIDIRECTIONAL;
    }

}
