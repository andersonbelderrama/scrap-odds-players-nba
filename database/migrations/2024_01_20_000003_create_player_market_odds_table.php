<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('player_market_odds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained()->onDelete('cascade');
            $table->string('player_name');
            $table->string('market_type'); // points, rebounds, etc
            $table->string('line_type')->nullable(); // over_under, specific_line
            $table->json('odds_data'); // Stores all odds variations
            $table->timestamps();
            
            $table->index(['game_id', 'player_name', 'market_type']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('player_market_odds');
    }
};