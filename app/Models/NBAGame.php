<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NBAGame extends Model
{
      use HasFactory;

      protected $fillable = [
            'game_id',
            'game_date',
            'home_team_name',
            'home_team_score',
            'away_team_name',
            'away_team_score',
            'periods',
            'boxscore_link',
      ];

      protected $casts = [
            'game_date' => 'date',
            'periods' => 'array',
      ];

      public function playerStats()
      {
            return $this->hasMany(NBAPlayerStat::class);
      }
}
