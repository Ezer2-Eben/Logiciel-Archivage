<?php

namespace App\Http\Controllers;

use App\Models\Document;
use Illuminate\Http\Request;
use App\Models\Log;

class DocumentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        Log::record($user, 'consultation_liste', 'Document', null, 'Consultation de la liste des documents');
        $query = Document::with(['user', 'category', 'files']);
        // Recherche par mot-clé
        if ($request->filled('q')) {
            $q = $request->input('q');
            $query->where('title', 'like', "%$q%")
                  ->orWhere('description', 'like', "%$q%")
                  ->orWhereHas('user', function($sub) use ($q) {
                      $sub->where('name', 'like', "%$q%")
                          ->orWhere('email', 'like', "%$q%") ;
                  });
        }
        // Filtres par type, date, catégorie, utilisateur
        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }
        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->to);
        }
        $documents = $query->orderBy('created_at', 'desc')->paginate(15);
        return response()->json($documents);
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
            'title' => 'required|string|max:255',
            'content' => 'nullable|string',
            'category_id' => 'required|exists:categories,id',
            'files.*' => 'nullable|file|max:10240', // 10MB max par fichier
        ]);

        $document = Document::create([
            'title' => $validated['title'],
            'content' => $validated['content'] ?? null,
            'category_id' => $validated['category_id'],
            'user_id' => $user->id,
            'status' => 'draft'
        ]);

        // Gestion de l'upload des fichiers
        if ($request->hasFile('files')) {
            foreach ($request->file('files') as $file) {
                $filename = time() . '_' . $file->getClientOriginalName();
                $path = $file->storeAs('documents', $filename, 'public');
                
                \App\Models\File::create([
                    'filename' => $filename,
                    'original_name' => $file->getClientOriginalName(),
                    'mime_type' => $file->getMimeType(),
                    'size' => $file->getSize(),
                    'path' => $path,
                    'user_id' => $user->id,
                    'document_id' => $document->id
                ]);
            }
        }

        $document->load(['user', 'category', 'files']);
        Log::record($user, 'creation', 'Document', $document->id, 'Création d\'un document');
        return response()->json($document, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Document $document)
    {
        $user = request()->user();
        Log::record($user, 'consultation', 'Document', $document->id, 'Consultation d\'un document');
        $document->load(['user', 'category', 'files']);
        return response()->json($document);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Document $document)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Document $document)
    {
        $user = $request->user();
        // Vérification des permissions
        if (!in_array($user->role, ['admin', 'archiviste'])) {
            return response()->json(['message' => 'Accès refusé.'], 403);
        }
        
        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'category_id' => 'sometimes|required|exists:categories,id',
        ]);
        $document->update($validated);
        Log::record($user, 'modification', 'Document', $document->id, 'Modification d\'un document');
        return response()->json($document);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Document $document)
    {
        $user = request()->user();
        // Vérification des permissions
        if (!in_array($user->role, ['admin', 'archiviste'])) {
            return response()->json(['message' => 'Accès refusé.'], 403);
        }
        
        $document->delete();
        Log::record($user, 'suppression', 'Document', $document->id, 'Suppression d\'un document');
        return response()->json(['message' => 'Document supprimé avec succès']);
    }
}
