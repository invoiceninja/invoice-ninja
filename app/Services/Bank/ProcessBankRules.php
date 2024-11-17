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

namespace App\Services\Bank;

use App\Factory\ExpenseCategoryFactory;
use App\Factory\ExpenseFactory;
use App\Jobs\Bank\MatchBankTransactions;
use App\Models\BankTransaction;
use App\Models\Client;
use App\Models\ExpenseCategory;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\AbstractService;
use App\Utils\Traits\GeneratesCounter;
use App\Utils\Traits\MakesHash;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class ProcessBankRules extends AbstractService
{
    use GeneratesCounter;
    use MakesHash;

    protected $credit_rules;

    protected $debit_rules;

    protected $categories;

    protected $invoices;

    /**
     * @param \App\Models\BankTransaction $bank_transaction
     */
    public function __construct(public BankTransaction $bank_transaction)
    {
    }

    public function run()
    {
        if ($this->bank_transaction->base_type == 'DEBIT') {
            $this->matchDebit();
        } else {
            $this->matchCredit();
        }
    }

    private function matchCredit()
    {
        $this->invoices = Invoice::query()->where('company_id', $this->bank_transaction->company_id)
                                ->whereIn('status_id', [1,2,3])
                                ->where('is_deleted', 0)
                                ->get();

        $invoice = $this->invoices->first(function ($value, $key) {
            return str_contains($this->bank_transaction->description, $value->number) || str_contains(str_replace("\n", "", $this->bank_transaction->description), $value->number);
        });

        if ($invoice) {
            $this->bank_transaction->invoice_ids = $invoice->hashed_id;
            $this->bank_transaction->status_id = BankTransaction::STATUS_MATCHED;
            $this->bank_transaction->save();
            return;
        }

    }

    // $payment.amount
    // $payment.transaction_reference
    // $payment.custom1
    // $payment.custom2
    // $payment.custom3
    // $payment.custom4
    // $invoice.amount
    // $invoice.number
    // $invoice.po_number
    // $invoice.custom1
    // $invoice.custom2
    // $invoice.custom3
    // $invoice.custom4
    // $client.id_number
    // $client.email
    // $client.custom1
    // $client.custom2
    // $client.custom3
    // $client.custom4
    // private function matchCreditXX() //phpstan-ignore-line
    // {
    //     $match_set = [];

    //     $this->credit_rules = $this->bank_transaction->company->credit_rules();

    //     foreach ($this->credit_rules as $bank_transaction_rule) {
    //         $match_set = [];

    //         if (!is_array($bank_transaction_rule['rules'])) {
    //             continue;
    //         }

    //         $rule_count = count($bank_transaction_rule['rules']);

    //         foreach ($bank_transaction_rule['rules'] as $rule) {

    //             $results = [];
                
    //             $payments = Payment::query()
    //                                 ->withTrashed()
    //                                 ->whereIn('status_id', [1,4])
    //                                 ->where('is_deleted', 0)
    //                                 ->whereNull('transaction_id')
    //                                 ->get();

    //             $invoices = Invoice::query()
    //                     ->withTrashed()
    //                     ->where('company_id', $this->bank_transaction->company_id)
    //                     ->whereIn('status_id', [1,2,3])
    //                     ->where('is_deleted', 0)
    //                     ->get();

    //             match($rule['search_key']) {
    //                 '$payment.amount' => $results = [Payment::class, $this->searchPaymentResource('amount', $rule, $payments)],
    //                 '$payment.transaction_reference' => $results = [Payment::class, $this->searchPaymentResource('transaction_reference', $rule, $payments)],
    //                 '$payment.custom1' => $results = [Payment::class, $this->searchPaymentResource('custom_value1', $rule, $payments)],
    //                 '$payment.custom2' => $results = [Payment::class, $this->searchPaymentResource('custom_value2', $rule, $payments)],
    //                 '$payment.custom3' => $results = [Payment::class, $this->searchPaymentResource('custom_value3', $rule, $payments)],
    //                 '$payment.custom4' => $results = [Payment::class, $this->searchPaymentResource('custom_value4', $rule, $payments)],
    //                 '$invoice.amount' => $results = [Invoice::class, $this->searchInvoiceResource('amount', $rule, $invoices)],
    //                 '$invoice.number' => $results = [Invoice::class, $this->searchInvoiceResource('number', $rule, $invoices)],
    //                 '$invoice.po_number' => $results = [Invoice::class, $this->searchInvoiceResource('po_number', $rule, $invoices)],
    //                 '$invoice.custom1' => $results = [Invoice::class, $this->searchInvoiceResource('custom_value1', $rule, $invoices)],
    //                 '$invoice.custom2' => $results = [Invoice::class, $this->searchInvoiceResource('custom_value2', $rule, $invoices)],
    //                 '$invoice.custom3' => $results = [Invoice::class, $this->searchInvoiceResource('custom_value3', $rule, $invoices)],
    //                 '$invoice.custom4' => $results = [Invoice::class, $this->searchInvoiceResource('custom_value4', $rule, $invoices)],
    //                 '$client.id_number' => $results = [Client::class, $this->searchClientResource('id_number', $rule, $invoices, $payments)],
    //                 '$client.email' => $results = [Client::class, $this->searchClientResource('email', $rule, $invoices, $payments)],
    //                 '$client.custom1' => $results = [Client::class, $this->searchClientResource('custom_value1', $rule, $invoices, $payments)],
    //                 '$client.custom2' => $results = [Client::class, $this->searchClientResource('custom_value2', $rule, $invoices, $payments)],
    //                 '$client.custom3' => $results = [Client::class, $this->searchClientResource('custom_value3', $rule, $invoices, $payments)],
    //                 '$client.custom4' => $results = [Client::class, $this->searchClientResource('custom_value4', $rule, $invoices, $payments)],
    //                 default => $results = [Client::class, [collect([]), Invoice::class]],
    //             };

    //             if($results[0] == 'App\Models\Client') {
    //                 $set = $results[1];
    //                 $result_set = $set[0];
    //                 $entity = $set[1];

    //                 if($result_set->count() > 0) {
    //                     $match_set[] = [$entity, $result_set->pluck('id')];
    //                 }

    //             } elseif($results[1]->count() > 0) {
    //                 $match_set[] = $results;
    //             }
    //         }
            
    //         if (($bank_transaction_rule['matches_on_all'] && $this->checkMatchSetForKey($match_set, $rule_count)) || (!$bank_transaction_rule['matches_on_all'] && count($match_set) > 0)) 
    //         {

    //             $this->bank_transaction->status_id = BankTransaction::STATUS_MATCHED;
    //             $this->bank_transaction->bank_transaction_rule_id = $bank_transaction_rule->id;
    //             $this->bank_transaction->save();

    //             $first_result = reset($match_set);

    //             $invoice_id = false;
    //             $payment_id = false;
    
    //             if($first_result[0] == Payment::class) {
    //                 $payment_id = $first_result[1][0];
    //             }                
    //             elseif($first_result[0] == Invoice::class) {
    //                 $invoice_id = $first_result[1][0];
    //             }

    //             if ($bank_transaction_rule['auto_convert']) {
    //                 (new MatchBankTransactions($this->bank_transaction->company->id, $this->bank_transaction->company->db, [
    //                     'transactions' => [
    //                         [
    //                         'id' => $this->bank_transaction->id,
    //                         'invoice_ids' => $invoice_id ?? '',
    //                         'payment_id' => $payment_id ?? '',
    //                         ],
    //                 ],
    //                 ]))->handle();
    //             }
    //             else {

    //                 if($invoice_id){
    //                     $this->bank_transaction->invoice_ids = $this->encodePrimaryKey($invoice_id);
    //                 }
    //                 elseif($payment_id){
    //                     $this->bank_transaction->payment_id = $payment_id;
    //                 }

    //                 $this->bank_transaction->save();

    //             }

    //         }

    //     }

    // }

    // private function checkMatchSetForKey(array $match_set, $rule_count)
    // {

    // }

    // private function searchInvoiceResource(string $column, array $rule, $invoices)
    // {

    //     return $invoices->when($column != 'amount', function ($q) use ($rule, $column) {
    //         return $q->filter(function ($record) use ($rule, $column) {
    //             return $this->matchStringOperator($this->bank_transaction->description, $record->{$column}, $rule['operator']);
    //         });
    //     })
    //             ->when($column == 'amount', function ($q) use ($rule, $column) {
    //                 return $q->filter(function ($record) use ($rule, $column) {
    //                     return $this->matchNumberOperator($this->bank_transaction->amount, $record->{$column}, $rule['operator']);
    //                 });
    //             })->pluck("id");

    // }

    // private function searchPaymentResource(string $column, array $rule, $payments)
    // {
    //     return $payments->when($column != 'amount', function ($q) use ($rule, $column) {
    //         return $q->filter(function ($record) use ($rule, $column) {
                
    //             $bool =  $this->matchStringOperator($this->bank_transaction->description, $record->{$column},  $rule['operator']);
    //             return $bool;
    //         });
    //     })
    //     ->when($column == 'amount', function ($q) use ($rule, $column) {
    //         return $q->filter(function ($record) use ($rule, $column) {
    //             return $this->matchNumberOperator($this->bank_transaction->amount, $record->{$column}, $rule['operator']);
    //         });
    //     })->pluck("id");

    // }

    // private function searchClientResource(string $column, array $rule, $invoices, $payments)
    // {

    //     $invoice_matches = Client::query()
    //             ->whereIn('id', $invoices->pluck('client_id'))
    //             ->when($column == 'email', function ($q) {
    //                 return $q->whereHas('contacts', function ($qc) {
    //                     $qc->where('email', $this->bank_transaction->description);
    //                 });
    //             })
    //             ->when($column != 'email', function ($q) use ($rule, $column) {

    //                 return $q->cursor()->filter(function ($record) use ($rule, $column) {
    //                     return $this->matchStringOperator($this->bank_transaction->description, $record->{$column}, $rule['operator']);
    //                 });
    //             })->pluck('id');


    //     $intersection = $invoices->whereIn('client_id', $invoice_matches);

    //     if($intersection->count() > 0) {
    //         return [$intersection, Invoice::class];
    //     }

    //     $payments_matches = Client::query()
    //             ->whereIn('id', $payments->pluck('client_id'))
    //             ->when($column == 'email', function ($q) {
    //                 return $q->whereHas('contacts', function ($qc) {
    //                     $qc->where('email', $this->bank_transaction->description);
    //                 });
    //             })
    //             ->when($column != 'email', function ($q) use ($rule, $column) {

    //                 return $q->cursor()->filter(function ($record) use ($rule, $column) {
    //                     return $this->matchStringOperator($this->bank_transaction->description, $record->{$column}, $rule['operator']);
    //                 });
    //             })->pluck('id');

    //     $intersection = $payments->whereIn('client_id', $payments_matches);

    //     if($intersection->count() > 0) {
    //         return [$intersection, Payment::class];
    //     }

    //     return [Client::class, collect([])];

    // }
    
    private function matchDebit()
    {
        $this->debit_rules = $this->bank_transaction->company->debit_rules();

        $this->categories = collect(Cache::get('bank_categories'));

        foreach ($this->debit_rules as $bank_transaction_rule) {
            $matches = 0;

            if (!is_array($bank_transaction_rule['rules'])) {
                continue;
            }

            foreach ($bank_transaction_rule['rules'] as $rule) {
                $rule_count = count($bank_transaction_rule['rules']);

                if ($rule['search_key'] == 'description') {
                    if ($this->matchStringOperator($this->bank_transaction->description, $rule['value'], $rule['operator'])) {
                        $matches++;
                    }
                }

                if ($rule['search_key'] == 'amount') {
                    if ($this->matchNumberOperator($this->bank_transaction->amount, $rule['value'], $rule['operator'])) {
                        $matches++;
                    }
                }

                if (($bank_transaction_rule['matches_on_all'] && ($matches == $rule_count)) || (!$bank_transaction_rule['matches_on_all'] && $matches > 0)) {
                    // $this->bank_transaction->client_id = empty($rule['client_id']) ? null : $rule['client_id'];
                    $this->bank_transaction->vendor_id = $bank_transaction_rule->vendor_id;
                    $this->bank_transaction->ninja_category_id = $bank_transaction_rule->category_id;
                    $this->bank_transaction->status_id = BankTransaction::STATUS_MATCHED;
                    $this->bank_transaction->bank_transaction_rule_id = $bank_transaction_rule->id;
                    $this->bank_transaction->save();

                    if ($bank_transaction_rule['auto_convert']) {
                        $expense = ExpenseFactory::create($this->bank_transaction->company_id, $this->bank_transaction->user_id);
                        $expense->category_id = $bank_transaction_rule->category_id ?: $this->resolveCategory();
                        $expense->amount = $this->bank_transaction->amount;
                        $expense->number = $this->getNextExpenseNumber($expense);
                        $expense->currency_id = $this->bank_transaction->currency_id;
                        $expense->date = Carbon::parse($this->bank_transaction->date);
                        $expense->payment_date = Carbon::parse($this->bank_transaction->date);
                        $expense->transaction_reference = $this->bank_transaction->description;
                        $expense->transaction_id = $this->bank_transaction->id;
                        $expense->vendor_id = $bank_transaction_rule->vendor_id;
                        $expense->invoice_documents = $this->bank_transaction->company->invoice_expense_documents;
                        $expense->should_be_invoiced = $this->bank_transaction->company->mark_expenses_invoiceable;
                        $expense->save();

                        $this->bank_transaction->expense_id = $this->coalesceExpenses($expense->hashed_id);
                        $this->bank_transaction->status_id = BankTransaction::STATUS_CONVERTED;
                        $this->bank_transaction->save();

                        break;
                    }
                }
            }
        }
    }

    private function coalesceExpenses($expense): string
    {

        if (!$this->bank_transaction->expense_id || strlen($this->bank_transaction->expense_id ?? '') < 2) {
            return $expense;
        }

        return collect(explode(",", $this->bank_transaction->expense_id))->push($expense)->implode(",");

    }

    private function resolveCategory()
    {
        $category = $this->categories->firstWhere('highLevelCategoryId', $this->bank_transaction->category_id);

        $ec = ExpenseCategory::query()->where('company_id', $this->bank_transaction->company_id)->where('bank_category_id', $this->bank_transaction->category_id)->first();

        if ($ec) {
            return $ec->id;
        }

        if ($category) {
            $ec = ExpenseCategoryFactory::create($this->bank_transaction->company_id, $this->bank_transaction->user_id);
            $ec->bank_category_id = $this->bank_transaction->category_id;
            $ec->name = $category->highLevelCategoryName;
            $ec->save();

            return $ec->id;
        }
    }

    private function matchNumberOperator($bt_value, $rule_value, $operator): bool
    {
        return match ($operator) {
            '>' => floatval($bt_value) > floatval($rule_value),
            '>=' => floatval($bt_value) >= floatval($rule_value),
            '=' => floatval($bt_value) == floatval($rule_value),
            '<' => floatval($bt_value) < floatval($rule_value),
            '<=' => floatval($bt_value) <= floatval($rule_value),
            default => false,
        };
    }

    private function matchStringOperator($bt_value, $rule_value, $operator): bool
    {
        $bt_value = strtolower(str_replace(" ", "", $bt_value));
        $rule_value = strtolower(str_replace(" ", "", $rule_value));
        $rule_length = iconv_strlen($rule_value);
// nlog($bt_value);
// nlog($rule_value);
// nlog($rule_length);
        return match ($operator) {
            'is' =>  $bt_value == $rule_value,
            'contains' => stripos($bt_value, $rule_value) !== false && strlen($rule_value) > 1,
            'starts_with' => substr($bt_value, 0, $rule_length) == $rule_value && strlen($rule_value) > 1,
            'is_empty' => empty($bt_value),
            default => false,
        };
    }
}



