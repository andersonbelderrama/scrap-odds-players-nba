<?php

namespace App\Livewire\NBA\PlayerMarkets;

use App\Models\Game;
use App\Models\PlayerMarketOdd;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Title;

#[Title('NBA Player Points Market')]
class PlayerPointsMarket extends Component
{
    use WithPagination;

    public $search = '';
    public $dateFilter = '';
    public $teamFilter = '';
    public $perPage = 10;
    public $sortField = 'game_datetime';
    public $sortDirection = 'desc';

    protected $queryString = [
        'search' => ['except' => ''],
        'dateFilter' => ['except' => ''],
        'teamFilter' => ['except' => ''],
        'sortField' => ['except' => 'game_datetime'],
        'sortDirection' => ['except' => 'desc'],
    ];

    public function mount()
    {
        $this->dateFilter = now()->setTimezone('America/Sao_Paulo')->format('Y-m-d');
    }

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingDateFilter()
    {
        $this->resetPage();
    }

    public function updatingTeamFilter()
    {
        $this->resetPage();
    }

    public function sortBy($field)
    {
        $this->resetPage();
        
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'desc';
        }
    }

    public function render()
    {
        $query = PlayerMarketOdd::query()
            ->select('player_market_odds.*')
            ->with('game')
            ->where('market_type', 'points');

        // Filtros permanecem iguais
        if ($this->search) {
            $query->where(function ($q) {
                $q->where('player_name', 'like', '%' . $this->search . '%')
                    ->orWhereHas('game', function ($q) {
                        $q->where('home_team', 'like', '%' . $this->search . '%')
                            ->orWhere('away_team', 'like', '%' . $this->search . '%');
                    });
            });
        }

        if ($this->dateFilter) {
            $query->whereHas('game', function ($q) {
                $q->whereDate('game_datetime', $this->dateFilter);
            });
        }

        if ($this->teamFilter) {
            $query->whereHas('game', function ($q) {
                $q->where('home_team', $this->teamFilter)
                    ->orWhere('away_team', $this->teamFilter);
            });
        }

        // Nova lógica de ordenação
        switch ($this->sortField) {
            case 'game_datetime':
                $query->leftJoin('games', 'games.id', '=', 'player_market_odds.game_id')
                    ->orderBy('games.game_datetime', $this->sortDirection);
                break;
            
            case 'home_team':
                $query->leftJoin('games', 'games.id', '=', 'player_market_odds.game_id')
                    ->orderBy('games.home_team', $this->sortDirection);
                break;
            
            case 'player_name':
                $query->orderBy('player_name', $this->sortDirection);
                break;
            
            default:
                $query->leftJoin('games', 'games.id', '=', 'player_market_odds.game_id')
                    ->orderBy('games.game_datetime', 'desc');
        }

        return view('livewire.nba.player-markets.player-points-market', [
            'odds' => $query->paginate($this->perPage),
            'teams' => Game::select('home_team')
                ->union(Game::select('away_team'))
                ->distinct()
                ->orderBy('home_team')
                ->pluck('home_team'),
            'totalGames' => Game::whereDate('game_datetime', $this->dateFilter)->count()
        ]);
    }
}
