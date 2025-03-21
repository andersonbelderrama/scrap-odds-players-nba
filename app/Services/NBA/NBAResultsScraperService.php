<?php

namespace App\Services\NBA;

use Illuminate\Support\Facades\Log;
use Nesk\Puphpeteer\Puppeteer;
use Nesk\Rialto\Data\JsFunction;
use Carbon\Carbon;
use App\Services\NBA\Traits\BrowserSetupTrait;
use App\Services\NBA\Traits\DateTimeHandlerTrait;
use App\Models\NBAGame;
use App\Models\NBAPlayer;
use App\Models\NBAPlayerStat;

class NBAResultsScraperService
{
      use BrowserSetupTrait;
      use DateTimeHandlerTrait;

      protected const BASE_URL = 'https://www.espn.com.br/nba/resultados/_/data/';
      protected const NAVIGATION_TIMEOUT = 60000;
      protected const SELECTORS = [
            'gameCards' => 'section.Card.gameModules',
            'gameContainers' => 'section.Scoreboard',
            'boxscoreButton' => 'a.Button[href*="/nba/placar/"]',
      ];

      protected $puppeteer;
      protected $browser;
      protected $page;
      protected $startTime;

      public function __construct()
      {
            $this->setupPuppeteer();
            $this->startTime = microtime(true);
      }

      public function scrape($date = null)
      {
            try {
                  // Se não for fornecida uma data, usa o dia anterior
                  if (!$date) {
                        $date = Carbon::yesterday()->format('Ymd');
                  }

                  Log::info("Iniciando scraping de resultados da NBA para a data: {$date}");
                  $this->initializeBrowser();

                  // Navega para a página de resultados da data específica
                  $url = self::BASE_URL . $date;
                  $this->navigateToPage($url);

                  // Extrai os jogos da página
                  $games = $this->scrapeGames();

                  if (empty($games)) {
                        Log::warning("Nenhum jogo encontrado para a data: {$date}");
                        return [];
                  }

                  Log::info("Encontrados " . count($games) . " jogos para a data: {$date}");

                  // Para cada jogo, extrai detalhes da ficha
                  $processedGames = $this->processGames($games);

                  // Salva os dados em um arquivo JSON
                  $jsonFile = $this->saveTemporaryData($processedGames);

                  // Salva os dados no banco de dados
                  $this->saveToDatabase($processedGames);

                  Log::info("Scraping concluído com sucesso. Dados salvos em: {$jsonFile}");

                  return $processedGames;
            } catch (\Exception $exception) {
                  $this->logError($exception);
                  throw $exception;
            } finally {
                  $this->cleanup();
            }
      }

      // Removido o método setupPuppeteer pois já está na trait BrowserSetupTrait

      // Removido o método initializeBrowser pois já está na trait BrowserSetupTrait

      protected function navigateToPage($url)
      {
            Log::info("Navegando para: {$url}");
            $this->page->goto($url, [
                  'waitUntil' => 'networkidle0',
                  'timeout' => self::NAVIGATION_TIMEOUT
            ]);

            // Substituindo waitForTimeout por uma alternativa
            $this->page->evaluate('() => new Promise(resolve => setTimeout(resolve, 2000))');
      }

      protected function waitForSelector($selector, $timeout = 30000)
      {
            try {
                  $this->page->waitForSelector($selector, ['timeout' => $timeout]);
                  return true;
            } catch (\Exception $e) {
                  Log::warning("Timeout ao esperar pelo seletor: {$selector}", [
                        'timeout' => $timeout,
                        'erro' => $e->getMessage()
                  ]);
                  return false;
            }
      }

