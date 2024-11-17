<div class="w-full">
    <div class="rounded-lg border bg-card bg-white text-card-foreground shadow-sm overflow-hidden" x-chunk="An order details card with order details, shipping information, customer information and payment information.">
        <div class="pt-6 px-6 flex flex-row items-start bg-muted/50">
            <div class="grid gap-0.5">
            <h3 class="font-semibold tracking-tight group flex items-center gap-2 text-lg">
                {{ ctrans('texts.invoices') }}
                <button class="inline-flex items-center justify-center whitespace-nowrap rounded-md text-sm font-medium ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50 border border-input bg-background hover:bg-accent hover:text-accent-foreground h-6 w-6 opacity-0 transition-opacity group-hover:opacity-100">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-copy h-3 w-3">
                        <rect width="14" height="14" x="8" y="8" rx="2" ry="2"></rect>
                        <path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"></path>
                    </svg>
                </button>
            </h3>
            </div>
        </div>

        <div class="p-6 text-sm">
            @foreach($invoices as $invoice)
            <div class="mb-4 w-full items-start gap-2 rounded-lg border p-3 text-left text-sm transition-all hover:bg-gray-100">
                <dl class="grid gap-1">
                <div class="flex items-center justify-between font-semibold">{{ ctrans('texts.invoice') }} {{ $invoice['number'] }}</div>
                
                <div class="flex items-center justify-between">
                    <dt class="text-muted-foreground">{{ ctrans('texts.invoice_date') }}</dt>
                    <dd>{{ $invoice['date'] }}</dd>
                </div>
                @if($invoice['due_date'])
                <div class="flex items-center justify-between">
                    <dt class="text-muted-foreground">{{ ctrans('texts.due_date') }}</dt>
                    <dd>{{ $invoice['due_date'] }}</dd>
                </div>
                @endif
                <div class="flex items-center justify-between">
                    <dt class="text-muted-foreground">{{ ctrans('texts.amount_due') }}</dt>
                    <dd>
                        {{ $invoice['formatted_currency'] }}    
                    </dd>
                </div>
                <div>
                    <button class="float-right" wire:loading.attr="disabled" wire:click="downloadDocument('{{ $invoice['invoice_id'] }}')" wire:target="downloadDocument('{{ $invoice['invoice_id'] }}')" type="button">
                        <span>
                            <svg enable-background="new 0 0 500 500" height="48px" id="Layer_1" version="1.1" viewBox="0 0 500 500" width="48px" xml:space="preserve" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink"><line fill="none" stroke="#130B7A" stroke-linecap="round" stroke-linejoin="round" stroke-miterlimit="2.6131" stroke-width="10" x1="133.661" x2="233.206" y1="126.169" y2="126.169"/><path d="  M233.206,126.169c7.22,0,13.136,5.94,13.136,13.112" fill="none" stroke="#130B7A" stroke-linecap="round" stroke-linejoin="round" stroke-miterlimit="2.6131" stroke-width="10"/><line fill="none" stroke="#130B7A" stroke-linecap="round" stroke-linejoin="round" stroke-miterlimit="2.6131" stroke-width="10" x1="246.342" x2="246.342" y1="139.281" y2="321.88"/><path d="  M246.342,321.88c0,7.184-5.94,13.111-13.136,13.111" fill="none" stroke="#130B7A" stroke-linecap="round" stroke-linejoin="round" stroke-miterlimit="2.6131" stroke-width="10"/><line fill="none" stroke="#130B7A" stroke-linecap="round" stroke-linejoin="round" stroke-miterlimit="2.6131" stroke-width="10" x1="233.206" x2="89.991" y1="334.991" y2="334.991"/><path d="  M89.991,334.991c-7.16,0-13.112-5.916-13.112-13.111" fill="none" stroke="#130B7A" stroke-linecap="round" stroke-linejoin="round" stroke-miterlimit="2.6131" stroke-width="10"/><polyline fill="none" points="  76.879,321.88 76.879,178.7 133.661,126.169 " stroke="#130B7A" stroke-linecap="round" stroke-linejoin="round" stroke-miterlimit="2.6131" stroke-width="10"/><line fill="none" stroke="#130B7A" stroke-linecap="round" stroke-linejoin="round" stroke-miterlimit="2.6131" stroke-width="10" x1="136.341" x2="136.341" y1="126.169" y2="173.437"/><path d="  M136.341,173.437c0,3.852-3.2,7.039-7.039,7.039" fill="none" stroke="#130B7A" stroke-linecap="round" stroke-linejoin="round" stroke-miterlimit="2.6131" stroke-width="10"/><line fill="none" stroke="#130B7A" stroke-linecap="round" stroke-linejoin="round" stroke-miterlimit="2.6131" stroke-width="10" x1="129.302" x2="76.879" y1="180.476" y2="180.476"/><g><path clip-rule="evenodd" d="M319.217,285.176c4.117,3.43,6.182,8.741,6.182,15.865   c0,7.159-2.125,12.411-6.314,15.743c-4.202,3.309-10.637,4.999-19.317,4.999h-10.492v17.325h-9.973v-59.1h20.296   C308.568,280.009,315.1,281.747,319.217,285.176L319.217,285.176z M312.166,309.589L312.166,309.589   c1.992-2.137,2.981-5.24,2.981-9.333s-1.255-6.99-3.791-8.681c-2.56-1.69-6.507-2.535-11.952-2.535h-10.13v23.688h11.591   C306.383,312.728,310.149,311.665,312.166,309.589z" fill="#130B7A" fill-rule="evenodd"/><path clip-rule="evenodd" d="M381.865,287.76c5.699,5.119,8.536,12.315,8.536,21.515   c0,9.164-2.765,16.444-8.271,21.805c-5.529,5.361-13.98,8.029-25.378,8.029h-19.619v-59.1h20.295   C368.029,280.009,376.179,282.568,381.865,287.76L381.865,287.76z M380.345,309.517L380.345,309.517   c0-13.522-7.764-20.308-23.254-20.308h-9.973v40.519h11.071c7.147,0,12.641-1.703,16.456-5.096   C378.449,321.203,380.345,316.181,380.345,309.517z" fill="#130B7A" fill-rule="evenodd"/><polygon clip-rule="evenodd" fill="#130B7A" fill-rule="evenodd" points="413.22,289.306 413.22,305.544 439.443,305.544    439.443,314.756 413.22,314.756 413.22,339.108 403.247,339.108 403.247,280.009 442.655,280.009 442.57,289.306  "/></g><line clip-rule="evenodd" fill="none" fill-rule="evenodd" stroke="#130B7A" stroke-linecap="round" stroke-linejoin="round" stroke-miterlimit="2.6131" stroke-width="10" x1="279.772" x2="455.706" y1="245.262" y2="245.262"/><path clip-rule="evenodd" d="  M455.706,245.262c10.674,0,19.294,8.645,19.294,19.293" fill="none" fill-rule="evenodd" stroke="#130B7A" stroke-linecap="round" stroke-linejoin="round" stroke-miterlimit="2.6131" stroke-width="10"/><line clip-rule="evenodd" fill="none" fill-rule="evenodd" stroke="#130B7A" stroke-linecap="round" stroke-linejoin="round" stroke-miterlimit="2.6131" stroke-width="10" x1="475" x2="475" y1="264.555" y2="354.563"/><path clip-rule="evenodd" d="  M475,354.563c0,10.648-8.62,19.269-19.294,19.269" fill="none" fill-rule="evenodd" stroke="#130B7A" stroke-linecap="round" stroke-linejoin="round" stroke-miterlimit="2.6131" stroke-width="10"/><line clip-rule="evenodd" fill="none" fill-rule="evenodd" stroke="#130B7A" stroke-linecap="round" stroke-linejoin="round" stroke-miterlimit="2.6131" stroke-width="10" x1="455.706" x2="44.293" y1="373.831" y2="373.831"/><path clip-rule="evenodd" d="  M44.293,373.831c-10.648,0-19.293-8.62-19.293-19.269" fill="none" fill-rule="evenodd" stroke="#130B7A" stroke-linecap="round" stroke-linejoin="round" stroke-miterlimit="2.6131" stroke-width="10"/><line clip-rule="evenodd" fill="none" fill-rule="evenodd" stroke="#130B7A" stroke-linecap="round" stroke-linejoin="round" stroke-miterlimit="2.6131" stroke-width="10" x1="25" x2="25" y1="354.563" y2="264.555"/><path clip-rule="evenodd" d="  M25,264.555c0-10.648,8.645-19.293,19.293-19.293" fill="none" fill-rule="evenodd" stroke="#130B7A" stroke-linecap="round" stroke-linejoin="round" stroke-miterlimit="2.6131" stroke-width="10"/><line clip-rule="evenodd" fill="none" fill-rule="evenodd" stroke="#130B7A" stroke-linecap="round" stroke-linejoin="round" stroke-miterlimit="2.6131" stroke-width="10" x1="163.349" x2="163.349" y1="186.874" y2="303.673"/><line clip-rule="evenodd" fill="none" fill-rule="evenodd" stroke="#130B7A" stroke-linecap="round" stroke-linejoin="round" stroke-miterlimit="2.6131" stroke-width="10" x1="163.349" x2="200.317" y1="303.673" y2="266.691"/><line clip-rule="evenodd" fill="none" fill-rule="evenodd" stroke="#130B7A" stroke-linecap="round" stroke-linejoin="round" stroke-miterlimit="2.6131" stroke-width="10" x1="163.349" x2="122.903" y1="303.673" y2="263.203"/></svg>
                        </span>
                        <div wire:loading wire:target="downloadDocument('{{ $invoice['invoice_id'] }}')" >
                            <svg class="animate-spin h-5 w-5 text-blue" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                        </div>
                    </button>
                </div>
                </dl>
            </div>
            @endforeach

            @if($gateway_fee)
            <button class="mb-4 w-full items-start gap-2 rounded-lg border p-3 text-left text-sm transition-all hover:bg-gray-100">
                <dl class="grid gap-3">

                    <div class="flex items-center justify-between">
                        <dt class="font-semibold text-muted-foreground">{{ ctrans('texts.gateway_fees') }}</dt>
                        <dd>{{ $gateway_fee }}</dd>
                    </div>
                
                </dl>
            </button>
            @endif

            <button class="mb-4 w-full items-start gap-2 rounded-lg border p-3 text-left text-sm transition-all hover:bg-gray-100">
                <dl class="grid gap-3">

                    <div class="flex items-center justify-between">
                        <dt class="font-semibold text-muted-foreground">{{ ctrans('texts.balance_due') }}</dt>
                        <dd>{{ $amount }}</dd>
                    </div>
                
                </dl>
            </button>

            <div data-orientation="horizontal" role="none" class="shrink-0 bg-border h-[1px] w-full my-4"></div>
            <div class="grid gap-3">
                <div class="font-semibold">{{ ctrans('texts.client_information') }}</div>
                <dl class="grid gap-3">
                <div class="flex items-center justify-between">
                    <dt class="text-muted-foreground">{{ ctrans('texts.client') }}</dt>
                    <dd>{{ $client->present()->name() }}</dd>
                </div>
                <div class="flex items-center justify-between">
                    <dt class="text-muted-foreground">{{ ctrans('texts.email') }}</dt>
                    <dd><a href="mailto:">{{ $client->present()->email() }}</a></dd>
                </div>
                @if($client->present()->phone())
                <div class="flex items-center justify-between">
                    <dt class="text-muted-foreground">{{ ctrans('texts.phone') }}</dt>
                    <dd><a href="tel:">{{ $client->present()->phone() }} </a></dd>
                </div>
                @endif
                </dl>
            </div>
            <div data-orientation="horizontal" role="none" class="shrink-0 bg-border h-[1px] w-full my-4"></div>
            <div class="grid gap-3">
                <div class="font-semibold"></div>
                <dl class="grid gap-3">
                </dl>
            </div>
        </div>
    </div>
</div>