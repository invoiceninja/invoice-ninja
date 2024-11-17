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

namespace App\Http\ViewComposers;

use App\Utils\Ninja;
use App\Utils\TranslationHelper;
use Illuminate\Support\Facades\App;
use Illuminate\View\View;

/**
 * Class PortalComposer.
 */
class PortalComposer
{
    public const MODULE_RECURRING_INVOICES = 1;

    public const MODULE_CREDITS = 2;

    public const MODULE_QUOTES = 4;

    public const MODULE_TASKS = 8;

    public const MODULE_EXPENSES = 16;

    public const MODULE_PROJECTS = 32;

    public const MODULE_VENDORS = 64;

    public const MODULE_TICKETS = 128;

    public const MODULE_PROPOSALS = 256;

    public const MODULE_RECURRING_EXPENSES = 512;

    public const MODULE_RECURRING_TASKS = 1024;

    public const MODULE_RECURRING_QUOTES = 2048;

    public const MODULE_INVOICES = 4096;

    public const MODULE_PROFORMAL_INVOICES = 8192;

    public const MODULE_PURCHASE_ORDERS = 16384;

    public $settings;

    /**
     * Bind data to the view.
     *
     * @param  View  $view
     * @return void
     */
    public function compose(View $view): void
    {
        $view->with($this->portalData());

        if (auth()->guard('contact')->user()) {
            App::forgetInstance('translator');
            $t = app('translator');
            $t->replace(Ninja::transformTranslations(auth()->guard('contact')->user()->client->getMergedSettings()));
        }
    }

    /**
     * @return array
     */
    private function portalData(): array
    {
        if (! auth()->guard('contact')->user()) {
            return [];
        }

        auth()->guard('contact')->user()->loadMissing(['client' => function ($query) {
            $query->without('gateway_tokens', 'documents'); // Exclude 'grandchildren' relation of 'client'
        }]);

        $this->settings = auth()->guard('contact')->user()->client->getMergedSettings();

        $data['sidebar'] = $this->sidebarMenu();
        $data['header'] = [];
        $data['footer'] = [];
        $data['countries'] = app('countries');
        $data['company'] = auth()->guard('contact')->user()->company;
        $data['client'] = auth()->guard('contact')->user()->client;
        $data['settings'] = $this->settings;
        $data['currencies'] = app('currencies');
        $data['contact'] = auth()->guard('contact')->user();

        $data['multiple_contacts'] = session()->get('multiple_contacts') ?: collect();

        return $data;
    }

    private function sidebarMenu(): array
    {
        $enabled_modules = auth()->guard('contact')->user()->company->enabled_modules;
        $data = [];

        if ($this->settings->enable_client_portal_dashboard) {
            $data[] = [ 'title' => ctrans('texts.dashboard'), 'url' => 'client.dashboard', 'icon' => 'activity', 'id' => 'dashboard'];
        }

        if (self::MODULE_INVOICES & $enabled_modules) {
            $data[] = ['title' => ctrans('texts.invoices'), 'url' => 'client.invoices.index', 'icon' => 'file-text', 'id' => 'invoices'];
        }

        if (self::MODULE_RECURRING_INVOICES & $enabled_modules) {
            $data[] = ['title' => ctrans('texts.recurring_invoices'), 'url' => 'client.recurring_invoices.index', 'icon' => 'file', 'id' => 'recurring_invoices'];
        }

        $data[] = ['title' => ctrans('texts.payments'), 'url' => 'client.payments.index', 'icon' => 'credit-card', 'id' => 'payments'];

        if (self::MODULE_QUOTES & $enabled_modules) {
            $data[] = ['title' => ctrans('texts.quotes'), 'url' => 'client.quotes.index', 'icon' => 'align-left', 'id' => 'quotes'];
        }

        if (self::MODULE_CREDITS & $enabled_modules) {
            $data[] = ['title' => ctrans('texts.credits'), 'url' => 'client.credits.index', 'icon' => 'credit-card', 'id' => 'credits'];
        }

        $data[] = ['title' => ctrans('texts.payment_methods'), 'url' => 'client.payment_methods.index', 'icon' => 'shield', 'id' => 'payment_methods'];
        $data[] = ['title' => ctrans('texts.documents'), 'url' => 'client.documents.index', 'icon' => 'download', 'id' => 'documents'];

        if (auth()->guard('contact')->user()->client->getSetting('enable_client_portal_tasks')) {
            $data[] = ['title' => ctrans('texts.tasks'), 'url' => 'client.tasks.index', 'icon' => 'clock', 'id' => 'tasks'];
        }

        $data[] = ['title' => ctrans('texts.statement'), 'url' => 'client.statement', 'icon' => 'activity', 'id' => 'statement'];

        if (Ninja::isHosted() && auth()->guard('contact')->user() && auth()->guard('contact')->user()->company_id == config('ninja.ninja_default_company_id')) {
            $data[] = ['title' => ctrans('texts.plan'), 'url' => 'client.plan', 'icon' => 'credit-card', 'id' => 'plan'];
        } else {
            $data[] = ['title' => ctrans('texts.subscriptions'), 'url' => 'client.subscriptions.index', 'icon' => 'calendar', 'id' => 'subsciptions'];
        }

        if (auth()->guard('contact')->user()->client->getSetting('client_initiated_payments')) {
            $data[] = ['title' => ctrans('texts.pre_payment'), 'url' => 'client.pre_payments.index', 'icon' => 'dollar-sign', 'id' => 'pre_payment'];
        }

        return $data;
    }
}
