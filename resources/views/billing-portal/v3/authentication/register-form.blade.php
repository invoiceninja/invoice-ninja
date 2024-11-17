<form wire:submit="register(Object.fromEntries(new FormData($event.target)))" class="space-y-3">
    @csrf

    <div class="grid grid-cols-12 gap-4 mt-10">
        @if($register_fields)
            @foreach($register_fields as $field)
                @if($field['visible'])
                    <div class="col-span-12 md:col-span-6">
                        <section class="flex items-center">
                            <label
                                for="{{ $field['key'] }}"
                                class="input-label">
                                @if(in_array($field['key'], ['custom_value1','custom_value2','custom_value3','custom_value4']))
                                    {{ (new App\Utils\Helpers())->makeCustomField($this->subscription->company->custom_fields, str_replace("custom_value","client", $field['key']))}}
                                @elseif(array_key_exists('label', $field))
                                    {{ ctrans("texts.{$field['label']}") }}
                                @else
                                    {{ ctrans("texts.{$field['key']}") }}
                                @endif
                            </label>

                            @if($field['required'])
                                <section class="text-red-400 ml-1 text-sm">*</section>
                            @endif
                        </section>

                        @if($field['key'] === 'email')
                            <input
                                id="{{ $field['key'] }}"
                                class="input w-full"
                                type="email"
                                name="{{ $field['key'] }}"
                                value="{{ old($field['key'], $this->email ?? '') }}"
                            />
                        @elseif($field['key'] === 'password')
                            <input
                                id="{{ $field['key'] }}"
                                class="input w-full"
                                type="password"
                                name="{{ $field['key'] }}"
                            />
                        @elseif($field['key'] === 'currency_id')
                            <select
                                id="currency_id"
                                class="input w-full form-select bg-white"
                                name="currency_id">
                                @foreach(App\Utils\TranslationHelper::getCurrencies() as $currency)
                                    <option
                                        {{ $currency->id == $this->subscription->company->settings->currency_id ? 'selected' : null }} value="{{ $currency->id }}">
                                        {{ $currency->getName() }}
                                    </option>
                                @endforeach
                            </select>
                        @elseif($field['key'] === 'country_id')
                            <select
                                id="country_id"
                                class="input w-full form-select bg-white"
                                wire:change="$set('formData.country_id', $event.target.value)"
                                name="country_id">
                                <option value=""></option>
                                @foreach(App\Utils\TranslationHelper::getCountries() as $country)
                                    <option
                                        {{ isset(auth()->user()->client->country->id) && $country->id == auth()->user()->client->country->id ? 'selected' : '' }}
                                        value="{{ $country->id }}">
                                        {{ $country->iso_3166_2 }}
                                        ({{ $country->getName() }})
                                    </option>
                                @endforeach
                            </select>
                        @elseif($field['key'] === 'shipping_country_id')
                            <select
                                id="shipping_country_id"
                                class="input w-full form-select bg-white"
                                wire:change="$set('formData.shipping_country_id', $event.target.value)"
                                name="shipping_country_id">
                                <option value=""></option>
                                @foreach(App\Utils\TranslationHelper::getCountries() as $country)
                                    <option
                                        {{ isset(auth()->user()->client->shipping_country->id) && $country->id == auth()->user()->client->shipping_country->id ? 'selected' : '' }}
                                        value="{{ $country->id }}">
                                        {{ $country->iso_3166_2 }}
                                        ({{ $country->getName() }})
                                    </option>
                                @endforeach
                            </select>
                        @else
                            <input
                                id="{{ $field['key'] }}"
                                class="input w-full"
                                name="{{ $field['key'] }}"
                                value="{{ old($field['key']) }}"
                            />
                        @endif

                        @error($field['key'])
                        <div class="validation validation-fail">
                            {{ $message }}
                        </div>
                        @enderror
                    </div>

                    @if($field['key'] === 'password')
                        <div class="col-span-12 md:col-span-6">
                            <section class="flex items-center">
                                <label
                                    for="password_confirmation"
                                    class="input-label">
                                    {{ ctrans('texts.password_confirmation') }}
                                </label>

                                @if($field['required'])
                                    <section class="text-red-400 ml-1 text-sm">*</section>
                                @endif
                            </section>

                            <input
                                id="password_confirmation"
                                type="password"
                                class="input w-full"
                                name="password_confirmation"
                            />
                        </div>
                    @endif
                @endif
            @endforeach
        @endif
    </div>

    <div class="col-span-12 md:col-span-6">
        <span class="inline-flex items-center" x-data="{ terms_of_service: false, privacy_policy: false }">
            @if(!empty($this->subscription->company->settings->client_portal_terms) || !empty($this->subscription->company->settings->client_portal_privacy_policy))
                <input type="checkbox" name="terms" class="form-checkbox mr-2 cursor-pointer" checked>
                <span class="text-sm text-gray-800">

                {{ ctrans('texts.i_agree_to_the') }}
            @endif

            @includeWhen(!empty($this->subscription->company->settings->client_portal_terms), 'portal.ninja2020.auth.includes.register.popup', ['property' => 'terms_of_service', 'title' => ctrans('texts.terms_of_service'), 'content' => $this->subscription->company->settings->client_portal_terms])
            @includeWhen(!empty($this->subscription->company->settings->client_portal_privacy_policy), 'portal.ninja2020.auth.includes.register.popup', ['property' => 'privacy_policy', 'title' => ctrans('texts.privacy_policy'), 'content' => $this->subscription->company->settings->client_portal_privacy_policy])

            @error('terms')
                <p class="text-red-600">{{ $message }}</p>
            @enderror
            </span>
        </span>
    </div>

    <button
        type="submit"
        class="button button-block bg-primary text-white mt-4">
        {{ ctrans('texts.next') }}
    </button>
</form>