      protected function scrapeGames()
      {
            Log::info('Extraindo jogos da página de resultados');

            return $this->page->evaluate(JsFunction::createWithBody('
                // Seletor principal para o card de jogos
                const gameCards = document.querySelectorAll("section.Card.gameModules");
                const games = [];
                
                gameCards.forEach(card => {
                    // Extrair a data do cabeçalho do card
                    const gameDate = card.querySelector("h3.Card__Header__Title")?.textContent.trim();
                    
                    // Selecionar todos os jogos dentro do card
                    const gameContainers = card.querySelectorAll("section.Scoreboard");
                    
                    gameContainers.forEach(container => {
                        // Extrair ID do jogo
                        const gameId = container.id;
                        
                        // Selecionar os competidores
                        const competitors = container.querySelectorAll("li.ScoreboardScoreCell__Item");
                        if (competitors.length !== 2) return;
                        
                        // Primeiro item é o time visitante, segundo é o time da casa
                        const awayTeam = competitors[0];
                        const homeTeam = competitors[1];
                        
                        // Extrair nomes dos times
                        const awayTeamName = awayTeam.querySelector("div.ScoreCell__TeamName")?.textContent.trim();
                        const homeTeamName = homeTeam.querySelector("div.ScoreCell__TeamName")?.textContent.trim();
                        
                        // Extrair pontuações
                        const awayScore = awayTeam.querySelector("div.ScoreCell__Score")?.textContent.trim();
                        const homeScore = homeTeam.querySelector("div.ScoreCell__Score")?.textContent.trim();
                        
                        // Extrair pontuação por período
                        const awayPeriods = Array.from(awayTeam.querySelectorAll("div.ScoreboardScoreCell__Value")).map(el => el.textContent.trim());
                        const homePeriods = Array.from(homeTeam.querySelectorAll("div.ScoreboardScoreCell__Value")).map(el => el.textContent.trim());
                        
                        // Extrair labels dos períodos
                        const periodLabels = Array.from(container.querySelectorAll("div.ScoreboardScoreCell__Heading")).map(el => el.textContent.trim());
                        
                        // Formatar períodos
                        let periods = [];
                        for (let i = 0; i < periodLabels.length - 1; i++) { // -1 para ignorar o "E" (total)
                            periods.push({
                                period: periodLabels[i],
                                awayScore: awayPeriods[i],
                                homeScore: homePeriods[i]
                            });
                        }
                        
                        // Extrair link da ficha
                        const boxscoreLink = container.querySelector("a.Button[href*=\"/nba/placar/\"]")?.getAttribute("href");
                        
                        games.push({
                            gameDate,
                            gameId,
                            boxscoreLink,
                            homeTeam: {
                                name: homeTeamName,
                                score: homeScore
                            },
                            awayTeam: {
                                name: awayTeamName,
                                score: awayScore
                            },
                            periods,
                            players: []
                        });
                    });
                });
                
                return games;
            '));
      }

      protected function processGames($games)
      {
            $processedGames = [];

            foreach ($games as $index => $game) {
                  try {
                        Log::info("Processando jogo " . ($index + 1) . " de " . count($games) . ": {$game['awayTeam']['name']} vs {$game['homeTeam']['name']}");

                        if (!empty($game['boxscoreLink'])) {
                              // Navega para a página da ficha
                              $boxscoreUrl = "https://www.espn.com.br" . $game['boxscoreLink'];
                              Log::info("Navegando para ficha do jogo: {$boxscoreUrl}");

                              $this->page->goto($boxscoreUrl, [
                                    'waitUntil' => 'networkidle0',
                                    'timeout' => self::NAVIGATION_TIMEOUT
                              ]);

                              // Substituindo waitForTimeout por uma alternativa
                              $this->page->evaluate('() => new Promise(resolve => setTimeout(resolve, 2000))');

                              // Extrai os dados dos jogadores
                              $playerStats = $this->scrapePlayerStats();

                              if (!empty($playerStats['homeTeamPlayers']) || !empty($playerStats['awayTeamPlayers'])) {
                                    Log::info("Estatísticas extraídas com sucesso: " .
                                          count($playerStats['homeTeamPlayers']) . " jogadores do time da casa, " .
                                          count($playerStats['awayTeamPlayers']) . " jogadores do time visitante");

                                    $game['players'] = $playerStats;
                              } else {
                                    Log::warning("Nenhuma estatística de jogador encontrada para este jogo");
                              }
                        } else {
                              Log::warning("Link da ficha não encontrado para o jogo: {$game['awayTeam']['name']} vs {$game['homeTeam']['name']}");
                        }

                        $processedGames[] = $game;
                  } catch (\Exception $e) {
                        Log::error("Erro ao processar jogo " . ($index + 1), [
                              'jogo' => "{$game['awayTeam']['name']} vs {$game['homeTeam']['name']}",
                              'erro' => $e->getMessage()
                        ]);

                        // Adiciona o jogo mesmo com erro, mas sem as estatísticas dos jogadores
                        $processedGames[] = $game;
                  }
            }

            return $processedGames;
      }

      protected function scrapePlayerStats()
      {
            Log::info('Extraindo estatísticas dos jogadores da ficha');

            return $this->page->evaluate(JsFunction::createWithBody('
                // Função para extrair estatísticas de uma tabela de jogadores
                function extractPlayerStats(playerTable, statsTable) {
                    const players = [];
                    let currentCategory = "";
                    let isStarter = false;
                    
                    // Extrair nomes dos jogadores e categorias
                    const playerRows = playerTable.querySelectorAll("tr.Table__TR");
                    const statsRows = statsTable.querySelectorAll("tr.Table__TR");
                    
                    playerRows.forEach((row, index) => {
                        // Verificar se é uma categoria (titulares, reservas, etc)
                        const categoryHeader = row.querySelector("div.Table__customHeader");
                        if (categoryHeader) {
                            currentCategory = categoryHeader.textContent.trim();
                            isStarter = currentCategory.toLowerCase() === "titulares";
                            
                            // Adicionar categoria como "jogador"
                            players.push({
                                name: currentCategory,
                                isStarter: isStarter,
                                stats: []
                            });
                            return;
                        }
                        
                        // Extrair nome do jogador
                        const playerNameElement = row.querySelector("span.Boxscore__AthleteName--long");
                        const playerShortNameElement = row.querySelector("span.Boxscore__AthleteName--short");
                        const jerseyElement = row.querySelector("span.playerJersey");
                        
                        if (playerNameElement && statsRows[index]) {
                            const playerName = playerNameElement.textContent.trim();
                            const playerShortName = playerShortNameElement ? playerShortNameElement.textContent.trim() : "";
                            const jersey = jerseyElement ? jerseyElement.textContent.replace("#", "") : "";
                            
                            // Extrair estatísticas do jogador
                            const statCells = statsRows[index].querySelectorAll("td.Table__TD");
                            const stats = {};
                            
                            if (statCells.length > 0) {
                                // Verificar se é uma linha "NJ-Decisão do técnico"
                                const colspan = statsRows[index].querySelector("td[colspan]");
                                if (colspan) {
                                    stats.status = colspan.textContent.trim();
                                } else {
                                    // Extrair estatísticas normais
                                    stats.minutes = statCells[0] ? statCells[0].textContent.trim() : "";
                                    stats.fieldGoals = statCells[1] ? statCells[1].textContent.trim() : "";
                                    stats.threePoints = statCells[2] ? statCells[2].textContent.trim() : "";
                                    stats.freeThrows = statCells[3] ? statCells[3].textContent.trim() : "";
                                    stats.offRebounds = statCells[4] ? statCells[4].textContent.trim() : "";
                                    stats.defRebounds = statCells[5] ? statCells[5].textContent.trim() : "";
                                    stats.totalRebounds = statCells[6] ? statCells[6].textContent.trim() : "";
                                    stats.assists = statCells[7] ? statCells[7].textContent.trim() : "";
                                    stats.steals = statCells[8] ? statCells[8].textContent.trim() : "";
                                    stats.blocks = statCells[9] ? statCells[9].textContent.trim() : "";
                                    stats.turnovers = statCells[10] ? statCells[10].textContent.trim() : "";
                                    stats.personalFouls = statCells[11] ? statCells[11].textContent.trim() : "";
                                    stats.plusMinus = statCells[12] ? statCells[12].textContent.trim() : "";
                                    stats.points = statCells[13] ? statCells[13].textContent.trim() : "";
                                }
                            }
                            
                            players.push({
                                name: playerName + (playerShortName ? " (" + playerShortName + ")" : "") + (jersey ? " #" + jersey : ""),
                                isStarter: isStarter,
                                stats: stats
                            });
                        }
                    });
                    
                    return players;
                }
                
                // Encontrar as tabelas de jogadores e estatísticas
                const tables = document.querySelectorAll("div.ResponsiveTable");
                if (tables.length < 2) return { homeTeamPlayers: [], awayTeamPlayers: [] };
                
                // Extrair nome das equipes
                const teamNames = document.querySelectorAll("div.Gamestrip__Team");
                const homeTeamName = teamNames.length >= 2 ? teamNames[1].querySelector("span.Gamestrip__Team__Name")?.textContent.trim() : "";
                const awayTeamName = teamNames.length >= 1 ? teamNames[0].querySelector("span.Gamestrip__Team__Name")?.textContent.trim() : "";
                
                // Processar tabelas para home e away
                let homeTeamPlayers = [];
                let awayTeamPlayers = [];
                
                // Cada ResponsiveTable contém uma tabela para nomes e outra para estatísticas
                tables.forEach((table, index) => {
                    const playerTable = table.querySelector("table.Table--fixed-left");
                    const statsTable = table.querySelector("div.Table__Scroller table");
                    
                    if (!playerTable || !statsTable) return;
                    
                    const players = extractPlayerStats(playerTable, statsTable);
                    
                    // Primeira tabela geralmente é do time da casa, segunda do visitante
                    if (index === 0) {
                        homeTeamPlayers = players;
                    } else if (index === 1) {
                        awayTeamPlayers = players;
                    }
                });
                
                return {
                    homeTeamName: homeTeamName || "Time da Casa",
                    awayTeamName: awayTeamName || "Time Visitante",
                    homeTeamPlayers: homeTeamPlayers,
                    awayTeamPlayers: awayTeamPlayers
                };
            '));
      }

      // Add the cleanup method if it's not properly loaded from the trait
      protected function cleanup()
      {
            if ($this->browser) {
                  $executionTime = microtime(true) - $this->startTime;
                  Log::info('Finalizando scraping', [
                        'tempo_execucao' => $this->formatExecutionTime($executionTime),
                        'memoria_utilizada' => round(memory_get_peak_usage(true) / 1024 / 1024, 2) . ' MB'
                  ]);
                  $this->browser->close();
            }
      }

      protected function formatExecutionTime($seconds)
      {
            $minutes = floor($seconds / 60);
            $seconds = $seconds % 60;

            return sprintf('%02d:%02.2f', $minutes, $seconds);
      }

      protected function logError(\Exception $exception)
      {
            Log::error('Erro durante o scraping', [
                  'mensagem' => $exception->getMessage(),
                  'arquivo' => $exception->getFile(),
                  'linha' => $exception->getLine(),
                  'trace' => $exception->getTraceAsString()
            ]);
      }

      protected function saveTemporaryData(array $data)
      {
            $filename = storage_path('app/temp/nba_results_' . now()->format('Y-m-d_His') . '.json');

            if (!file_exists(storage_path('app/temp'))) {
                  mkdir(storage_path('app/temp'), 0777, true);
            }

            file_put_contents(
                  $filename,
                  json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
            );

            Log::info('Dados temporários salvos', ['arquivo' => $filename]);

            return $filename;
      }

      protected function saveToDatabase($games)
      {
            Log::info("Salvando dados no banco de dados...");

            foreach ($games as $gameData) {
                  try {
                        // Converter a data do jogo para o formato correto
                        $gameDate = $this->parseGameDate($gameData['gameDate']);

                        // Criar ou atualizar o jogo
                        $game = NBAGame::updateOrCreate(
                              ['game_id' => $gameData['gameId']],
                              [
                                    'game_date' => $gameDate,
                                    'home_team_name' => $gameData['homeTeam']['name'],
                                    'home_team_score' => $gameData['homeTeam']['score'],
                                    'away_team_name' => $gameData['awayTeam']['name'],
                                    'away_team_score' => $gameData['awayTeam']['score'],
                                    'periods' => $gameData['periods'],
                                    'boxscore_link' => $gameData['boxscoreLink'],
                              ]
                        );

                        // Processar jogadores e estatísticas
                        if (!empty($gameData['players'])) {
                              $this->savePlayerStats($game, $gameData['players']);
                        }

                        Log::info("Jogo salvo: {$gameData['awayTeam']['name']} vs {$gameData['homeTeam']['name']}");
                  } catch (\Exception $e) {
                        Log::error("Erro ao salvar jogo: {$e->getMessage()}", [
                              'game_id' => $gameData['gameId'] ?? 'N/A',
                              'trace' => $e->getTraceAsString()
                        ]);
                  }
            }

            Log::info("Dados salvos no banco de dados com sucesso!");
      }

      protected function savePlayerStats($game, $playersData)
      {
            // Processar jogadores do time da casa
            if (!empty($playersData['homeTeamPlayers'])) {
                  $this->processTeamPlayers($game, $playersData['homeTeamPlayers'], $game->home_team_name);
            }

            // Processar jogadores do time visitante
            if (!empty($playersData['awayTeamPlayers'])) {
                  $this->processTeamPlayers($game, $playersData['awayTeamPlayers'], $game->away_team_name);
            }
      }

      protected function processTeamPlayers($game, $players, $teamName)
      {
            $isStarter = false;

            foreach ($players as $playerData) {
                  // Pular categorias (titulares, reservas, etc)
                  if (in_array(strtolower($playerData['name']), ['titulares', 'reservas', 'team', ''])) {
                        $isStarter = strtolower($playerData['name']) === 'titulares';
                        continue;
                  }

                  // Extrair número da camisa se disponível
                  $jerseyNumber = null;
                  if (preg_match('/#(\d+)$/', $playerData['name'], $matches)) {
                        $jerseyNumber = $matches[1];
                        $playerName = trim(str_replace(" #{$jerseyNumber}", '', $playerData['name']));
                  } else {
                        $playerName = $playerData['name'];
                  }

                  // Criar ou atualizar o jogador
                  $player = NBAPlayer::updateOrCreate(
                        [
                              'name' => $playerName,
                              'team_name' => $teamName
                        ],
                        [
                              'is_starter' => $isStarter,
                              'jersey_number' => $jerseyNumber
                        ]
                  );

                  // Criar ou atualizar as estatísticas do jogador
                  if (!empty($playerData['stats'])) {
                        $statsData = [
                              'nba_game_id' => $game->id,
                              'nba_player_id' => $player->id
                        ];

                        // Se o jogador tem status (NJ-Decisão do técnico, etc)
                        if (isset($playerData['stats']['status'])) {
                              $statsData['status'] = $playerData['stats']['status'];
                        } else {
                              // Estatísticas normais
                              $statsData = array_merge($statsData, [
                                    'minutes' => $playerData['stats']['minutes'] ?? null,
                                    'field_goals' => $playerData['stats']['fieldGoals'] ?? null,
                                    'three_points' => $playerData['stats']['threePoints'] ?? null,
                                    'free_throws' => $playerData['stats']['freeThrows'] ?? null,
                                    'off_rebounds' => $playerData['stats']['offRebounds'] ?? null,
                                    'def_rebounds' => $playerData['stats']['defRebounds'] ?? null,
                                    'total_rebounds' => $playerData['stats']['totalRebounds'] ?? null,
                                    'assists' => $playerData['stats']['assists'] ?? null,
                                    'steals' => $playerData['stats']['steals'] ?? null,
                                    'blocks' => $playerData['stats']['blocks'] ?? null,
                                    'turnovers' => $playerData['stats']['turnovers'] ?? null,
                                    'personal_fouls' => $playerData['stats']['personalFouls'] ?? null,
                                    'plus_minus' => $playerData['stats']['plusMinus'] ?? null,
                                    'points' => $playerData['stats']['points'] ?? null
                              ]);
                        }

                        NBAPlayerStat::updateOrCreate(
                              [
                                    'nba_game_id' => $game->id,
                                    'nba_player_id' => $player->id
                              ],
                              $statsData
                        );
                  }
            }
      }
}
