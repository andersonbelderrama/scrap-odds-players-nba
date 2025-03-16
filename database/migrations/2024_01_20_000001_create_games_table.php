<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('games', function (Blueprint $table) {
            $table->id();
            $table->string('home_team');
            $table->string('away_team');
            $table->string('betfair_link');
            $table->dateTime('game_datetime');
            $table->timestamps();
            
            // Índices para otimização
            $table->index('game_datetime');
            $table->index(['home_team', 'away_team']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('games');
    }
};