// $payment.amount => "Payment Amount", float
// $payment.transaction_reference => "Payment Transaction Reference", string
// $invoice.amount => "Invoice Amount", float
// $invoice.number => "Invoice Number", string
// $client.id_number => "Client ID Number", string
// $client.email => "Client Email", string
// $invoice.po_number => "Invoice Purchase Order Number", string


// private function matchCredit()
// {
//     $this->invoices = Invoice::query()->where('company_id', $this->bank_transaction->company_id)
//                             ->whereIn('status_id', [1,2,3])
//                             ->where('is_deleted', 0)
//                             ->get();

//     $invoice = $this->invoices->first(function ($value, $key) {
//         return str_contains($this->bank_transaction->description, $value->number) || str_contains(str_replace("\n", "", $this->bank_transaction->description), $value->number);
//     });

//     if ($invoice) {
//         $this->bank_transaction->invoice_ids = $invoice->hashed_id;
//         $this->bank_transaction->status_id = BankTransaction::STATUS_MATCHED;
//         $this->bank_transaction->save();
//         return;
//     }

//     $this->credit_rules = $this->bank_transaction->company->credit_rules();

//     //stub for credit rules
//     foreach ($this->credit_rules as $bank_transaction_rule) {
//     $matches = 0;

//         if (!is_array($bank_transaction_rule['rules'])) {
//             continue;
//         }

