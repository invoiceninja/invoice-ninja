<div class="md:hidden">
    <div @click="sidebarOpen = false" class="fixed inset-0 z-30 bg-gray-600 opacity-0 pointer-events-none transition-opacity ease-linear duration-300" :class="{'opacity-75 pointer-events-auto': sidebarOpen, 'opacity-0 pointer-events-none': !sidebarOpen}"></div>
    <div class="fixed inset-y-0 left-0 flex flex-col z-40 max-w-xs w-full pt-5 pb-4 bg-white transform ease-in-out duration-300 -translate-x-full" :class="{'translate-x-0': sidebarOpen, '-translate-x-full': !sidebarOpen}">
        <div class="absolute top-0 right-0 -mr-14 p-1">
            <button x-show="sidebarOpen" @click="sidebarOpen = false" class="flex items-center justify-center h-12 w-12 rounded-full focus:outline-none focus:bg-gray-600">
                <svg class="h-6 w-6 text-white" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
        <div class="flex-shrink-0 flex items-center px-4">
            <img class="h-8 w-auto" src="{!! auth()->guard('contact')->user()->company->present()->logo($settings) !!}" alt="{{ auth()->guard('contact')->user()->company->present()->name() }} logo" />
        </div>
        <div class="mt-5 flex-1 h-0 overflow-y-auto">
            <nav class="flex-1 pb-4 pt-0 bg-white">
                @foreach($sidebar as $row)
                <a class="group flex items-center p-4 text-sm leading-5 font-medium hover:font-semibold focus:outline-none focus:font-semibold transition ease-in-out duration-150 {{ isActive($row['url'], true) ? 'bg-primary text-white' : 'text-gray-900' }}" 
                href="{{ route($row['url']) }}"
                id="{{ $row['id'] }}">
                    @if(isActive($row['url'], true))
                        <img src="{{ asset('images/svg/' . $row['icon'] . '.svg') }}"
                             class="w-5 h-5 fill-current mr-3" alt=""/>
                    @else
                        <img src="{{ asset('images/svg/dark/' . $row['icon'] . '.svg') }}"
                             class="w-5 h-5 fill-current mr-3" alt=""/>
                    @endif

                    <span>{{ $row['title'] }}</span>
                </a>
                @endforeach
            </nav>

            @if(!auth()->guard('contact')->user()->user->account->isPaid())
            <div class="flex-shrink-0 flex bg-white p-4 justify-center">
                <div class="flex items-center">
                    <a target="_blank" href="https://www.facebook.com/invoiceninja/">
                        <svg class="text-gray-900 hover:text-gray-300 mr-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"></path>
                        </svg>
                    </a>
                    <a target="_blank" href="https://twitter.com/invoiceninja">
                        <svg class="text-gray-900 hover:text-gray-300 mr-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M23 3a10.9 10.9 0 0 1-3.14 1.53 4.48 4.48 0 0 0-7.86 3v1A10.66 10.66 0 0 1 3 4s-4 9 5 13a11.64 11.64 0 0 1-7 2c9 5 20 0 20-11.5a4.5 4.5 0 0 0-.08-.83A7.72 7.72 0 0 0 23 3z"></path>
                        </svg>
                    </a>
                    <a target="_blank" href="https://github.com/invoiceninja/invoiceninja">
                        <svg class="text-gray-900 hover:text-gray-300 mr-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M9 19c-5 1.5-5-2.5-7-3m14 6v-3.87a3.37 3.37 0 0 0-.94-2.61c3.14-.35 6.44-1.54 6.44-7A5.44 5.44 0 0 0 20 4.77 5.07 5.07 0 0 0 19.91 1S18.73.65 16 2.48a13.38 13.38 0 0 0-7 0C6.27.65 5.09 1 5.09 1A5.07 5.07 0 0 0 5 4.77a5.44 5.44 0 0 0-1.5 3.78c0 5.42 3.3 6.61 6.44 7A3.37 3.37 0 0 0 9 18.13V22"></path>
                        </svg> </a>
                    <a target="_blank" href="https://www.invoiceninja.com/contact">
                        <svg class="text-gray-900 hover:text-gray-300 mr-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                            <polyline points="22,6 12,13 2,6"></polyline>
                        </svg>
                    </a>
                    <a target="_blank" href="https://www.youtube.com/channel/UCXAHcBvhW05PDtWYIq7WDFA">
                        <svg class="text-gray-900 hover:text-gray-300 mr-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"></path>
                        </svg>
                    </a>
                </div>
            </div>
            @endif
        </div>
        <div class="flex-shrink-0 w-14"></div>
    </div>
</div>
