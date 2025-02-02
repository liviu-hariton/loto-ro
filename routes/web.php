<?php

use Illuminate\Support\Facades\Route;
use LHDev\LotoRo\Http\Controllers\LotoRo;

/**
 * These routes are for fetching data from the source and save it to the local database
 */
Route::get('/lotoro-649', [LotoRo::class, 'fetch649'])->name('lotoro-649');
Route::get('/lotoro-540', [LotoRo::class, 'fetch540'])->name('lotoro-540');

/**
 * These routes are for loading the data from the local database
 */
Route::get('/lotoro-export', [LotoRo::class, 'exportData'])->name('lotoro-export');
Route::get('/lotoro-draws', [LotoRo::class, 'getDrawsDates'])->name('lotoro-draws');
Route::get('/lotoro-draw', [LotoRo::class, 'getDraw'])->name('lotoro-draw');
Route::get('/lotoro-most-drawn-numbers', [LotoRo::class, 'getMostDrawnNumbers'])->name('lotoro-most-drawn-numbers');
Route::get('/lotoro-least-drawn-numbers', [LotoRo::class, 'getLeastDrawnNumbers'])->name('lotoro-least-drawn-numbers');
Route::get('/lotoro-prizes-distribution', [LotoRo::class, 'getPrizesDistribution'])->name('lotoro-prizes-distribution');
Route::get('/lotoro-total-prize-fund', [LotoRo::class, 'getPrizeFund'])->name('lotoro-total-prize-fund');
Route::get('/lotoro-total-winners', [LotoRo::class, 'getWinners'])->name('lotoro-total-winners');
Route::get('/lotoro-not-drawn-numbers', [LotoRo::class, 'getNotDrawnNumbers'])->name('lotoro-not-drawn-numbers');
Route::get('/lotoro-generate-numbers', [LotoRo::class, 'generateNumbers'])->name('lotoro-generate-numbers');