//         foreach ($bank_transaction_rule['rules'] as $rule) {
//             $rule_count = count($bank_transaction_rule['rules']);

//             $invoiceNumbers = false;
//             $invoiceNumber = false;
//             $invoiceAmounts = false;
//             $paymentAmounts = false;
//             $paymentReferences = false;
//             $clientIdNumbers = false;
//             $clientEmails = false;
//             $invoicePONumbers = false;

//             if ($rule['search_key'] == '$invoice.number') {

//                 $invoiceNumbers = Invoice::query()->where('company_id', $this->bank_transaction->company_id)
//                                         ->whereIn('status_id', [1,2,3])
//                                         ->where('is_deleted', 0)
//                                         ->get();

//                 $invoiceNumber = $invoiceNumbers->first(function ($value, $key) {
//                     return str_contains($this->bank_transaction->description, $value->number) || str_contains(str_replace("\n", "", $this->bank_transaction->description), $value->number);
//                 });

//                 if($invoiceNumber)
//                     $matches++;

//             }

//             if ($rule['search_key'] == '$invoice.po_number') {

//                 $invoicePONumbers = Invoice::query()->where('company_id', $this->bank_transaction->company_id)
//                                         ->whereIn('status_id', [1,2,3])
//                                         ->where('is_deleted', 0)
//                                         ->where('po_number', $this->bank_transaction->description)
//                                         ->get();

