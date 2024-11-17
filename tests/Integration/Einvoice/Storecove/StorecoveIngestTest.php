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

namespace Tests\Integration\Einvoice\Storecove;

use Tests\TestCase;
use App\Models\Client;
use App\Models\Company;
use App\Models\Country;
use App\Models\Expense;
use App\Models\Invoice;
use App\Utils\TempFile;
use Tests\MockAccountData;
use Illuminate\Support\Str;
use App\Models\ClientContact;
use App\DataMapper\InvoiceItem;
use App\DataMapper\Tax\TaxModel;
use App\DataMapper\ClientSettings;
use App\DataMapper\CompanySettings;
use App\Utils\Traits\SavesDocuments;
use App\Services\EDocument\Standards\Peppol;
use Symfony\Component\Serializer\Serializer;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Symfony\Component\Serializer\Encoder\XmlEncoder;
use InvoiceNinja\EInvoice\Models\Peppol\PaymentMeans;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use App\Services\EDocument\Gateway\Storecove\Storecove;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;
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
use App\Services\EDocument\Gateway\Storecove\Models\Invoice as StorecoveInvoice;
use App\Services\EDocument\Standards\Validation\XsltDocumentValidator;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;

class StorecoveIngestTest extends TestCase
{
    use MockAccountData;
    use DatabaseTransactions;
    use SavesDocuments;

    private int $routing_id = 0;

    private string $test_invoice = '{"legal_entity_id":290868,"direction":"in","guid":"3f0981f1-5105-4970-81f2-6b7482ad27d7","document":{"document_type":"invoice","source":"peppol","invoice":{"accounting_cost":"23089","accounting_currency_taxable_amount":null,"accounting_currency_tax_amount":null,"accounting_currency_tax_amount_currency":null,"accounting_currency_exchange_rate":null,"accounting_supplier_party":{"party":{"company_name":"Test 0106 identifier Storecove","registration_name":"Test 0106 identifier Storecove","address":{"street1":"Address 34","street2":null,"city":"Holst","zip":"2324 DF","county":null,"country":"NL"},"contact":{"email":"sender@company.com","first_name":"Jony","last_name":"Ponski","phone":"088-333333333"}},"public_identifiers":[{"scheme":"NL:KVK","id":"012345677"},{"scheme":"NL:VAT","id":"NL000000000B45"}]},"allowance_charges":[{"amount_excluding_tax":11.2,"base_amount_excluding_tax":null,"reason":"late payment","taxes_duties_fees":[{"category":"standard","country":"NL","percentage":21.0,"amount":null,"type":"VAT"}]},{"amount_excluding_tax":-1.0,"base_amount_excluding_tax":null,"reason":"bonus","taxes_duties_fees":[{"category":"standard","country":"NL","percentage":21.0,"amount":null,"type":"VAT"}]}],"amount_including_tax":27.27,"attachments":[],"delivery":{"actual_delivery_date":"2024-10-29","quantity":null,"delivery_location":{"id":"871690930000478611","scheme_id":"0088","location_name":null,"address":{"street1":"line1","street2":"line2","city":"CITY","zip":"3423423","county":"CA","country":"US"}},"delivery_party":null,"shipment":{"allowance_charges":[],"origin_address":{"country":null},"shipping_marks":null}},"delivery_terms":{"delivery_location_id":null,"incoterms":null,"special_terms":null},"document_currency_code":"USD","due_date":"2024-11-29","invoice_lines":[{"accounting_cost":"23089","additional_item_properties":[{"name":"UtilityConsumptionPoint","value":"871690930000222221"},{"name":"UtilityConsumptionPointAddress","value":"VE HAZERSWOUDE-XXXXX"}],"allowance_charges":[{"amount_excluding_tax":-0.25,"base_amount_excluding_tax":0.0,"reason":"special discount"},{"amount_excluding_tax":-0.75,"base_amount_excluding_tax":0.0,"reason":"even more special discount"}],"amount_excluding_tax":2.67,"amount_including_tax":null,"base_quantity":2.5,"description":"Supply","invoice_period":"2024-09-30 - 2024-10-30","item_price":0.1433773,"line_id":"1","name":"Supply peak","note":"Only half the story...","quantity":63.992,"quantity_unit_code":"KWH","references":[{"document_description":null,"document_id":"BBBBBBBB","document_id_scheme_id":null,"document_id_scheme_agency_id":null,"document_id_scheme_version_id":null,"document_id_list_id":null,"document_id_list_agency_id":null,"document_id_list_version_id":null,"document_type":"item_commodity_code","document_type_code":null,"document_uuid":null,"line_id":null,"issue_date":null},{"document_description":null,"document_id":"CCCCCCCC","document_id_scheme_id":null,"document_id_scheme_agency_id":null,"document_id_scheme_version_id":null,"document_id_list_id":"ZZZ","document_id_list_agency_id":null,"document_id_list_version_id":null,"document_type":"item_classification_code","document_type_code":null,"document_uuid":null,"line_id":null,"issue_date":null},{"document_description":null,"document_id":"buyer reference or purchase order reference is recommended","document_id_scheme_id":null,"document_id_scheme_agency_id":null,"document_id_scheme_version_id":null,"document_id_list_id":null,"document_id_list_agency_id":null,"document_id_list_version_id":null,"document_type":"line_purchase_order","document_type_code":null,"document_uuid":null,"line_id":"1","issue_date":null},{"document_description":null,"document_id":"8718868597083","document_id_scheme_id":"0088","document_id_scheme_agency_id":"9","document_id_scheme_version_id":null,"document_id_list_id":null,"document_id_list_agency_id":null,"document_id_list_version_id":null,"document_type":"line_standard_item_identification","document_type_code":null,"document_uuid":null,"line_id":null,"issue_date":null},{"document_description":null,"document_id":"E_DVK_PKlik_KVP_LP","document_id_scheme_id":null,"document_id_scheme_agency_id":null,"document_id_scheme_version_id":null,"document_id_list_id":null,"document_id_list_agency_id":null,"document_id_list_version_id":null,"document_type":"line_sellers_item_identification","document_type_code":null,"document_uuid":null,"line_id":null,"issue_date":null},{"document_description":null,"document_id":"9 008 115","document_id_scheme_id":null,"document_id_scheme_agency_id":null,"document_id_scheme_version_id":null,"document_id_list_id":null,"document_id_list_agency_id":null,"document_id_list_version_id":null,"document_type":"line_buyers_item_identification","document_type_code":null,"document_uuid":null,"line_id":null,"issue_date":null}],"taxes_duties_fees":[{"category":"standard","country":"NL","percentage":21.0,"amount":null,"type":"VAT"}]},{"accounting_cost":"23089","additional_item_properties":[{"name":"UtilityConsumptionPoint","value":"871690930000222221"},{"name":"UtilityConsumptionPointAddress","value":"VE HAZERSWOUDE-XXXXX"}],"allowance_charges":[{"amount_excluding_tax":-0.25,"base_amount_excluding_tax":0.0,"reason":"special discount"},{"amount_excluding_tax":-0.75,"base_amount_excluding_tax":0.0,"reason":"even more special discount"}],"amount_excluding_tax":9.67,"amount_including_tax":null,"base_quantity":2.78951212,"description":"Supply","invoice_period":"2024-09-30 - 2024-10-30","item_price":2.30944245,"line_id":"2","name":"Supply peak","note":"Only half the story...","quantity":12.888,"quantity_unit_code":"K6","references":[{"document_description":null,"document_id":"BBBBBBBB","document_id_scheme_id":null,"document_id_scheme_agency_id":null,"document_id_scheme_version_id":null,"document_id_list_id":null,"document_id_list_agency_id":null,"document_id_list_version_id":null,"document_type":"item_commodity_code","document_type_code":null,"document_uuid":null,"line_id":null,"issue_date":null},{"document_description":null,"document_id":"CCCCCCCC","document_id_scheme_id":null,"document_id_scheme_agency_id":null,"document_id_scheme_version_id":null,"document_id_list_id":"ZZZ","document_id_list_agency_id":null,"document_id_list_version_id":null,"document_type":"item_classification_code","document_type_code":null,"document_uuid":null,"line_id":null,"issue_date":null},{"document_description":null,"document_id":"buyer reference or purchase order reference is recommended","document_id_scheme_id":null,"document_id_scheme_agency_id":null,"document_id_scheme_version_id":null,"document_id_list_id":null,"document_id_list_agency_id":null,"document_id_list_version_id":null,"document_type":"line_purchase_order","document_type_code":null,"document_uuid":null,"line_id":"1","issue_date":null},{"document_description":null,"document_id":"8718868597083","document_id_scheme_id":"0088","document_id_scheme_agency_id":"9","document_id_scheme_version_id":null,"document_id_list_id":null,"document_id_list_agency_id":null,"document_id_list_version_id":null,"document_type":"line_standard_item_identification","document_type_code":null,"document_uuid":null,"line_id":null,"issue_date":null},{"document_description":null,"document_id":"E_DVK_PKlik_KVP_LP","document_id_scheme_id":null,"document_id_scheme_agency_id":null,"document_id_scheme_version_id":null,"document_id_list_id":null,"document_id_list_agency_id":null,"document_id_list_version_id":null,"document_type":"line_sellers_item_identification","document_type_code":null,"document_uuid":null,"line_id":null,"issue_date":null},{"document_description":null,"document_id":"9 008 115","document_id_scheme_id":null,"document_id_scheme_agency_id":null,"document_id_scheme_version_id":null,"document_id_list_id":null,"document_id_list_agency_id":null,"document_id_list_version_id":null,"document_type":"line_buyers_item_identification","document_type_code":null,"document_uuid":null,"line_id":null,"issue_date":null}],"taxes_duties_fees":[{"category":"standard","country":"NL","percentage":21.0,"amount":null,"type":"VAT"}]}],"invoice_number":"2024-10-30T23:20:29-2e8c0274","invoice_period":"2024-09-30 - 2024-10-30","issue_date":"2024-10-30","issue_reasons":[],"issue_time":null,"note":"This is the invoice note. Senders can enter free text. This may not be read by the receiver, so it is discouraged to use this for electronic invoicing.","payable_rounding_amount":0.02,"payment_means_array":[{"account":"NL50RABO0162432445","amount":null,"branche_code":null,"code":"credit_transfer","holder":null,"network":null,"payment_id":"44556677"}],"payment_terms":{"note":"For payment terms, only a note is supported by Peppol currently."},"prepaid_amount":1.0,"references":[{"document_description":null,"document_id":"buyer reference or purchase order reference is recommended","document_id_scheme_id":null,"document_id_scheme_agency_id":null,"document_id_scheme_version_id":null,"document_id_list_id":null,"document_id_list_agency_id":null,"document_id_list_version_id":null,"document_type":"purchase_order","document_type_code":null,"document_uuid":null,"line_id":null,"issue_date":null},{"document_description":null,"document_id":"buyer reference or purchase order reference is recommended","document_id_scheme_id":null,"document_id_scheme_agency_id":null,"document_id_scheme_version_id":null,"document_id_list_id":null,"document_id_list_agency_id":null,"document_id_list_version_id":null,"document_type":"buyer_reference","document_type_code":null,"document_uuid":null,"line_id":null,"issue_date":null},{"document_description":null,"document_id":"PreviousInvoiceNumber123456","document_id_scheme_id":null,"document_id_scheme_agency_id":null,"document_id_scheme_version_id":null,"document_id_list_id":null,"document_id_list_agency_id":null,"document_id_list_version_id":null,"document_type":"billing","document_type_code":null,"document_uuid":null,"line_id":null,"issue_date":null},{"document_description":null,"document_id":"contract123","document_id_scheme_id":null,"document_id_scheme_agency_id":null,"document_id_scheme_version_id":null,"document_id_list_id":null,"document_id_list_agency_id":null,"document_id_list_version_id":null,"document_type":"contract","document_type_code":null,"document_uuid":null,"line_id":null,"issue_date":null}],"self_billing_mode":false,"sub_type":"invoice","system_generated_primary_image":false,"tax_point_date":"2024-10-30","tax_subtotals":[{"category":"standard","country":"NL","percentage":21.0,"taxable_amount":22.54,"tax_amount":4.73,"type":"VAT"}],"tax_system":"tax_line_percentages","time_zone":null,"ubl_extensions":[]}}}';

