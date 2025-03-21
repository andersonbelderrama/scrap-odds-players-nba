<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NBAPlayerStat extends Model
{
    use HasFactory;

    protected $fillable = [
        'nba_game_id',
        'nba_player_id',
        'minutes',
        'field_goals',
        'three_points',
        'free_throws',
        'off_rebounds',
        'def_rebounds',
        'total_rebounds',
        'assists',
        'steals',
        'blocks',
        'turnovers',
        'personal_fouls',
        'plus_minus',
        'points',
        'status',
    ];

    public function game()
    {
        return $this->belongsTo(NBAGame::class, 'nba_game_id');
    }

    public function player()
    {
        return $this->belongsTo(NBAPlayer::class, 'nba_player_id');
    }
}
