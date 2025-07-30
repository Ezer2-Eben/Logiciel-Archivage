<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Routes publiques d'authentification
Route::post('login', [App\Http\Controllers\AuthController::class, 'login']);
Route::post('register', [App\Http\Controllers\AuthController::class, 'register']);

// Routes protégées par Sanctum
Route::middleware('auth:sanctum')->group(function () {
    Route::post('logout', [App\Http\Controllers\AuthController::class, 'logout']);
    Route::get('user', [App\Http\Controllers\AuthController::class, 'user']);
    // Ajoutez ici les routes CRUD pour les ressources (documents, fichiers, catégories, etc.)
    Route::apiResource('documents', App\Http\Controllers\DocumentController::class);
    Route::apiResource('files', App\Http\Controllers\FileController::class);
    Route::get('files/{file}/download', [App\Http\Controllers\FileController::class, 'download']);
    Route::apiResource('categories', App\Http\Controllers\CategoryController::class);
    Route::apiResource('audits', App\Http\Controllers\AuditController::class);
    Route::apiResource('logs', App\Http\Controllers\LogController::class);
    Route::apiResource('users', App\Http\Controllers\UserController::class);
}); 