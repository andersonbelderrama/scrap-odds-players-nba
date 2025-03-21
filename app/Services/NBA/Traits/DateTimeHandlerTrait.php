<?php

namespace App\Services\NBA\Traits;

use Illuminate\Support\Facades\Log;

trait DateTimeHandlerTrait
{
      /**
       * Converte uma data em formato brasileiro textual para o formato Y-m-d
       * 
       * @param string $dateString Data no formato "dia de mês, ano" (ex: "sábado, 15 de março, 2025")
       * @return string Data no formato Y-m-d
       */
      protected function parseGameDate($dateString)
      {
            // Converter string de data (ex: "sábado, 15 de março, 2025") para formato Y-m-d
            try {
                  $months = [
                        'janeiro' => '01',
                        'fevereiro' => '02',
                        'março' => '03',
                        'abril' => '04',
                        'maio' => '05',
                        'junho' => '06',
                        'julho' => '07',
                        'agosto' => '08',
                        'setembro' => '09',
                        'outubro' => '10',
                        'novembro' => '11',
                        'dezembro' => '12'
                  ];
                  
                  // Extrair dia, mês e ano
                  preg_match('/(\d+) de (\w+), (\d+)/', $dateString, $matches);
                  
                  if (count($matches) >= 4) {
                        $day = str_pad($matches[1], 2, '0', STR_PAD_LEFT);
                        $month = $months[mb_strtolower($matches[2])];
                        $year = $matches[3];
                        
                        return "{$year}-{$month}-{$day}";
                  }
                  
                  // Se não conseguir extrair, retorna a data atual
                  return now()->format('Y-m-d');
            } catch (\Exception $e) {
                  Log::error("Erro ao converter data: {$e->getMessage()}", [
                        'data_string' => $dateString
                  ]);
                  return now()->format('Y-m-d');
            }
      }

      protected function adjustGameDateTime(?string $dateTime)
      {
            if (!$dateTime) {
                  return null;
            }

            try {
                  // Clean and parse the datetime string
                  $cleanDateTime = preg_replace('/\(.*\)/', '', $dateTime);
                  $gameDate = new \DateTime(trim($cleanDateTime));

                  // Add debug log to verify the time adjustment
                  Log::info('Ajustando horário do jogo', [
                        'original' => $cleanDateTime,
                        'ajustado' => $gameDate->modify('-10 minutes')->format('Y-m-d H:i:s')
                  ]);

                  return $gameDate;
            } catch (\Exception $e) {
                  Log::error('Erro ao ajustar horário', [
                        'datetime' => $dateTime,
                        'erro' => $e->getMessage()
                  ]);
                  return null;
            }
      }

      protected function formatGameDate(?\DateTime $dateTime, string $format = 'd/m/Y')
      {
            return $dateTime ? $dateTime->format($format) : 'Data inválida';
      }

      protected function formatGameTime(?\DateTime $dateTime, string $format = 'H:i')
      {
            return $dateTime ? $dateTime->format($format) : 'Hora inválida';
      }

      protected function formatExecutionTime(float $seconds): string
      {
            $hours = floor($seconds / 3600);
            $minutes = floor(($seconds % 3600) / 60);
            $remainingSeconds = $seconds % 60;

            return sprintf(
                  '%02d:%02d:%02d',
                  $hours,
                  $minutes,
                  round($remainingSeconds)
            );
      }
}
