<?php

namespace App\Http\Controllers;

use App\Models\File;
use Illuminate\Http\Request;
use App\Models\Log;

class FileController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        Log::record($user, 'consultation_liste', 'File', null, 'Consultation de la liste des fichiers');
        $query = File::with(['document']);
        if ($request->filled('document_id')) {
            $query->where('document_id', $request->document_id);
        }
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }
        $files = $query->orderBy('created_at', 'desc')->paginate(15);
        return response()->json($files);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $user = $request->user();
        // Vérification des permissions
        if (!in_array($user->role, ['admin', 'archiviste'])) {
            return response()->json(['message' => 'Accès refusé.'], 403);
        }
        
        $validated = $request->validate([
            'document_id' => 'required|exists:documents,id',
            'file' => 'required|file|max:10240', // 10 Mo max
            'type' => 'required|string',
            'description' => 'nullable|string',
        ]);
        $path = $request->file('file')->store('uploads');
        $file = File::create([
            'document_id' => $validated['document_id'],
            'path' => $path,
            'type' => $validated['type'],
            'description' => $validated['description'] ?? null,
        ]);
        Log::record($user, 'creation', 'File', $file->id, 'Upload d\'un fichier');
        return response()->json($file, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(File $file)
    {
        $user = request()->user();
        Log::record($user, 'consultation', 'File', $file->id, 'Consultation d\'un fichier');
        $file->load('document');
        return response()->json($file);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(File $file)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, File $file)
    {
        $user = $request->user();
        // Vérification des permissions
        if (!in_array($user->role, ['admin', 'archiviste'])) {
            return response()->json(['message' => 'Accès refusé.'], 403);
        }
        
        $validated = $request->validate([
            'type' => 'sometimes|required|string',
            'description' => 'nullable|string',
        ]);
        $file->update($validated);
        Log::record($user, 'modification', 'File', $file->id, 'Modification d\'un fichier');
        return response()->json($file);
    }

    /**
     * Download the specified file.
     */
    public function download(File $file)
    {
        $user = request()->user();
        Log::record($user, 'telechargement', 'File', $file->id, 'Téléchargement d\'un fichier');
        
        if (!$file->path || !\Storage::exists($file->path)) {
            return response()->json(['message' => 'Fichier non trouvé'], 404);
        }
        
        return \Storage::download($file->path, $file->original_name ?? $file->filename);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(File $file)
    {
        $user = request()->user();
        // Vérification des permissions
        if (!in_array($user->role, ['admin', 'archiviste'])) {
            return response()->json(['message' => 'Accès refusé.'], 403);
        }
        
        // Suppression du fichier physique
        if ($file->path && \Storage::exists($file->path)) {
            \Storage::delete($file->path);
        }
        $file->delete();
        Log::record($user, 'suppression', 'File', $file->id, 'Suppression d\'un fichier');
        return response()->json(['message' => 'Fichier supprimé avec succès']);
    }
}
