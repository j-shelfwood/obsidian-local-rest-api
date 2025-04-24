<?php

use App\Http\Controllers\FileController;
use App\Http\Controllers\MetadataController;
use App\Http\Controllers\NoteController;
use Illuminate\Support\Facades\Route;

// File endpoints
Route::prefix('files')->controller(FileController::class)->group(function () {
    Route::get('/', 'index');           // List all files
    Route::post('/', 'store');          // Create new file
    // Show raw file content (path needs to be URL encoded)
    Route::get('/{path}', 'show')->where('path', '.*');
    // Update/replace file content (path needs to be URL encoded)
    Route::put('/{path}', 'update')->where('path', '.*');
    // Delete file (path needs to be URL encoded)
    Route::delete('/{path}', 'destroy')->where('path', '.*');
});

// Note endpoints
Route::prefix('notes')->controller(NoteController::class)->group(function () {
    Route::get('/', 'index');           // List notes
    Route::post('/', 'store');          // Create note
    // Show note content + metadata (path needs to be URL encoded)
    Route::get('/{path}', 'show')->where('path', '.*');
    // Replace note (path needs to be URL encoded)
    Route::put('/{path}', 'update')->where('path', '.*');
    // Update note (path needs to be URL encoded)
    Route::patch('/{path}', 'patch')->where('path', '.*');
    // Delete note (path needs to be URL encoded)
    Route::delete('/{path}', 'destroy')->where('path', '.*');
});

// Bulk operations under their own prefix
Route::prefix('bulk')->controller(NoteController::class)->group(function () {
    Route::delete('notes/delete', 'bulkDelete');
    Route::patch('notes/update', 'bulkUpdate');
});

// Metadata endpoints
Route::prefix('metadata')->controller(MetadataController::class)->group(function () {
    Route::get('/keys', 'keys');           // List all metadata keys
    Route::get('/values/{key}', 'values'); // List unique values for a key
});
