<?php

use App\Http\Controllers\Api\V1\ClientContactController;
use App\Http\Controllers\Api\V1\ClientController;
use App\Http\Controllers\Api\V1\ClientCredentialController;
use App\Http\Controllers\Api\V1\CnpjLookupController;
use App\Http\Controllers\Api\V1\EstablishmentController;
use App\Http\Controllers\Api\V1\ExportController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\DocumentImportController;
use App\Http\Controllers\Api\V1\NoteController;
use App\Http\Controllers\Api\V1\OperationsInboxController;
use App\Http\Controllers\Api\V1\OperationsSummaryController;
use App\Http\Controllers\Api\V1\SyncController;
use App\Http\Middleware\EnsureActiveUser;
use App\Http\Middleware\EnsureAdminTwoFactor;
use App\Http\Middleware\EnsureOfficeContext;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::middleware(['auth:sanctum', EnsureActiveUser::class])->group(function (): void {
        Route::get('/me', MeController::class);

        Route::middleware([EnsureOfficeContext::class, EnsureAdminTwoFactor::class])->group(function (): void {
            Route::get('/clients', [ClientController::class, 'index']);
            Route::get('/cnpj/{cnpj}/lookup', CnpjLookupController::class)->middleware('throttle:30,1');
            Route::post('/clients', [ClientController::class, 'store']);
            Route::get('/clients/{client}', [ClientController::class, 'show']);
            Route::patch('/clients/{client}', [ClientController::class, 'update']);

            Route::post('/clients/{client}/establishments', [EstablishmentController::class, 'store']);
            Route::patch('/establishments/{establishment}', [EstablishmentController::class, 'update']);

            Route::get('/clients/{client}/contacts', [ClientContactController::class, 'index']);
            Route::post('/clients/{client}/contacts', [ClientContactController::class, 'store']);
            Route::patch('/clients/{client}/contacts/{contact}', [ClientContactController::class, 'update']);
            Route::delete('/clients/{client}/contacts/{contact}', [ClientContactController::class, 'destroy']);

            Route::get('/clients/{client}/credential', [ClientCredentialController::class, 'show']);
            Route::post('/clients/{client}/credential', [ClientCredentialController::class, 'store']);

            // Catálogo unificado Documentos (canônico)
            Route::get('/documents', [NoteController::class, 'index']);
            Route::get('/documents/by-client', [NoteController::class, 'byClient']);
            Route::get('/documents/insights', [NoteController::class, 'insights']);
            Route::post('/documents/import', [DocumentImportController::class, 'store']);
            Route::get('/documents/{accessKey}', [NoteController::class, 'show']);
            Route::get('/documents/{accessKey}/xml', [NoteController::class, 'downloadXml']);
            Route::post('/documents/{accessKey}/unlock-xml', [NoteController::class, 'unlockXml']);
            Route::post('/documents/{accessKey}/manifestations', [NoteController::class, 'manifest']);

            // Alias compatível (legado "notes")
            Route::get('/notes', [NoteController::class, 'index']);
            Route::get('/notes/by-client', [NoteController::class, 'byClient']);
            Route::get('/notes/insights', [NoteController::class, 'insights']);
            Route::get('/notes/{accessKey}', [NoteController::class, 'show']);
            Route::get('/notes/{accessKey}/xml', [NoteController::class, 'downloadXml']);

            Route::get('/sync-runs', [SyncController::class, 'history']);
            Route::post('/sync-runs', [SyncController::class, 'trigger']);

            Route::get('/exports', [ExportController::class, 'index']);
            Route::post('/exports', [ExportController::class, 'store']);
            Route::get('/exports/{export}/download', [ExportController::class, 'download']);

            Route::get('/operations/summary', OperationsSummaryController::class);
            Route::get('/operations/inbox', OperationsInboxController::class);
        });
    });
});
