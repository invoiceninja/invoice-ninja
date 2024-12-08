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

namespace App\Providers;

use App\Models\Bank;
use App\Models\Size;
use App\Models\Country;
use App\Models\Gateway;
use App\Models\Currency;
use App\Models\Industry;
use App\Models\Language;
use App\Models\Timezone;
use App\Models\DateFormat;
use App\Models\PaymentTerm;
use App\Models\PaymentType;
use App\Models\DatetimeFormat;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\ServiceProvider;
use App\DataMapper\EmailTemplateDefaults;

class StaticServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        /** @return \Illuminate\Support\Collection<Currency> */
        app()->singleton('currencies', function ($app) {

            if ($resource = Cache::get('currencies')) {
                return $resource;
            }

            $resource = Currency::query()->orderBy('name')->get();

            Cache::forever('currencies', $resource);

            return $resource;

        });

        /** @return \Illuminate\Support\Collection<Language> */
        app()->singleton('languages', function ($app) {

            if ($resource = Cache::get('languages')) {
                return $resource;
            }

            $resource = Language::query()->orderBy('name')->get();

            Cache::forever('languages', $resource);

            return $resource;

        });

        /** @return \Illuminate\Support\Collection<Country> */
        app()->singleton('countries', function ($app) {

            if ($resource = Cache::get('countries')) {
                return $resource;
            }

            $resource = Country::query()->orderBy('name')->get();

            Cache::forever('countries', $resource);

            return $resource;

        });

        /** @return \Illuminate\Support\Collection<PaymentType> */
        app()->singleton('payment_types', function ($app) {

            if ($resource = Cache::get('payment_types')) {
                return $resource;
            }

            $resource = PaymentType::query()->orderBy('id')->get();

            Cache::forever('payment_types', $resource);

            return $resource;

        });


        /** @return \Illuminate\Support\Collection<Bank> */
        app()->singleton('banks', function ($app) {


            if ($resource = Cache::get('banks')) {
                return $resource;
            }

            $resource = Bank::query()->orderBy('name')->get();

            Cache::forever('banks', $resource);

            return $resource;

        });

        /** @return \Illuminate\Support\Collection<DateFormat> */
        app()->singleton('date_formats', function ($app) {


            if ($resource = Cache::get('date_formats')) {
                return $resource;
            }

            $resource = DateFormat::query()->orderBy('id')->get();

            Cache::forever('date_formats', $resource);

            return $resource;

        });

        /** @return \Illuminate\Support\Collection<Timezone> */
        app()->singleton('timezones', function ($app) {


            if ($resource = Cache::get('timezones')) {
                return $resource;
            }

            $resource = Timezone::query()->orderBy('id')->get();

            Cache::forever('timezones', $resource);

            return $resource;

        });

        /** @return \Illuminate\Support\Collection<Gateway> */
        app()->singleton('gateways', function ($app) {

            if ($resource = Cache::get('gateways')) {
                return $resource;
            }

            $resource = Gateway::query()->orderBy('id')->get();

            Cache::forever('gateways', $resource);

            return $resource;


        });

        /** @return \Illuminate\Support\Collection<Industry> */
        app()->singleton('industries', function ($app) {


            if ($resource = Cache::get('industries')) {
                return $resource;
            }

            $resource = Industry::query()->orderBy('id')->get();

            Cache::forever('industries', $resource);

            return $resource;

        });

        /** @return \Illuminate\Support\Collection<Size> */
        app()->singleton('sizes', function ($app) {


            if ($resource = Cache::get('sizes')) {
                return $resource;
            }

            $resource = Size::query()->orderBy('id')->get();

            Cache::forever('sizes', $resource);

            return $resource;

        });

        /** @return \Illuminate\Support\Collection<DatetimeFormat> */
        app()->singleton('datetime_formats', function ($app) {

            if ($resource = Cache::get('datetime_formats')) {
                return $resource;
            }

            $resource = DatetimeFormat::query()->orderBy('id')->get();

            Cache::forever('datetime_formats', $resource);

            return $resource;

        });

        app()->singleton('templates', function ($app) {
            return [
                'invoice' => [
                    'subject' => EmailTemplateDefaults::emailInvoiceSubject(),
                    'body' => EmailTemplateDefaults::emailInvoiceTemplate(),
                ],
                'quote' => [
                    'subject' => EmailTemplateDefaults::emailQuoteSubject(),
                    'body' => EmailTemplateDefaults::emailQuoteTemplate(),
                ],
                'payment' => [
                    'subject' => EmailTemplateDefaults::emailPaymentSubject(),
                    'body' => EmailTemplateDefaults::emailPaymentTemplate(),
                ],
                'payment_failed' => [
                    'subject' => EmailTemplateDefaults::emailPaymentFailedSubject(),
                    'body' => EmailTemplateDefaults::emailPaymentFailedTemplate(),
                ],
                'quote_reminder1' => [
                    'subject' => EmailTemplateDefaults::emailQuoteReminder1Subject(),
                    'body' => EmailTemplateDefaults::emailQuoteReminder1Body(),
                ],
                'reminder1' => [
                    'subject' => EmailTemplateDefaults::emailReminder1Subject(),
                    'body' => EmailTemplateDefaults::emailReminder1Template(),
                ],
                'reminder2' => [
                    'subject' => EmailTemplateDefaults::emailReminder2Subject(),
                    'body' => EmailTemplateDefaults::emailReminder2Template(),
                ],
                'reminder3' => [
                    'subject' => EmailTemplateDefaults::emailReminder3Subject(),
                    'body' => EmailTemplateDefaults::emailReminder3Template(),
                ],
                'reminder_endless' => [
                    'subject' => EmailTemplateDefaults::emailReminderEndlessSubject(),
                    'body' => EmailTemplateDefaults::emailReminderEndlessTemplate(),
                ],
                'statement' => [
                    'subject' => EmailTemplateDefaults::emailStatementSubject(),
                    'body' => EmailTemplateDefaults::emailStatementTemplate(),
                ],
                'purchase_order' => [
                    'subject' => EmailTemplateDefaults::emailPurchaseOrderSubject(),
                    'body' => EmailTemplateDefaults::emailPurchaseOrderTemplate(),
                ],
                'partial_payment' => [
                    'subject' => EmailTemplateDefaults::emailPaymentPartialSubject(),
                    'body' => EmailTemplateDefaults::emailPaymentPartialTemplate(),
                ]
            ];

        });

    }

    public function boot()
    {

    }
}
