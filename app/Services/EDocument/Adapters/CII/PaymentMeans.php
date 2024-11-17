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

namespace App\Services\EDocument\Adapters\CII;

use App\Services\EDocument\Interfaces\PaymentMeansInterface;

class PaymentMeans implements PaymentMeansInterface
{

    public static array $payment_means_codelist = [
        '1' => 'Instrument not defined',
        '2' => 'Automated clearing house credit',
        '3' => 'Automated clearing house debit',
        '4' => 'ACH demand debit reversal',
        '5' => 'ACH demand credit reversal',
        '6' => 'ACH demand credit',
        '7' => 'ACH demand debit',
        '8' => 'Hold',
        '9' => 'National or regional clearing',
        '10' => 'In cash',
        '11' => 'ACH savings credit reversal',
        '12' => 'ACH savings debit reversal',
        '13' => 'ACH savings credit',
        '14' => 'ACH savings debit',
        '15' => 'Bookentry credit',
        '16' => 'Bookentry debit',
        '17' => 'ACH demand cash concentration/disbursement (CCD) credit',
        '18' => 'ACH demand cash concentration/disbursement (CCD) debit',
        '19' => 'ACH demand corporate trade payment (CTP) credit',
        '20' => 'Cheque',
        '21' => 'Banker\'s draft',
        '22' => 'Certified banker\'s draft',
        '23' => 'Bank cheque (issued by a banking or similar establishment)',
        '24' => 'Bill of exchange awaiting acceptance',
        '25' => 'Certified cheque',
        '26' => 'Local cheque',
        '27' => 'ACH demand corporate trade payment (CTP) debit',
        '28' => 'ACH demand corporate trade exchange (CTX) credit',
        '29' => 'ACH demand corporate trade exchange (CTX) debit',
        '30' => 'Credit transfer',
        '31' => 'Debit transfer',
        '32' => 'ACH demand cash concentration/disbursement plus (CCD+)',
        '33' => 'ACH demand cash concentration/disbursement plus (CCD+)',
        '34' => 'ACH prearranged payment and deposit (PPD)',
        '35' => 'ACH savings cash concentration/disbursement (CCD) credit',
        '36' => 'ACH savings cash concentration/disbursement (CCD) debit',
        '37' => 'ACH savings corporate trade payment (CTP) credit',
        '38' => 'ACH savings corporate trade payment (CTP) debit',
        '39' => 'ACH savings corporate trade exchange (CTX) credit',
        '40' => 'ACH savings corporate trade exchange (CTX) debit',
        '41' => 'ACH savings cash concentration/disbursement plus (CCD+)',
        '42' => 'Payment to bank account',
        '43' => 'ACH savings cash concentration/disbursement plus (CCD+)',
        '44' => 'Accepted bill of exchange',
        '45' => 'Referenced home-banking credit transfer',
        '46' => 'Interbank debit transfer',
        '47' => 'Home-banking debit transfer',
        '48' => 'Bank card',
        '49' => 'Direct debit',
        '50' => 'Payment by postgiro',
        '51' => 'FR, norme 6 97-Telereglement CFONB (French Organisation for',
        '52' => 'Urgent commercial payment',
        '53' => 'Urgent Treasury Payment',
        '54' => 'Credit card',
        '55' => 'Debit card',
        '56' => 'Bankgiro',
        '57' => 'Standing agreement',
        '58' => 'SEPA credit transfer',
        '59' => 'SEPA direct debit',
        '60' => 'Promissory note',
        '61' => 'Promissory note signed by the debtor',
        '62' => 'Promissory note signed by the debtor and endorsed by a bank',
        '63' => 'Promissory note signed by the debtor and endorsed by a',
        '64' => 'Promissory note signed by a bank',
        '65' => 'Promissory note signed by a bank and endorsed by another',
        '66' => 'Promissory note signed by a third party',
        '67' => 'Promissory note signed by a third party and endorsed by a',
        '68' => 'Online payment service',
        '69' => 'Transfer Advice',
        '70' => 'Bill drawn by the creditor on the debtor',
        '74' => 'Bill drawn by the creditor on a bank',
        '75' => 'Bill drawn by the creditor, endorsed by another bank',
        '76' => 'Bill drawn by the creditor on a bank and endorsed by a',
        '77' => 'Bill drawn by the creditor on a third party',
        '78' => 'Bill drawn by creditor on third party, accepted and',
        '91' => 'Not transferable banker\'s draft',
        '92' => 'Not transferable local cheque',
        '93' => 'Reference giro',
        '94' => 'Urgent giro',
        '95' => 'Free format giro',
        '96' => 'Requested method for payment was not used',
        '97' => 'Clearing between partners',
        'ZZZ' => 'Mutually defined',
    ];

