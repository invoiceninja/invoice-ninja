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

namespace App\Http\Controllers\ClientPortal;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class SubscriptionPurchaseController extends Controller
{
    public function index(Subscription $subscription, Request $request)
    {
        App::setLocale($subscription->company->locale());

        if ($subscription->trashed()) {
            return $this->render('generic.not_available', ['passed_account' => $subscription->company->account, 'passed_company' => $subscription->company]);
        }

        /* Make sure the contact is logged into the correct company for this subscription */
        if (auth()->guard('contact')->user() && auth()->guard('contact')->user()->company_id != $subscription->company_id) {
            auth()->guard('contact')->logout();
            $request->session()->invalidate();
        }

        if ($request->has('locale')) {
            $this->setLocale($request->query('locale'));
        }

        return view('billing-portal.purchase', [
            'subscription' => $subscription,
            'hash' => Str::uuid()->toString(),
            'request_data' => $request->all(),
        ]);
    }

    public function upgrade(Subscription $subscription, Request $request)
    {
        App::setLocale($subscription->company->locale());

        /* Make sure the contact is logged into the correct company for this subscription */
        if (auth()->guard('contact')->user() && auth()->guard('contact')->user()->company_id != $subscription->company_id) {
            auth()->guard('contact')->logout();
            $request->session()->invalidate();
        }

        if ($request->has('locale')) {
            $this->setLocale($request->query('locale'));
        }

        if (!auth()->guard('contact')->check() && $subscription->registration_required && $subscription->company->client_can_register) {
            session()->put('url.intended', route('client.subscription.upgrade', ['subscription' => $subscription->hashed_id]));

            return redirect()->route('client.register', ['company_key' => $subscription->company->company_key]);
        } elseif (!auth()->guard('contact')->check() && $subscription->registration_required && ! $subscription->company->client_can_register) {
            return render('generic.subscription_blocked', ['account' => $subscription->company->account, 'company' => $subscription->company]);
        }

        return view('billing-portal.purchasev2', [
            'subscription' => $subscription,
            'hash' => Str::uuid()->toString(),
            'request_data' => $request->all(),
        ]);
    }

    public function v3(Subscription $subscription, Request $request)
    {

        return view('billing-portal.v3.index', [
            'subscription' => $subscription,
            'hash' => Str::uuid()->toString(),
            'request_data' => $request->all(),
        ]);
    }

    /**
     * Set locale for incoming request.
     *
     * @param string $locale
     * @return string
     */
    private function setLocale(string $locale): string
    {

        /** @var \Illuminate\Support\Collection<\App\Models\Language> */
        $languages = app('languages');

        $record = $languages->first(function ($item) use ($locale) {
            /** @var \App\Models\Language $item */
            return $item->locale == $locale;
        });

        return $record ? $record->locale : 'en';

    }
}
