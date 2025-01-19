<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2024. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 *
 * Documentation of Api-Usage: https://developer.gocardless.com/bank-account-data/overview
 *
 * Institutions: Are Banks or Payment-Providers, which manages bankaccounts.
 *
 * Accounts: Accounts are existing bank_accounts at a specific institution.
 *
 * Requisitions: Are registered/active user-flows to authenticate one or many accounts. After completition, the accoundId could be used to fetch data for this account. After the access expires, the user could create a new requisition to connect accounts again.
 */

namespace App\Helpers\Bank\Nordigen;

use App\Models\Company;
use App\Services\Email\Email;
use App\Models\BankIntegration;
use App\Services\Email\EmailObject;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use Illuminate\Mail\Mailables\Address;
use App\Helpers\Bank\Nordigen\Transformer\AccountTransformer;
use App\Helpers\Bank\Nordigen\Transformer\TransactionTransformer;

class Nordigen
{
    public bool $test_mode; // https://developer.gocardless.com/bank-account-data/sandbox

    public string $sandbox_institutionId = "SANDBOXFINANCE_SFIN0000";

    protected \Nordigen\NordigenPHP\API\NordigenClient $client;

    public function __construct()
    {
        $this->test_mode = config('ninja.nordigen.test_mode');

        if (!(config('ninja.nordigen.secret_id') && config('ninja.nordigen.secret_key'))) {
            throw new \Exception('missing nordigen credentials');
        }

        $this->client = new \Nordigen\NordigenPHP\API\NordigenClient(config('ninja.nordigen.secret_id'), config('ninja.nordigen.secret_key'));

        $this->client->createAccessToken();
    }

    // metadata-section for frontend
    public function getInstitutions()
    {
        if ($this->test_mode) {
            return [$this->client->institution->getInstitution($this->sandbox_institutionId)];
        }

        return $this->client->institution->getInstitutions();
    }

    // requisition-section
    public function createRequisition(string $redirect, string $institutionId, string $reference, string $userLanguage)
    {
        if ($this->test_mode && $institutionId != $this->sandbox_institutionId) {
            throw new \Exception('invalid institutionId while in test-mode');
        }

        return $this->client->requisition->createRequisition($redirect, $institutionId, $this->getExtendedEndUserAggreementId($institutionId), $reference, $userLanguage);
    }

    private function getExtendedEndUserAggreementId(string $institutionId): string|null
    {

        $endUserAggreements = null;
        $endUserAgreement = null;

        // try to fetch endUserAgreements
        try {
            $endUserAggreements = $this->client->endUserAgreement->getEndUserAgreements();
        } catch (\Exception $e) { // not able to accept it
            nlog("Nordigen: Was not able to fetch endUserAgreements. We continue with defaults to setup bank_integration. {$institutionId} {$e->getMessage()} {$e->getCode()}");

            return null;
        }

        // try to find an existing valid endUserAgreement
        foreach ($endUserAggreements["results"] as $row) {
            $endUserAgreement = $row;

            // Validate Institution
            if ($endUserAgreement["institution_id"] != $institutionId)
                continue;

            // Validate Access Scopes
            $requiredScopes = ["balances", "details", "transactions"];
            if (isset($endUserAgreement['access_scope']) && array_diff($requiredScopes, $endUserAgreement['access_scope']))
                continue;

            // try to accept the endUserAgreement when not already accepted
            if (empty($endUserAgreement["accepted"]))
                try {
                    $this->client->endUserAgreement->acceptEndUserAgreement($endUserAgreement["id"], request()->userAgent(), request()->ip());
                } catch (\Exception $e) { // not able to accept it
                    nlog("Nordigen: Was not able to confirm an existing outstanding endUserAgreement for this institution. We now try to find another or will create and confirm a new one. {$institutionId} {$endUserAgreement["id"]} {$e->getMessage()} {$e->getCode()}");
                    $endUserAgreement = null;

                    continue;
                }

            break;
        }

        // try to create and accept an endUserAgreement
        if (!$endUserAgreement)
            try {
                $endUserAgreement = $this->client->endUserAgreement->createEndUserAgreement($institutionId, ['details', 'balances', 'transactions'], 90, 180);
                $this->client->endUserAgreement->acceptEndUserAgreement($endUserAgreement["id"], request()->userAgent(), request()->ip());
            } catch (\Exception $e) { // not able to create this for this institution
                nlog("Nordigen: Was not able to create and confirm a new endUserAgreement for this institution. We continue with defaults to setup bank_integration. {$institutionId} {$e->getMessage()} {$e->getCode()}");

                return null;
            }

        return $endUserAgreement["id"];
    }