    public static array $payment_means_requirements = [
        '1' => [], // Instrument not defined
        '2' => [
            'PayeeFinancialAccount.ID',
            'PayeeFinancialAccount.FinancialInstitutionBranch.FinancialInstitution.ID'
        ], // ACH credit
        '3' => [
            'PaymentMandate.PayerFinancialAccount.ID',
            'PayeeFinancialAccount.FinancialInstitutionBranch.FinancialInstitution.ID'
        ], // ACH debit
        '4' => [
            'PaymentMandate.PayerFinancialAccount.ID',
            'PayeeFinancialAccount.FinancialInstitutionBranch.FinancialInstitution.ID'
        ], // ACH demand debit reversal
        '5' => [
            'PayeeFinancialAccount.ID',
            'PayeeFinancialAccount.FinancialInstitutionBranch.FinancialInstitution.ID'
        ], // ACH demand credit reversal
        '6' => [
            'PayeeFinancialAccount.ID',
            'PayeeFinancialAccount.FinancialInstitutionBranch.FinancialInstitution.ID'
        ], // ACH demand credit
        '7' => [
            'PaymentMandate.PayerFinancialAccount.ID',
            'PayeeFinancialAccount.FinancialInstitutionBranch.FinancialInstitution.ID'
        ], // ACH demand debit
        '8' => [], // Hold
        '9' => [
            'PayeeFinancialAccount.ID',
            'PayeeFinancialAccount.FinancialInstitutionBranch.FinancialInstitution.ID'
        ], // National or regional clearing
        '10' => [], // In cash
        '11' => [
            'PayeeFinancialAccount.ID',
            'PayeeFinancialAccount.FinancialInstitutionBranch.FinancialInstitution.ID'
        ], // ACH savings credit reversal
        '12' => [
            'PaymentMandate.PayerFinancialAccount.ID',
            'PayeeFinancialAccount.FinancialInstitutionBranch.FinancialInstitution.ID'
        ], // ACH savings debit reversal
        '13' => [
            'PayeeFinancialAccount.ID',
            'PayeeFinancialAccount.FinancialInstitutionBranch.FinancialInstitution.ID'
        ], // ACH savings credit
        '14' => [
            'PaymentMandate.PayerFinancialAccount.ID',
            'PayeeFinancialAccount.FinancialInstitutionBranch.FinancialInstitution.ID'
        ], // ACH savings debit
        '15' => [
            'PayeeFinancialAccount.Name',
            'PayeeFinancialAccount.ProprietaryID'
        ], // Bookentry credit
        '16' => [
            'PayeeFinancialAccount.Name',
            'PayeeFinancialAccount.ProprietaryID'
        ], // Bookentry debit
        '17' => [
            'PayeeFinancialAccount.ID',
            'PayeeFinancialAccount.FinancialInstitutionBranch.FinancialInstitution.ID'
        ], // ACH demand CCD credit
        '18' => [
            'PaymentMandate.PayerFinancialAccount.ID',
            'PayeeFinancialAccount.FinancialInstitutionBranch.FinancialInstitution.ID'
        ], // ACH demand CCD debit
        '19' => [
            'PayeeFinancialAccount.ID',
            'PayeeFinancialAccount.FinancialInstitutionBranch.FinancialInstitution.ID'
        ], // ACH demand CTP credit
        '20' => [], // Cheque
        '21' => [], // Banker's draft
        '22' => [], // Certified banker's draft
        '23' => [], // Bank cheque
        '24' => [], // Bill of exchange awaiting acceptance
        '25' => [], // Certified cheque
        '26' => [], // Local cheque
        '27' => [
            'PaymentMandate.PayerFinancialAccount.ID',
            'PayeeFinancialAccount.FinancialInstitutionBranch.FinancialInstitution.ID'
        ], // ACH demand CTP debit
        '28' => [
            'PayeeFinancialAccount.ID',
            'PayeeFinancialAccount.FinancialInstitutionBranch.FinancialInstitution.ID'
        ], // ACH demand CTX credit
        '29' => [
            'PaymentMandate.PayerFinancialAccount.ID',
            'PayeeFinancialAccount.FinancialInstitutionBranch.FinancialInstitution.ID'
        ], // ACH demand CTX debit
        '30' => [
            'PayeeFinancialAccount.ID',
            'PayeeFinancialAccount.FinancialInstitutionBranch.FinancialInstitution.ID'
        ], // Credit transfer
        '31' => [
            'PayeeFinancialAccount.ID',
            'PayeeFinancialAccount.FinancialInstitutionBranch.FinancialInstitution.ID'
        ], // Debit transfer
        '32' => [
            'PayeeFinancialAccount.ID',
            'PayeeFinancialAccount.FinancialInstitutionBranch.FinancialInstitution.ID'
        ], // ACH demand CCD+ credit
        '33' => [
            'PaymentMandate.PayerFinancialAccount.ID',
            'PayeeFinancialAccount.FinancialInstitutionBranch.FinancialInstitution.ID'
        ], // ACH demand CCD+ debit
        '34' => [
            'PayeeFinancialAccount.ID',
            'PayeeFinancialAccount.FinancialInstitutionBranch.FinancialInstitution.ID'
        ], // ACH PPD
        '35' => [
            'PayeeFinancialAccount.ID',
            'PayeeFinancialAccount.FinancialInstitutionBranch.FinancialInstitution.ID'
        ], // ACH savings CCD credit
        '36' => [
            'PaymentMandate.PayerFinancialAccount.ID',
            'PayeeFinancialAccount.FinancialInstitutionBranch.FinancialInstitution.ID'
        ], // ACH savings CCD debit
        '37' => [
            'PayeeFinancialAccount.ID',
            'PayeeFinancialAccount.FinancialInstitutionBranch.FinancialInstitution.ID'
        ], // ACH savings CTP credit
        '38' => [
            'PaymentMandate.PayerFinancialAccount.ID',
            'PayeeFinancialAccount.FinancialInstitutionBranch.FinancialInstitution.ID'
        ], // ACH savings CTP debit
        '39' => [
            'PayeeFinancialAccount.ID',
            'PayeeFinancialAccount.FinancialInstitutionBranch.FinancialInstitution.ID'
        ], // ACH savings CTX credit
        '40' => [
            'PaymentMandate.PayerFinancialAccount.ID',
            'PayeeFinancialAccount.FinancialInstitutionBranch.FinancialInstitution.ID'
        ], // ACH savings CTX debit
        '41' => [
            'PayeeFinancialAccount.ID',
            'PayeeFinancialAccount.FinancialInstitutionBranch.FinancialInstitution.ID'
        ], // ACH savings CCD+ credit
        '42' => [
            'PayeeFinancialAccount.ID',
            'PayeeFinancialAccount.FinancialInstitutionBranch.FinancialInstitution.ID'
        ], // Payment to bank account
        '43' => [
            'PaymentMandate.PayerFinancialAccount.ID',
            'PayeeFinancialAccount.FinancialInstitutionBranch.FinancialInstitution.ID'
        ], // ACH savings CCD+ debit
        '44' => [], // Accepted bill of exchange
        '45' => [
            'PayeeFinancialAccount.ID',
            'PayeeFinancialAccount.FinancialInstitutionBranch.FinancialInstitution.ID'
        ], // Referenced home-banking credit transfer
        '46' => [
            'PayeeFinancialAccount.ID',
            'PayeeFinancialAccount.FinancialInstitutionBranch.FinancialInstitution.ID'
        ], // Interbank debit transfer
        '47' => [
            'PayeeFinancialAccount.ID',
            'PayeeFinancialAccount.FinancialInstitutionBranch.FinancialInstitution.ID'
        ], // Home-banking debit transfer
        '48' => [
            'PayeeFinancialAccount.NetworkID',
            'PayeeFinancialAccount.ID'
        ], // Bank card
        '49' => [
            'PaymentMandate.PayerFinancialAccount.ID',
            'PayeeFinancialAccount.FinancialInstitutionBranch.FinancialInstitution.ID'
        ], // Direct debit
        '50' => [
            'PayeeFinancialAccount.Name'
        ], // Payment by postgiro
        '51' => [
            'PayeeFinancialAccount.ID',
            'PayeeFinancialAccount.FinancialInstitutionBranch.FinancialInstitution.ID'
        ], // FR, norme 6 97-Telereglement CFONB
        '52' => [
            'PayeeFinancialAccount.ID',
            'PayeeFinancialAccount.FinancialInstitutionBranch.FinancialInstitution.ID'
        ], // Urgent commercial payment
        '53' => [
            'PayeeFinancialAccount.ID',
            'PayeeFinancialAccount.FinancialInstitutionBranch.FinancialInstitution.ID'
        ], // Urgent Treasury Payment
        '54' => [
            'CardAccount.NetworkID',
            'CardAccount.PrimaryAccountNumberID',
            'CardAccount.HolderName'
        ], // Credit card
        '55' => [
            'CardAccount.NetworkID',
            'CardAccount.PrimaryAccountNumberID',
            'CardAccount.HolderName'
        ], // Debit card
        '56' => [
            'PayeeFinancialAccount.Name'
        ], // Bankgiro
        '57' => [
            'PayeeFinancialAccount.ID',
            'PayeeFinancialAccount.FinancialInstitutionBranch.FinancialInstitution.ID'
        ], // Standing agreement
        '58' => [
            'PayeeFinancialAccount.ID',
            'PayeeFinancialAccount.FinancialInstitutionBranch.FinancialInstitution.ID'
        ], // SEPA credit transfer
        '59' => [
            'PaymentMandate.PayerFinancialAccount.ID',
            'PayeeFinancialAccount.FinancialInstitutionBranch.FinancialInstitution.ID'
        ], // SEPA direct debit
        '60' => [], // Promissory note
        '61' => [], // Promissory note signed by debtor
        '62' => [
            'PayeeFinancialAccount.FinancialInstitutionBranch.FinancialInstitution.ID'
        ], // Promissory note signed by debtor and endorsed by bank
        '63' => [], // Promissory note signed by debtor and endorsed
        '64' => [
            'PayeeFinancialAccount.FinancialInstitutionBranch.FinancialInstitution.ID'
        ], // Promissory note signed by bank
        '65' => [
            'PayeeFinancialAccount.FinancialInstitutionBranch.FinancialInstitution.ID'
        ], // Promissory note signed by bank and endorsed by another
        '66' => [], // Promissory note signed by third party
        '67' => [
            'PayeeFinancialAccount.FinancialInstitutionBranch.FinancialInstitution.ID'
        ], // Promissory note signed by third party and endorsed by bank
        '68' => [
            'PayeeFinancialAccount.ID'
        ], // Online payment service
        '69' => [
            'PayeeFinancialAccount.ID',
            'PayeeFinancialAccount.FinancialInstitutionBranch.FinancialInstitution.ID'
        ], // Transfer Advice
        '70' => [], // Bill drawn by creditor on debtor
        '74' => [
            'PayeeFinancialAccount.FinancialInstitutionBranch.FinancialInstitution.ID'
        ], // Bill drawn by creditor on bank
        '75' => [
            'PayeeFinancialAccount.FinancialInstitutionBranch.FinancialInstitution.ID'
        ], // Bill drawn by creditor, endorsed by bank
        '76' => [
            'PayeeFinancialAccount.FinancialInstitutionBranch.FinancialInstitution.ID'
        ], // Bill drawn by creditor on bank and endorsed
        '77' => [], // Bill drawn by creditor on third party
        '78' => [], // Bill drawn by creditor on third party, accepted
        '91' => [], // Not transferable banker's draft
        '92' => [], // Not transferable local cheque
        '93' => [
            'PayeeFinancialAccount.ID',
            'PayeeFinancialAccount.FinancialInstitutionBranch.FinancialInstitution.ID'
        ], // Reference giro
        '94' => [
            'PayeeFinancialAccount.ID',
            'PayeeFinancialAccount.FinancialInstitutionBranch.FinancialInstitution.ID'
        ], // Urgent giro
        '95' => [
            'PayeeFinancialAccount.ID',
            'PayeeFinancialAccount.FinancialInstitutionBranch.FinancialInstitution.ID'
        ], // Free format giro
        '96' => [], // Requested method not used
        '97' => [
            'PayeeFinancialAccount.Name'
        ], // Clearing between partners
        'ZZZ' => [], // Mutually defined
    ];


