<?php

use App\Http\Controllers\FileController;
use App\Http\Controllers\FrontMatterController;
use App\Http\Controllers\NoteController;
use Illuminate\Support\Facades\Route;

// File endpoints
Route::prefix('files')->group(function () {
    Route::get('/tree', [FileController::class, 'tree']);
    Route::get('/', [FileController::class, 'index']);
    Route::get('/raw', [FileController::class, 'raw']);
});

// Note endpoints
Route::prefix('notes')->group(function () {
    Route::get('/', [NoteController::class, 'index']);
    Route::get('/search', [NoteController::class, 'search']);
    Route::get('/{path}', [NoteController::class, 'show'])->where('path', '.*');
    Route::post('/', [NoteController::class, 'store']);
    Route::put('/{path}', [NoteController::class, 'update'])->where('path', '.*');
    Route::patch('/{path}', [NoteController::class, 'patch'])->where('path', '.*');
    Route::delete('/{path}', [NoteController::class, 'destroy'])->where('path', '.*');
    Route::post('/bulk-delete', [NoteController::class, 'bulkDelete']);
    Route::post('/bulk-update', [NoteController::class, 'bulkUpdate']);
});

// Front-matter inspection endpoints
Route::prefix('front-matter')->group(function () {
    Route::get('/keys', [FrontMatterController::class, 'keys']);
    Route::get('/values/{key}', [FrontMatterController::class, 'values']);
});
