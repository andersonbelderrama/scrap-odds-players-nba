<?php

use App\Livewire\NBA\PlayerMarkets\PlayerPointsMarket;
use Illuminate\Support\Facades\Route;

Route::get('/', PlayerPointsMarket::class)->name('player-points-market');
