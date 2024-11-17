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

namespace App\Utils;

use Illuminate\Support\Str;

/**
 * Statics.
 */
class Statics
{
    /**
     * Date format types.
     * @var array
     */
    public static $date = [
        ['format' => 'd/M/Y', 'picker_format' => 'dd/M/yyyy', 'format_moment' => 'DD/MMM/YYYY', 'format_dart' => 'dd/MMM/yyyy'],
        ['format' => 'd-M-Y', 'picker_format' => 'dd-M-yyyy', 'format_moment' => 'DD-MMM-YYYY', 'format_dart' => 'dd-MMM-yyyy'],
        ['format' => 'd/F/Y', 'picker_format' => 'dd/MM/yyyy', 'format_moment' => 'DD/MMMM/YYYY', 'format_dart' => 'dd/MMMM/yyyy'],
        ['format' => 'd-F-Y', 'picker_format' => 'dd-MM-yyyy', 'format_moment' => 'DD-MMMM-YYYY', 'format_dart' => 'dd-MMMM-yyyy'],
        ['format' => 'M j, Y', 'picker_format' => 'M d, yyyy', 'format_moment' => 'MMM D, YYYY', 'format_dart' => 'MMM d, yyyy'],
        ['format' => 'F j, Y', 'picker_format' => 'MM d, yyyy', 'format_moment' => 'MMMM D, YYYY', 'format_dart' => 'MMMM d, yyyy'],
        ['format' => 'D M j, Y', 'picker_format' => 'D MM d, yyyy', 'format_moment' => 'ddd MMM Do, YYYY', 'format_dart' => 'EEE MMM d, yyyy'],
        ['format' => 'Y-m-d', 'picker_format' => 'yyyy-mm-dd', 'format_moment' => 'YYYY-MM-DD', 'format_dart' => 'yyyy-MM-dd'],
        ['format' => 'd-m-Y', 'picker_format' => 'dd-mm-yyyy', 'format_moment' => 'DD-MM-YYYY', 'format_dart' => 'dd-MM-yyyy'],
        ['format' => 'm/d/Y', 'picker_format' => 'mm/dd/yyyy', 'format_moment' => 'MM/DD/YYYY', 'format_dart' => 'MM/dd/yyyy'],
        ['format' => 'd.m.Y', 'picker_format' => 'dd.mm.yyyy', 'format_moment' => 'D.MM.YYYY', 'format_dart' => 'dd.MM.yyyy'],
        ['format' => 'j. M. Y', 'picker_format' => 'd. M. yyyy', 'format_moment' => 'DD. MMM. YYYY', 'format_dart' => 'd. MMM. yyyy'],
        ['format' => 'j. F Y', 'picker_format' => 'd. MM yyyy', 'format_moment' => 'DD. MMMM YYYY', 'format_dart' => 'd. MMMM yyyy'],
    ];

