<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;
use App\Models\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CategoryController extends Controller
{
    /**
     * Afficher la liste des catégories avec recherche et pagination
     */
    public function index(Request $request)
    {
        $user = $request->user();
        Log::record($user, 'consultation_liste', 'Category', null, 'Consultation des catégories');

        $query = Category::withCount('documents');

        // Recherche par nom ou description
        if ($request->filled('q')) {
            $searchTerm = $request->input('q');
            $query->where(function ($q) use ($searchTerm) {
                $q->where('name', 'like', "%{$searchTerm}%")
                    ->orWhere('description', 'like', "%{$searchTerm}%");
            });
        }

        // Tri
        $sortField = $request->input('sort_by', 'name');
        $sortDirection = $request->input('sort_dir', 'asc');
        $allowedSorts = ['name', 'documents_count', 'created_at'];
        if (in_array($sortField, $allowedSorts)) {
            $query->orderBy($sortField, $sortDirection);
        }

        // Pagination
        $perPage = $request->input('per_page', 20);
        $categories = $query->orderBy('name', 'asc')->paginate($perPage);

        return response()->json([
            'data' => $categories->items(),
            'total' => $categories->total(),
            'current_page' => $categories->currentPage(),
            'per_page' => $categories->perPage(),
            'last_page' => $categories->lastPage()
        ]);
    }

    /**
     * Créer une nouvelle catégorie
     */
    public function store(Request $request)
    {
        $user = $request->user();

        // Autoriser uniquement 'admin' et 'archiviste'
        if (!in_array($user->role, ['admin', 'archiviste'])) {
            return response()->json(['message' => 'Accès refusé'], 403);
        }

        \Log::info('Données reçues pour création de catégorie', [
            'user_id' => $user->id,
            'name' => $request->input('name'),
            'description' => $request->input('description'),
        ]);

        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255|unique:categories,name',
                'description' => 'nullable|string|max:1000',
            ]);
        } catch (ValidationException $e) {
            \Log::error('Erreur validation création catégorie', [
                'errors' => $e->errors(),
                'user_id' => $user->id,
            ]);
            return response()->json([
                'message' => 'Erreur de validation',
                'errors' => $e->errors()
            ], 422);
        }

        DB::beginTransaction();
        try {
            $category = Category::create([
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'user_id' => $user->id
            ]);

            \Log::info('Catégorie créée avec succès', [
                'category_id' => $category->id,
                'name' => $category->name
            ]);

            Log::record($user, 'creation', 'Category', $category->id, 'Catégorie créée : ' . $category->name);

            DB::commit();

            return response()->json([
                'message' => 'Catégorie créée avec succès',
                'data' => $category
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Erreur serveur lors de la création de la catégorie', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'message' => 'Erreur lors de la création de la catégorie',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Afficher une catégorie avec ses documents
     */
    public function show(Category $category)
    {
        $user = request()->user();
        Log::record($user, 'consultation', 'Category', $category->id, 'Consultation : ' . $category->name);

        $category->loadCount('documents');

        $documents = $category->documents()
            ->with(['user', 'files'])
            ->where('etat', 'actif')
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return response()->json([
            'category' => $category,
            'documents' => $documents
        ]);
    }

    /**
     * Mettre à jour une catégorie
     */
    public function update(Request $request, Category $category)
    {
        $user = $request->user();

        if (!in_array($user->role, ['admin', 'archiviste'])) {
            return response()->json(['message' => 'Accès refusé'], 403);
        }

        \Log::info('Mise à jour de la catégorie', [
            'category_id' => $category->id,
            'user_id' => $user->id,
            'data' => $request->all()
        ]);

        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255|unique:categories,name,' . $category->id,
                'description' => 'nullable|string|max:1000',
            ]);
        } catch (ValidationException $e) {
            \Log::error('Erreur validation mise à jour catégorie', [
                'category_id' => $category->id,
                'errors' => $e->errors()
            ]);
            return response()->json([
                'message' => 'Erreur de validation',
                'errors' => $e->errors()
            ], 422);
        }

        DB::beginTransaction();
        try {
            $category->update($validated);

            \Log::info('Catégorie mise à jour', [
                'category_id' => $category->id,
                'name' => $category->name
            ]);

            Log::record($user, 'modification', 'Category', $category->id, 'Catégorie modifiée : ' . $category->name);

            DB::commit();

            return response()->json([
                'message' => 'Catégorie mise à jour avec succès',
                'data' => $category->fresh()
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Erreur serveur lors de la mise à jour', [
                'category_id' => $category->id,
                'error' => $e->getMessage()
            ]);
            return response()->json([
                'message' => 'Erreur lors de la mise à jour',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Supprimer une catégorie (suppression logique ou physique selon usage)
     */
    public function destroy(Category $category)
    {
        $user = request()->user();

        if (!in_array($user->role, ['admin', 'archiviste'])) {
            return response()->json(['message' => 'Accès refusé'], 403);
        }

        DB::beginTransaction();
        try {
            // Vérifier si la catégorie est utilisée
            if ($category->documents()->where('etat', 'actif')->exists()) {
                \Log::warning('Tentative de suppression d\'une catégorie utilisée', [
                    'category_id' => $category->id,
                    'user_id' => $user->id
                ]);
                return response()->json([
                    'message' => 'Impossible de supprimer : cette catégorie contient des documents actifs.'
                ], 422);
            }

            $categoryName = $category->name;
            $category->delete();

            \Log::info('Catégorie supprimée', [
                'category_id' => $category->id,
                'name' => $categoryName
            ]);

            Log::record($user, 'suppression', 'Category', $category->id, 'Catégorie supprimée : ' . $categoryName);

            DB::commit();

            return response()->json([
                'message' => 'Catégorie supprimée avec succès'
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Erreur lors de la suppression de la catégorie', [
                'category_id' => $category->id,
                'error' => $e->getMessage()
            ]);
            return response()->json([
                'message' => 'Erreur lors de la suppression',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}