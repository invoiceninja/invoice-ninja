<div class="grid grid-cols-1 md:grid-cols-2">
    <div class="p-2">
        @livewire('flow2.invoice-summary')
    </div>

    <div class="p-2">
        @if($errors->any())
        <div class="alert alert-error">
            <ul>
                @foreach($errors->all() as $error)
                    <li class="text-sm">{{ $error }}</li>
                @endforeach
            </ul>
        </div>
        @endif

        @php
            session()->forget('errors');
        @endphp

        @livewire($this->component, [], key($this->componentUniqueId()))
    </div>
</div>
