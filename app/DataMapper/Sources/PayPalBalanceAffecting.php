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

namespace App\DataMapper\Sources;

use App\DataMapper\InvoiceItem;

class PayPalBalanceAffecting
{
    private array $key_map = [
        'Date' => 'date',
        'Time' => 'time',
        'TimeZone' => 'timezone',
        'Name' => 'name',
        'Type' => 'type',
        'Status' => 'status',
        'Currency' => 'currency',
        'Gross' => 'gross',
        'Fee' => 'fee',
        'Net' => 'net',
        'From Email Address' => 'fromEmailAddress',
        'To Email Address' => 'toEmailAddress',
        'Transaction ID' => 'transactionId',
        'Shipping Address' => 'shippingAddress',
        'Item Title' => 'itemTitle',
        'Item ID' => 'itemId',
        'Option 1 Name' => 'option1Name',
        'Option 1 Value' => 'option1Value',
        'Option 2 Name' => 'option2Name',
        'Option 2 Value' => 'option2Value',
        'Reference Txn ID' => 'referenceTxId',
        'Invoice Number' => 'invoiceNumber',
        'Custom Number' => 'customNumber',
        'Quantity' => 'quantity',
        'Receipt ID' => 'receiptId',
        'Address Line 1' => 'addressLine1',
        'Address Line 2/District/Neighborhood' => 'addressLine2DistrictNeighborhood',
        'Town/City' => 'townCity',
        'State/Province/Region/County/Territory/Prefecture/Republic' => 'stateProvinceRegionCountyTerritoryPrefectureRepublic',
        'Zip/Postal Code' => 'zipPostalCode',
        'Country' => 'country',
        'Contact Phone Number' => 'contactPhoneNumber',
        'Subject' => 'subject',
        'Note' => 'note',
        'Transaction Event Code' => 'transactionEventCode',
        'Payment Tracking ID' => 'paymentTrackingId',
        'Item Details' => 'itemDetails',
        'Authorization Review Status' => 'authorizationReviewStatus',
        'Country Code' => 'countryCode',
        'Tip' => 'tip',
        'Discount' => 'discount',
        'Credit Transactional Fee' => 'creditTransactionalFee',
        'Original Invoice ID' => 'originalInvoiceId',
    ];

    public $date;
    public $time;
    public $timezone;
    public $name;
    public $type;
    public $status;
    public $currency;
    public $gross;
    public $fee;
    public $net;
    public $fromEmailAddress;
    public $toEmailAddress;
    public $transactionId;
    public $shippingAddress;
    public $itemTitle;
    public $itemId;
    public $option1Name;
    public $option1Value;
    public $option2Name;
    public $option2Value;
    public $referenceTxnId;
    public $invoiceNumber;
    public $customNumber;
    public $quantity;
    public $receiptId;
    public $addressLine1;
    public $addressLine2DistrictNeighborhood;
    public $townCity;
    public $stateProvinceRegionCountyTerritoryPrefectureRepublic;
    public $zipPostalCode;
    public $country;
    public $contactPhoneNumber;
    public $subject;
    public $note;
    public $transactionEventCode;
    public $paymentTrackingId;
    public $itemDetails;
    public $authorizationReviewStatus;
    public $countryCode;
    public $tip;
    public $discount;
    public $creditTransactionalFee;
    public $originalInvoiceId;

    public function __construct(private array $import_row)
    {
    }

    public function run(): self
    {
        $this->cleanUp();

        foreach ($this->import_row as $key => $value) {

            $prop = $this->key_map[$key] ?? false;

            if ($prop) {

                echo "Setting {$prop} to {$value}".PHP_EOL;
                $this->{$prop} = $value;

            }
        }

        return $this;
    }

    private function cleanUp(): self
    {

        foreach ($this->key_map as $value) {
            echo "Setting {$value} to null".PHP_EOL;
            $this->{$value} = null;
        }

        return $this;
    }

