<?php

use App\Http\Controllers\MediaController;
use App\Http\Controllers\PublicController;
use Illuminate\Support\Facades\Route;

// Rotas públicas sem sessão: nenhuma depende de login nem grava cookies.
Route::get('/', [PublicController::class, 'home'])->name('home');
Route::get('/acoes', [PublicController::class, 'actions'])->name('actions.index');
Route::get('/acoes/{slug}', [PublicController::class, 'action'])->name('actions.show')->where('slug', '[a-z0-9-]+');
Route::get('/areas', [PublicController::class, 'areas'])->name('areas.index');
Route::get('/p/{slug}', [PublicController::class, 'page'])->name('pages.show')->where('slug', '[a-z0-9-]+');
Route::get('/equipe', [PublicController::class, 'team'])->name('team.index');
Route::view('/acessibilidade', 'public.accessibility')->name('accessibility');
Route::view('/privacidade', 'public.privacy')->name('privacy');
Route::get('/midia/{media:uuid}/{variant}', [MediaController::class, 'public'])
    ->name('media.show')->where('variant', 'sm|md|lg|xl|file');
