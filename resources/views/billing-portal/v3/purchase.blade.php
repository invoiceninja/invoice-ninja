<div class="grid grid-cols-12 bg-gray-50">
    <!-- Left Column - Cart -->
    <div class="col-span-12 xl:col-span-6 bg-white border-r border-gray-200">
        <div class="w-full p-10 lg:mt-24 md:max-w-xl mx-auto">
            <img
                class="h-8"
                src="{{ $this->subscription->company->present()->logo }}"
                alt="{{ $this->subscription->company->present()->name }}"
            />

            <svg id="spinner" class="animate-spin h-8 w-8 text-primary mt-10 hidden" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>

            <div class="my-10" id="container">
                @livewire($this->component, ['context' => $context, 'subscription_id' => $this->subscription->hashed_id], key($this->componentUniqueId()))
            </div>
        </div>
    </div>

    <!-- Right Column - Summary -->
    <div class="col-span-12 xl:col-span-6">
        <div class="sticky top-0">
            <div class="w-full p-10 lg:mt-24 md:max-w-xl mx-auto">
                <div class="my-6 space-y-10 xl:ml-5">
                    @livewire('billing-portal.summary', ['subscription_id' => $this->subscription->hashed_id, 'context' => $context], key($this->summaryUniqueId()))
                </div>
            </div>
        </div>
    </div>

    <!-- Hidden Form -->
    <form
        action="{{ route('client.payments.process', ['hash' => $hash, 'sidebar' => 'hidden', 'source' => 'subscriptions']) }}"
        method="post"
        id="payment-method-form"
        class="hidden">
        @csrf
        <input type="hidden" name="action" value="payment">
        <input type="hidden" name="invoices[]" />
        <input type="hidden" name="payable_invoices[0][amount]" value="{{ $this->context['form']['payable_amount'] ?? '' }}" />
        <input type="hidden" name="payable_invoices[0][invoice_id]" value="{{ $this->context['form']['invoice_hashed_id'] ?? '' }}" />
        <input type="hidden" name="company_gateway_id" value="{{ $this->context['form']['company_gateway_id'] ?? '' }}" />
        <input type="hidden" name="payment_method_id" value="{{ $this->context['form']['payment_method_id'] ?? '' }}" />
        <input type="hidden" name="contact_first_name" value="{{ $this->context['contact']['first_name'] ?? '' }}"  />
        <input type="hidden" name="contact_last_name" value="{{ $this->context['contact']['last_name'] ?? '' }}" />
        <input type="hidden" name="contact_email" value="{{ $this->context['contact']['email'] ?? '' }}" />
    </form>
</div>