    public function getRequisition(string $requisitionId)
    {
        try {
            return $this->client->requisition->getRequisition($requisitionId);
        } catch (\Exception $e) {
            if (strpos($e->getMessage(), "Invalid Requisition ID") !== false) {
                return false;
            }

            throw $e;
        }
    }

    // TODO: return null on not found
    public function getAccount(string $account_id)
    {
        try {
            $out = new \stdClass();

            $out->data = $this->client->account($account_id)->getAccountDetails()["account"];
            $out->metadata = $this->client->account($account_id)->getAccountMetaData();
            $out->balances = $this->client->account($account_id)->getAccountBalances()["balances"];
            $out->institution = $this->client->institution->getInstitution($out->metadata["institution_id"]);

            $it = new AccountTransformer();
            return $it->transform($out);

        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $response = $e->getResponse();
            $statusCode = $response->getStatusCode();

            if ($statusCode === 429) {
                nlog("Nordigen Rate Limit hit for account {$account_id}");
                return ['error' => 'Nordigen Institution Rate Limit Reached'];
            }
        } catch (\Exception $e) {

            nlog("Nordigen getAccount() failed => {$account_id} => " . $e->getMessage());
            return ['error' => $e->getMessage(), 'requisition' => true];

        }
    }

    /**
     * isAccountActive
     *
     * @param  string $account_id
     * @return bool
     */
    public function isAccountActive(string $account_id): bool
    {
        try {
            $account = $this->client->account($account_id)->getAccountMetaData();

            if ($account["status"] != "READY") {
                nlog('nordigen account was not in status ready. accountId: ' . $account_id . ' status: ' . $account["status"]);
                return false;
            }

            return true;
        } catch (\Exception $e) {

            nlog("Nordigen:: AccountActiveStatus:: {$e->getMessage()} {$e->getCode()}");

            if (strpos($e->getMessage(), "Invalid Account ID") !== false) {
                return false;
            }

            throw $e;
        }
    }


    /**
     * getTransactions
     *
     * @param  string $accountId
     * @param  string $dateFrom
     * @return array
     */
    public function getTransactions(Company $company, string $accountId, string $dateFrom = null): array
    {
        $transactionResponse = $this->client->account($accountId)->getAccountTransactions($dateFrom);

        $it = new TransactionTransformer($company);
        return $it->transform($transactionResponse);
    }

    public function disabledAccountEmail(BankIntegration $bank_integration): void
    {
        $cache_key = "email_quota:{$bank_integration->company->company_key}:{$bank_integration->id}";

        if (Cache::has($cache_key)) {
            return;
        }

        App::setLocale($bank_integration->company->getLocale());

        $mo = new EmailObject();
        $mo->subject = ctrans('texts.nordigen_requisition_subject');
        $mo->body = ctrans('texts.nordigen_requisition_body');
        $mo->text_body = ctrans('texts.nordigen_requisition_body');
        $mo->company_key = $bank_integration->company->company_key;
        $mo->html_template = 'email.template.generic';
        $mo->to = [new Address($bank_integration->company->owner()->email, $bank_integration->company->owner()->present()->name())];
        $mo->email_template_body = 'nordigen_requisition_body';
        $mo->email_template_subject = 'nordigen_requisition_subject';

        Email::dispatch($mo, $bank_integration->company);

        Cache::put($cache_key, true, 60 * 60 * 24);

    }

}