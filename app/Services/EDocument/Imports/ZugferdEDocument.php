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

namespace App\Services\EDocument\Imports;

use App\Factory\ExpenseFactory;
use App\Factory\VendorFactory;
use App\Jobs\Util\UploadFile;
use App\Models\Country;
use App\Models\Currency;
use App\Models\Expense;
use App\Models\Vendor;
use App\Models\VendorContact;
use App\Services\AbstractService;
use App\Utils\TempFile;
use App\Utils\Traits\SavesDocuments;
use Exception;
use App\Models\Company;
use App\Repositories\ExpenseRepository;
use App\Repositories\VendorContactRepository;
use App\Repositories\VendorRepository;
use horstoeko\zugferd\ZugferdDocumentReader;
use horstoeko\zugferdvisualizer\ZugferdVisualizer;
use horstoeko\zugferdvisualizer\renderer\ZugferdVisualizerLaravelRenderer;
use Illuminate\Http\UploadedFile;

class ZugferdEDocument extends AbstractService
{
    use SavesDocuments;
    public ZugferdDocumentReader|string $document;

    /**
     * @throws Exception
     */
    public function __construct(public UploadedFile $file, public Company $company)
    {
        # curl -X POST http://localhost:8000/api/v1/edocument/upload -H "Content-Type: multipart/form-data" -H "X-API-TOKEN: 7tdDdkz987H3AYIWhNGXy8jTjJIoDhkAclCDLE26cTCj1KYX7EBHC66VEitJwWhn" -H "X-Requested-With: XMLHttpRequest" -F _method=PUT -F documents[]=@einvoice.xml
    }

    /**
     * @throws Exception
     */
    public function run(): Expense
    {
        /** @var \App\Models\User $user */
        $user = $this->company->owner();

        $this->document = ZugferdDocumentReader::readAndGuessFromContent($this->file->get());
        $this->document->getDocumentInformation($documentno, $documenttypecode, $documentdate, $invoiceCurrency, $taxCurrency, $documentname, $documentlanguage, $effectiveSpecifiedPeriod);
        $this->document->getDocumentSummation($grandTotalAmount, $duePayableAmount, $lineTotalAmount, $chargeTotalAmount, $allowanceTotalAmount, $taxBasisTotalAmount, $taxTotalAmount, $roundingAmount, $totalPrepaidAmount);

        /** @var \App\Models\Expense $expense */
        $expense = Expense::where("company_id", $this->company->id)->where('amount', $grandTotalAmount)->where("transaction_reference", $documentno)->whereDate("date", $documentdate)->first();
        if (!$expense) {
            // The document does not exist as an expense
            // Handle accordingly
            $visualizer = new ZugferdVisualizer($this->document);
            $visualizer->setDefaultTemplate();
            $visualizer->setRenderer(app(ZugferdVisualizerLaravelRenderer::class));
            $visualizer->setPdfFontDefault("arial");
            $visualizer->setPdfPaperSize('A4-P');
            $visualizer->setTemplate('edocument.xinvoice');

            $expense = ExpenseFactory::create($this->company->id, $user->id);
            $expense->date = $documentdate;
            $expense->public_notes = $documentno;
            $expense->currency_id = Currency::whereCode($invoiceCurrency)->first()->id ?? $this->company->settings->currency_id;
            $expense->save();

            $documents = [$this->file];
            if ($this->file->getExtension() == "xml")
                array_push($documents, TempFile::UploadedFileFromRaw($visualizer->renderPdf(), $documentno . "_visualiser.pdf", "application/pdf"));
            $this->saveDocuments($documents, $expense);

            $expense->save();

            if ($taxCurrency && $taxCurrency != $invoiceCurrency) {
                $expense->private_notes = ctrans("texts.tax_currency_mismatch");
            }
            $expense->uses_inclusive_taxes = true;
            $expense->amount = $grandTotalAmount;
            $counter = 1;
            if ($this->document->firstDocumentTax()) {
                do {
                    $this->document->getDocumentTax($categoryCode, $typeCode, $basisAmount, $calculatedAmount, $rateApplicablePercent, $exemptionReason, $exemptionReasonCode, $lineTotalBasisAmount, $allowanceChargeBasisAmount, $taxPointDate, $dueDateTypeCode);
                    $expense->{"tax_amount$counter"} = $calculatedAmount;
                    $expense->{"tax_rate$counter"} = $rateApplicablePercent;
                    $expense->{"tax_name$counter"} = $typeCode;
                    $counter++;
                } while ($this->document->nextDocumentTax());
            }
            $this->document->getDocumentSeller($name, $buyer_id, $buyer_description);
            $this->document->getDocumentSellerContact($person_name, $person_department, $contact_phone, $contact_fax, $contact_email);
            $this->document->getDocumentSellerAddress($address_1, $address_2, $address_3, $postcode, $city, $country, $subdivision);
            $this->document->getDocumentSellerTaxRegistration($taxtype);

            $taxid = null;
            if (array_key_exists("VA", $taxtype)) {
                $taxid = $taxtype["VA"];
            }

            $vendor = Vendor::query()
                            ->where("company_id", $this->company->id)
                            ->where(function ($q) use($taxid, $person_name, $contact_email){
                                $q->when(!is_null($taxid), function ($when_query) use($taxid){
                                    $when_query->orWhere('vat_number', $taxid); 
                                }) 
                                ->orWhere("name", $person_name)
                                ->orWhereHas('contacts', function ($qq) use ($contact_email){
                                $qq->where("email", $contact_email);
                                });
                            })->first();
                            
            if ($vendor) {
                $expense->vendor_id = $vendor->id;
            } else {
                $vendor = VendorFactory::create($this->company->id, $user->id);
                $vendor->name = $name;
                if ($taxid != null) {
                    $vendor->vat_number = $taxid;
                }
                $vendor->currency_id = Currency::query()->where('code', $invoiceCurrency)->first()->id;
                $vendor->phone = $contact_phone;
                $vendor->address1 = $address_1;
                $vendor->address2 = $address_2;
                $vendor->city = $city;
                $vendor->postal_code = $postcode;

                $country = app('countries')->first(function ($c) use ($country) {
                    /** @var \App\Models\Country $c */
                    return $c->iso_3166_2 == $country || $c->iso_3166_3 == $country;
                });
                if ($country)
                    $vendor->country_id = $country->id;

                $vendor_repo = new VendorRepository(new VendorContactRepository());
                $vendor = $vendor_repo->save([], $vendor);

                $expense->vendor_id = $vendor->id;
            }
            $expense->transaction_reference = $documentno;
        } else {
            // The document exists as an expense
            // Handle accordingly
            nlog("Zugferd: Document already exists {$expense->hashed_id}");
            $expense->private_notes = $expense->private_notes . ctrans("texts.edocument_import_already_exists", ["date" => time()]);
        }

        $expense_repo = new ExpenseRepository();
        $expense = $expense_repo->save([],$expense);
        
        return $expense;
    }
}