    /**
     * Date Time Format types.
     * @var array
     */
    public static $date_time = [
        ['format' => 'd/M/Y g:i a', 'format_moment' => 'DD/MMM/YYYY h:mm:ss a', 'format_dart' => 'dd/MMM/yyyy h:mm a'],
        ['format' => 'd-M-Y g:i a', 'format_moment' => 'DD-MMM-YYYY h:mm:ss a', 'format_dart' => 'dd-MMM-yyyy h:mm a'],
        ['format' => 'd/F/Y g:i a', 'format_moment' => 'DD/MMMM/YYYY h:mm:ss a', 'format_dart' => 'dd/MMMM/yyyy h:mm a'],
        ['format' => 'd-F-Y g:i a', 'format_moment' => 'DD-MMMM-YYYY h:mm:ss a', 'format_dart' => 'dd-MMMM-yyyy h:mm a'],
        ['format' => 'M j, Y g:i a', 'format_moment' => 'MMM D, YYYY h:mm:ss a', 'format_dart' => 'MMM d, yyyy h:mm a'],
        ['format' => 'F j, Y g:i a', 'format_moment' => 'MMMM D, YYYY h:mm:ss a', 'format_dart' => 'MMMM d, yyyy h:mm a'],
        ['format' => 'D M jS, Y g:i a', 'format_moment' => 'ddd MMM Do, YYYY h:mm:ss a', 'format_dart' => 'EEE MMM d, yyyy h:mm a'],
        ['format' => 'Y-m-d g:i a', 'format_moment' => 'YYYY-MM-DD h:mm:ss a', 'format_dart' => 'yyyy-MM-dd h:mm a'],
        ['format' => 'd-m-Y g:i a', 'format_moment' => 'DD-MM-YYYY h:mm:ss a', 'format_dart' => 'dd-MM-yyyy h:mm a'],
        ['format' => 'm/d/Y g:i a', 'format_moment' => 'MM/DD/YYYY h:mm:ss a', 'format_dart' => 'MM/dd/yyyy h:mm a'],
        ['format' => 'd.m.Y g:i a', 'format_moment' => 'D.MM.YYYY h:mm:ss a', 'format_dart' => 'dd.MM.yyyy h:mm a'],
        ['format' => 'j. M. Y g:i a', 'format_moment' => 'DD. MMM. YYYY h:mm:ss a', 'format_dart' => 'd. MMM. yyyy h:mm a'],
        ['format' => 'j. F Y g:i a', 'format_moment' => 'DD. MMMM YYYY h:mm:ss a', 'format_dart' => 'd. MMMM yyyy h:mm a'],
    ];

    /**
     * Company statics.
     * @param  string|bool $locale The user locale
     * @return array          Array of statics
     */
    public static function company($locale = 'en'): array
    {
        $data = [];

        /** @var \Illuminate\Support\Collection<\App\Models\Industry> */
        $industries = app('industries');

        $data['industries'] = $industries->each(function ($industry) {
            $industry->name = ctrans('texts.industry_'.$industry->name);
        })->sortBy(function ($industry) {
            return $industry->name;
        })->values();

        /** @var \Illuminate\Support\Collection<\App\Models\Country> */
        $countries = app('countries');

        $data['countries'] = $countries->each(function ($country) {
            $country->name = ctrans('texts.country_'.$country->name);
        })->sortBy(function ($country) {
            return $country->name;
        })->values();


        /** @var \Illuminate\Support\Collection<\App\Models\PaymentType> */
        $payment_types = app('payment_types');

        $data['payment_types'] = $payment_types->each(function ($pType) {
            $pType->name = ctrans('texts.payment_type_'.$pType->name);
            $pType->id = (string) $pType->id;
        })->sortBy(function ($pType) {
            return $pType->name;
        })->values();

        /** @var \Illuminate\Support\Collection<\App\Models\Language> */
        $languages = app('languages');

        $data['languages'] = $languages->each(function ($lang) {
            $lang->name = ctrans('texts.lang_'.$lang->name);
        })->sortBy(function ($lang) {
            return $lang->name;
        })->values();


        /** @var \Illuminate\Support\Collection<\App\Models\Currency> */
        $currencies = app('currencies');

        $data['currencies'] = $currencies->each(function ($currency) {
            $currency->name = ctrans('texts.currency_'.Str::slug($currency->name, '_'));
        })->sortBy(function ($currency) {
            return $currency->name;
        })->values();

        $data['sizes'] = app('sizes');
        $data['datetime_formats'] = app('datetime_formats');
        $data['gateways'] = app('gateways');
        $data['timezones'] = app('timezones');
        $data['date_formats'] = app('date_formats');
        $data['templates'] = app('templates');

        $data['bulk_updates'] = [
            'client' => \App\Models\Client::$bulk_update_columns,
            'expense' => \App\Models\Expense::$bulk_update_columns,
            'recurring_invoice' => \App\Models\RecurringInvoice::$bulk_update_columns,
        ];

        return $data;
    }
}