    private string $document = '{"legal_entity_id":290868,"direction":"in","guid":"3f0981f1-5105-4970-81f2-6b7482ad27d7","original":"PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0iVVRGLTgiIHN0YW5kYWxv\nbmU9Im5vIj8+CjxJbnZvaWNlIHhtbG5zPSJ1cm46b2FzaXM6bmFtZXM6c3Bl\nY2lmaWNhdGlvbjp1Ymw6c2NoZW1hOnhzZDpJbnZvaWNlLTIiCiAgICAgICAg\nIHhtbG5zOmNhYz0idXJuOm9hc2lzOm5hbWVzOnNwZWNpZmljYXRpb246dWJs\nOnNjaGVtYTp4c2Q6Q29tbW9uQWdncmVnYXRlQ29tcG9uZW50cy0yIgogICAg\nICAgICB4bWxuczpjYmM9InVybjpvYXNpczpuYW1lczpzcGVjaWZpY2F0aW9u\nOnVibDpzY2hlbWE6eHNkOkNvbW1vbkJhc2ljQ29tcG9uZW50cy0yIgogICAg\nICAgICB4bWxuczpjZWM9InVybjpvYXNpczpuYW1lczpzcGVjaWZpY2F0aW9u\nOnVibDpzY2hlbWE6eHNkOkNvbW1vbkV4dGVuc2lvbkNvbXBvbmVudHMtMiI+\nCiAgIDxjYmM6VUJMVmVyc2lvbklEPjIuMTwvY2JjOlVCTFZlcnNpb25JRD4K\nICAgPGNiYzpDdXN0b21pemF0aW9uSUQ+dXJuOmNlbi5ldTplbjE2OTMxOjIw\nMTcjY29tcGxpYW50I3VybjpmZGM6bmVuLm5sOm5sY2l1czp2MS4wPC9jYmM6\nQ3VzdG9taXphdGlvbklEPgogICA8Y2JjOlByb2ZpbGVJRD51cm46ZmRjOnBl\ncHBvbC5ldToyMDE3OnBvYWNjOmJpbGxpbmc6MDE6MS4wPC9jYmM6UHJvZmls\nZUlEPgogICA8Y2JjOklEPjIwMjQtMTAtMzBUMjM6MjA6MjktMmU4YzAyNzQ8\nL2NiYzpJRD4KICAgPGNiYzpJc3N1ZURhdGU+MjAyNC0xMC0zMDwvY2JjOklz\nc3VlRGF0ZT4KICAgPGNiYzpEdWVEYXRlPjIwMjQtMTEtMjk8L2NiYzpEdWVE\nYXRlPgogICA8Y2JjOkludm9pY2VUeXBlQ29kZSBsaXN0QWdlbmN5SUQ9IjYi\nIGxpc3RJRD0iVU5DTDEwMDEiPjM4MDwvY2JjOkludm9pY2VUeXBlQ29kZT4K\nICAgPGNiYzpOb3RlPlRoaXMgaXMgdGhlIGludm9pY2Ugbm90ZS4gU2VuZGVy\ncyBjYW4gZW50ZXIgZnJlZSB0ZXh0LiBUaGlzIG1heSBub3QgYmUgcmVhZCBi\neSB0aGUgcmVjZWl2ZXIsIHNvIGl0IGlzIGRpc2NvdXJhZ2VkIHRvIHVzZSB0\naGlzIGZvciBlbGVjdHJvbmljIGludm9pY2luZy48L2NiYzpOb3RlPgogICA8\nY2JjOlRheFBvaW50RGF0ZT4yMDI0LTEwLTMwPC9jYmM6VGF4UG9pbnREYXRl\nPgogICA8Y2JjOkRvY3VtZW50Q3VycmVuY3lDb2RlIGxpc3RBZ2VuY3lJRD0i\nNiIgbGlzdElEPSJJU080MjE3Ij5VU0Q8L2NiYzpEb2N1bWVudEN1cnJlbmN5\nQ29kZT4KICAgPGNiYzpBY2NvdW50aW5nQ29zdD4yMzA4OTwvY2JjOkFjY291\nbnRpbmdDb3N0PgogICA8Y2JjOkJ1eWVyUmVmZXJlbmNlPmJ1eWVyIHJlZmVy\nZW5jZSBvciBwdXJjaGFzZSBvcmRlciByZWZlcmVuY2UgaXMgcmVjb21tZW5k\nZWQ8L2NiYzpCdXllclJlZmVyZW5jZT4KICAgPGNhYzpJbnZvaWNlUGVyaW9k\nPgogICAgICA8Y2JjOlN0YXJ0RGF0ZT4yMDI0LTA5LTMwPC9jYmM6U3RhcnRE\nYXRlPgogICAgICA8Y2JjOkVuZERhdGU+MjAyNC0xMC0zMDwvY2JjOkVuZERh\ndGU+CiAgIDwvY2FjOkludm9pY2VQZXJpb2Q+CiAgIDxjYWM6T3JkZXJSZWZl\ncmVuY2U+CiAgICAgIDxjYmM6SUQ+YnV5ZXIgcmVmZXJlbmNlIG9yIHB1cmNo\nYXNlIG9yZGVyIHJlZmVyZW5jZSBpcyByZWNvbW1lbmRlZDwvY2JjOklEPgog\nICAgICA8Y2JjOlNhbGVzT3JkZXJJRD5SMDY3ODgxMTE8L2NiYzpTYWxlc09y\nZGVySUQ+CiAgIDwvY2FjOk9yZGVyUmVmZXJlbmNlPgogICA8Y2FjOkJpbGxp\nbmdSZWZlcmVuY2U+CiAgICAgIDxjYWM6SW52b2ljZURvY3VtZW50UmVmZXJl\nbmNlPgogICAgICAgICA8Y2JjOklEPlByZXZpb3VzSW52b2ljZU51bWJlcjEy\nMzQ1NjwvY2JjOklEPgogICAgICA8L2NhYzpJbnZvaWNlRG9jdW1lbnRSZWZl\ncmVuY2U+CiAgIDwvY2FjOkJpbGxpbmdSZWZlcmVuY2U+CiAgIDxjYWM6RGVz\ncGF0Y2hEb2N1bWVudFJlZmVyZW5jZT4KICAgICAgPGNiYzpJRD5ERFQxMjM8\nL2NiYzpJRD4KICAgPC9jYWM6RGVzcGF0Y2hEb2N1bWVudFJlZmVyZW5jZT4K\nICAgPGNhYzpSZWNlaXB0RG9jdW1lbnRSZWZlcmVuY2U+CiAgICAgIDxjYmM6\nSUQ+YWFhYXh4eHg8L2NiYzpJRD4KICAgPC9jYWM6UmVjZWlwdERvY3VtZW50\nUmVmZXJlbmNlPgogICA8Y2FjOk9yaWdpbmF0b3JEb2N1bWVudFJlZmVyZW5j\nZT4KICAgICAgPGNiYzpJRD5iYmJieXl5eTwvY2JjOklEPgogICA8L2NhYzpP\ncmlnaW5hdG9yRG9jdW1lbnRSZWZlcmVuY2U+CiAgIDxjYWM6Q29udHJhY3RE\nb2N1bWVudFJlZmVyZW5jZT4KICAgICAgPGNiYzpJRD5jb250cmFjdDEyMzwv\nY2JjOklEPgogICA8L2NhYzpDb250cmFjdERvY3VtZW50UmVmZXJlbmNlPgog\nICA8Y2FjOkFjY291bnRpbmdTdXBwbGllclBhcnR5PgogICAgICA8Y2FjOlBh\ncnR5PgogICAgICAgICA8Y2JjOkVuZHBvaW50SUQgc2NoZW1lSUQ9IjAxMDYi\nPjAxMjM0NTY3NzwvY2JjOkVuZHBvaW50SUQ+CiAgICAgICAgIDxjYWM6UGFy\ndHlJZGVudGlmaWNhdGlvbj4KICAgICAgICAgICAgPGNiYzpJRCBzY2hlbWVB\nZ2VuY3lJRD0iWlpaIiBzY2hlbWVJRD0iMDEwNiI+MDEyMzQ1Njc3PC9jYmM6\nSUQ+CiAgICAgICAgIDwvY2FjOlBhcnR5SWRlbnRpZmljYXRpb24+CiAgICAg\nICAgIDxjYWM6UGFydHlOYW1lPgogICAgICAgICAgICA8Y2JjOk5hbWU+VGVz\ndCAwMTA2IGlkZW50aWZpZXIgU3RvcmVjb3ZlPC9jYmM6TmFtZT4KICAgICAg\nICAgPC9jYWM6UGFydHlOYW1lPgogICAgICAgICA8Y2FjOlBvc3RhbEFkZHJl\nc3M+CiAgICAgICAgICAgIDxjYmM6U3RyZWV0TmFtZT5BZGRyZXNzIDM0PC9j\nYmM6U3RyZWV0TmFtZT4KICAgICAgICAgICAgPGNiYzpDaXR5TmFtZT5Ib2xz\ndDwvY2JjOkNpdHlOYW1lPgogICAgICAgICAgICA8Y2JjOlBvc3RhbFpvbmU+\nMjMyNCBERjwvY2JjOlBvc3RhbFpvbmU+CiAgICAgICAgICAgIDxjYWM6Q291\nbnRyeT4KICAgICAgICAgICAgICAgPGNiYzpJZGVudGlmaWNhdGlvbkNvZGUg\nbGlzdEFnZW5jeUlEPSI2IiBsaXN0SUQ9IklTTzMxNjYtMTpBbHBoYTIiPk5M\nPC9jYmM6SWRlbnRpZmljYXRpb25Db2RlPgogICAgICAgICAgICA8L2NhYzpD\nb3VudHJ5PgogICAgICAgICA8L2NhYzpQb3N0YWxBZGRyZXNzPgogICAgICAg\nICA8Y2FjOlBhcnR5VGF4U2NoZW1lPgogICAgICAgICAgICA8Y2JjOkNvbXBh\nbnlJRCBzY2hlbWVBZ2VuY3lJRD0iWlpaIiBzY2hlbWVJRD0iOTk0NCI+Tkww\nMDAwMDAwMDBCNDU8L2NiYzpDb21wYW55SUQ+CiAgICAgICAgICAgIDxjYWM6\nVGF4U2NoZW1lPgogICAgICAgICAgICAgICA8Y2JjOklEIHNjaGVtZUFnZW5j\neUlEPSI2IiBzY2hlbWVJRD0iVU4vRUNFIDUxNTMiPlZBVDwvY2JjOklEPgog\nICAgICAgICAgICA8L2NhYzpUYXhTY2hlbWU+CiAgICAgICAgIDwvY2FjOlBh\ncnR5VGF4U2NoZW1lPgogICAgICAgICA8Y2FjOlBhcnR5TGVnYWxFbnRpdHk+\nCiAgICAgICAgICAgIDxjYmM6UmVnaXN0cmF0aW9uTmFtZT5UZXN0IDAxMDYg\naWRlbnRpZmllciBTdG9yZWNvdmU8L2NiYzpSZWdpc3RyYXRpb25OYW1lPgog\nICAgICAgICAgICA8Y2JjOkNvbXBhbnlJRCBzY2hlbWVBZ2VuY3lJRD0iWlpa\nIiBzY2hlbWVJRD0iMDEwNiI+MDEyMzQ1Njc3PC9jYmM6Q29tcGFueUlEPgog\nICAgICAgICA8L2NhYzpQYXJ0eUxlZ2FsRW50aXR5PgogICAgICAgICA8Y2Fj\nOkNvbnRhY3Q+CiAgICAgICAgICAgIDxjYmM6TmFtZT5Kb255IFBvbnNraTwv\nY2JjOk5hbWU+CiAgICAgICAgICAgIDxjYmM6VGVsZXBob25lPjA4OC0zMzMz\nMzMzMzM8L2NiYzpUZWxlcGhvbmU+CiAgICAgICAgICAgIDxjYmM6RWxlY3Ry\nb25pY01haWw+c2VuZGVyQGNvbXBhbnkuY29tPC9jYmM6RWxlY3Ryb25pY01h\naWw+CiAgICAgICAgIDwvY2FjOkNvbnRhY3Q+CiAgICAgIDwvY2FjOlBhcnR5\nPgogICA8L2NhYzpBY2NvdW50aW5nU3VwcGxpZXJQYXJ0eT4KICAgPGNhYzpB\nY2NvdW50aW5nQ3VzdG9tZXJQYXJ0eT4KICAgICAgPGNhYzpQYXJ0eT4KICAg\nICAgICAgPGNiYzpFbmRwb2ludElEIHNjaGVtZUlEPSIwMDg4Ij45NDI5MDQ3\nMDc5OTYwPC9jYmM6RW5kcG9pbnRJRD4KICAgICAgICAgPGNhYzpQYXJ0eU5h\nbWU+CiAgICAgICAgICAgIDxjYmM6TmFtZT5VbnRpdGxlZCBDb21wYW55PC9j\nYmM6TmFtZT4KICAgICAgICAgPC9jYWM6UGFydHlOYW1lPgogICAgICAgICA8\nY2FjOlBvc3RhbEFkZHJlc3M+CiAgICAgICAgICAgIDxjYmM6U3RyZWV0TmFt\nZT5BZGRyZXNzIDE8L2NiYzpTdHJlZXROYW1lPgogICAgICAgICAgICA8Y2Jj\nOkFkZGl0aW9uYWxTdHJlZXROYW1lPkFkZHJlc3MgMTwvY2JjOkFkZGl0aW9u\nYWxTdHJlZXROYW1lPgogICAgICAgICAgICA8Y2JjOkNpdHlOYW1lPkNpdHk8\nL2NiYzpDaXR5TmFtZT4KICAgICAgICAgICAgPGNiYzpQb3N0YWxab25lPlBv\nc3RhbCBDb2RlPC9jYmM6UG9zdGFsWm9uZT4KICAgICAgICAgICAgPGNiYzpD\nb3VudHJ5U3ViZW50aXR5PlN0YXRlPC9jYmM6Q291bnRyeVN1YmVudGl0eT4K\nICAgICAgICAgICAgPGNhYzpDb3VudHJ5PgogICAgICAgICAgICAgICA8Y2Jj\nOklkZW50aWZpY2F0aW9uQ29kZSBsaXN0QWdlbmN5SUQ9IjYiIGxpc3RJRD0i\nSVNPMzE2Ni0xOkFscGhhMiI+REU8L2NiYzpJZGVudGlmaWNhdGlvbkNvZGU+\nCiAgICAgICAgICAgIDwvY2FjOkNvdW50cnk+CiAgICAgICAgIDwvY2FjOlBv\nc3RhbEFkZHJlc3M+CiAgICAgICAgIDxjYWM6UGFydHlUYXhTY2hlbWU+CiAg\nICAgICAgICAgIDxjYmM6Q29tcGFueUlEIHNjaGVtZUFnZW5jeUlEPSJaWloi\nIHNjaGVtZUlEPSI5OTMwIj5ERTkyMzM1NjQ4OTwvY2JjOkNvbXBhbnlJRD4K\nICAgICAgICAgICAgPGNhYzpUYXhTY2hlbWU+CiAgICAgICAgICAgICAgIDxj\nYmM6SUQgc2NoZW1lQWdlbmN5SUQ9IjYiIHNjaGVtZUlEPSJVTi9FQ0UgNTE1\nMyI+VkFUPC9jYmM6SUQ+CiAgICAgICAgICAgIDwvY2FjOlRheFNjaGVtZT4K\nICAgICAgICAgPC9jYWM6UGFydHlUYXhTY2hlbWU+CiAgICAgICAgIDxjYWM6\nUGFydHlMZWdhbEVudGl0eT4KICAgICAgICAgICAgPGNiYzpSZWdpc3RyYXRp\nb25OYW1lPlVudGl0bGVkIENvbXBhbnk8L2NiYzpSZWdpc3RyYXRpb25OYW1l\nPgogICAgICAgICAgICA8Y2JjOkNvbXBhbnlJRCBzY2hlbWVJRD0iMDEwNiI+\nOTk5OTk5OTk8L2NiYzpDb21wYW55SUQ+CiAgICAgICAgIDwvY2FjOlBhcnR5\nTGVnYWxFbnRpdHk+CiAgICAgICAgIDxjYWM6Q29udGFjdD4KICAgICAgICAg\nICAgPGNiYzpOYW1lPlBvbiBKb2huc29uPC9jYmM6TmFtZT4KICAgICAgICAg\nICAgPGNiYzpUZWxlcGhvbmU+MDg4LTQ0NDQ0NDQ0NDwvY2JjOlRlbGVwaG9u\nZT4KICAgICAgICAgICAgPGNiYzpFbGVjdHJvbmljTWFpbD5yZWNlaXZlckBj\nb21wYW55LmNvbTwvY2JjOkVsZWN0cm9uaWNNYWlsPgogICAgICAgICA8L2Nh\nYzpDb250YWN0PgogICAgICA8L2NhYzpQYXJ0eT4KICAgPC9jYWM6QWNjb3Vu\ndGluZ0N1c3RvbWVyUGFydHk+CiAgIDxjYWM6RGVsaXZlcnk+CiAgICAgIDxj\nYmM6QWN0dWFsRGVsaXZlcnlEYXRlPjIwMjQtMTAtMjk8L2NiYzpBY3R1YWxE\nZWxpdmVyeURhdGU+CiAgICAgIDxjYWM6RGVsaXZlcnlMb2NhdGlvbj4KICAg\nICAgICAgPGNiYzpJRCBzY2hlbWVJRD0iMDA4OCI+ODcxNjkwOTMwMDAwNDc4\nNjExPC9jYmM6SUQ+CiAgICAgICAgIDxjYWM6QWRkcmVzcz4KICAgICAgICAg\nICAgPGNiYzpTdHJlZXROYW1lPmxpbmUxPC9jYmM6U3RyZWV0TmFtZT4KICAg\nICAgICAgICAgPGNiYzpBZGRpdGlvbmFsU3RyZWV0TmFtZT5saW5lMjwvY2Jj\nOkFkZGl0aW9uYWxTdHJlZXROYW1lPgogICAgICAgICAgICA8Y2JjOkNpdHlO\nYW1lPkNJVFk8L2NiYzpDaXR5TmFtZT4KICAgICAgICAgICAgPGNiYzpQb3N0\nYWxab25lPjM0MjM0MjM8L2NiYzpQb3N0YWxab25lPgogICAgICAgICAgICA8\nY2JjOkNvdW50cnlTdWJlbnRpdHk+Q0E8L2NiYzpDb3VudHJ5U3ViZW50aXR5\nPgogICAgICAgICAgICA8Y2FjOkNvdW50cnk+CiAgICAgICAgICAgICAgIDxj\nYmM6SWRlbnRpZmljYXRpb25Db2RlIGxpc3RBZ2VuY3lJRD0iNiIgbGlzdElE\nPSJJU08zMTY2LTE6QWxwaGEyIj5VUzwvY2JjOklkZW50aWZpY2F0aW9uQ29k\nZT4KICAgICAgICAgICAgPC9jYWM6Q291bnRyeT4KICAgICAgICAgPC9jYWM6\nQWRkcmVzcz4KICAgICAgPC9jYWM6RGVsaXZlcnlMb2NhdGlvbj4KICAgICAg\nPGNhYzpEZWxpdmVyeVBhcnR5PgogICAgICAgICA8Y2FjOlBhcnR5TmFtZT4K\nICAgICAgICAgICAgPGNiYzpOYW1lPkRlbGl2ZXJlZCBUbyBOYW1lPC9jYmM6\nTmFtZT4KICAgICAgICAgPC9jYWM6UGFydHlOYW1lPgogICAgICA8L2NhYzpE\nZWxpdmVyeVBhcnR5PgogICA8L2NhYzpEZWxpdmVyeT4KICAgPGNhYzpQYXlt\nZW50TWVhbnM+CiAgICAgIDxjYmM6UGF5bWVudE1lYW5zQ29kZT4zMDwvY2Jj\nOlBheW1lbnRNZWFuc0NvZGU+CiAgICAgIDxjYmM6UGF5bWVudElEPjQ0NTU2\nNjc3PC9jYmM6UGF5bWVudElEPgogICAgICA8Y2FjOlBheWVlRmluYW5jaWFs\nQWNjb3VudD4KICAgICAgICAgPGNiYzpJRD5OTDUwUkFCTzAxNjI0MzI0NDU8\nL2NiYzpJRD4KICAgICAgPC9jYWM6UGF5ZWVGaW5hbmNpYWxBY2NvdW50Pgog\nICA8L2NhYzpQYXltZW50TWVhbnM+CiAgIDxjYWM6UGF5bWVudFRlcm1zPgog\nICAgICA8Y2JjOk5vdGU+Rm9yIHBheW1lbnQgdGVybXMsIG9ubHkgYSBub3Rl\nIGlzIHN1cHBvcnRlZCBieSBQZXBwb2wgY3VycmVudGx5LjwvY2JjOk5vdGU+\nCiAgIDwvY2FjOlBheW1lbnRUZXJtcz4KICAgPGNhYzpBbGxvd2FuY2VDaGFy\nZ2U+CiAgICAgIDxjYmM6Q2hhcmdlSW5kaWNhdG9yPnRydWU8L2NiYzpDaGFy\nZ2VJbmRpY2F0b3I+CiAgICAgIDxjYmM6QWxsb3dhbmNlQ2hhcmdlUmVhc29u\nPmxhdGUgcGF5bWVudDwvY2JjOkFsbG93YW5jZUNoYXJnZVJlYXNvbj4KICAg\nICAgPGNiYzpBbW91bnQgY3VycmVuY3lJRD0iVVNEIj4xMS4yMDwvY2JjOkFt\nb3VudD4KICAgICAgPGNhYzpUYXhDYXRlZ29yeT4KICAgICAgICAgPGNiYzpJ\nRCBzY2hlbWVBZ2VuY3lJRD0iNiIgc2NoZW1lSUQ9IlVOQ0w1MzA1Ij5TPC9j\nYmM6SUQ+CiAgICAgICAgIDxjYmM6UGVyY2VudD4yMS4wPC9jYmM6UGVyY2Vu\ndD4KICAgICAgICAgPGNhYzpUYXhTY2hlbWU+CiAgICAgICAgICAgIDxjYmM6\nSUQgc2NoZW1lQWdlbmN5SUQ9IjYiIHNjaGVtZUlEPSJVTi9FQ0UgNTE1MyI+\nVkFUPC9jYmM6SUQ+CiAgICAgICAgIDwvY2FjOlRheFNjaGVtZT4KICAgICAg\nPC9jYWM6VGF4Q2F0ZWdvcnk+CiAgIDwvY2FjOkFsbG93YW5jZUNoYXJnZT4K\nICAgPGNhYzpBbGxvd2FuY2VDaGFyZ2U+CiAgICAgIDxjYmM6Q2hhcmdlSW5k\naWNhdG9yPmZhbHNlPC9jYmM6Q2hhcmdlSW5kaWNhdG9yPgogICAgICA8Y2Jj\nOkFsbG93YW5jZUNoYXJnZVJlYXNvbj5ib251czwvY2JjOkFsbG93YW5jZUNo\nYXJnZVJlYXNvbj4KICAgICAgPGNiYzpBbW91bnQgY3VycmVuY3lJRD0iVVNE\nIj4xLjAwPC9jYmM6QW1vdW50PgogICAgICA8Y2FjOlRheENhdGVnb3J5Pgog\nICAgICAgICA8Y2JjOklEIHNjaGVtZUFnZW5jeUlEPSI2IiBzY2hlbWVJRD0i\nVU5DTDUzMDUiPlM8L2NiYzpJRD4KICAgICAgICAgPGNiYzpQZXJjZW50PjIx\nLjA8L2NiYzpQZXJjZW50PgogICAgICAgICA8Y2FjOlRheFNjaGVtZT4KICAg\nICAgICAgICAgPGNiYzpJRCBzY2hlbWVBZ2VuY3lJRD0iNiIgc2NoZW1lSUQ9\nIlVOL0VDRSA1MTUzIj5WQVQ8L2NiYzpJRD4KICAgICAgICAgPC9jYWM6VGF4\nU2NoZW1lPgogICAgICA8L2NhYzpUYXhDYXRlZ29yeT4KICAgPC9jYWM6QWxs\nb3dhbmNlQ2hhcmdlPgogICA8Y2FjOlRheFRvdGFsPgogICAgICA8Y2JjOlRh\neEFtb3VudCBjdXJyZW5jeUlEPSJVU0QiPjQuNzM8L2NiYzpUYXhBbW91bnQ+\nCiAgICAgIDxjYWM6VGF4U3VidG90YWw+CiAgICAgICAgIDxjYmM6VGF4YWJs\nZUFtb3VudCBjdXJyZW5jeUlEPSJVU0QiPjIyLjU0PC9jYmM6VGF4YWJsZUFt\nb3VudD4KICAgICAgICAgPGNiYzpUYXhBbW91bnQgY3VycmVuY3lJRD0iVVNE\nIj40LjczPC9jYmM6VGF4QW1vdW50PgogICAgICAgICA8Y2FjOlRheENhdGVn\nb3J5PgogICAgICAgICAgICA8Y2JjOklEIHNjaGVtZUFnZW5jeUlEPSI2IiBz\nY2hlbWVJRD0iVU5DTDUzMDUiPlM8L2NiYzpJRD4KICAgICAgICAgICAgPGNi\nYzpQZXJjZW50PjIxLjA8L2NiYzpQZXJjZW50PgogICAgICAgICAgICA8Y2Fj\nOlRheFNjaGVtZT4KICAgICAgICAgICAgICAgPGNiYzpJRCBzY2hlbWVBZ2Vu\nY3lJRD0iNiIgc2NoZW1lSUQ9IlVOL0VDRSA1MTUzIj5WQVQ8L2NiYzpJRD4K\nICAgICAgICAgICAgPC9jYWM6VGF4U2NoZW1lPgogICAgICAgICA8L2NhYzpU\nYXhDYXRlZ29yeT4KICAgICAgPC9jYWM6VGF4U3VidG90YWw+CiAgIDwvY2Fj\nOlRheFRvdGFsPgogICA8Y2FjOkxlZ2FsTW9uZXRhcnlUb3RhbD4KICAgICAg\nPGNiYzpMaW5lRXh0ZW5zaW9uQW1vdW50IGN1cnJlbmN5SUQ9IlVTRCI+MTIu\nMzQ8L2NiYzpMaW5lRXh0ZW5zaW9uQW1vdW50PgogICAgICA8Y2JjOlRheEV4\nY2x1c2l2ZUFtb3VudCBjdXJyZW5jeUlEPSJVU0QiPjIyLjU0PC9jYmM6VGF4\nRXhjbHVzaXZlQW1vdW50PgogICAgICA8Y2JjOlRheEluY2x1c2l2ZUFtb3Vu\ndCBjdXJyZW5jeUlEPSJVU0QiPjI3LjI3PC9jYmM6VGF4SW5jbHVzaXZlQW1v\ndW50PgogICAgICA8Y2JjOkFsbG93YW5jZVRvdGFsQW1vdW50IGN1cnJlbmN5\nSUQ9IlVTRCI+MS4wMDwvY2JjOkFsbG93YW5jZVRvdGFsQW1vdW50PgogICAg\nICA8Y2JjOkNoYXJnZVRvdGFsQW1vdW50IGN1cnJlbmN5SUQ9IlVTRCI+MTEu\nMjA8L2NiYzpDaGFyZ2VUb3RhbEFtb3VudD4KICAgICAgPGNiYzpQcmVwYWlk\nQW1vdW50IGN1cnJlbmN5SUQ9IlVTRCI+MS4wMDwvY2JjOlByZXBhaWRBbW91\nbnQ+CiAgICAgIDxjYmM6UGF5YWJsZVJvdW5kaW5nQW1vdW50IGN1cnJlbmN5\nSUQ9IlVTRCI+MC4wMjwvY2JjOlBheWFibGVSb3VuZGluZ0Ftb3VudD4KICAg\nICAgPGNiYzpQYXlhYmxlQW1vdW50IGN1cnJlbmN5SUQ9IlVTRCI+MjYuMjk8\nL2NiYzpQYXlhYmxlQW1vdW50PgogICA8L2NhYzpMZWdhbE1vbmV0YXJ5VG90\nYWw+CiAgIDxjYWM6SW52b2ljZUxpbmU+CiAgICAgIDxjYmM6SUQ+MTwvY2Jj\nOklEPgogICAgICA8Y2JjOk5vdGU+T25seSBoYWxmIHRoZSBzdG9yeS4uLjwv\nY2JjOk5vdGU+CiAgICAgIDxjYmM6SW52b2ljZWRRdWFudGl0eSB1bml0Q29k\nZT0iS1dIIiB1bml0Q29kZUxpc3RJRD0iVU5FQ0VSZWMyMCI+NjMuOTkyPC9j\nYmM6SW52b2ljZWRRdWFudGl0eT4KICAgICAgPGNiYzpMaW5lRXh0ZW5zaW9u\nQW1vdW50IGN1cnJlbmN5SUQ9IlVTRCI+Mi42NzwvY2JjOkxpbmVFeHRlbnNp\nb25BbW91bnQ+CiAgICAgIDxjYmM6QWNjb3VudGluZ0Nvc3Q+MjMwODk8L2Ni\nYzpBY2NvdW50aW5nQ29zdD4KICAgICAgPGNhYzpJbnZvaWNlUGVyaW9kPgog\nICAgICAgICA8Y2JjOlN0YXJ0RGF0ZT4yMDI0LTA5LTMwPC9jYmM6U3RhcnRE\nYXRlPgogICAgICAgICA8Y2JjOkVuZERhdGU+MjAyNC0xMC0zMDwvY2JjOkVu\nZERhdGU+CiAgICAgIDwvY2FjOkludm9pY2VQZXJpb2Q+CiAgICAgIDxjYWM6\nT3JkZXJMaW5lUmVmZXJlbmNlPgogICAgICAgICA8Y2JjOkxpbmVJRD4xPC9j\nYmM6TGluZUlEPgogICAgICA8L2NhYzpPcmRlckxpbmVSZWZlcmVuY2U+CiAg\nICAgIDxjYWM6RG9jdW1lbnRSZWZlcmVuY2U+CiAgICAgICAgIDxjYmM6SUQ+\nQUFBQUFBQUE8L2NiYzpJRD4KICAgICAgICAgPGNiYzpEb2N1bWVudFR5cGVD\nb2RlPjEzMDwvY2JjOkRvY3VtZW50VHlwZUNvZGU+CiAgICAgIDwvY2FjOkRv\nY3VtZW50UmVmZXJlbmNlPgogICAgICA8Y2FjOkFsbG93YW5jZUNoYXJnZT4K\nICAgICAgICAgPGNiYzpDaGFyZ2VJbmRpY2F0b3I+ZmFsc2U8L2NiYzpDaGFy\nZ2VJbmRpY2F0b3I+CiAgICAgICAgIDxjYmM6QWxsb3dhbmNlQ2hhcmdlUmVh\nc29uPnNwZWNpYWwgZGlzY291bnQ8L2NiYzpBbGxvd2FuY2VDaGFyZ2VSZWFz\nb24+CiAgICAgICAgIDxjYmM6QW1vdW50IGN1cnJlbmN5SUQ9IlVTRCI+MC4y\nNTwvY2JjOkFtb3VudD4KICAgICAgPC9jYWM6QWxsb3dhbmNlQ2hhcmdlPgog\nICAgICA8Y2FjOkFsbG93YW5jZUNoYXJnZT4KICAgICAgICAgPGNiYzpDaGFy\nZ2VJbmRpY2F0b3I+ZmFsc2U8L2NiYzpDaGFyZ2VJbmRpY2F0b3I+CiAgICAg\nICAgIDxjYmM6QWxsb3dhbmNlQ2hhcmdlUmVhc29uPmV2ZW4gbW9yZSBzcGVj\naWFsIGRpc2NvdW50PC9jYmM6QWxsb3dhbmNlQ2hhcmdlUmVhc29uPgogICAg\nICAgICA8Y2JjOkFtb3VudCBjdXJyZW5jeUlEPSJVU0QiPjAuNzU8L2NiYzpB\nbW91bnQ+CiAgICAgIDwvY2FjOkFsbG93YW5jZUNoYXJnZT4KICAgICAgPGNh\nYzpJdGVtPgogICAgICAgICA8Y2JjOkRlc2NyaXB0aW9uPlN1cHBseTwvY2Jj\nOkRlc2NyaXB0aW9uPgogICAgICAgICA8Y2JjOk5hbWU+U3VwcGx5IHBlYWs8\nL2NiYzpOYW1lPgogICAgICAgICA8Y2FjOkJ1eWVyc0l0ZW1JZGVudGlmaWNh\ndGlvbj4KICAgICAgICAgICAgPGNiYzpJRD45IDAwOCAxMTU8L2NiYzpJRD4K\nICAgICAgICAgPC9jYWM6QnV5ZXJzSXRlbUlkZW50aWZpY2F0aW9uPgogICAg\nICAgICA8Y2FjOlNlbGxlcnNJdGVtSWRlbnRpZmljYXRpb24+CiAgICAgICAg\nICAgIDxjYmM6SUQ+RV9EVktfUEtsaWtfS1ZQX0xQPC9jYmM6SUQ+CiAgICAg\nICAgIDwvY2FjOlNlbGxlcnNJdGVtSWRlbnRpZmljYXRpb24+CiAgICAgICAg\nIDxjYWM6U3RhbmRhcmRJdGVtSWRlbnRpZmljYXRpb24+CiAgICAgICAgICAg\nIDxjYmM6SUQgc2NoZW1lQWdlbmN5SUQ9IjkiIHNjaGVtZUlEPSIwMDg4Ij44\nNzE4ODY4NTk3MDgzPC9jYmM6SUQ+CiAgICAgICAgIDwvY2FjOlN0YW5kYXJk\nSXRlbUlkZW50aWZpY2F0aW9uPgogICAgICAgICA8Y2FjOkNvbW1vZGl0eUNs\nYXNzaWZpY2F0aW9uPgogICAgICAgICAgICA8Y2JjOkNvbW1vZGl0eUNvZGU+\nQkJCQkJCQkI8L2NiYzpDb21tb2RpdHlDb2RlPgogICAgICAgICA8L2NhYzpD\nb21tb2RpdHlDbGFzc2lmaWNhdGlvbj4KICAgICAgICAgPGNhYzpDb21tb2Rp\ndHlDbGFzc2lmaWNhdGlvbj4KICAgICAgICAgICAgPGNiYzpJdGVtQ2xhc3Np\nZmljYXRpb25Db2RlIGxpc3RJRD0iWlpaIj5DQ0NDQ0NDQzwvY2JjOkl0ZW1D\nbGFzc2lmaWNhdGlvbkNvZGU+CiAgICAgICAgIDwvY2FjOkNvbW1vZGl0eUNs\nYXNzaWZpY2F0aW9uPgogICAgICAgICA8Y2FjOkNsYXNzaWZpZWRUYXhDYXRl\nZ29yeT4KICAgICAgICAgICAgPGNiYzpJRCBzY2hlbWVBZ2VuY3lJRD0iNiIg\nc2NoZW1lSUQ9IlVOQ0w1MzA1Ij5TPC9jYmM6SUQ+CiAgICAgICAgICAgIDxj\nYmM6UGVyY2VudD4yMS4wPC9jYmM6UGVyY2VudD4KICAgICAgICAgICAgPGNh\nYzpUYXhTY2hlbWU+CiAgICAgICAgICAgICAgIDxjYmM6SUQgc2NoZW1lQWdl\nbmN5SUQ9IjYiIHNjaGVtZUlEPSJVTi9FQ0UgNTE1MyI+VkFUPC9jYmM6SUQ+\nCiAgICAgICAgICAgIDwvY2FjOlRheFNjaGVtZT4KICAgICAgICAgPC9jYWM6\nQ2xhc3NpZmllZFRheENhdGVnb3J5PgogICAgICAgICA8Y2FjOkFkZGl0aW9u\nYWxJdGVtUHJvcGVydHk+CiAgICAgICAgICAgIDxjYmM6TmFtZT5VdGlsaXR5\nQ29uc3VtcHRpb25Qb2ludDwvY2JjOk5hbWU+CiAgICAgICAgICAgIDxjYmM6\nVmFsdWU+ODcxNjkwOTMwMDAwMjIyMjIxPC9jYmM6VmFsdWU+CiAgICAgICAg\nIDwvY2FjOkFkZGl0aW9uYWxJdGVtUHJvcGVydHk+CiAgICAgICAgIDxjYWM6\nQWRkaXRpb25hbEl0ZW1Qcm9wZXJ0eT4KICAgICAgICAgICAgPGNiYzpOYW1l\nPlV0aWxpdHlDb25zdW1wdGlvblBvaW50QWRkcmVzczwvY2JjOk5hbWU+CiAg\nICAgICAgICAgIDxjYmM6VmFsdWU+VkUgSEFaRVJTV09VREUtWFhYWFg8L2Ni\nYzpWYWx1ZT4KICAgICAgICAgPC9jYWM6QWRkaXRpb25hbEl0ZW1Qcm9wZXJ0\neT4KICAgICAgPC9jYWM6SXRlbT4KICAgICAgPGNhYzpQcmljZT4KICAgICAg\nICAgPGNiYzpQcmljZUFtb3VudCBjdXJyZW5jeUlEPSJVU0QiPjAuMTQzMzc3\nMzwvY2JjOlByaWNlQW1vdW50PgogICAgICAgICA8Y2JjOkJhc2VRdWFudGl0\neSB1bml0Q29kZT0iS1dIIiB1bml0Q29kZUxpc3RJRD0iVU5FQ0VSZWMyMCI+\nMi41PC9jYmM6QmFzZVF1YW50aXR5PgogICAgICA8L2NhYzpQcmljZT4KICAg\nPC9jYWM6SW52b2ljZUxpbmU+CiAgIDxjYWM6SW52b2ljZUxpbmU+CiAgICAg\nIDxjYmM6SUQ+MjwvY2JjOklEPgogICAgICA8Y2JjOk5vdGU+T25seSBoYWxm\nIHRoZSBzdG9yeS4uLjwvY2JjOk5vdGU+CiAgICAgIDxjYmM6SW52b2ljZWRR\ndWFudGl0eSB1bml0Q29kZT0iSzYiIHVuaXRDb2RlTGlzdElEPSJVTkVDRVJl\nYzIwIj4xMi44ODg8L2NiYzpJbnZvaWNlZFF1YW50aXR5PgogICAgICA8Y2Jj\nOkxpbmVFeHRlbnNpb25BbW91bnQgY3VycmVuY3lJRD0iVVNEIj45LjY3PC9j\nYmM6TGluZUV4dGVuc2lvbkFtb3VudD4KICAgICAgPGNiYzpBY2NvdW50aW5n\nQ29zdD4yMzA4OTwvY2JjOkFjY291bnRpbmdDb3N0PgogICAgICA8Y2FjOklu\ndm9pY2VQZXJpb2Q+CiAgICAgICAgIDxjYmM6U3RhcnREYXRlPjIwMjQtMDkt\nMzA8L2NiYzpTdGFydERhdGU+CiAgICAgICAgIDxjYmM6RW5kRGF0ZT4yMDI0\nLTEwLTMwPC9jYmM6RW5kRGF0ZT4KICAgICAgPC9jYWM6SW52b2ljZVBlcmlv\nZD4KICAgICAgPGNhYzpPcmRlckxpbmVSZWZlcmVuY2U+CiAgICAgICAgIDxj\nYmM6TGluZUlEPjE8L2NiYzpMaW5lSUQ+CiAgICAgIDwvY2FjOk9yZGVyTGlu\nZVJlZmVyZW5jZT4KICAgICAgPGNhYzpEb2N1bWVudFJlZmVyZW5jZT4KICAg\nICAgICAgPGNiYzpJRD5BQUFBQUFBQTwvY2JjOklEPgogICAgICAgICA8Y2Jj\nOkRvY3VtZW50VHlwZUNvZGU+MTMwPC9jYmM6RG9jdW1lbnRUeXBlQ29kZT4K\nICAgICAgPC9jYWM6RG9jdW1lbnRSZWZlcmVuY2U+CiAgICAgIDxjYWM6QWxs\nb3dhbmNlQ2hhcmdlPgogICAgICAgICA8Y2JjOkNoYXJnZUluZGljYXRvcj5m\nYWxzZTwvY2JjOkNoYXJnZUluZGljYXRvcj4KICAgICAgICAgPGNiYzpBbGxv\nd2FuY2VDaGFyZ2VSZWFzb24+c3BlY2lhbCBkaXNjb3VudDwvY2JjOkFsbG93\nYW5jZUNoYXJnZVJlYXNvbj4KICAgICAgICAgPGNiYzpBbW91bnQgY3VycmVu\nY3lJRD0iVVNEIj4wLjI1PC9jYmM6QW1vdW50PgogICAgICA8L2NhYzpBbGxv\nd2FuY2VDaGFyZ2U+CiAgICAgIDxjYWM6QWxsb3dhbmNlQ2hhcmdlPgogICAg\nICAgICA8Y2JjOkNoYXJnZUluZGljYXRvcj5mYWxzZTwvY2JjOkNoYXJnZUlu\nZGljYXRvcj4KICAgICAgICAgPGNiYzpBbGxvd2FuY2VDaGFyZ2VSZWFzb24+\nZXZlbiBtb3JlIHNwZWNpYWwgZGlzY291bnQ8L2NiYzpBbGxvd2FuY2VDaGFy\nZ2VSZWFzb24+CiAgICAgICAgIDxjYmM6QW1vdW50IGN1cnJlbmN5SUQ9IlVT\nRCI+MC43NTwvY2JjOkFtb3VudD4KICAgICAgPC9jYWM6QWxsb3dhbmNlQ2hh\ncmdlPgogICAgICA8Y2FjOkl0ZW0+CiAgICAgICAgIDxjYmM6RGVzY3JpcHRp\nb24+U3VwcGx5PC9jYmM6RGVzY3JpcHRpb24+CiAgICAgICAgIDxjYmM6TmFt\nZT5TdXBwbHkgcGVhazwvY2JjOk5hbWU+CiAgICAgICAgIDxjYWM6QnV5ZXJz\nSXRlbUlkZW50aWZpY2F0aW9uPgogICAgICAgICAgICA8Y2JjOklEPjkgMDA4\nIDExNTwvY2JjOklEPgogICAgICAgICA8L2NhYzpCdXllcnNJdGVtSWRlbnRp\nZmljYXRpb24+CiAgICAgICAgIDxjYWM6U2VsbGVyc0l0ZW1JZGVudGlmaWNh\ndGlvbj4KICAgICAgICAgICAgPGNiYzpJRD5FX0RWS19QS2xpa19LVlBfTFA8\nL2NiYzpJRD4KICAgICAgICAgPC9jYWM6U2VsbGVyc0l0ZW1JZGVudGlmaWNh\ndGlvbj4KICAgICAgICAgPGNhYzpTdGFuZGFyZEl0ZW1JZGVudGlmaWNhdGlv\nbj4KICAgICAgICAgICAgPGNiYzpJRCBzY2hlbWVBZ2VuY3lJRD0iOSIgc2No\nZW1lSUQ9IjAwODgiPjg3MTg4Njg1OTcwODM8L2NiYzpJRD4KICAgICAgICAg\nPC9jYWM6U3RhbmRhcmRJdGVtSWRlbnRpZmljYXRpb24+CiAgICAgICAgIDxj\nYWM6Q29tbW9kaXR5Q2xhc3NpZmljYXRpb24+CiAgICAgICAgICAgIDxjYmM6\nQ29tbW9kaXR5Q29kZT5CQkJCQkJCQjwvY2JjOkNvbW1vZGl0eUNvZGU+CiAg\nICAgICAgIDwvY2FjOkNvbW1vZGl0eUNsYXNzaWZpY2F0aW9uPgogICAgICAg\nICA8Y2FjOkNvbW1vZGl0eUNsYXNzaWZpY2F0aW9uPgogICAgICAgICAgICA8\nY2JjOkl0ZW1DbGFzc2lmaWNhdGlvbkNvZGUgbGlzdElEPSJaWloiPkNDQ0ND\nQ0NDPC9jYmM6SXRlbUNsYXNzaWZpY2F0aW9uQ29kZT4KICAgICAgICAgPC9j\nYWM6Q29tbW9kaXR5Q2xhc3NpZmljYXRpb24+CiAgICAgICAgIDxjYWM6Q2xh\nc3NpZmllZFRheENhdGVnb3J5PgogICAgICAgICAgICA8Y2JjOklEIHNjaGVt\nZUFnZW5jeUlEPSI2IiBzY2hlbWVJRD0iVU5DTDUzMDUiPlM8L2NiYzpJRD4K\nICAgICAgICAgICAgPGNiYzpQZXJjZW50PjIxLjA8L2NiYzpQZXJjZW50Pgog\nICAgICAgICAgICA8Y2FjOlRheFNjaGVtZT4KICAgICAgICAgICAgICAgPGNi\nYzpJRCBzY2hlbWVBZ2VuY3lJRD0iNiIgc2NoZW1lSUQ9IlVOL0VDRSA1MTUz\nIj5WQVQ8L2NiYzpJRD4KICAgICAgICAgICAgPC9jYWM6VGF4U2NoZW1lPgog\nICAgICAgICA8L2NhYzpDbGFzc2lmaWVkVGF4Q2F0ZWdvcnk+CiAgICAgICAg\nIDxjYWM6QWRkaXRpb25hbEl0ZW1Qcm9wZXJ0eT4KICAgICAgICAgICAgPGNi\nYzpOYW1lPlV0aWxpdHlDb25zdW1wdGlvblBvaW50PC9jYmM6TmFtZT4KICAg\nICAgICAgICAgPGNiYzpWYWx1ZT44NzE2OTA5MzAwMDAyMjIyMjE8L2NiYzpW\nYWx1ZT4KICAgICAgICAgPC9jYWM6QWRkaXRpb25hbEl0ZW1Qcm9wZXJ0eT4K\nICAgICAgICAgPGNhYzpBZGRpdGlvbmFsSXRlbVByb3BlcnR5PgogICAgICAg\nICAgICA8Y2JjOk5hbWU+VXRpbGl0eUNvbnN1bXB0aW9uUG9pbnRBZGRyZXNz\nPC9jYmM6TmFtZT4KICAgICAgICAgICAgPGNiYzpWYWx1ZT5WRSBIQVpFUlNX\nT1VERS1YWFhYWDwvY2JjOlZhbHVlPgogICAgICAgICA8L2NhYzpBZGRpdGlv\nbmFsSXRlbVByb3BlcnR5PgogICAgICA8L2NhYzpJdGVtPgogICAgICA8Y2Fj\nOlByaWNlPgogICAgICAgICA8Y2JjOlByaWNlQW1vdW50IGN1cnJlbmN5SUQ9\nIlVTRCI+Mi4zMDk0NDI0NTwvY2JjOlByaWNlQW1vdW50PgogICAgICAgICA8\nY2JjOkJhc2VRdWFudGl0eSB1bml0Q29kZT0iSzYiIHVuaXRDb2RlTGlzdElE\nPSJVTkVDRVJlYzIwIj4yLjc4OTUxMjEyPC9jYmM6QmFzZVF1YW50aXR5Pgog\nICAgICA8L2NhYzpQcmljZT4KICAgPC9jYWM6SW52b2ljZUxpbmU+CjwvSW52\nb2ljZT4K\n"}';

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();

