<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\NBA\PlayerPointsOddsScraperService;

class ScrapeNBAPlayerPointsOddsCommand extends Command
{
      protected $signature = 'nba:scrape-player-points-odds';
      protected $description = 'Captura odds de pontos de jogadores da NBA da Betfair';

      public function handle(PlayerPointsOddsScraperService $scraper)
      {
            $this->info('Iniciando captura de odds de pontos de jogadores NBA...');

            try {
                  $result = $scraper->scrape();

                  $this->info("Processados com sucesso {$result['data']['total_games']} jogos");
                  $this->info("Tempo de execução: {$result['data']['execution_time']} segundos");

                  return Command::SUCCESS;
            } catch (\Exception $e) {
                  $this->error("Erro durante a captura: {$e->getMessage()}");
                  return Command::FAILURE;
            }
      }
}
