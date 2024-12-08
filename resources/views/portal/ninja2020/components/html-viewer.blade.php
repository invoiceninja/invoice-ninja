@push('head')
<style>

table, th, td {
  /* border: 1px solid black; */
  border-collapse: collapse;
}

td
{
 max-width: 1px;
 overflow: hidden;
 text-overflow: ellipsis;
 white-space: nowrap;
}

span {
    display: block;
    padding: 3px;
    margin-right:10px;
}

.icon {
  display: inline-block;
  vertical-align: middle;
}

</style>
@endpush

<div class="w-full bg-white py-3 border-2 shadow sm:rounded-lg">

    <div class="px-3 border-fuchsia-600 border-b-2 pb-3">
    
        <div id="company-details" class="mx-auto">
            {!! $company_details !!}
        </div>

    </div>

    <div class="border-fuchsia-600 border-b-2 pb-3 mt-3">

        <div id="entity-details"> {!! $entity_details !!} </div>

    </div>

    <div id="user-details" class="mt-3 px-3 border-b-2 border-fuschia-600 flex flex-col items-end"> 

        <div x-data="{ show_user: false }" class="mb-3">

            <button @click="show_user = !show_user" :aria-expanded="show_user ? 'true' : 'false'" :class="{ 'active': show_user }" class="bg-gray-100 hover:bg-gray-200 text-gray-800 font-bold py-2 px-4 rounded inline-flex items-center">
                <span class="overflow-ellipsis  overflow-hidden">{{ $user_name }}</span>
                <svg xmlns="http://www.w3.org/2000/svg" height="1em" viewBox="0 0 512 512"><path d="M233.4 406.6c12.5 12.5 32.8 12.5 45.3 0l192-192c12.5-12.5 12.5-32.8 0-45.3s-32.8-12.5-45.3 0L256 338.7 86.6 169.4c-12.5-12.5-32.8-12.5-45.3 0s-12.5 32.8 0 45.3l192 192z"/></svg>
            </button>

            <div id="terms" class="py-3"  x-show="show_user">
                {!! $user_details !!}
            </div>


        </div>
         
    </div>

    @if($products->count() > 0)
    <div id="product-details" class="py-6 mr-5 ml-5">
        <table width="100%">
            <thead>
                <tr class="border-b-2">
                    <th style="text-align:left; width:70%; padding-left:2px;">Item</th>
                    <th style="text-align:right; width:30%; padding-right:2px;">Amount</th>
                </tr>
            </thead>
            <tbody>
                @foreach($products as $product)
                <tr style="display: table-row;" class="border-b-2">
                    <td>
                        <div class="product-information">
                            <div class="item-details">

                                <p class="overflow-ellipsis overflow-hidden px-1 mb-2">{!! $product['notes'] !!}</p>
                                <p class="mt-2">
                                    @if($show_quantity)
                                    {{ $product['quantity'] }} x
                                    @endif

                                    @if($show_cost)
                                    {{ $product['cost'] }}
                                    @endif
                                </p>
                                
                            </div>
                        </div>
                    </td>
                    
                    <td style="text-align:right; padding-right:2px;">
                    @if($show_line_total)
                        {{ $product['line_total'] }}
                    @endif
                    </td>
                </tr>
                @endforeach                   
            </tbody>
        </table>
    </div>
    @endif 
    @if($services->count() > 0)
    <div id="task-details" class="py-6 mr-3 ml-3">
        <table width="100%">
            <thead>
                <tr class="border-b-2">
                    <th style="text-align:left; width:70%; padding-left:2px;">Service</th>
                    <th style="text-align:right; width:30%; padding-right:2px;">Amount</th>
                </tr>
            </thead>
            <tbody>
                @foreach($services as $service)
                <tr style="display: table-row;" class="border-b-2">
                    <td>
                        <div class="">
                            <div class="">
                                <p class="mt-2">{{ $service['quantity'] }} × {{ $service['cost'] }}</p> 
                                <p class="overflow-ellipsis overflow-hidden px-1 mb-2">{!! $service['notes'] !!}</p>
                            </div>
                        </div>
                    </td>
                    <td style="text-align:right; padding-right:2px;">{{ $service['line_total'] }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif
    <div id="totals" class="mb-10 mr-3 ml-3">
        <table width="100%">
            <tbody>
                @if($discount)
                <tr>
                    <td style="text-align:left; padding-right:10px;" class="text-lg">{{ ctrans('texts.discount') }}</td>
                    <td style="text-align:right; padding-right:10px;" class="text-lg">{{ $discount }}</td>
                </tr>
                @endif
                @if($taxes)
                <tr>
                    <td style="text-align:left; padding-right:10px;" class="text-lg">{{ ctrans('texts.tax') }}</td>
                    <td style="text-align:right; padding-right:10px;" class="text-lg">{{ $taxes }}</td>
                </tr>
                @endif
                <tr>
                    <td style="text-align:left; padding-right:10px;" class="text-lg">{{ ctrans('texts.total') }}</td>
                    <td style="text-align:right; padding-right:10px;" class="text-lg">{{ $amount }}</td>
                </tr>
                @if(!$is_quote)
                 <tr>
                    <td style="text-align:left; padding-right:10px;" class="text-lg">{{ ctrans('texts.balance') }}</td>
                    <td style="text-align:right; padding-right:10px;" class="text-lg">{{ $balance }}</td>
                </tr>
                @endif
            </tbody>
        </table>

    </div>

    @if(strlen($entity->public_notes ?? '') > 3)
    <div x-data="{ show_notes: false }" class="mb-10 mr-5 ml-5 flex flex-col items-end">
        
        <button @click="show_notes = !show_notes" :aria-expanded="show_notes ? 'true' : 'false'" :class="{ 'active': show_notes }" class="bg-gray-100 hover:bg-gray-200 text-gray-800 font-bold py-2 px-4 rounded inline-flex items-center">
            <span>{{ ctrans('texts.notes') }}</span>
            <svg xmlns="http://www.w3.org/2000/svg" height="1em" viewBox="0 0 512 512"><path d="M233.4 406.6c12.5 12.5 32.8 12.5 45.3 0l192-192c12.5-12.5 12.5-32.8 0-45.3s-32.8-12.5-45.3 0L256 338.7 86.6 169.4c-12.5-12.5-32.8-12.5-45.3 0s-12.5 32.8 0 45.3l192 192z"/></svg>
        </button>
        
        <div id="notes" class="py-10 border-b-2 border-fuschia-600"  x-show="show_notes">     
            {!! html_entity_decode(e($entity->public_notes)) !!}
        </div>

    </div>
    @endif

    @if(strlen($entity->terms ?? '') > 3)
    <div x-data="{ show_terms: false }" class="mb-10 mr-5 ml-5 flex flex-col items-end">

        <button @click="show_terms = !show_terms" :aria-expanded="show_terms ? 'true' : 'false'" :class="{ 'active': show_terms }" class="bg-gray-100 hover:bg-gray-200 text-gray-800 font-bold py-2 px-4 rounded inline-flex items-center">
            <span>{{ ctrans('texts.terms') }}</span>
            <svg xmlns="http://www.w3.org/2000/svg" height="1em" viewBox="0 0 512 512"><path d="M233.4 406.6c12.5 12.5 32.8 12.5 45.3 0l192-192c12.5-12.5 12.5-32.8 0-45.3s-32.8-12.5-45.3 0L256 338.7 86.6 169.4c-12.5-12.5-32.8-12.5-45.3 0s-12.5 32.8 0 45.3l192 192z"/></svg>
        </button>

        <div id="terms" class="py-10 border-b-2 border-fuschia-600"  x-show="show_terms">
            {!! html_entity_decode($entity->terms) !!}
        </div>

    </div>
    @endif

    @if(strlen($entity->footer ?? '') > 3)
    <div x-data="{ show_footer: false }" class="mb-10 mr-5 ml-5 flex flex-col items-end">

        <button @click="show_footer = !show_footer" :aria-expanded="show_footer ? 'true' : 'false'" :class="{ 'active': show_footer }" class="bg-gray-100 hover:bg-gray-200 text-gray-800 font-bold py-2 px-4 rounded inline-flex items-center">
            <span>{{ ctrans('texts.footer') }}</span>
            <svg xmlns="http://www.w3.org/2000/svg" height="1em" viewBox="0 0 512 512"><path d="M233.4 406.6c12.5 12.5 32.8 12.5 45.3 0l192-192c12.5-12.5 12.5-32.8 0-45.3s-32.8-12.5-45.3 0L256 338.7 86.6 169.4c-12.5-12.5-32.8-12.5-45.3 0s-12.5 32.8 0 45.3l192 192z"/></svg>
        </button>

        <div id="terms" class="py-10 border-b-2 border-fuschia-600"  x-show="show_footer">
            {!! html_entity_decode($entity->footer) !!}
        </div>

    </div>
    @endif

@push('head')
<script>
    document.addEventListener('DOMContentLoaded', () => {
        
        Array.from(document.getElementsByClassName("entity-field")).forEach(function(item) {
            if(item.innerText.length == 0){
                item.parentNode.remove();
            }
        });

        document.addEventListener('livewire:init', () => {

            Livewire.hook('message.processed', (message, component) => {

                Array.from(document.getElementsByClassName("entity-field")).forEach(function(item) {
                    if(item.innerText.length == 0){
                        item.parentNode.remove();
                    }
                });

            });

        });

        var timeout = false; 
        
        /* Watch for resize of window and ensure we unset props with no values */
        window.addEventListener('resize', function() {
            clearTimeout(timeout);
            timeout = setTimeout(getDimensions, 250);
        });

        getDimensions();

        function getDimensions() {

            const width = window.innerWidth;
            
            if(width < 900){
            
                Array.from(document.getElementsByClassName("entity-field")).forEach(function(item) {
                    if(item.innerText.length == 0){
                        item.parentNode.remove();
                    }
                });

            }
        }

    });
</script>
@endpush