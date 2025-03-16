<div>
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-200">Mercado de Pontos de Jogadores</h1>
        @if ($totalGames > 0)
            <p class="text-gray-400 mt-3 text-lg">{{ $totalGames }} jogos agendados para
                {{ \Carbon\Carbon::parse($dateFilter)->format('d/m/Y') }}</p>
        @endif
    </div>

    <!-- Filtros -->
    <div class="mb-8 bg-gray-800/50 backdrop-blur-sm rounded-xl shadow-lg border border-gray-700">
        <div class="border-b border-gray-700 px-8 py-4">
            <h2 class="text-lg font-semibold text-gray-200">Filtros de Pesquisa</h2>
        </div>

        <div class="p-8">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 items-end">
                <div class="space-y-3">
                    <label class="block text-base font-medium text-gray-300">Buscar Jogador/Time</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                            <x-heroicon-o-magnifying-glass class="h-5 w-5 text-gray-400" />
                        </div>
                        <input type="text" wire:model.live="search"
                            class="pl-11 block w-full h-12 rounded-lg border-gray-600 bg-gray-700/50 text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 px-4">
                    </div>
                </div>

                <div class="space-y-3">
                    <label class="block text-base font-medium text-gray-300">Data do Jogo</label>
                    <input type="date" wire:model.live="dateFilter"
                        class="block w-full h-12 rounded-lg border-gray-600 bg-gray-700/50 text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 px-4">
                </div>

                <div class="space-y-3">
                    <label class="block text-base font-medium text-gray-300">Time</label>
                    <select wire:model.live="teamFilter"
                        class="block w-full h-12 rounded-lg border-gray-600 bg-gray-700/50 text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 px-4">
                        <option value="">Todos os Times</option>
                        @foreach ($teams as $team)
                            <option value="{{ $team }}">{{ $team }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabela -->
    <div class="bg-gray-800/50 backdrop-blur-sm rounded-xl shadow-lg overflow-hidden border border-gray-700">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-700">
                <thead class="bg-gray-900/50">
                    <tr>
                        @foreach (['game_datetime' => 'Data/Horário', 'home_team' => 'Confronto', 'player_name' => 'Jogador', 'odds' => 'Linhas de Pontuação'] as $field => $label)
                            <!-- Table header sorting icons -->
                            <th wire:click="sortBy('{{ $field }}')"
                                class="px-6 py-4 text-left text-xs font-medium text-gray-300 uppercase tracking-wider cursor-pointer hover:bg-gray-800/50">
                                <div class="flex items-center space-x-1">
                                    <span>{{ $label }}</span>
                                    @if ($sortField === $field)
                                        @if ($sortDirection == 'asc')
                                            <x-heroicon-o-arrow-up class="h-4 w-4" />
                                        @else
                                            <x-heroicon-o-arrow-down class="h-4 w-4" />
                                        @endif
                                    @else
                                        <x-heroicon-o-arrows-up-down class="h-4 w-4 opacity-50" />
                                    @endif
                                </div>
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-700">
                    @forelse($odds as $odd)
                        <tr class="hover:bg-gray-700/50 transition-colors">
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-300">
                                {{ $odd->game->game_datetime->format('d/m/Y H:i') }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-100">
                                {{ $odd->game->home_team }} vs {{ $odd->game->away_team }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-100">
                                {{ $odd->player_name }}
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex flex-wrap gap-2">
                                    @foreach ($odd->odds_data as $line => $oddValue)
                                        <div class="inline-flex items-center bg-gray-700/50 rounded-full px-4 py-2">
                                            <span class="text-sm font-medium text-gray-100">{{ $line }}</span>
                                            <span
                                                class="ml-2 font-medium text-sm text-indigo-400">{{ $oddValue }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-6 py-4 text-center text-sm text-gray-400">
                                Nenhum registro encontrado
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($odds->count() > 0)
            <div class="border-t border-gray-700 px-6 py-4">
                <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
                    <div class="flex items-center space-x-4">
                        <span class="text-sm text-gray-400">Mostrar</span>
                        <select wire:model.live="perPage"
                            class="block w-24 rounded-lg border-gray-600 bg-gray-700/50 text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option>10</option>
                            <option>25</option>
                            <option>50</option>
                            <option>100</option>
                        </select>
                        <span class="text-sm text-gray-400">por página</span>
                    </div>
                    <div>
                        {{ $odds->links('vendor.livewire.tailwind') }}
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>
