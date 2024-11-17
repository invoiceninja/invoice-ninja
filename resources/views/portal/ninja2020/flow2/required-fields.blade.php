<div x-data="{ fields: @entangle('fields') }"
    class="rounded-lg border bg-card text-card-foreground shadow-sm overflow-hidden px-4 py-5 bg-white sm:gap-4 sm:px-6">

    <p class="font-semibold tracking-tight group flex items-center gap-2 text-lg mb-3">
        {{ ctrans('texts.required_fields') }}
    </p>
    
    @if($is_loading)
        <svg class="animate-spin h-5 w-5 text-primary" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor"
                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
            </path>
        </svg>
    @else
        <form id="required-client-info-form"
            x-on:submit.prevent="$wire.handleSubmit(Object.fromEntries(new FormData(document.getElementById('required-client-info-form'))))"
            class="-ml-4 lg:-ml-5">
            @foreach($fields as $field)
                @component('portal.ninja2020.components.general.card-element', ['title' => $field['label']])
                @if($field['name'] == 'client_country_id' || $field['name'] == 'client_shipping_country_id')
                    <select id="client_country" class="input w-full form-select bg-white" name="{{ $field['name'] }}"
                        wire:model="{{ $field['name'] }}">
                        <option value="none"></option>

                        @foreach($countries as $country)
                            <option value="{{ $country->id }}">
                                {{ $country->iso_3166_2 }} ({{ $country->name }})
                            </option>
                        @endforeach
                    </select>
                @else
                    <input class="input w-full" type="{{ $field['type'] ?? 'text' }}" name="{{ $field['name'] }}"
                        wire:model="{{ $field['name'] }}">
                @endif

                @if(count($errors) && array_key_exists($field['name'], $errors))
                    <p class="mt-2 text-gray-900 border-red-300 px-2 py-1 bg-gray-100">
                        {{ $errors[$field['name']][0] }}
                    </p>
                @endif
                @endcomponent

            @endforeach

            <div class="bg-white px-4 py-5 flex items-center w-full justify-end space-x-3">
                <svg wire:loading class="animate-spin h-5 w-5 text-primary" xmlns="http://www.w3.org/2000/svg" fill="none"
                    viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor"
                        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                    </path>
                </svg>

                <button wire:loading.attr="disabled" class="button button-primary bg-primary">
                    <span>{{ ctrans('texts.next') }}</span>
                </button>
            </div>
        </form>
    @endif
</div>