//                 if($invoicePONumbers->count() > 0) {
//                     $matches++;
//                 }

//             }

//             if ($rule['search_key'] == '$invoice.amount') {

//                 $$invoiceAmounts = Invoice::query()->where('company_id', $this->bank_transaction->company_id)
//                                                             ->whereIn('status_id', [1,2,3])
//                                                             ->where('is_deleted', 0)
//                                                             ->where('amount', $rule['operator'], $this->bank_transaction->amount)
//                                                             ->get();

//                 $invoiceAmounts = $this->invoices;

//                 if($invoiceAmounts->count() > 0) {
//                     $matches++;
//                 }

//             }

//             if ($rule['search_key'] == '$payment.amount') {


//                 $paymentAmounts = Payment::query()->where('company_id', $this->bank_transaction->company_id)
//                                         ->whereIn('status_id', [1,4])
//                                         ->where('is_deleted', 0)
//                                         ->whereNull('transaction_id')
//                                         ->where('amount', $rule['operator'], $this->bank_transaction->amount)
//                                         ->get();



//                 if($paymentAmounts->count() > 0) {
//                     $matches++;
//                 }

//             }


//             if ($rule['search_key'] == '$payment.transaction_reference') {

//                 $ref_search = $this->bank_transaction->description;

//                 switch ($rule['operator']) {
//                     case 'is':
//                         $operator = '=';
//                         break;
//                     case 'contains':
//                         $ref_search = "%".$ref_search."%";
//                         $operator = 'LIKE';
//                         break;

