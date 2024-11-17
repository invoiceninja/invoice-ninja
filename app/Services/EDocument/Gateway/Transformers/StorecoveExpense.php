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

namespace App\Services\EDocument\Gateway\Transformers;

use App\Utils\Ninja;
use App\Models\Vendor;
use App\Models\Company;
use App\Models\Activity;
use App\Factory\VendorFactory;
use App\Factory\ExpenseFactory;
use App\Repositories\VendorRepository;
use App\Repositories\ExpenseRepository;
use App\Repositories\ActivityRepository;
use Symfony\Component\Serializer\Serializer;
use App\Repositories\VendorContactRepository;
use Symfony\Component\Serializer\Encoder\XmlEncoder;
use InvoiceNinja\EInvoice\Models\Peppol\PaymentMeans;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use App\Services\EDocument\Gateway\Storecove\Storecove;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;
use App\Services\EDocument\Gateway\Storecove\Models\Invoice;
use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Mapping\Loader\AttributeLoader;
use InvoiceNinja\EInvoice\Models\Peppol\Invoice as PeppolInvoice;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory;
use App\Services\EDocument\Gateway\Storecove\PeppolToStorecoveNormalizer;
use Symfony\Component\Serializer\NameConverter\MetadataAwareNameConverter;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;

class StorecoveExpense 
{
    
    public function __construct(private Storecove $storecove){}

    public function getStorecoveInvoice($storecove_json)
    {
                
        $classMetadataFactory = new ClassMetadataFactory(new AttributeLoader());

        // Create a proper PropertyInfoExtractor
        $phpDocExtractor = new PhpDocExtractor();
        $reflectionExtractor = new ReflectionExtractor();

        $propertyInfo = new PropertyInfoExtractor(
            // List of extractors for type info
            [$reflectionExtractor, $phpDocExtractor],
            // List of extractors for descriptions
            [$phpDocExtractor],
            // List of extractors for access info
            // [$reflectionExtractor],
            // // List of extractors for mutation info
            // [$reflectionExtractor],
            // // List of extractors for initialization info
            // [$reflectionExtractor]
        );

        $normalizers = [
            new DateTimeNormalizer(),
            new ArrayDenormalizer(),
            new ObjectNormalizer(
                $classMetadataFactory,
                null,
                null,
                $propertyInfo
            )
        ];

        $context = [
            DateTimeNormalizer::FORMAT_KEY => 'Y-m-d',
            AbstractObjectNormalizer::SKIP_NULL_VALUES => true,
            AbstractObjectNormalizer::DISABLE_TYPE_ENFORCEMENT => false,
            AbstractObjectNormalizer::DEEP_OBJECT_TO_POPULATE => true,  // Add this
        ];

        $encoders = [new JsonEncoder()];


        $serializer = new Serializer($normalizers, $encoders);

        $context = [
            DateTimeNormalizer::FORMAT_KEY => 'Y-m-d',
            AbstractObjectNormalizer::SKIP_NULL_VALUES => true,
            AbstractObjectNormalizer::DISABLE_TYPE_ENFORCEMENT => false,  // Enforce types
        ];

        $storecove_invoice = $serializer->deserialize(
            $storecove_json,
            Invoice::class,
            'json',
            $context
        );

        return $storecove_invoice;
    }

    public function createExpense(Invoice $storecove_invoice, Company $company)
    {

        $expense_array = $this->transform($storecove_invoice);

        $vendor = Vendor::query()
                ->where('company_id', $company->id)
                ->where(function($query) use ($expense_array) {
                // Check VAT number if present
                if (strlen($expense_array['vendor']['vat_number']) > 2) {
                    $query->orWhere('vat_number', $expense_array['vendor']['vat_number']);
                }
                
                // Check ID number if present
                if (strlen($expense_array['vendor']['id_number']) > 2) {
                    $query->orWhere('id_number', $expense_array['vendor']['id_number']);
                }
                
                // If no valid identifiers, force no results
                if (strlen($expense_array['vendor']['vat_number']) <= 2 && strlen($expense_array['vendor']['id_number']) <= 2) {
                    $query->where('id', 0); // Forces no match
                }

            })->first();
        
        if(!$vendor)
        {
            $vendor_repo = new VendorRepository(new VendorContactRepository());
            $vendor = VendorFactory::create($company->id, $company->owner()->id);
            $vendor = $vendor_repo->save($expense_array['vendor'], $vendor);
        }


        $expense_repo = new ExpenseRepository();

        $expense = ExpenseFactory::create($vendor->company_id, $vendor->user_id);
        $expense->vendor_id = $vendor->id;
        
        unset($expense_array['vendor']);
       
        $expense = $expense_repo->save($expense_array, $expense);

        $fields = new \stdClass();

        $fields->expense_id = $expense->id;
        $fields->user_id = $expense->user_id;
        $fields->company_id = $expense->company_id;
        $fields->account_id = $expense->company->account_id;
        $fields->vendor_id = $expense->vendor_id;
        $fields->activity_type_id = Activity::E_EXPENSE_CREATED;

        $activity_repo = new ActivityRepository();
        $activity_repo->save($fields, $expense, Ninja::eventVars());

        return $expense;

    }

