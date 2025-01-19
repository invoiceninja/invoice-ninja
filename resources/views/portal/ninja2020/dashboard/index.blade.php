@extends('portal.ninja2020.layout.app')
@section('meta_title', ctrans('texts.dashboard'))

@section('body')

    @if($client->getSetting('custom_message_dashboard'))
        @component('portal.ninja2020.components.message')
            <pre>{{ $client->getSetting('custom_message_dashboard') }}</pre>
        @endcomponent
    @endif

    <div class="flex flex-col xl:flex-row gap-4">
        <div class="w-full rounded-md border border-[#E5E7EB] bg-white p-5 text-sm text-[#6C727F]">
            <h3 class="mb-4 text-xl font-semibold text-[#212529]">{{ $contact->client->present()->name() }}</h3>
            <p>{{ $contact->phone }}</p>
            <p>{{ $client->address1 }}</p>
            <p>{{ $client->city }}, {{ $client->state }}</p>
            <p>{{ $client->postal_code }}</p>
            <p>{{ App\Models\Country::find($client->country_id)?->name }}</p>
        </div>

        <div class="w-full flex flex-row items-center rounded-md border border-[#E5E7EB] bg-white p-5 md:flex-col md:justify-center">
            <div class="bg-blue-light mr-3 flex h-12 w-12 items-center justify-center rounded md:mb-6 md:mr-0">
                <svg xmlns="http://www.w3.org/2000/svg" width="25" height="24" viewBox="0 0 25 24" fill="none">
                    <path d="M14 9.75C14 9.55109 13.921 9.36032 13.7803 9.21967C13.6397 9.07902 13.4489 9 13.25 9H7.25C7.05109 9 6.86032 9.07902 6.71967 9.21967C6.57902 9.36032 6.5 9.55109 6.5 9.75C6.5 9.94891 6.57902 10.1397 6.71967 10.2803C6.86032 10.421 7.05109 10.5 7.25 10.5H13.25C13.4489 10.5 13.6397 10.421 13.7803 10.2803C13.921 10.1397 14 9.94891 14 9.75ZM13 12.75C13 12.5511 12.921 12.3603 12.7803 12.2197C12.6397 12.079 12.4489 12 12.25 12H7.25C7.05109 12 6.86032 12.079 6.71967 12.2197C6.57902 12.3603 6.5 12.5511 6.5 12.75C6.5 12.9489 6.57902 13.1397 6.71967 13.2803C6.86032 13.421 7.05109 13.5 7.25 13.5H12.25C12.4489 13.5 12.6397 13.421 12.7803 13.2803C12.921 13.1397 13 12.9489 13 12.75ZM13.25 15C13.4489 15 13.6397 15.079 13.7803 15.2197C13.921 15.3603 14 15.5511 14 15.75C14 15.9489 13.921 16.1397 13.7803 16.2803C13.6397 16.421 13.4489 16.5 13.25 16.5H7.25C7.05109 16.5 6.86032 16.421 6.71967 16.2803C6.57902 16.1397 6.5 15.9489 6.5 15.75C6.5 15.5511 6.57902 15.3603 6.71967 15.2197C6.86032 15.079 7.05109 15 7.25 15H13.25Z" fill="{{ $settings->primary_color }}" />
                    <path fill-rule="evenodd" clip-rule="evenodd" d="M6.5 21.7499H19.5C20.2293 21.7499 20.9288 21.4601 21.4445 20.9444C21.9603 20.4287 22.25 19.7292 22.25 18.9999V13.4999C22.25 13.301 22.171 13.1102 22.0303 12.9695C21.8897 12.8289 21.6989 12.7499 21.5 12.7499H18.25V4.94287C18.25 3.51987 16.641 2.69188 15.483 3.51888L15.308 3.64388C14.9248 3.9159 14.4663 4.0617 13.9964 4.06099C13.5264 4.06027 13.0684 3.91307 12.686 3.63988C12.0476 3.18554 11.2835 2.94141 10.5 2.94141C9.71645 2.94141 8.95238 3.18554 8.314 3.63988C7.93162 3.91307 7.47359 4.06027 7.00364 4.06099C6.53369 4.0617 6.07521 3.9159 5.692 3.64388L5.517 3.51888C4.359 2.69188 2.75 3.51887 2.75 4.94287V17.9999C2.75 18.9944 3.14509 19.9483 3.84835 20.6515C4.55161 21.3548 5.50544 21.7499 6.5 21.7499ZM9.186 4.85988C9.56995 4.58732 10.0291 4.4409 10.5 4.4409C10.9709 4.4409 11.4301 4.58732 11.814 4.85988C12.4507 5.31499 13.2136 5.56009 13.9962 5.56099C14.7788 5.56188 15.5423 5.31853 16.18 4.86487L16.355 4.73987C16.3923 4.71328 16.4363 4.69747 16.482 4.69418C16.5277 4.69088 16.5735 4.70022 16.6143 4.72117C16.6551 4.74213 16.6893 4.7739 16.7132 4.813C16.7372 4.8521 16.7499 4.89703 16.75 4.94287V18.9999C16.75 19.4499 16.858 19.8749 17.05 20.2499H6.5C5.90326 20.2499 5.33097 20.0128 4.90901 19.5909C4.48705 19.1689 4.25 18.5966 4.25 17.9999V4.94287C4.25012 4.89703 4.26284 4.8521 4.28678 4.813C4.31072 4.7739 4.34495 4.74213 4.38573 4.72117C4.4265 4.70022 4.47226 4.69088 4.51798 4.69418C4.56371 4.69747 4.60765 4.71328 4.645 4.73987L4.82 4.86487C5.45775 5.31853 6.22116 5.56188 7.0038 5.56099C7.78644 5.56009 8.54929 5.31499 9.186 4.85988ZM18.25 18.9999V14.2499H20.75V18.9999C20.75 19.3314 20.6183 19.6493 20.3839 19.8838C20.1495 20.1182 19.8315 20.2499 19.5 20.2499C19.1685 20.2499 18.8505 20.1182 18.6161 19.8838C18.3817 19.6493 18.25 19.3314 18.25 18.9999Z" fill="{{ $settings->primary_color }}" />
                </svg>
            </div>
            <div class="md:text-center">
                <p class="text-light-grey-text mb-2 text-xs md:text-sm">{{ ctrans('texts.total_invoices') }}</p>
                <p class="text-2xl font-semibold text-[#212529] md:text-[32px]">
                    {{ App\Utils\Number::formatMoney($total_invoices, $client) }}
                </p>
            </div>
        </div>

        <div class="flex flex-row items-center rounded-md border border-[#E5E7EB] bg-white p-5 md:flex-col md:justify-center w-full">
            <div class="bg-blue-light mr-3 flex h-12 w-12 items-center justify-center rounded md:mb-6 md:mr-0">
                <svg xmlns="http://www.w3.org/2000/svg" width="21" height="20" viewBox="0 0 21 20" fill="none">
                    <path fill-rule="evenodd" clip-rule="evenodd" d="M8.20216 6.89268C7.96951 7.13715 7.9 7.34117 7.9 7.5C7.9 7.65883 7.96951 7.86285 8.20216 8.10732C8.43825 8.35539 8.81295 8.61064 9.32952 8.84023C10.3609 9.29862 11.8348 9.6 13.5 9.6C15.1652 9.6 16.6391 9.29862 17.6705 8.84023C18.187 8.61064 18.5618 8.35539 18.7978 8.10732C19.0305 7.86285 19.1 7.65883 19.1 7.5C19.1 7.34117 19.0305 7.13715 18.7978 6.89268C18.5618 6.64461 18.187 6.38936 17.6705 6.15977C16.6391 5.70138 15.1652 5.4 13.5 5.4C11.8348 5.4 10.3609 5.70138 9.32952 6.15977C8.81295 6.38936 8.43825 6.64461 8.20216 6.89268ZM8.76093 4.88043C10.0097 4.32542 11.6858 4 13.5 4C15.3142 4 16.9903 4.32542 18.2391 4.88043C18.8626 5.15755 19.4105 5.50564 19.812 5.92754C20.2169 6.35305 20.5 6.88563 20.5 7.5C20.5 8.11437 20.2169 8.64695 19.812 9.07246C19.4105 9.49436 18.8626 9.84246 18.2391 10.1196C16.9903 10.6746 15.3142 11 13.5 11C11.6858 11 10.0097 10.6746 8.76093 10.1196C8.13743 9.84246 7.58952 9.49436 7.18801 9.07246C6.78307 8.64695 6.5 8.11437 6.5 7.5C6.5 6.88563 6.78307 6.35305 7.18801 5.92754C7.58952 5.50564 8.13743 5.15755 8.76093 4.88043Z" fill="{{ $settings->primary_color }}" />
                    <path fill-rule="evenodd" clip-rule="evenodd" d="M7.2 7C7.5866 7 7.9 7.32335 7.9 7.72222V16.3889C7.9 16.552 7.96903 16.762 8.20027 17.0138C8.4349 17.2692 8.80769 17.5325 9.32271 17.7696C10.351 18.243 11.8245 18.5556 13.5 18.5556C15.1755 18.5556 16.649 18.243 17.6773 17.7696C18.1923 17.5325 18.5651 17.2692 18.7997 17.0138C19.031 16.762 19.1 16.552 19.1 16.3889V7.72222C19.1 7.32335 19.4134 7 19.8 7C20.1866 7 20.5 7.32335 20.5 7.72222V16.3889C20.5 17.0202 20.219 17.5685 19.8159 18.0074C19.4161 18.4426 18.8702 18.8022 18.2477 19.0887C17.001 19.6626 15.3245 20 13.5 20C11.6755 20 9.99897 19.6626 8.75229 19.0887C8.12981 18.8022 7.58385 18.4426 7.18411 18.0074C6.78097 17.5685 6.5 17.0202 6.5 16.3889V7.72222C6.5 7.32335 6.8134 7 7.2 7Z" fill="{{ $settings->primary_color }}" />
                    <path fill-rule="evenodd" clip-rule="evenodd" d="M7.62478 0.0127665C9.72231 -0.0922152 11.802 0.452205 13.5894 1.57442C13.9251 1.7852 14.0293 2.23294 13.8221 2.57448C13.615 2.91602 13.1749 3.02202 12.8392 2.81125C11.2931 1.84058 9.49357 1.37113 7.67921 1.4652C7.6671 1.46583 7.65498 1.46614 7.64286 1.46614C5.94066 1.46614 4.43684 1.78055 3.3853 2.25712C2.85861 2.49582 2.4767 2.76099 2.23613 3.01836C1.9989 3.27215 1.92857 3.4832 1.92857 3.64622C1.92857 3.81909 2.0091 4.05001 2.29175 4.33065C2.57662 4.6135 3.02405 4.90009 3.62983 5.15464C3.99444 5.30786 4.16793 5.73278 4.01733 6.10372C3.86673 6.47467 3.44907 6.65117 3.08446 6.49795C2.37595 6.20023 1.75195 5.82552 1.29396 5.37078C0.833752 4.91383 0.5 4.33085 0.5 3.64622C0.5 3.00988 0.788603 2.4579 1.20092 2.01679C1.60991 1.57926 2.16817 1.21766 2.80399 0.929505C4.0733 0.354242 5.7768 0.0149655 7.62478 0.0127665ZM6.92857 11.6398C7.32306 11.6398 7.64286 11.9652 7.64286 12.3665C7.64286 12.5307 7.71329 12.742 7.94925 12.9953C8.18867 13.2523 8.56907 13.5173 9.0946 13.7558C10.1439 14.2321 11.6475 14.5466 13.3571 14.5466C15.0668 14.5466 16.5704 14.2321 17.6197 13.7558C18.1452 13.5173 18.5256 13.2523 18.765 12.9953C19.001 12.742 19.0714 12.5307 19.0714 12.3665C19.0714 11.9652 19.3912 11.6398 19.7857 11.6398C20.1802 11.6398 20.5 11.9652 20.5 12.3665C20.5 13.0017 20.2133 13.5535 19.8019 13.9951C19.394 14.4329 18.8369 14.7948 18.2017 15.0831C16.9296 15.6605 15.2189 16 13.3571 16C11.4954 16 9.78466 15.6605 8.51254 15.0831C7.87735 14.7948 7.32026 14.4329 6.91235 13.9951C6.50099 13.5535 6.21429 13.0017 6.21429 12.3665C6.21429 11.9652 6.53408 11.6398 6.92857 11.6398Z" fill="{{ $settings->primary_color }}" />
                    <path fill-rule="evenodd" clip-rule="evenodd" d="M1.29996 3C1.74176 3 2.09992 3.31603 2.09992 3.70587V12.1763C2.09992 12.3442 2.19011 12.5685 2.50666 12.8411C2.82569 13.1159 3.32679 13.3943 4.00522 13.6415C4.41357 13.7904 4.60787 14.2031 4.4392 14.5634C4.27054 14.9237 3.80278 15.0952 3.39444 14.9464C2.60096 14.6572 1.90212 14.2932 1.38919 13.8515C0.873783 13.4076 0.5 12.8413 0.5 12.1763V3.70587C0.5 3.31603 0.858153 3 1.29996 3Z" fill="{{ $settings->primary_color }}" />
                    <path fill-rule="evenodd" clip-rule="evenodd" d="M1.29996 7C1.74176 7 2.09992 7.35815 2.09992 7.79996C2.09992 7.99025 2.19011 8.24445 2.50666 8.55339C2.82569 8.86476 3.32679 9.18024 4.00522 9.46046C4.41357 9.62913 4.60787 10.0969 4.4392 10.5052C4.27054 10.9136 3.80278 11.1079 3.39444 10.9392C2.60096 10.6115 1.90212 10.199 1.38919 9.69838C0.873783 9.19536 0.5 8.55361 0.5 7.79996C0.5 7.35815 0.858153 7 1.29996 7Z" fill="{{ $settings->primary_color }}" />
                </svg>
            </div>
            <div class="md:text-center">
                <p class="text-light-grey-text mb-2 text-xs md:text-sm">{{ ctrans('texts.paid_to_date') }}</p>
                <p class="text-2xl font-semibold text-[#212529] md:text-[32px]">
                    {{ App\Utils\Number::formatMoney($client->paid_to_date, $client) }}
                </p>
            </div>
        </div>

        <div class="flex flex-row items-center rounded-md border border-[#E5E7EB] bg-white p-5 md:flex-col md:justify-center w-full">
            <div class="bg-blue-light mr-3 flex h-12 w-12 items-center justify-center rounded md:mb-6 md:mr-0">
                <svg xmlns="http://www.w3.org/2000/svg" width="23" height="22" viewBox="0 0 23 22" fill="none">
                    <path d="M11.5 8.5V1V8.5ZM5.5 10L11.5 8.5L17.5 7M13.5 14L17.5 7L13.5 14ZM21.5 14L17.5 7L21.5 14ZM9.5 17L5.5 10L9.5 17ZM1.5 17L5.5 10L1.5 17Z" fill="{{ $settings->primary_color }}" />
                    <path d="M11.5 8.5V1M11.5 8.5L5.5 10M11.5 8.5L17.5 7M5.5 10L9.5 17M5.5 10L1.5 17M17.5 7L13.5 14M17.5 7L21.5 14" stroke="{{ $settings->primary_color }}" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                    <path d="M5.5 21C6.56087 21 7.57828 20.5786 8.32843 19.8284C9.07857 19.0783 9.5 18.0609 9.5 17H1.5C1.5 18.0609 1.92143 19.0783 2.67157 19.8284C3.42172 20.5786 4.43913 21 5.5 21ZM17.5 18C18.5609 18 19.5783 17.5786 20.3284 16.8284C21.0786 16.0783 21.5 15.0609 21.5 14H13.5C13.5 15.0609 13.9214 16.0783 14.6716 16.8284C15.4217 17.5786 16.4391 18 17.5 18Z" stroke="{{ $settings->primary_color }}" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
            </div>
            <div class="md:text-center">
                <p class="text-light-grey-text mb-2 text-xs md:text-sm">
                    {{ ctrans('texts.open_balance') }}
                </p>
                <p class="text-2xl font-semibold text-[#212529] md:text-[32px]">
                    {{ App\Utils\Number::formatMoney($client->balance, $client) }}
                </p>
            </div>
        </div>
    </div>

    <div class="flex flex-wrap items-stretch rounded-md border border-[#E5E7EB] bg-white p-4 md:gap-y-6 xl:flex-nowrap mt-4">
        <div class="flex basis-1/2 items-center xl:basis-auto xl:border-r xl:border-[#E5E7EB] xl:pr-20">
            <p class="text-base font-semibold text-[#212529]">{{ ctrans('texts.invoice_from') }}</p>
        </div>
        <div class="flex w-full xl:w-auto mt-2 xl:mt-0 items-center xl:basis-auto xl:justify-center xl:border-r xl:border-[#E5E7EB] xl:px-20">
            <div class="flex items-center">
                @if($client->company->getLogo())
                <div class="h-6 w-6 overflow-hidden rounded">
                    <img src="{{ $client->company->getLogo() }}" alt="company-logo" class="h-fit w-full" />
                </div>
                @endif
                <div class="pl-1.5">
                    <p class="text-xs font-semibold leading-normal text-black">
                        {{ $client->company->present()->name() }}
                    </p>
                </div>
            </div>
        </div>

        <div class="text-light-grey-text flex grow basis-full flex-col justify-center pt-5 text-sm md:basis-1/2 md:border-r md:border-[#E5E7EB] md:pt-0 xl:basis-auto xl:px-5 space-y-2">
            <p>{{ $client->company->settings->address1 }}</p>
            <p>{{ $client->company->settings->city }} {{ $client->company->settings->state }}</p>
            <p>{{ $client->company->settings->postal_code }}</p>
            <p>{{ $client->company->country()->name ?? '' }}</p>
        </div>

        <div class="text-light-grey-text flex grow basis-full flex-col justify-center text-sm md:basis-1/2 md:pl-4 xl:basis-auto xl:px-5 space-y-2 mt-3 xl:mt-0">
            <p><span class="font-semibold">{{ ctrans('texts.vat') }}</span>: {{ $client->company->settings->vat_number }}</p>
            <p>
                <a class="underline" href="mailto:{{ $client->company->settings->email }}" target="_blank">{{ $client->company->settings->email }}</a>
            </p>
            <p>{{ $client->company->settings->phone }}</p>
            <p>
                <a class="underline" href="{{ $client->company->settings->website }}" target="_blank">
                    {{ $client->company->settings->website }}
                </a>
            </p>
        </div>
    </div>
@stop
