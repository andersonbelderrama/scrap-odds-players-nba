<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\NBA\NBAResultsScraperService;
use Carbon\Carbon;

class ScrapeNBAResultsCommand extends Command
{
      protected $signature = 'nba:scrape-results {date? : Data no formato YYYYMMDD (ex: 20250315)}';
      protected $description = 'Extrai resultados de jogos da NBA para uma data específica';

      public function handle()
      {
            $date = $this->argument('date');

            if (!$date) {
                  $date = Carbon::yesterday()->format('Ymd');
                  $this->info("Nenhuma data fornecida. Usando a data de ontem: {$date}");
            } else {
                  // Validar formato da data
                  if (!preg_match('/^\d{8}$/', $date)) {
                        $this->error('Formato de data inválido. Use o formato YYYYMMDD (ex: 20250315)');
                        return 1;
                  }
            }

            $this->info("Iniciando extração de resultados da NBA para a data: {$date}");
            $this->info("URL: https://www.espn.com.br/nba/resultados/_/data/{$date}");

            try {
                  $scraper = new NBAResultsScraperService();
                  $results = $scraper->scrape($date);

                  if (empty($results)) {
                        $this->warn("Nenhum jogo encontrado para a data especificada.");
                        $this->info("Verifique se a data está correta e se houve jogos nesse dia.");
                        $this->info("Tente acessar manualmente: https://www.espn.com.br/nba/resultados/_/data/{$date}");
                  } else {
                        $this->info("Extração concluída com sucesso!");
                        $this->info("Total de jogos extraídos: " . count($results));
                        
                        // Exibe um resumo dos jogos encontrados
                        $this->info("\nResumo dos jogos encontrados:");
                        foreach ($results as $index => $game) {
                              $this->info(($index + 1) . ". {$game['awayTeam']['name']} {$game['awayTeam']['score']} @ {$game['homeTeam']['name']} {$game['homeTeam']['score']}");
                        }
                  }

                  return 0;
            } catch (\Exception $e) {
                  $this->error("Erro durante a extração: " . $e->getMessage());
                  $this->error("Stack trace: " . $e->getTraceAsString());
                  return 1;
            }
      }
}
