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

namespace App\Http\Controllers;

use App\Http\Requests\EInvoice\ShowQuotaRequest;
use App\Http\Requests\EInvoice\ValidateEInvoiceRequest;
use App\Http\Requests\EInvoice\UpdateEInvoiceConfiguration;
use App\Services\EDocument\Standards\Validation\Peppol\EntityLevel;
use Illuminate\Http\JsonResponse;
use InvoiceNinja\EInvoice\Models\Peppol\BranchType\FinancialInstitutionBranch;
use InvoiceNinja\EInvoice\Models\Peppol\FinancialInstitutionType\FinancialInstitution;
use InvoiceNinja\EInvoice\Models\Peppol\FinancialAccountType\PayeeFinancialAccount;
use InvoiceNinja\EInvoice\Models\Peppol\PaymentMeans;
use InvoiceNinja\EInvoice\Models\Peppol\CardAccountType\CardAccount;
use InvoiceNinja\EInvoice\Models\Peppol\IdentifierType\ID;
use InvoiceNinja\EInvoice\Models\Peppol\CodeType\CardTypeCode;
use InvoiceNinja\EInvoice\Models\Peppol\CodeType\PaymentMeansCode;

class EInvoiceController extends BaseController
{
    private array $einvoice_props = [
        'payment_means',
    ];

    /**
     * Checks a given model for validity for sending
     *
     * @param  ValidateEInvoiceRequest $request
     *
     */
    public function validateEntity(ValidateEInvoiceRequest $request)
    {
        $el = new EntityLevel();

        $data = [];

        match($request->entity) {
            'invoices' => $data = $el->checkInvoice($request->getEntity()),
            'clients' => $data = $el->checkClient($request->getEntity()),
            'companies' => $data = $el->checkCompany($request->getEntity()),
            default => $data['passes'] = false,
        };

        return response()->json($data, $data['passes'] ? 200 : 422);

    }

    /**
     * Updated the E-Invoice Setting Configurations
     *
     * @param  UpdateEInvoiceConfiguration $request
     * @return void
     */
    public function configurations(UpdateEInvoiceConfiguration $request)
    {
        $einvoice = new \InvoiceNinja\EInvoice\Models\Peppol\Invoice();

        $payment_means_array = $request->input('payment_means', []);

        nlog($payment_means_array);

        $einvoice->PaymentMeans = [];

        foreach ($payment_means_array as $payment_means) {
            $pm = new PaymentMeans();

            $pmc = new PaymentMeansCode();
            $pmc->value = $payment_means['code'];
            $pm->PaymentMeansCode = $pmc;

            if (in_array($payment_means['code'], ['54,55'])) {
                $ctc = new CardTypeCode();
                $ctc->value = $payment_means['card_type'];
                $card_account = new CardAccount();
                $card_account->HolderName = $payment_means['card_holder'];
                $card_account->CardTypeCode = $ctc;
                $pm->CardAccount = $card_account;
            }

            if (isset($payment_means['iban'])) {
                $fib = new FinancialInstitutionBranch();
                $fi = new FinancialInstitution();
                $bic_id = new ID();
                $bic_id->value = $payment_means['bic_swift'];
                $fi->ID = $bic_id;
                $fib->FinancialInstitution = $fi;
                $pfa = new PayeeFinancialAccount();
                $iban_id = new ID();
                $iban_id->value = $payment_means['iban'];
                $pfa->ID = $iban_id;
                $pfa->Name = $payment_means['account_holder'];
                $pfa->FinancialInstitutionBranch = $fib;

                $pm->PayeeFinancialAccount = $pfa;

            }

            if (isset($payment_means['information'])) {
                $pm->InstructionNote = $payment_means['information'];
            }

            nlog($pm);
            $einvoice->PaymentMeans[] = $pm;
        }

        nlog($einvoice);

        $stub = new \stdClass();
        $stub->Invoice = $einvoice;

        $company = auth()->user()->company();
        $company->e_invoice = $stub;
        $company->save();
    }

    /**
     * Returns the current E-Invoice Quota.
     *
     * @param  ShowQuotaRequest $request
     * @return JsonResponse
     */
    public function quota(ShowQuotaRequest $request): JsonResponse
    {
        /**
        * @var \App\Models\Company
        */
        $company = auth()->user()->company();

        $response = \Illuminate\Support\Facades\Http::baseUrl(config('ninja.hosted_ninja_url'))
            ->withHeaders([
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'X-EInvoice-Token' => $company->account->e_invoicing_token,
            ])
            ->post('/api/einvoice/quota', data: [
                'license_key' => config('ninja.license_key'),
                'account_key' => $company->account->key,
            ]);


        if ($response->status() == 422) {
            return response()->json(['message' => $response->json('message')], 422);
        }

        if ($response->getStatusCode() === 400) {
            return response()->json(['message' => $response->json('message')], 400);
        }

        return response()->json([
            'quota' => $response->body(),
        ]);
    }
}
