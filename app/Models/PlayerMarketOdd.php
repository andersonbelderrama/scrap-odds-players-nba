<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class PlayerMarketOdd extends Model
{
      protected $fillable = [
            'game_id',
            'player_name',
            'market_type',
            'line_type',
            'odds_data'
      ];

      protected $casts = [
            'odds_data' => 'array'
      ];

      public function game()
      {
            return $this->belongsTo(Game::class);
      }

      protected static function boot()
      {
            parent::boot();

            static::saving(function ($model) {
                  // Verifica se já existe um registro para o mesmo jogador no mesmo jogo e tipo de mercado
                  $exists = static::where('id', '!=', $model->id)
                        ->where('game_id', $model->game_id)
                        ->where('player_name', $model->player_name)
                        ->where('market_type', $model->market_type)
                        ->exists();

                  if ($exists) {
                        Log::error('Duplicidade encontrada', [
                              'player' => $model->player_name,
                              'game_id' => $model->game_id,
                              'market_type' => $model->market_type
                        ]);
                        return false; // Impede o salvamento mas não interrompe o serviço
                  }

                  return true;
            });
      }
}
