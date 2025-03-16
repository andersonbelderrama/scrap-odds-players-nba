<?php

namespace App\Services\NBA;

class PlayerPointsOddsScraperService extends AbstractNBAOddsScraperService
{
    protected const POINTS_MARKET_URN = 'ppb:tbd:cardgroup:pebble:marketTemplateEvent:ZxEDTxIAACIAf6YW';

    protected function getMarketURN(): string
    {
        return self::POINTS_MARKET_URN;
    }

    protected function getTabName(): string
    {
        return 'Jogador';
    }

    protected function getMarketType(): string
    {
        return 'points';
    }
}