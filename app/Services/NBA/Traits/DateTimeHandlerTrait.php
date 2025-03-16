<?php

namespace App\Services\NBA\Traits;

use Illuminate\Support\Facades\Log;

trait DateTimeHandlerTrait
{
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
