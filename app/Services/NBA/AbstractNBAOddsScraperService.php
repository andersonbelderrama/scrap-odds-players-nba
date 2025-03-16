<?php

namespace App\Services\NBA;

use App\Models\Game;
use App\Models\PlayerMarketOdd;
use App\Models\PlayerOdd;
use Illuminate\Support\Facades\Log;
use Nesk\Rialto\Exceptions\Node;
use Nesk\Puphpeteer\Puppeteer;
use Nesk\Rialto\Data\JsFunction;
use App\Services\NBA\Traits\BrowserSetupTrait;
use App\Services\NBA\Traits\DateTimeHandlerTrait;

abstract class AbstractNBAOddsScraperService
{
      use BrowserSetupTrait;
      use DateTimeHandlerTrait;

      protected const NBA_BASE_URL = 'https://www.betfair.bet.br/apostas/basquete/nba/c-10547864';
      protected const NAVIGATION_TIMEOUT = 60000;
      protected const EXTENDED_TIMEOUT = 120000;
      protected const WAIT_TIME = 5000;
      protected const SELECTORS = [
            'mainContainer' => '.e97b8d5265087184-container',
            'tabsContainer' => 'div[class*=baf6b661cffec60b-scrollableTabsContainer]',
            'noContent' => '.ad8debb7840a272d-noContentAvailableContainer'
      ];

      protected $puppeteer;
      protected $browser;
      protected $page;
      protected $startTime;

      abstract protected function getMarketURN(): string;
      abstract protected function getTabName(): string;
      abstract protected function getMarketType(): string;

      public function __construct()
      {
            $this->setupPuppeteer();
            $this->startTime = microtime(true);
      }

      public function scrape()
      {
            try {
                  Log::info("Iniciando scraping de odds de {$this->getMarketType()} NBA");
                  $this->initializeBrowser();

                  $futureGames = $this->scrapeFutureGames();
                  $processedGames = $this->processGames($futureGames);

                  // Salvar no banco de dados
                  $this->saveToDatabase($processedGames);

                  $response = $this->buildSuccessResponse($processedGames);

                  // Comentado para usar apenas em depuração
                  //$this->saveTemporaryData($response); // Added this line

                  return $response;
            } catch (Node\Exception $exception) {
                  $this->logError($exception);
                  throw $exception;
            } finally {
                  $this->cleanup();
            }
      }

      protected function scrapeFutureGames()
      {
            $this->navigateToMainPage();
            Log::info('Buscando jogos futuros NBA');

            return $this->page->evaluate(JsFunction::createWithBody($this->getFutureGamesScript()));
      }

      protected function processGames(array $games)
      {
            $processedGames = [];

            foreach ($games as $game) {
                  if (!$this->validateGame($game)) {
                        continue;
                  }

                  // Adjust the game time before processing
                  $game['gameDateTime'] = $this->adjustGameDateTime($game['gameDateTime'])->format('c');

                  Log::info('Processando jogo', [
                        'confronto' => "{$game['homeTeam']} vs {$game['awayTeam']}",
                        'horario_original' => $game['gameDateTime']
                  ]);

                  try {
                        $this->navigateToGamePage($game);
                        $playerOdds = $this->extractPlayerOdds($game);

                        $game['player_odds'] = $playerOdds ?? [];
                        $processedGames[] = $game;
                  } catch (\Exception $e) {
                        $this->logGameProcessingError($game, $e);
                        continue;
                  }
            }

            return $processedGames;
      }

