<div class="flex items-center justify-between mt-4">
    <section class="flex items-center">
        <div class="items-center" style="{{ $mobile ? '' : 'display: none' }}" id="pagination-button-container">
            <button class="input-label focus:outline-none hover:text-blue-600 transition ease-in-out duration-300"
                id="previous-page-button" title="Previous page">
                <svg class="w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                </svg>
            </button>
            <button class="input-label focus:outline-none hover:text-blue-600 transition ease-in-out duration-300"
                id="next-page-button" title="Next page">
                <svg class="w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                </svg>
            </button>
        </div>
        <span class="text-sm text-gray-700 ml-2 {{ $mobile ? 'block' : 'hidden' }}">{{ ctrans('texts.page') }}:
            <span id="current-page-container"></span>
            <span>{{ strtolower(ctrans('texts.of')) }}</span>
            <span id="total-page-container"></span>
        </span>
    </section>
    <section class="flex items-center space-x-1">
        <div class="flex items-center mr-4 space-x-1 {{ $mobile ? 'block' : 'hidden' }}">
            <span class="text-gray-600 mr-2" id="zoom-level">100%</span>
            <a href="#" id="zoom-in">
                <svg class="text-gray-400 hover:text-gray-600 focus:outline-none focus:text-gray-600 cursor-pointer"
                    xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                    stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="11" cy="11" r="8"></circle>
                    <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                    <line x1="11" y1="8" x2="11" y2="14"></line>
                    <line x1="8" y1="11" x2="14" y2="11"></line>
                </svg>
            </a>
            <a href="#" id="zoom-out">
                <svg class="text-gray-400 hover:text-gray-600 focus:outline-none focus:text-gray-600 cursor-pointer"
                    xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                    stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="11" cy="11" r="8"></circle>
                    <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                    <line x1="8" y1="11" x2="14" y2="11"></line>
                </svg>
            </a>
        </div>
    </section>
</div>

@livewire('pdf-slot', ['class' => get_class($entity), 'entity' => $entity, 'invitation' => $invitation, 'db' => $entity->company->db])

@if($mobile)
    @push('footer')
        {{-- @vite('resources/js/clients/shared/pdf.js') --}}
    @endpush
@endif