    public function getClient(): array
    {
        $client = [
            'name' => $this->name,
            'contacts' => [$this->getContact()],
            'email' => $this->fromEmailAddress,
        ];

        $client = array_merge($client, $this->returnAddress());
        $client = array_merge($client, $this->returnShippingAddress());

        return $client;
    }

    public function getInvoice(): array
    {
        $item = new InvoiceItem();
        $item->cost = $this->gross ?? 0;
        $item->product_key = $this->itemId ?? '';
        $item->notes = $this->subject ?? $this->itemDetails;
        $item->quantity = 1;

        return [
            'number' => trim($this->invoiceNumber ?? $this->transactionId),
            'date' => str_replace('/', '-', $this->date ?? ''),
            'line_items' => [$item],
            'name' => $this->name ?? '',
            'email' => $this->fromEmailAddress ?? '',
            'transaction_reference' => $this->transactionId ?? '',
        ];
    }

    public function getContact(): array
    {
        $name_parts = explode(" ", $this->name ?? '');

        if (count($name_parts) == 2) {
            $contact['first_name'] = $name_parts[0];
            $contact['last_name'] = $name_parts[1];
        } else {
            $contact['first_name'] = $this->name ?? '';
        }

        $contact['email'] = $this->fromEmailAddress ?? '';
        $contact['phone'] = $this->contactPhoneNumber ?? '';

        return $contact;
    }

    private function returnAddress(): array
    {
        return [
            'address1' => $this->addressLine1 ?? '',
            'address2' => $this->addressLine2DistrictNeighborhood ?? '',
            'city' => $this->townCity ?? '',
            'state' => $this->stateProvinceRegionCountyTerritoryPrefectureRepublic ?? '',
            'country_id' => $this->countryCode ?? '',
            'postal_code' => $this->zipPostalCode ?? '',
        ];
    }

    private function returnShippingAddress(): array
    {
        if (strlen($this->shippingAddress ?? '') < 3) {
            return [];
        }

        $ship_parts = explode(",", $this->shippingAddress);

        if (count($ship_parts) != 7) {
            return [];
        }

        return [
            'shipping_address1' => $ship_parts[2],
            'shipping_address2' => '',
            'shipping_city' => $ship_parts[3],
            'shipping_state' => $ship_parts[4],
            'shipping_postal_code' => $ship_parts[5],
            'shipping_country_id' => $ship_parts[6],
        ];
    }

    public function getType(): string
    {
        return $this->type ?? '';
    }

    public function isInvoiceType(): bool
    {
        return $this->type == 'Website Payment';
    }
}



// $csv = Reader::createFromString($csvFile);
// // $csvdelimiter = self::detectDelimiter($csvfile);
// $csv->setDelimiter(",");
// $stmt = new Statement();
// $data = iterator_to_array($stmt->process($csv));

// $header = $data[0];
// $arr = [];

// foreach($data as $key => $value) {


//     if($key == 0) {
//         continue;
//     }

//     $arr[] = array_combine($header, $value);

// }

// $arr;

// $company =  Company::find(3358);
// $owner = $company->owner();
// $client_repo = new ClientRepository(new ClientContactRepository());
// $invoice_repo = new InvoiceRepository();

// foreach($arr as $pp) {

//     $p = new PayPalBalanceAffecting($pp);
//     $p->run();


//     if(!$p->isInvoiceType()) {
//         continue;
//     }

//     $import_c = $p->getClient();
//     $import_i = $p->getInvoice();


//     $contact = ClientContact::where('company_id', 3358)->where('email', $import_c['email'])->first();


//     if(!$contact) {

//         $cc = ClientFactory::create($company->id, $owner->id);

//         $client = $client_repo->save($import_c, $cc);

//     } else {
//         $client = $contact->client;
//     }

//     $i = InvoiceFactory::create($company->id, $owner->id);
//     $i->client_id = $client->id;
//     $invoice_repo->save($import_i, $i);


// }