    public function transform(Invoice $storecove_invoice): array
    {

        $tax_totals = [];

        foreach ($storecove_invoice->getTaxSubtotals() as $tdf) {

            $tax_totals[] = (array)$tdf;
        }

        $totals = collect($tax_totals);

        $party = $storecove_invoice->getAccountingSupplierParty()->getParty();
        $pis = $storecove_invoice->getAccountingSupplierParty()->getPublicIdentifiers();

        $vat_number = '';
        $id_number = '';

        foreach ($pis as $pi) {
            if ($ident = $this->storecove->router->resolveIdentifierTypeByValue($pi->getScheme())) {
                if ($ident == 'vat_number') {
                    $vat_number = $pi->getId();
                } elseif ($ident == 'id_number') {
                    $id_number = $pi->getId();
                }
            }
        }

        $item_descriptions = collect();
        foreach ($storecove_invoice->getInvoiceLines() as $item) {
            $item_descriptions->push($item->getDescription());
        }

        $tax_map = collect($tax_totals)
            ->groupBy('type')
            ->map(function ($group) {
                return [
                    'type' => $group->first()['type'],
                    'category' => $group->first()['category'],
                    'percentage' => $group->first()['percentage'],
                    'country' => $group->first()['country'],
                    'total_tax_amount' => $group->sum('tax_amount')
                ];
            })->toArray();

        $tax_name1 = '';
        $tax_rate1 = 0;
        $tax_name2 = '';
        $tax_rate2 = 0;
        $tax_name3 = '';
        $tax_rate3 = 0;
        $tax_amount1 = 0;
        $tax_amount2 = 0;
        $tax_amount3 = 0;

        $key = 0;
        foreach ($tax_map as $tax) {

            switch ($key) {
                case 0:
                    $tax_name1 = $tax['type'];
                    $tax_rate1 = $tax['percentage'];
                    $tax_amount1 = $tax['total_tax_amount'];
                    break;
                case 1:
                    $tax_name2 = $tax['type'];
                    $tax_rate2 = $tax['percentage'];
                    $tax_amount2 = $tax['total_tax_amount'];
                    break;
                case 2:
                    $tax_name3 = $tax['type'];
                    $tax_rate3 = $tax['percentage'];
                    $tax_amount3 = $tax['total_tax_amount'];
                    break;
            }
            $key++;
        }

        /** @var \Illuminate\Support\Collection<\App\Models\Currency> */
        $currencies = app('currencies');

        $currency = $currencies->first(function ($c) use ($storecove_invoice) {
            return $storecove_invoice->getDocumentCurrencyCode() == $c->code;
        })->id ?? 1;

        //vendor
        $vendor = [
            'name' => $party->getCompanyName() ?? $party->getRegistrationName(),
            'phone' => $party->getContact()->getPhone() ?? '',
            'currency_id' => $currency,
            'id_number' => $id_number,
            'vat_number' => $vat_number,
            'address1' => $party->getAddress()->getStreet1() ?? '',
            'address2' => $party->getAddress()->getStreet2() ?? '',
            'city' => $party->getAddress()->getCity() ?? '',
            'state' => $party->getAddress()->getCounty() ?? '',
            'postal_code' => $party->getAddress()->getZip() ?? '',
            'contacts' => [
                [
                    'first_name' => $party->getContact()->getFirstName() ?? '',
                    'last_name' => $party->getContact()->getLastName() ?? '',
                    'email' => $party->getContact()->getEmail() ?? '',
                    'phone' => $party->getContact()->getPhone() ?? '',
                ]
            ],
        ];

        //expense
        $expense = [
            'amount' => $storecove_invoice->getAmountIncludingTax(),
            'currency_id' => $currency,
            'date' => $storecove_invoice->getIssueDate(),
            'public_notes' => $storecove_invoice->getNote() ?? '',
            'private_notes' => $storecove_invoice->getInvoiceNumber(),
            'transaction_reference' => '',
            'uses_inclusive_taxes' => true,
            'tax_name1' => $tax_name1,
            'tax_rate1' => $tax_rate1,
            'tax_name2' => $tax_name2,
            'tax_rate2' => $tax_rate2,
            'tax_name3' => $tax_name3,
            'tax_rate3' => $tax_rate3,
            'tax_amount1' => $tax_amount1,
            'tax_amount2' => $tax_amount2,
            'tax_amount3' => $tax_amount3,
            'calculate_tax_by_amount' => true,
            'vendor' => $vendor,
        ];

        nlog($expense);

        return $expense;

    }
}