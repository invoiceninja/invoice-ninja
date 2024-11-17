<label class="flex items-center cursor-pointer">
    <input type="checkbox" class="form-checkbox mr-2"
           wire:change="updateAutoBilling" {{ $this->invoice->auto_bill_enabled ? 'checked' : '' }}>

    <span class="text-sm leading-5 font-medium text-gray-900">
        {{ ctrans('texts.enable_auto_bill') }}
    </span>
</label>
