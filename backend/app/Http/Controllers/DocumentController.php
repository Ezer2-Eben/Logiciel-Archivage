<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\File;
use Illuminate\Http\Request;
use App\Models\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

class DocumentController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        Log::record($user, 'consultation_liste', 'Document', null, 'Consultation des documents');

        $query = Document::with(['user', 'category', 'files'])
            ->where('etat', 'actif');

        if ($request->filled('q')) {
            $q = $request->input('q');
            $query->where(function($subQuery) use ($q) {
                $subQuery->where('title', 'like', "%$q%")
                    ->orWhere('content', 'like', "%$q%")
                    ->orWhereHas('user', function($userQuery) use ($q) {
                        $userQuery->where('name', 'like', "%$q%");
                    });
            });
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        $perPage = $request->input('per_page', 10);
        $documents = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json([
            'data' => $documents->items(),
            'total' => $documents->total(),
            'current_page' => $documents->currentPage()
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();

        if (!in_array($user->role, ['admin', 'archiviste'])) {
            return response()->json(['message' => 'Accès refusé'], 403);
        }

        // Debug - Log toutes les données reçues
        \Log::info('=== DÉBUT DEBUG STORE ===');
        \Log::info('Headers:', $request->headers->all());
        \Log::info('Données POST:', $request->all());
        \Log::info('Files reçus:', [
            'hasFile_files' => $request->hasFile('files'),
            'hasFile_files_array' => $request->hasFile('files.0'),
            'all_files' => $request->file(),
        ]);

        // Validation avec des règles plus permissives
        try {
            $validated = $request->validate([
                'title' => 'required|string|max:255',
                'content' => 'nullable|string',
                'category_id' => 'required|exists:categories,id',
                'files' => 'nullable|array',
                'files.*' => 'nullable|file|max:20480' // 20MB max, tous types de fichiers
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            \Log::error('Erreur de validation:', $e->errors());
            return response()->json([
                'message' => 'Erreur de validation',
                'errors' => $e->errors()
            ], 422);
        }

        DB::beginTransaction();
        try {
            // Création du document
            $document = Document::create([
                'title' => $validated['title'],
                'content' => $validated['content'] ?? '',
                'category_id' => $validated['category_id'],
                'user_id' => $user->id,
                'etat' => 'actif'
            ]);

            \Log::info('Document créé avec ID: ' . $document->id);

            // ✅ Créer un dossier unique pour ce document
            $documentFolderName = 'document_' . $document->id . '_' . date('Y-m-d_H-i-s');
            $documentFolderPath = 'documents/' . $documentFolderName;
            $fullDocumentPath = storage_path('app/public/' . $documentFolderPath);

            // Créer le dossier spécifique au document
            if (!file_exists($fullDocumentPath)) {
                mkdir($fullDocumentPath, 0755, true);
                \Log::info('Dossier document créé: ' . $fullDocumentPath);
            }

            // Gestion des fichiers avec plusieurs tentatives de récupération
            $files = null;
            if ($request->hasFile('files')) {
                $files = $request->file('files');
            } elseif ($request->hasFile('files.0')) {
                // Cas où les fichiers arrivent indexés
                $files = [];
                $i = 0;
                while ($request->hasFile("files.$i")) {
                    $files[] = $request->file("files.$i");
                    $i++;
                }
            }

            if ($files && count($files) > 0) {
                \Log::info('Traitement de ' . count($files) . ' fichiers pour le dossier: ' . $documentFolderPath);

                foreach ($files as $index => $file) {
                    if ($file && $file->isValid()) {
                        try {
                            $originalName = $file->getClientOriginalName();
                            $extension = $file->getClientOriginalExtension();
                            
                            // ✅ Garder le nom original mais avec un préfixe temporel si doublon
                            $filename = pathinfo($originalName, PATHINFO_FILENAME);
                            $finalName = $originalName;
                            
                            // Vérifier si le fichier existe déjà dans le dossier
                            $counter = 1;
                            while (file_exists($fullDocumentPath . '/' . $finalName)) {
                                $finalName = $filename . '_(' . $counter . ').' . $extension;
                                $counter++;
                            }
                            
                            // ✅ Stocker le fichier dans le dossier spécifique au document
                            $relativePath = $documentFolderPath . '/' . $finalName;
                            $file->storeAs($documentFolderPath, $finalName, 'public');
                            $fullFilePath = storage_path('app/public/' . $relativePath);
                            
                            \Log::info("Fichier stocké:", [
                                'original_name' => $originalName,
                                'final_name' => $finalName,
                                'relative_path' => $relativePath,
                                'full_path' => $fullFilePath,
                                'exists' => file_exists($fullFilePath),
                                'size_on_disk' => file_exists($fullFilePath) ? filesize($fullFilePath) : 'N/A'
                            ]);

                            // ✅ Création de l'enregistrement en base avec le chemin dans le dossier spécifique
                            $fileRecord = File::create([
                                'document_id' => $document->id,
                                'original_name' => $originalName,
                                'path' => $relativePath, // Chemin relatif complet incluant le dossier
                                'mime_type' => $file->getMimeType(),
                                'size' => $file->getSize(),
                                'user_id' => $user->id
                            ]);

                            \Log::info("Enregistrement fichier créé:", [
                                'id' => $fileRecord->id,
                                'document_id' => $fileRecord->document_id,
                                'original_name' => $fileRecord->original_name,
                                'path' => $fileRecord->path,
                                'folder' => $documentFolderName
                            ]);

                        } catch (\Exception $fileError) {
                            \Log::error("Erreur traitement fichier $index:", [
                                'error' => $fileError->getMessage(),
                                'trace' => $fileError->getTraceAsString(),
                                'file_name' => $file ? $file->getClientOriginalName() : 'N/A',
                                'file_size' => $file ? $file->getSize() : 'N/A',
                                'document_folder' => $documentFolderPath
                            ]);
                        }
                    } else {
                        \Log::warning("Fichier invalide à l'index $index:", [
                            'is_null' => is_null($file),
                            'is_valid' => $file ? $file->isValid() : 'N/A',
                            'error' => $file ? $file->getError() : 'N/A'
                        ]);
                    }
                }
            } else {
                \Log::info('Aucun fichier à traiter - dossier créé mais vide: ' . $documentFolderPath);
            }

            DB::commit();
            
            // Rechargement du document avec ses relations
            $document->load(['user', 'category', 'files']);
            
            Log::record($user, 'creation', 'Document', $document->id, 'Document créé: ' . $document->title);

            \Log::info('=== Document créé avec succès ===', [
                'document_id' => $document->id,
                'files_count' => $document->files->count(),
                'files_details' => $document->files->toArray()
            ]);

            return response()->json([
                'message' => 'Document créé avec succès',
                'document' => $document
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('=== ERREUR CRÉATION DOCUMENT ===', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $user->id,
                'request_data' => $request->except(['files'])
            ]);
            return response()->json([
                'message' => 'Erreur lors de la création du document',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show(Document $document)
    {
        $user = request()->user();
        Log::record($user, 'consultation', 'Document', $document->id, 'Consulté: ' . $document->title);

        $document->load(['user', 'category', 'files']);
        return response()->json($document);
    }

    public function update(Request $request, Document $document)
    {
        $user = $request->user();

        if (!in_array($user->role, ['admin', 'archiviste'])) {
            return response()->json(['message' => 'Accès refusé'], 403);
        }

        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'content' => 'nullable|string',
            'category_id' => 'sometimes|required|exists:categories,id',
        ]);

        $document->update($validated);
        Log::record($user, 'modification', 'Document', $document->id, 'Modifié: ' . $document->title);

        return response()->json([
            'message' => 'Document mis à jour',
            'document' => $document->fresh(['user', 'category', 'files'])
        ]);
    }

    public function destroy(Document $document)
    {
        $user = auth()->user();

        if (!in_array($user->role, ['admin', 'archiviste'])) {
            return response()->json(['message' => 'Accès refusé'], 403);
        }

        DB::beginTransaction();
        try {
            // ✅ Optionnel : Supprimer physiquement le dossier et les fichiers
            // Vous pouvez choisir de les conserver ou de les supprimer
            
            // Pour les conserver (recommandé pour un système d'archivage) :
            $document->update(['etat' => 'inactif']);
            
            // Pour les supprimer physiquement (décommentez si souhaité) :
            /*
            $documentFiles = $document->files;
            foreach ($documentFiles as $file) {
                $filePath = storage_path('app/public/' . $file->path);
                if (file_exists($filePath)) {
                    unlink($filePath);
                }
                // Supprimer aussi le dossier s'il est vide
                $folderPath = dirname($filePath);
                if (is_dir($folderPath) && count(scandir($folderPath)) == 2) { // seulement . et ..
                    rmdir($folderPath);
                }
            }
            */
            
            Log::record($user, 'suppression_logique', 'Document', $document->id, 'Désactivé: ' . $document->title);

            DB::commit();
            
            return response()->json([
                'message' => 'Document désactivé avec succès',
                'document_id' => $document->id
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error("Erreur désactivation document", [
                'error' => $e->getMessage(),
                'document_id' => $document->id,
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'message' => 'Échec de la désactivation',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function updateStatus(Request $request, Document $document)
    {
        $user = $request->user();

        if (!in_array($user->role, ['admin', 'archiviste'])) {
            return response()->json(['message' => 'Accès refusé'], 403);
        }

        $validated = $request->validate([
            'etat' => 'required|in:actif,inactif'
        ]);

        DB::beginTransaction();
        try {
            $document->update(['etat' => $validated['etat']]);
            
            $action = $validated['etat'] === 'inactif' ? 'suppression_logique' : 'reactivation';
            $message = $validated['etat'] === 'inactif' ? 'Désactivé' : 'Réactivé';
            
            Log::record($user, $action, 'Document', $document->id, $message . ': ' . $document->title);

            DB::commit();
            
            return response()->json([
                'message' => 'Statut du document mis à jour',
                'document' => $document->fresh(['user', 'category', 'files'])
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error("Erreur mise à jour statut document", [
                'error' => $e->getMessage(),
                'document_id' => $document->id,
                'new_status' => $validated['etat']
            ]);
            return response()->json([
                'message' => 'Erreur lors de la mise à jour du statut',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function exportDocument(Document $document) // ✅ Changé pour utiliser model binding
    {
        try {
            $user = auth()->user();
            if (!$user) {
                return response()->json(['error' => 'Non authentifié'], 401);
            }

            $document->load(['files', 'category', 'user']);
            $zipFileName = 'document_' . $document->id . '_' . now()->format('Ymd_His') . '.zip';
            $zipPath = storage_path('app/public/exports/' . $zipFileName);

            if (!file_exists(dirname($zipPath))) {
                mkdir(dirname($zipPath), 0755, true);
            }

            $zip = new ZipArchive;
            if ($zip->open($zipPath, ZipArchive::CREATE) !== TRUE) {
                throw new \Exception("Impossible de créer l'archive ZIP");
            }

            foreach ($document->files as $file) {
                $filePath = storage_path('app/public/' . $file->path);
                if (file_exists($filePath)) {
                    $zip->addFile($filePath, $file->original_name);
                }
            }

            $infoContent = "Titre: {$document->title}\n";
            $infoContent .= "Description: {$document->content}\n";
            $infoContent .= "Catégorie: " . ($document->category ? $document->category->name : 'Non définie') . "\n";
            $infoContent .= "Auteur: " . ($document->user ? $document->user->name : 'Inconnu') . "\n";
            $infoContent .= "Date: {$document->created_at}\n";
            $zip->addFromString('description.txt', $infoContent);

            $zip->close();

            Log::record($user, 'export', 'Document', $document->id, 'Export: ' . $document->title);

            return response()->download($zipPath)->deleteFileAfterSend(true);
        } catch (\Exception $e) {
            \Log::error("Erreur export document", [
                'document_id' => $document->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['error' => 'Erreur lors de l\'export: ' . $e->getMessage()], 500);
        }
    }

    public function exportMultiple(Request $request)
    {
        try {
            $user = auth()->user();
            if (!$user) {
                return response()->json(['error' => 'Non authentifié'], 401);
            }

            $request->validate([
                'ids' => 'required|string'
            ]);

            $ids = explode(',', $request->ids);
            $documents = Document::with(['files', 'category', 'user'])->whereIn('id', $ids)->get();

            if ($documents->isEmpty()) {
                throw new \Exception("Aucun document valide à exporter");
            }

            $zipFileName = 'documents_export_' . now()->format('Ymd_His') . '.zip';
            $zipPath = storage_path('app/public/exports/' . $zipFileName);

            if (!file_exists(dirname($zipPath))) {
                mkdir(dirname($zipPath), 0755, true);
            }

            $zip = new ZipArchive;
            if ($zip->open($zipPath, ZipArchive::CREATE) !== TRUE) {
                throw new \Exception("Impossible de créer l'archive ZIP");
            }

            foreach ($documents as $document) {
                $folderName = 'document_' . $document->id;
                
                foreach ($document->files as $file) {
                    $filePath = storage_path('app/public/' . $file->path);
                    if (file_exists($filePath)) {
                        $zip->addFile($filePath, $folderName . '/' . $file->original_name);
                    }
                }

                $infoContent = "Titre: {$document->title}\n";
                $infoContent .= "Description: {$document->content}\n";
                $infoContent .= "Catégorie: " . ($document->category ? $document->category->name : 'Non définie') . "\n";
                $infoContent .= "Auteur: " . ($document->user ? $document->user->name : 'Inconnu') . "\n";
                $infoContent .= "Date: {$document->created_at}\n";
                $zip->addFromString($folderName . '/description.txt', $infoContent);
            }

            $zip->close();

            Log::record($user, 'export_multiple', 'Document', null, 'Export multiple: ' . count($documents) . ' documents');

            return response()->download($zipPath)->deleteFileAfterSend(true);
        } catch (\Exception $e) {
            \Log::error("Erreur export multiple documents", [
                'ids' => $request->ids ?? 'non défini',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['error' => 'Erreur lors de l\'export multiple: ' . $e->getMessage()], 500);
        }
    }

    /**
     * ✅ Lister les fichiers d'un document avec informations détaillées
     */
    public function listFiles(Document $document)
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json(['error' => 'Non authentifié'], 401);
        }

        Log::record($user, 'consultation_fichiers', 'Document', $document->id, 
            'Consultation des fichiers du document: ' . $document->title);

        $files = $document->files()->with('user')->get();
        
        // Ajouter des informations supplémentaires
        $filesWithInfo = $files->map(function ($file) {
            $fullPath = storage_path('app/public/' . $file->path);
            return [
                'id' => $file->id,
                'original_name' => $file->original_name,
                'path' => $file->path,
                'mime_type' => $file->mime_type,
                'size' => $file->size,
                'formatted_size' => $this->formatBytes($file->size),
                'exists_on_disk' => file_exists($fullPath),
                'uploaded_by' => $file->user ? $file->user->name : 'Inconnu',
                'uploaded_at' => $file->created_at,
                'folder' => dirname($file->path)
            ];
        });

        return response()->json([
            'document' => [
                'id' => $document->id,
                'title' => $document->title,
                'folder_path' => $this->getDocumentFolderPath($document)
            ],
            'files' => $filesWithInfo,
            'total_files' => $filesWithInfo->count(),
            'total_size' => $files->sum('size'),
            'formatted_total_size' => $this->formatBytes($files->sum('size'))
        ]);
    }

    /**
     * ✅ Formater la taille des fichiers en format lisible
     */
    private function formatBytes($size, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        $base = log($size, 1024);
        $base = floor($base);
        
        return round(pow(1024, $base - $base), $precision) . ' ' . $units[$base];
    }

    /**
     * ✅ Ajouter des fichiers à un document existant
     */
    public function addFiles(Request $request, Document $document)
    {
        $user = $request->user();

        if (!in_array($user->role, ['admin', 'archiviste'])) {
            return response()->json(['message' => 'Accès refusé'], 403);
        }

        $request->validate([
            'files' => 'required|array',
            'files.*' => 'required|file|max:20480'
        ]);

        DB::beginTransaction();
        try {
            // Obtenir le dossier du document
            $documentFolderPath = $this->getDocumentFolderPath($document);
            $fullDocumentPath = storage_path('app/public/' . $documentFolderPath);

            // S'assurer que le dossier existe
            if (!file_exists($fullDocumentPath)) {
                mkdir($fullDocumentPath, 0755, true);
            }

            $addedFiles = [];
            $files = $request->file('files');

            foreach ($files as $index => $file) {
                if ($file && $file->isValid()) {
                    try {
                        $originalName = $file->getClientOriginalName();
                        $extension = $file->getClientOriginalExtension();
                        
                        // Éviter les doublons dans le dossier
                        $filename = pathinfo($originalName, PATHINFO_FILENAME);
                        $finalName = $originalName;
                        
                        $counter = 1;
                        while (file_exists($fullDocumentPath . '/' . $finalName)) {
                            $finalName = $filename . '_(' . $counter . ').' . $extension;
                            $counter++;
                        }
                        
                        // Stocker le fichier
                        $relativePath = $documentFolderPath . '/' . $finalName;
                        $file->storeAs($documentFolderPath, $finalName, 'public');
                        
                        // Créer l'enregistrement en base
                        $fileRecord = File::create([
                            'document_id' => $document->id,
                            'original_name' => $originalName,
                            'path' => $relativePath,
                            'mime_type' => $file->getMimeType(),
                            'size' => $file->getSize(),
                            'user_id' => $user->id
                        ]);

                        $addedFiles[] = $fileRecord;

                    } catch (\Exception $fileError) {
                        \Log::error("Erreur ajout fichier", [
                            'error' => $fileError->getMessage(),
                            'document_id' => $document->id,
                            'file_name' => $file->getClientOriginalName()
                        ]);
                    }
                }
            }

            DB::commit();
            
            Log::record($user, 'ajout_fichiers', 'Document', $document->id, 
                'Ajout de ' . count($addedFiles) . ' fichiers au document: ' . $document->title);

            return response()->json([
                'message' => count($addedFiles) . ' fichier(s) ajouté(s) avec succès',
                'files' => $addedFiles,
                'document' => $document->fresh(['files'])
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Erreur ajout fichiers', [
                'error' => $e->getMessage(),
                'document_id' => $document->id
            ]);
            return response()->json([
                'message' => 'Erreur lors de l\'ajout des fichiers',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * ✅ Méthode utilitaire pour obtenir le chemin du dossier d'un document
     */
    private function getDocumentFolderPath(Document $document)
    {
        // Si le document a des fichiers, extraire le dossier du premier fichier
        if ($document->files->isNotEmpty()) {
            $firstFilePath = $document->files->first()->path;
            // Extraire le dossier parent (ex: documents/document_1_2024-01-01_12-30-45)
            return dirname($firstFilePath);
        }
        
        // Sinon, générer le nom de dossier basé sur l'ID et la date de création
        return 'documents/document_' . $document->id . '_' . $document->created_at->format('Y-m-d_H-i-s');
    }

    public function downloadFile($documentId, $fileId)
    {
        try {
            $user = auth()->user();
            if (!$user) {
                return response()->json(['error' => 'Non authentifié'], 401);
            }

            $document = Document::findOrFail($documentId);
            $file = $document->files()->findOrFail($fileId);

            $filePath = storage_path('app/public/' . $file->path);
            if (!file_exists($filePath)) {
                throw new \Exception("Fichier non trouvé");
            }

            Log::record($user, 'telechargement', 'File', $file->id, 'Téléchargement: ' . $file->original_name);

            return response()->download($filePath, $file->original_name, [
                'Content-Type' => $file->mime_type
            ]);
        } catch (\Exception $e) {
            \Log::error("Erreur téléchargement fichier", [
                'document_id' => $documentId,
                'file_id' => $fileId,
                'error' => $e->getMessage()
            ]);
            return response()->json(['error' => 'Erreur lors du téléchargement: ' . $e->getMessage()], 500);
        }
    }
}