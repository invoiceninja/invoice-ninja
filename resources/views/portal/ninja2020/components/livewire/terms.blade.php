<div class="pb-8">
    <div class="bg-white p-6 rounded-lg shadow-lg">
        <h2 class="text-xl text-center py-0 px-4">{{ ctrans('texts.terms') }}</h2>
        <div class="mt-0 h-64 overflow-y-auto">
            <div class="py-0">
                <p class="text-sm leading-6 font-medium text-gray-500">{{ ctrans('texts.invoice') }} {{ $this->invoice->number }}:</p>
                @if($variables && $this->invoice->terms)
                    <h5 data-ref="entity-terms">{!! $this->invoice->parseHtmlVariables('terms', $variables) !!}</h5>
                @elseif($this->invoice->terms)
                    <h5 data-ref="entity-terms" class="text-sm leading-5 text-gray-900">{!! $this->invoice->terms !!}</h5>
                @else
                    <i class="text-sm leading-5 text-gray-500">{{ ctrans('texts.not_specified') }}</i>
                @endif
            </div>
        </div>
        <div class="flex flex-col items-end px-4 py-4">
            <div class="w-full flex justify-end mb-2">
                <button id="accept-terms-button" class="button button-primary bg-primary hover:bg-primary-darken">{{ ctrans('texts.next') }}</button>
            </div>
            <span class="text-xs text-gray-600 text-right">{{ ctrans('texts.by_clicking_next_you_accept_terms')}}</span>
        </div>
    </div>
    
    @script
    <script>
            
        document.addEventListener('DOMContentLoaded', function () {
            
            document.getElementById('accept-terms-button').addEventListener('click', function() {          
                $wire.dispatch('terms-accepted');
            });
             
        });

    </script>
    @endscript

</div>