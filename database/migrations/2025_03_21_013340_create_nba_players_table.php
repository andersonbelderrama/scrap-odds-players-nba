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
            Schema::create('nba_players', function (Blueprint $table) {
                  $table->id();
                  $table->string('name');
                  $table->string('team_name');
                  $table->boolean('is_starter')->default(false);
                  $table->string('jersey_number')->nullable();
                  $table->timestamps();

                  // Índice composto para evitar duplicatas
                  $table->unique(['name', 'team_name']);
            });
      }

      /**
       * Reverse the migrations.
       */
      public function down(): void
      {
            Schema::dropIfExists('nba_players');
      }
};
