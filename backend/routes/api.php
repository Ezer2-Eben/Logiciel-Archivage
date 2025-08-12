<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\AuditController;
use App\Http\Controllers\LogController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\FileController;

Route::post('login', [AuthController::class, 'login']);
Route::post('register', [AuthController::class, 'register']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('logout', [AuthController::class, 'logout']);
    Route::get('user', [AuthController::class, 'user']);

    // ✅ Routes spécifiques documents (AVANT apiResource pour éviter les conflits)
    Route::get('/documents/trashed', [DocumentController::class, 'trashed']);
    Route::get('/documents/export-multiple', [DocumentController::class, 'exportMultiple']);
    Route::get('/documents/{document}/export', [DocumentController::class, 'exportDocument']);
    Route::patch('/documents/{document}/restore', [DocumentController::class, 'restore']);
    Route::patch('/documents/{document}/status', [DocumentController::class, 'updateStatus']);
    Route::post('/documents/{document}/files', [DocumentController::class, 'addFiles']); // ✅ Nouvelle route
    Route::get('/documents/{document}/files', [DocumentController::class, 'listFiles']); // ✅ Lister les fichiers
    Route::get('/documents/{document}/files/{file}/download', [DocumentController::class, 'downloadFile']);

    // Routes API Resource pour documents
    Route::apiResource('documents', DocumentController::class);
    
    // Autres resources
    Route::apiResource('categories', CategoryController::class);
    Route::apiResource('audits', AuditController::class);
    Route::apiResource('logs', LogController::class);
    Route::apiResource('users', UserController::class);

    // Routes pour les fichiers (FilesPage)
    Route::get('/files', [FileController::class, 'index']);
    Route::get('/files/{file}', [FileController::class, 'show']);
    Route::get('/files/{file}/download', [FileController::class, 'download']);
    Route::get('/files/export-multiple', [FileController::class, 'exportMultiple']);
});