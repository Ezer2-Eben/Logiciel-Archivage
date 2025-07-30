<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;
use App\Models\Log;

class CategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        Log::record($user, 'consultation_liste', 'Category', null, 'Consultation de la liste des catégories');
        $categories = Category::withCount('documents')->orderBy('name')->paginate(20);
        return response()->json($categories);
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
        if ($user->role !== 'admin') {
            return response()->json(['message' => 'Accès refusé.'], 403);
        }
        
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:categories,name',
            'description' => 'nullable|string',
        ]);
        $category = Category::create($validated);
        Log::record($user, 'creation', 'Category', $category->id, 'Création d\'une catégorie');
        return response()->json($category, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Category $category)
    {
        $user = request()->user();
        Log::record($user, 'consultation', 'Category', $category->id, 'Consultation d\'une catégorie');
        $category->load('documents');
        return response()->json($category);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Category $category)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Category $category)
    {
        $user = $request->user();
        // Vérification des permissions
        if ($user->role !== 'admin') {
            return response()->json(['message' => 'Accès refusé.'], 403);
        }
        
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255|unique:categories,name,' . $category->id,
            'description' => 'nullable|string',
        ]);
        $category->update($validated);
        Log::record($user, 'modification', 'Category', $category->id, 'Modification d\'une catégorie');
        return response()->json($category);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Category $category)
    {
        $user = request()->user();
        // Vérification des permissions
        if ($user->role !== 'admin') {
            return response()->json(['message' => 'Accès refusé.'], 403);
        }
        
        $category->delete();
        Log::record($user, 'suppression', 'Category', $category->id, 'Suppression d\'une catégorie');
        return response()->json(['message' => 'Catégorie supprimée avec succès']);
    }
}