   public static array $payment_means_requirements_codes = [
    '1' => [], // Instrument not defined
    '2' => ['iban', 'bic_swift'], // ACH credit
    '3' => ['payer_bank_account', 'iban', 'bic_swift'], // ACH debit
    '4' => ['payer_bank_account', 'iban', 'bic_swift'], // ACH demand debit reversal
    '5' => ['iban', 'bic_swift'], // ACH demand credit reversal
    '6' => ['iban', 'bic_swift'], // ACH demand credit
    '7' => ['payer_bank_account', 'iban', 'bic_swift'], // ACH demand debit
    '8' => [], // Hold
    '9' => ['iban', 'bic_swift'], // National or regional clearing
    '10' => [], // In cash
    '11' => ['iban', 'bic_swift'], // ACH savings credit reversal
    '12' => ['payer_bank_account', 'iban', 'bic_swift'], // ACH savings debit reversal
    '13' => ['iban', 'bic_swift'], // ACH savings credit
    '14' => ['payer_bank_account', 'iban', 'bic_swift'], // ACH savings debit
    '15' => ['account_holder', 'bsb_sort'], // Bookentry credit
    '16' => ['account_holder', 'bsb_sort'], // Bookentry debit
    '17' => ['iban', 'bic_swift'], // ACH demand CCD credit
    '18' => ['payer_bank_account', 'iban', 'bic_swift'], // ACH demand CCD debit
    '19' => ['iban', 'bic_swift'], // ACH demand CTP credit
    '20' => [], // Cheque
    '21' => [], // Banker's draft
    '22' => [], // Certified banker's draft
    '23' => [], // Bank cheque
    '24' => [], // Bill of exchange awaiting acceptance
    '25' => [], // Certified cheque
    '26' => [], // Local cheque
    '27' => ['payer_bank_account', 'iban', 'bic_swift'], // ACH demand CTP debit
    '28' => ['iban', 'bic_swift'], // ACH demand CTX credit
    '29' => ['payer_bank_account', 'iban', 'bic_swift'], // ACH demand CTX debit
    '30' => ['iban', 'bic_swift','account_holder'], // Credit transfer
    '31' => ['iban', 'bic_swift','account_holder'], // Debit transfer
    '32' => ['iban', 'bic_swift','account_holder'], // ACH demand CCD+ credit
    '33' => ['payer_bank_account', 'iban', 'bic_swift'], // ACH demand CCD+ debit
    '34' => ['iban', 'bic_swift','account_holder'], // ACH PPD
    '35' => ['iban', 'bic_swift','account_holder'], // ACH savings CCD credit
    '36' => ['payer_bank_account', 'iban', 'bic_swift'], // ACH savings CCD debit
    '37' => ['iban', 'bic_swift','account_holder'], // ACH savings CTP credit
    '38' => ['payer_bank_account', 'iban', 'bic_swift'], // ACH savings CTP debit
    '39' => ['iban', 'bic_swift','account_holder'], // ACH savings CTX credit
    '40' => ['payer_bank_account', 'iban', 'bic_swift'], // ACH savings CTX debit
    '41' => ['iban', 'bic_swift','account_holder'], // ACH savings CCD+ credit
    '42' => ['iban', 'bic_swift','account_holder'], // Payment to bank account
    '43' => ['payer_bank_account', 'iban', 'bic_swift'], // ACH savings CCD+ debit
    '44' => [], // Accepted bill of exchange
    '45' => ['iban', 'bic_swift'], // Referenced home-banking credit transfer
    '46' => ['iban', 'bic_swift'], // Interbank debit transfer
    '47' => ['iban', 'bic_swift'], // Home-banking debit transfer
    '48' => ['card_type', 'card_number'], // Bank card
    '49' => ['payer_bank_account', 'iban', 'bic_swift'], // Direct debit
    '50' => ['account_holder'], // Payment by postgiro
    '51' => ['iban', 'bic_swift'], // FR, norme 6 97-Telereglement CFONB
    '52' => ['iban', 'bic_swift'], // Urgent commercial payment
    '53' => ['iban', 'bic_swift'], // Urgent Treasury Payment
    '54' => ['card_type', 'card_number', 'card_holder'], // Credit card
    '55' => ['card_type', 'card_number', 'card_holder'], // Debit card
    '56' => ['account_holder'], // Bankgiro
    '57' => ['iban', 'bic_swift'], // Standing agreement
    '58' => ['iban', 'bic_swift'], // SEPA credit transfer
    '59' => ['payer_bank_account', 'iban', 'bic_swift'], // SEPA direct debit
    '60' => [], // Promissory note
    '61' => [], // Promissory note signed by debtor
    '62' => ['bic_swift'], // Promissory note signed by debtor and endorsed by bank
    '63' => [], // Promissory note signed by debtor and endorsed
    '64' => ['bic_swift'], // Promissory note signed by bank
    '65' => ['bic_swift'], // Promissory note signed by bank and endorsed by another
    '66' => [], // Promissory note signed by third party
    '67' => ['bic_swift'], // Promissory note signed by third party and endorsed by bank
    '68' => ['iban'], // Online payment service
    '69' => ['iban', 'bic_swift'], // Transfer Advice
    '70' => [], // Bill drawn by creditor on debtor
    '74' => ['bic_swift'], // Bill drawn by creditor on bank
    '75' => ['bic_swift'], // Bill drawn by creditor, endorsed by bank
    '76' => ['bic_swift'], // Bill drawn by creditor on bank and endorsed
    '77' => [], // Bill drawn by creditor on third party
    '78' => [], // Bill drawn by creditor on third party, accepted
    '91' => [], // Not transferable banker's draft
    '92' => [], // Not transferable local cheque
    '93' => ['iban', 'bic_swift'], // Reference giro
    '94' => ['iban', 'bic_swift'], // Urgent giro
    '95' => ['iban', 'bic_swift'], // Free format giro
    '96' => [], // Requested method not used
    '97' => ['account_holder'], // Clearing between partners
    'ZZZ' => [], // Mutually defined
];