      protected function extractPlayerOdds(array $game)
      {
            if (!$this->navigateToPlayerTab()) {
                  return null;
            }

            // Adiciona uma pequena pausa antes de extrair as odds
            $this->page->evaluate(JsFunction::createWithBody('
            return new Promise(resolve => setTimeout(resolve, 2000));
        '));

            $odds = $this->page->evaluate(JsFunction::createWithBody($this->getPlayerOddsExtractionScript()));

            if (isset($odds['error'])) {
                  $this->logOddsExtractionError($game, $odds);
                  return null;
            }

            // Adicionar log de sucesso
            if (!empty($odds['data']['players'])) {
                  Log::info('Odds extraídas com sucesso', [
                        'jogo' => "{$game['homeTeam']} vs {$game['awayTeam']}",
                        'total_jogadores' => $odds['data']['totalPlayers'],
                        'tipos_odds' => $odds['data']['oddsTypes'],
                        'timestamp' => $odds['data']['timestamp']
                  ]);
            } else {
                  Log::warning('Nenhum jogador encontrado', [
                        'jogo' => "{$game['homeTeam']} vs {$game['awayTeam']}",
                        'timestamp' => $odds['data']['timestamp'] ?? null
                  ]);
            }

            return $odds['data']['players'] ?? null;
      }

      protected function navigateToPlayerTab()
      {
            Log::info('Procurando aba de jogadores');
            $hasPlayerTab = $this->page->evaluate(JsFunction::createWithBody($this->getPlayerTabScript()));

            if (!$hasPlayerTab) {
                  Log::warning('Aba de jogadores não encontrada');
                  return false;
            }

            $this->page->evaluate(JsFunction::createWithBody('
            return new Promise(resolve => setTimeout(resolve, 8000));
        '));

            return true;
      }

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

      protected function buildSuccessResponse(array $games)
      {
            return [
                  'success' => true,
                  'data' => [
                        'games' => $games,
                        'total_games' => count($games),
                        'execution_time' => $this->formatExecutionTime(microtime(true) - $this->startTime),
                        'timestamp' => now()->toIso8601String()
                  ]
            ];
      }

      protected function waitForSelector(string $selector, int $timeout = self::NAVIGATION_TIMEOUT)
      {
            try {
                  $this->page->waitForSelector($selector, ['timeout' => $timeout]);
                  return true;
            } catch (\Exception $e) {
                  Log::warning("Timeout waiting for selector: {$selector}", [
                        'timeout' => $timeout,
                        'error' => $e->getMessage()
                  ]);
                  return false;
            }
      }

      protected function navigateToMainPage()
      {
            $this->page->goto(self::NBA_BASE_URL, [
                  'waitUntil' => 'networkidle0',
                  'timeout' => self::NAVIGATION_TIMEOUT
            ]);

            if (!$this->waitForSelector(self::SELECTORS['mainContainer'])) {
                  throw new \RuntimeException('Failed to load NBA main page');
            }
      }

      protected function navigateToGamePage(array $game)
      {
            $gameUrl = "https://www.betfair.bet.br/apostas/" . $game['link'];
            Log::info('Acessando página do jogo', ['url' => $gameUrl]);

            $this->page->goto($gameUrl, [
                  'waitUntil' => 'networkidle0',
                  'timeout' => self::NAVIGATION_TIMEOUT
            ]);

            if (!$this->waitForSelector(self::SELECTORS['tabsContainer'], self::EXTENDED_TIMEOUT)) {
                  throw new \RuntimeException('Failed to load game page');
            }
      }

      protected function validateGame(array $game): bool
      {
            $required = ['homeTeam', 'awayTeam', 'link', 'gameDateTime'];

            foreach ($required as $field) {
                  if (!isset($game[$field]) || empty($game[$field])) {
                        Log::warning('Invalid game data', [
                              'missing_field' => $field,
                              'game' => $game
                        ]);
                        return false;
                  }
            }

            return true;
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

      protected function logGameProcessingError(array $game, \Exception $error)
      {
            Log::error('Erro ao processar jogo', [
                  'jogo' => "{$game['homeTeam']} vs {$game['awayTeam']}",
                  'erro' => $error->getMessage(),
                  'stack' => $error->getTraceAsString()
            ]);
      }

      protected function logOddsExtractionError(array $game, array $odds)
      {
            $gameDate = $this->adjustGameDateTime($game['gameDateTime']);

            Log::error('Erro ao extrair odds', [
                  'erro' => $odds['error'],
                  'tipo' => $odds['type'] ?? 'unknown',
                  'jogo' => "{$game['homeTeam']} vs {$game['awayTeam']}",
                  'data_jogo' => $this->formatGameDate($gameDate),
                  'hora_jogo' => $this->formatGameTime($gameDate),
                  'data_original' => $game['gameDateTime'],
                  'detalhes' => $odds['debug'] ?? []
            ]);
      }

      protected function getFutureGamesScript()
      {
            return '
        return Array.from(document.querySelectorAll(".e050104376d5250d-couponContainer")).map(game => {
            const isLive = game.querySelector("._73836e21fc8d6105-status");
            if (!isLive) {
                const teams = Array.from(game.querySelectorAll("._443c5e6894fef559-teamNameLabel")).map(team => team.textContent.trim());
                const link = game.querySelector("a").getAttribute("href");
                const dateTimeElement = game.querySelector("time[class*=_73836e21fc8d6105-datetime]");
                const gameDateTime = dateTimeElement ? dateTimeElement.getAttribute("datetime") : null;
                
                return {
                    homeTeam: teams[0],
                    awayTeam: teams[1],
                    link: link,
                    gameDateTime: gameDateTime
                };
            }
            return null;
        }).filter(game => game !== null);
        ';
      }

      protected function getPlayerTabScript()
      {
            return '
        function findAndClickPlayerTab() {
            const tabs = document.querySelectorAll("button[class*=_2d4e28c0fbd008c6-tab]");
            const playerTab = Array.from(tabs).find(tab => {
                const titleSpan = tab.querySelector("span[class*=_2d4e28c0fbd008c6-title]");
                return titleSpan && titleSpan.textContent.trim() === "' . $this->getTabName() . '";
            });
        
            if (playerTab) {
                console.log("Tab ' . $this->getTabName() . ' encontrada");
                playerTab.click();
                return true;
            }
            
            console.log("Tab ' . $this->getTabName() . ' não encontrada");
            return false;
        }
        return findAndClickPlayerTab();
        ';
      }

      protected function getPlayerOddsExtractionScript()
      {
            return '
        function extractPlayerOdds() {
            const pointsSection = document.querySelector(\'div[data-urn^="' . $this->getMarketURN() . '"]\');
            
            if (!pointsSection) {
                const noContentContainer = document.querySelector(".ad8debb7840a272d-noContentAvailableContainer");
                if (noContentContainer) {
                    return { 
                        error: "Mercado de ' . $this->getMarketType() . ' não disponível",
                        type: "no_market_available"
                    };
                }
                return { 
                    error: "Seção de ' . $this->getMarketType() . ' não encontrada",
                    type: "section_not_found"
                };
            }

            // Adiciona verificação do botão "Mostrar mais"
            const showMoreButton = pointsSection.querySelector("button[class*=e938788a425ceff5-showMoreButton]");
            if (showMoreButton) {
                showMoreButton.click();
                // Aguarda o carregamento dos novos elementos
                return new Promise(resolve => {
                    setTimeout(() => {
                        resolve(extractPlayerOddsData(pointsSection));
                    }, 1000);
                });
            }

            return extractPlayerOddsData(pointsSection);
        }

        function extractPlayerOddsData(pointsSection) {
            const marketContainer = pointsSection.querySelector("div[class*=_859d54077a1da3b1-pebbleMarketCardContainer]");
            
            if (!marketContainer) {
                return { 
                    error: "Container principal não encontrado",
                    debug: { markets: Array.from(pointsSection.querySelectorAll("div")).map(d => d.className) }
                };
            }

            const gridRunnerList = marketContainer.querySelector("div[class*=f951d35abcad3586-gridRunnerList]");
            
            if (!gridRunnerList) {
                return { 
                    error: "Lista de jogadores não encontrada",
                    debug: { containerHtml: marketContainer.outerHTML }
                };
            }

            const oddsColumns = marketContainer.querySelectorAll("span[class*=aba60753d8d80695-column]");
            const oddsTypes = Array.from(oddsColumns).map(col => col.textContent.trim());
            const playerLines = gridRunnerList.querySelectorAll("div[class*=_1e758322da13703b-runnerLine]");
            
            if (playerLines.length === 0) {
                return { 
                    error: "Nenhuma linha de jogador encontrada",
                    debug: { 
                        gridHtml: gridRunnerList.outerHTML,
                        oddsTypes: oddsTypes 
                    }
                };
            }

            const players = [];
            playerLines.forEach(line => {
                const nameElement = line.querySelector("p[class*=_1e758322da13703b-runnerName]");
                if (!nameElement) return;

                const name = nameElement.textContent.trim();
                const oddButtons = line.querySelectorAll("button[class*=c84e4011151df22b-button]");
                
                const odds = {};
                oddButtons.forEach((button, index) => {
                    const oddLabel = button.querySelector("span[class*=c84e4011151df22b-label]");
                    if (oddLabel && oddsTypes[index]) {
                        odds[oddsTypes[index]] = oddLabel.textContent.trim();
                    }
                });
                
                if (name && Object.keys(odds).length > 0) {
                    players.push({ name, odds });
                }
            });
            
            return {
                success: true,
                data: {
                    players: players,
                    totalPlayers: players.length,
                    oddsTypes: oddsTypes,
                    timestamp: new Date().toISOString()
                }
            };
        }
        
        return extractPlayerOdds();
        ';
      }

      protected function saveToDatabase(array $processedGames): void
      {
            foreach ($processedGames as $gameData) {
                  $game = Game::updateOrCreate(
                        [
                              'home_team' => $gameData['homeTeam'],
                              'away_team' => $gameData['awayTeam'],
                              'game_datetime' => $gameData['gameDateTime']
                        ],
                        [
                              'betfair_link' => $gameData['link']
                        ]
                  );

                  if (!empty($gameData['player_odds'])) {
                        foreach ($gameData['player_odds'] as $oddData) {
                              $oddsArray = [];
                              foreach ($oddData['odds'] as $line => $odd) {
                                    $oddsArray[$line] = $odd;
                              }

                              PlayerMarketOdd::updateOrCreate(
                                    [
                                          'game_id' => $game->id,
                                          'player_name' => $oddData['name'],
                                          'market_type' => $this->getMarketType()
                                    ],
                                    [
                                          'line_type' => 'specific_line',
                                          'odds_data' => $oddsArray
                                    ]
                              );
                        }
                  }
            }
      }

      /*
      * Método para salvar os dados temporários em um arquivo JSON
      * Usar apenas para depuração
      * @param array $response            
      */
      protected function saveTemporaryData(array $response)
      {
            $filename = storage_path('app/temp/nba_odds_' . now()->format('Y-m-d_His') . '.json');

            if (!file_exists(storage_path('app/temp'))) {
                  mkdir(storage_path('app/temp'), 0777, true);
            }

            file_put_contents(
                  $filename,
                  json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
            );

            Log::info('Dados temporários salvos', ['arquivo' => $filename]);

            return $filename;
      }
}
