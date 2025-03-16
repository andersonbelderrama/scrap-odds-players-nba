<div class="hidden lg:fixed lg:inset-y-0 lg:z-50 lg:flex lg:w-72 lg:flex-col">
    <div class="flex grow flex-col gap-y-5 overflow-y-auto bg-gray-800 px-6 pb-4">
        <div class="flex h-16 shrink-0 items-center">
            <x-logo />
        </div>
        <nav class="flex flex-1 flex-col">
            <ul role="list" class="flex flex-1 flex-col gap-y-7">
                <li>
                    <div class="text-xs font-semibold leading-6 text-gray-400">Mercados</div>
                    <ul role="list" class="-mx-2 mt-2 space-y-1">
                        <li>
                            <a href="{{ route('player-points-market') }}" 
                               class="{{ request()->routeIs('player-points-market') ? 'bg-gray-700 text-white' : 'text-gray-400 hover:text-white hover:bg-gray-700' }} group flex gap-x-3 rounded-md p-2 text-sm leading-6 font-semibold">
                                <x-icons.chart-bar />
                                Pontos de Jogadores
                            </a>
                        </li>
                    </ul>
                </li>
            </ul>
        </nav>
    </div>
</div>