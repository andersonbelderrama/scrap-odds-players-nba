<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
      /**
       * Run the migrations.
       */
      public function up(): void
      {
            Schema::create('nba_player_stats', function (Blueprint $table) {
                  $table->id();
                  $table->foreignId('nba_game_id')->constrained('nba_games')->onDelete('cascade');
                  $table->foreignId('nba_player_id')->constrained('nba_players')->onDelete('cascade');
                  $table->string('minutes')->nullable();
                  $table->string('field_goals')->nullable();
                  $table->string('three_points')->nullable();
                  $table->string('free_throws')->nullable();
                  $table->integer('off_rebounds')->nullable();
                  $table->integer('def_rebounds')->nullable();
                  $table->integer('total_rebounds')->nullable();
                  $table->integer('assists')->nullable();
                  $table->integer('steals')->nullable();
                  $table->integer('blocks')->nullable();
                  $table->integer('turnovers')->nullable();
                  $table->integer('personal_fouls')->nullable();
                  $table->string('plus_minus')->nullable();
                  $table->integer('points')->nullable();
                  $table->string('status')->nullable();
                  $table->timestamps();

                  // Índice composto para evitar duplicatas
                  $table->unique(['nba_game_id', 'nba_player_id']);
            });
      }

      /**
       * Reverse the migrations.
       */
      public function down(): void
      {
            Schema::dropIfExists('nba_player_stats');
      }
};
