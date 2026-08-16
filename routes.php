<?php

use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;
use VanDmade\Blocksmith\Http\Controllers\DocumentController;
use VanDmade\Blocksmith\Http\Controllers\RevisionController;
use VanDmade\Blocksmith\Http\Controllers\VerificationController;

Route::middleware(['can:manage-blocksmith', SubstituteBindings::class])
    ->prefix('blocksmith')
    ->name('blocksmith.')
    ->group(function() {
        Route::get('list/documents', [DocumentController::class, 'list'])->name('documents.list');
        Route::prefix('document')
            ->name('documents.')
            ->group(function() {
                Route::get('data', [DocumentController::class, 'data'])->name('data');
                Route::post('/', [DocumentController::class, 'store'])->name('store');
                Route::get('{document}', [DocumentController::class, 'get'])->name('show');
                Route::put('{document}', [DocumentController::class, 'update'])->name('update');
                Route::delete('{document}', [DocumentController::class, 'destroy'])->name('destroy');
            });
        Route::prefix('revision')
            ->name('revisions.')
            ->group(function() {
                Route::get('{revision}', [RevisionController::class, 'get'])->name('show');
                Route::get('document/{document}/history', [RevisionController::class, 'history'])->name('history');
            });
        Route::get('verify/{revision}', [VerificationController::class, 'verify'])->name('verify');
    });

// Not behind the manage-blocksmith gate on purpose - the signature itself is what
// authenticates a request here, the same way any signed route works. Generated via
// URL::temporarySignedRoute() in DocumentController::get().
Route::get('blocksmith/document/{document}/download', [DocumentController::class, 'download'])
    ->name('blocksmith.documents.download')
    ->middleware(['signed', SubstituteBindings::class]);