        if (config('ninja.testvars.travis') !== false || !config('ninja.storecove_api_key')) {
            $this->markTestSkipped("do not run in CI");
        }
                
        $this->withoutMiddleware(
            ThrottleRequests::class
        );

    }

    public function getStorecoveInvoice($x)
    {
                
        $storecove = new Storecove();

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
            $x,
            StorecoveInvoice::class,
            'json',
            $context
        );

        return $storecove_invoice;
    }

    public function testRenderHtmlDocument()
    {
                
        $doc = json_decode($this->document, true);

        $decoded = base64_decode($doc['original']);
        $guid = $doc['guid'];

        $filename = "{$guid}.xml";

        $xslt = new XsltDocumentValidator($decoded);
        $html = $xslt->getHtml();

        nlog($html);

        $this->assertIsString($html);
    }

    public function testSaveDocument()
    {
        $doc = json_decode($this->document,true);

        $decoded = base64_decode($doc['original']);
        $guid = $doc['guid'];

        $filename = "{$guid}.xml";
        $document = TempFile::UploadedFileFromBase64($doc['original'], $filename);

        $this->saveDocument($document, $this->expense);

        $e = $this->expense->fresh()->load('documents');

        $this->assertEquals(1, $this->expense->documents->count());

        $d = $this->expense->documents->first();
        
    }

    public function testExpenseCreation()
    {

        $x = json_decode($this->test_invoice, true);
        $doc = $x['document']['invoice'];
        $x = json_encode($doc);

        $storecove_invoice = $this->getStorecoveInvoice($x);

        $this->assertInstanceOf(StorecoveInvoice::class, $storecove_invoice);

        $storecove = new Storecove();
        $expense = $storecove->expense->createExpense($storecove_invoice, $this->company);

        // $this->assertInstanceOf(Expense::class, $expense);

    }

    public function testIngestStorecoveDocument()
    {
        $storecove = new Storecove();
        $x = json_decode($this->test_invoice, true);

        $doc = $x['document']['invoice'];

        $x = json_encode($doc);

        $storecove_invoice = $this->getStorecoveInvoice($x);

        $this->assertInstanceOf(StorecoveInvoice::class, $storecove_invoice);
        $this->assertEquals(27.27, $storecove_invoice->getAmountIncludingTax());

        $tax_totals = [];

        foreach ($storecove_invoice->getTaxSubtotals() as $tdf) {

            $tax_totals[] = (array)$tdf;
        }

        $totals = collect($tax_totals);
        $this->assertEquals(4.73, $totals->sum('tax_amount'));

        $party = $storecove_invoice->getAccountingSupplierParty()->getParty();
        $pis = $storecove_invoice->getAccountingSupplierParty()->getPublicIdentifiers();
        
        $vat_number = '';
        $id_number = '';

        foreach($pis as $pi)
        {
            if($ident = $storecove->router->resolveIdentifierTypeByValue($pi->getScheme()))
            {
                if($ident == 'vat_number')
                    $vat_number = $pi->getId();
                elseif($ident == 'id_number')
                    $id_number = $pi->getId();
            }
        }

        $item_descriptions = collect();
        foreach($storecove_invoice->getInvoiceLines() as $item)
        {
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
        
        $currency = app('currencies')->first(function ($c) use ($storecove_invoice){
            return $storecove_invoice->getDocumentCurrencyCode() == $c->iso_3166_3;
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
                    'last_name' => $party->getContact()->getFirstName() ?? '',
                    'email' => $party->getContact()->getEmail() ?? '',
                    'phone' => $party->getContact()->getPhone() ?? '',
                ]
            ],
        ];

        //expense
        $expense = [
            'amount' => $storecove_invoice->getAmountIncludingTax(),
            'currency_id' => $currency,
            'vendor_id' => '',
            'date' => $storecove_invoice->getIssueDate(),
            'public_notes' => $item_descriptions->implode("\n"),
            'private_notes' => $storecove_invoice->getNote() ?? '',
            'transaction_reference' => $storecove_invoice->getInvoiceNumber(),
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
        ];


    }

}