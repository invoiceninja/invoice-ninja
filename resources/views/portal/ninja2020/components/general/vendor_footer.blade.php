<footer class="bg-white px-4 py-5 shadow px-4 sm:px-6 md:px-8 flex justify-center border-t border-gray-200 justify-between items-center" x-data="{ privacy: false, tos: false }">
    <section>
        @if(auth()->guard('vendor')->user() && auth()->guard('vendor')->user()->user->account->isPaid())
            <span class="text-xs md:text-sm text-gray-700">{{ ctrans('texts.footer_label', ['company' => auth()->guard('vendor')->user()->vendor->company->present()->name(), 'year' => date('Y')]) }}</span>
        @else
            <span href="https://invoiceninja.com" target="_blank" class="text-xs md:text-sm text-gray-700">
                {{ ctrans('texts.copyright') }} &copy; {{ date('Y') }}
                <a class="text-primary hover:underline" href="https://invoiceninja.com" target="_blank">Invoice Ninja</a>.
            </span>
        @endif

        <div class="flex items-center">
            @if(strlen($settings->client_portal_privacy_policy) > 1)
                <a x-on:click="privacy = true; tos = false" href="#" class="hover:underline text-sm primary-color flex items-center mr-2">{{ __('texts.privacy_policy')}}</a>
            @endif

            @if(strlen($settings->client_portal_privacy_policy) > 1 && strlen($settings->client_portal_terms) > 1)
                <!-- <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-minus">
                    <line x1="5" y1="12" x2="19" y2="12"></line>
                </svg> Long dash between items. -->
            @endif

            @if(strlen($settings->client_portal_terms) > 1)
                <a x-on:click="privacy = false; tos = true" href="#" class="hover:underline text-sm primary-color flex items-center mr-2">{{ __('texts.terms')}}</a>
            @endif
        </div>
    </section>

    @if(auth()->guard('vendor')->user()->user && !auth()->guard('vendor')->user()->user->account->isPaid())
        <a href="https://invoiceninja.com" target="_blank">
            <img class="h-8" src="{{ asset('images/invoiceninja-black-logo-2.png') }}" alt="Invoice Ninja Logo">
        </a>
    @endif

    @if(strlen($settings->client_portal_privacy_policy) > 1)
        @component('portal.ninja2020.components.general.pop-up', ['title' => __('texts.privacy_policy') ,'show_property' => 'privacy'])
            {!! nl2br($settings->client_portal_privacy_policy) !!}
        @endcomponent
    @endif

    @if(strlen($settings->client_portal_terms) > 1)
        @component('portal.ninja2020.components.general.pop-up', ['title' => __('texts.terms') ,'show_property' => 'tos'])
            {!! nl2br($settings->client_portal_terms) !!}
        @endcomponent
    @endif

    <div class="bg-gray-200 hidden"></div>
</footer>
