<?php

use App\Http\Controllers\AgentToolsController;
use App\Http\Controllers\FileController;
use App\Http\Controllers\IntelligenceController;
use App\Http\Controllers\MetadataController;
use App\Http\Controllers\NoteController;
use App\Http\Controllers\TemporalController;
use App\Http\Controllers\VaultController;
use App\Http\Controllers\WorkflowController;
use Illuminate\Support\Facades\Route;

// AI-Native Vault Operations
Route::prefix('vault')->controller(VaultController::class)->group(function () {
    // Directory operations with pagination
    Route::get('/directory', 'listDirectory');           // List directory with pagination
    Route::get('/search', 'searchVault');                // Search vault content
    Route::get('/notes/recent', 'getRecentNotes');       // Get recent notes
    Route::get('/notes/daily', 'getDailyNote');          // Get daily note
    Route::get('/notes/related/{path}', 'getRelatedNotes')->where('path', '.*'); // Find related notes
    Route::get('/overview', 'getVaultOverview');         // Vault overview (README + folder summary)
});

// Agent Tools - practical vault operations
Route::prefix('agent')->group(function () {
    Route::post('grep', [AgentToolsController::class, 'grepVault']);
    Route::post('query-frontmatter', [AgentToolsController::class, 'queryFrontmatter']);
    Route::get('backlinks/{note_path}', [AgentToolsController::class, 'getBacklinks']);
    Route::get('tags', [AgentToolsController::class, 'getTags']);
    Route::get('stats', [AgentToolsController::class, 'getVaultStats']);
});

// File Operations with Write Modes
Route::prefix('files')->controller(FileController::class)->group(function () {
    Route::get('/', 'index');           // List all files (legacy)
    Route::get('/{path}', 'show')->where('path', '.*');  // Read file
    Route::post('/write', 'write');     // Intelligent write (overwrite/append/prepend)
    Route::delete('/{path}', 'destroy')->where('path', '.*'); // Delete item

    // Legacy endpoints for backward compatibility
    Route::post('/', 'store');          // Create file (legacy)
    Route::put('/{path}', 'update')->where('path', '.*'); // Update file (legacy)
});

// Note Operations with Upsert
Route::prefix('notes')->controller(NoteController::class)->group(function () {
    Route::get('/', 'index');           // List notes (with search support)
    Route::post('/upsert', 'upsert');   // Create or update note intelligently
    Route::get('/{path}', 'show')->where('path', '.*');
    Route::delete('/{path}', 'destroy')->where('path', '.*');

    // Legacy endpoints for backward compatibility
    Route::post('/', 'store');
    Route::put('/{path}', 'update')->where('path', '.*');
    Route::patch('/{path}', 'patch')->where('path', '.*');
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