    public static array $payment_means_field_map = [
        'iban' => 'PayeeFinancialAccount.ID',
        'bic_swift' => 'PayeeFinancialAccount.FinancialInstitutionBranch.FinancialInstitution.ID',
        'payer_bank_account' => 'PaymentMandate.PayerFinancialAccount.ID',
        'account_holder' => 'PayeeFinancialAccount.Name',
        'bsb_sort' => 'PayeeFinancialAccount.ProprietaryID',
        'card_type' => 'CardAccount.NetworkID',
        'card_number' => 'CardAccount.PrimaryAccountNumberID',
        'card_holder' => 'CardAccount.HolderName'
    ];


    public string $code = '1';

    public ?string $information = null;

    public ?string $card_type = null;

    public ?string $cardId = null;

    public ?string $cardholder_name = null;

    public ?string $buyerIban = null;

    public ?string $iban = null;

    public ?string $account_name = null;

    public ?string $payeePropId = null;

    public ?string $bic = null;

    public function __construct(mixed $existing_payment_means = null)
    {
        if($existing_payment_means){
            
            $properties = get_object_vars($this);
            foreach ($properties as $property => $value) {
                if (property_exists($existing_payment_means, $property)) {
                    $this->$property = $existing_payment_means->$property;
                }
            }

        }
    }
    //requires an object which looks like this
    // @param string      $typecode         __BT-81, From BASIC WL__ The expected or used means of payment, expressed as a code. The entries from the UNTDID 4461 code list must be used. A distinction should be made between SEPA and non-SEPA payments as well 
    // as between credit payments, direct debits, card payments and other means of payment 
    // In particular, the following codes can be used:
    //  *                                   //    10: cash - 
                                            //    20: check - 
                                            //    30: transfer - 
                                            //    42: Payment to bank account - 
                                            //    48: Card payment - 
                                            //    49: direct debit - 
                                            //    57: Standing order - 
                                            //    58: SEPA Credit Transfer - 
                                            //    59: SEPA Direct Debit - 
                                            //    97: Report
    //  *
    //  * @param  string|null $information      __BT-82, From EN 16931__ The expected or used means of payment expressed in text form, e.g. cash, bank transfer, direct debit, credit card, etc.
    //  * @param  string|null $card_type         __BT-, From __ The type of the card
    //  * @param  string|null $cardId           __BT-84, From BASIC WL__ The primary account number (PAN) to which the card used for payment belongs. In accordance with card payment security standards, an invoice should never contain a full payment card master account number. 
    //The following specification of the PCI Security Standards Council currently applies: The first 6 and last 4 digits at most are to be displayed
    //  * @param  string|null $cardholder_name   __BT-, From __ Name of the payment card holder
    //  * @param  string|null $buyerIban        __BT-91, From BASIC WL__ Direct debit: ID of the account to be debited
    //  * @param  string|null $iban        __BT-, From __ Transfer: A unique identifier for the financial account held with a payment service provider to which the payment should be made, e.g. Use an IBAN (in the case of a SEPA payment) for a national 
    //ProprietaryID account number
    //  * @param  string|null $payeeAccountName __BT-, From __ The name of the payment account held with a payment service provider to which the payment should be made. Information only required if different from the name of the payee / seller
    //  * @param  string|null $payeePropId      __BT-, From __ National account number (not for SEPA)
    //  * @param  string|null $bic         __BT-, From __ Seller's banking institution, An identifier for the payment service provider with whom the payment account is managed, such as the BIC or a national bank code, if required. No identification scheme is to be used.
    //  *
    public function run()
    {

        $TradeSettlementFinancialCardType = new \horstoeko\zugferd\entities\extended\ram\TradeSettlementFinancialCardType();
        $TradeSettlementFinancialCardType->setCardholderName($this->cardholder_name)
                                         ->setID(new \horstoeko\zugferd\entities\extended\udt\IDType($this->cardId));

        $DebtorFinancialAccountType = new \horstoeko\zugferd\entities\extended\ram\DebtorFinancialAccountType();
        $DebtorFinancialAccountType->setIBANID(new \horstoeko\zugferd\entities\extended\udt\IDType($this->buyerIban));

        $CreditorFinancialAccountType = new \horstoeko\zugferd\entities\extended\ram\CreditorFinancialAccountType();
        $CreditorFinancialAccountType->setAccountName($this->account_name)
                                     ->setProprietaryID(new \horstoeko\zugferd\entities\extended\udt\IDType($this->payeePropId))
                                     ->setIBANID(new \horstoeko\zugferd\entities\extended\udt\IDType($this->iban));

        $CreditorFinancialInstitutionType = new \horstoeko\zugferd\entities\extended\ram\CreditorFinancialInstitutionType();
        $CreditorFinancialInstitutionType->setBICID(new \horstoeko\zugferd\entities\extended\udt\IDType($this->bic));
        
        $TradeSettlementPaymentMeansType = new \horstoeko\zugferd\entities\extended\ram\TradeSettlementPaymentMeansType();
        $TradeSettlementPaymentMeansType->setTypeCode($this->code)->setInformation($this->information);
        $TradeSettlementPaymentMeansType->setPayeePartyCreditorFinancialAccount($CreditorFinancialAccountType);
        $TradeSettlementPaymentMeansType->setPayerPartyDebtorFinancialAccount($DebtorFinancialAccountType);
        $TradeSettlementPaymentMeansType->setApplicableTradeSettlementFinancialCard($TradeSettlementFinancialCardType);
        $TradeSettlementPaymentMeansType->setPayeeSpecifiedCreditorFinancialInstitution($CreditorFinancialInstitutionType);

        $HeaderTradeSettlementType = new \horstoeko\zugferd\entities\extended\ram\HeaderTradeSettlementType();
        $HeaderTradeSettlementType->addToSpecifiedTradeSettlementPaymentMeans($TradeSettlementPaymentMeansType);

        $SupplyChainTradeTransactionType = new \horstoeko\zugferd\entities\extended\ram\SupplyChainTradeTransactionType();
        $SupplyChainTradeTransactionType->setApplicableHeaderTradeSettlement($HeaderTradeSettlementType);

    }

    public static function getPaymentMeansCodelist()
    {
        return array_keys(self::$payment_means_requirements_codes);
    }
}