//                     default:
//                         $operator = '=';
//                         break;
//                 }

//                 $paymentReferences = Payment::query()->where('company_id', $this->bank_transaction->company_id)
//                                         ->whereIn('status_id', [1,4])
//                                         ->where('is_deleted', 0)
//                                         ->whereNull('transaction_id')
//                                         ->where('transaction_reference', $operator, $ref_search)
//                                         ->get();



//                 if($paymentReferences->count() > 0) {
//                     $matches++;
//                 }

//             }

//             if ($rule['search_key'] == '$client.id_number') {

//                 $ref_search = $this->bank_transaction->description;

//                 switch ($rule['operator']) {
//                     case 'is':
//                         $operator = '=';
//                         break;
//                     case 'contains':
//                         $ref_search = "%".$ref_search."%";
//                         $operator = 'LIKE';
//                         break;

//                     default:
//                         $operator = '=';
//                         break;
//                 }

//                 $clientIdNumbers = Client::query()->where('company_id', $this->bank_transaction->company_id)
//                                     ->where('id_number', $operator, $ref_search)
//                                     ->get();

//                 if($clientIdNumbers->count() > 0) {
//                     $matches++;
//                 }

//             }


//             if ($rule['search_key'] == '$client.email') {

//                 $clientEmails = Client::query()
//                                     ->where('company_id', $this->bank_transaction->company_id)
//                                     ->whereHas('contacts', function ($q){
//                                         $q->where('email', $this->bank_transaction->description);
//                                     })
//                                     ->get();


