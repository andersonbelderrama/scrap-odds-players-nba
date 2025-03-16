<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class Game extends Model
{
      protected $fillable = [
            'home_team',
            'away_team',
            'game_datetime',
            'betfair_link'
      ];

      protected $casts = [
            'game_datetime' => 'datetime'
      ];

      public function playerMarketOdds()
      {
            return $this->hasMany(PlayerMarketOdd::class);
      }

      protected static function boot()
      {
            parent::boot();

            static::saving(function ($model) {
                  $exists = static::where('id', '!=', $model->id)
                        ->whereDate('game_datetime', $model->game_datetime)
                        ->where(function ($query) use ($model) {
                              $query->where('home_team', $model->home_team)
                                    ->where('away_team', $model->away_team);
                        })
                        ->exists();

                  if ($exists) {
                        Log::error('Duplicidade de jogo encontrada', [
                              'home_team' => $model->home_team,
                              'away_team' => $model->away_team,
                              'game_datetime' => $model->game_datetime
                        ]);
                        return false;
                  }

                  return true;
            });
      }
}
