<?php

namespace App\Http\Controllers;

use App\Models\File;
use App\Models\Log;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class FileController extends Controller
{
    /**
     * Liste des fichiers avec recherche, filtres par catégorie et type
     */
    public function index(Request $request)
    {
        $user = $request->user();
        Log::record($user, 'consultation_liste', 'File', null, 'Consultation de la liste des fichiers');

        $query = File::with(['document']);

        // Filtre par catégorie via document
        if ($request->filled('category_id')) {
            $query->whereHas('document', function ($q) use ($request) {
                $q->where('category_id', $request->category_id);
            });
        }

        // Filtre par type
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        // Recherche full-text
        if ($request->filled('q')) {
            $q = $request->q;
            $query->where(function ($sub) use ($q) {
                $sub->where('original_name', 'LIKE', "%{$q}%")
                    ->orWhere('description', 'LIKE', "%{$q}%");
            });
        }

        $perPage = $request->input('per_page', 15);
        $files = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json([
            'data' => $files->items(),
            'total' => $files->total(),
            'current_page' => $files->currentPage(),
            'per_page' => $files->perPage(),
        ]);
    }

    /**
     * Télécharger un fichier
     */
    public function download(File $file)
{
    $user = request()->user();
    Log::record($user, 'telechargement', 'File', $file->id, 'Téléchargement d\'un fichier');

    try {
        if (!$file->path) {
            return response()->json(['error' => 'Chemin du fichier manquant'], 404);
        }

        $filePath = storage_path('app/' . $file->path);

        if (!file_exists($filePath)) {
            return response()->json(['error' => 'Fichier non trouvé dans le stockage'], 404);
        }

        $fileName = $file->original_name ?? $file->filename ?? 'fichier';

        return response()->download($filePath, $fileName, [
            'Content-Type' => $file->mime_type ?? 'application/octet-stream'
        ]);
    } catch (\Exception $e) {
        \Log::error("Erreur téléchargement fichier", [
            'file_id' => $file->id,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        return response()->json(['error' => 'Erreur serveur'], 500);
    }
}

    /**
     * Afficher un fichier
     */
    public function show(File $file)
    {
        $user = request()->user();
        Log::record($user, 'consultation', 'File', $file->id, 'Consultation d\'un fichier');
        $file->load('document');
        return response()->json($file);
    }
}