//                 if($clientEmails->count() > 0) {
//                     $matches++;
//                 }

//                 if (($bank_transaction_rule['matches_on_all'] && ($matches == $rule_count)) || (!$bank_transaction_rule['matches_on_all'] && $matches > 0)) {

//                     //determine which combination has succeeded, ie link a payment / or / invoice
//                     $invoice_ids = null;
//                     $payment_id = null;

//                     if($invoiceNumber){
//                         $invoice_ids = $invoiceNumber->hashed_id;
//                     }

//                     if($invoicePONumbers && strlen($invoice_ids ?? '') == 0){

//                         if($clientEmails){ // @phpstan-ignore-line

//                             $invoice_ids = $this->matchInvoiceAndClient($invoicePONumbers, $clientEmails);

//                         }

//                         if($clientIdNumbers && strlen($invoice_ids ?? '') == 0)
//                         {

//                             $invoice_ids = $this->matchInvoiceAndClient($invoicePONumbers, $clientIdNumbers);

//                         }

//                         if(strlen($invoice_ids ?? '') == 0)
//                         {
//                             $invoice_ids = $invoicePONumbers->first()->hashed_id;
//                         }

//                     }


//                     if($invoiceAmounts && strlen($invoice_ids ?? '') == 0) {

//                         if($clientEmails) {// @phpstan-ignore-line

//                             $invoice_ids = $this->matchInvoiceAndClient($invoiceAmounts, $clientEmails);

//                         }

//                         if($clientIdNumbers && strlen($invoice_ids ?? '') == 0) {

//                             $invoice_ids = $this->matchInvoiceAndClient($invoiceAmounts, $clientIdNumbers);

//                         }

//                         if(strlen($invoice_ids ?? '') == 0) {
//                             $invoice_ids = $invoiceAmounts->first()->hashed_id;
//                         }

//                     }


//                     if($paymentAmounts && strlen($invoice_ids ?? '') == 0 && is_null($payment_id)) {

//                         if($clientEmails) {// @phpstan-ignore-line

//                             $payment_id = $this->matchPaymentAndClient($paymentAmounts, $clientEmails);

//                         }

//                         if($clientIdNumbers && is_null($payment_id)) {


//                             $payment_id = $this->matchPaymentAndClient($paymentAmounts, $clientEmails);

//                         }

//                         if(is_null($payment_id)) {
//                             $payment_id = $paymentAmounts->first()->id;
//                         }

//                     }

//                     if(strlen($invoice_ids ?? '') > 1 || is_int($payment_id))
//                     {

//                         $this->bank_transaction->payment_id = $payment_id;
//                         $this->bank_transaction->invoice_ids = $invoice_ids;
//                         $this->bank_transaction->status_id = BankTransaction::STATUS_MATCHED;
//                         $this->bank_transaction->bank_transaction_rule_id = $bank_transaction_rule->id;
//                         $this->bank_transaction->save();

//                     }

//                 }

//             }

//         }

//     }

// }


// private function matchPaymentAndClient($payments, $clients): ?int
// {
//     /** @var \Illuminate\Support\Collection<Payment> $payments */
//     foreach($payments as $payment) {
//         foreach($clients as $client) {

//             if($payment->client_id == $client->id) {
//                 return $payment->id;

//             }
//         }
//     }

//     return null;
// }

// private function matchInvoiceAndClient($invoices, $clients): ?Invoice
// {
//     /** @var \Illuminate\Support\Collection<Invoice> $invoices */
//     foreach($invoices as $invoice) {
//         foreach($clients as $client) {

//             if($invoice->client_id == $client->id) {
//                 return $invoice->hashed_id;

//             }
//         }
//     }

//     return null;
// }
