<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use App\Models\Log;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        // Vérification des permissions
        if ($user->role !== 'admin') {
            return response()->json(['message' => 'Accès refusé.'], 403);
        }
        
        Log::record($user, 'consultation_liste', 'User', null, 'Consultation de la liste des utilisateurs');
        $query = User::withCount(['documents', 'logs']);
        
        if ($request->filled('role')) {
            $query->where('role', $request->role);
        }
        
        if ($request->filled('q')) {
            $q = $request->input('q');
            $query->where(function($sub) use ($q) {
                $sub->where('name', 'like', "%$q%")
                    ->orWhere('email', 'like', "%$q%");
            });
        }
        
        $users = $query->orderBy('created_at', 'desc')->paginate(20);
        return response()->json($users);
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
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
            'role' => 'required|in:admin,archiviste,utilisateur',
        ]);
        
        $newUser = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => bcrypt($validated['password']),
            'role' => $validated['role'],
        ]);
        
        Log::record($user, 'creation', 'User', $newUser->id, 'Création d\'un utilisateur');
        return response()->json($newUser, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(User $user)
    {
        $currentUser = request()->user();
        // Vérification des permissions
        if ($currentUser->role !== 'admin') {
            return response()->json(['message' => 'Accès refusé.'], 403);
        }
        
        Log::record($currentUser, 'consultation', 'User', $user->id, 'Consultation d\'un utilisateur');
        $user->load(['documents', 'logs']);
        return response()->json($user);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(User $user)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, User $user)
    {
        $currentUser = $request->user();
        // Vérification des permissions
        if ($currentUser->role !== 'admin') {
            return response()->json(['message' => 'Accès refusé.'], 403);
        }
        
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|string|email|max:255|unique:users,email,' . $user->id,
            'password' => 'nullable|string|min:8',
            'role' => 'sometimes|required|in:admin,archiviste,utilisateur',
        ]);
        
        if (isset($validated['password'])) {
            $validated['password'] = bcrypt($validated['password']);
        }
        
        $user->update($validated);
        Log::record($currentUser, 'modification', 'User', $user->id, 'Modification d\'un utilisateur');
        return response()->json($user);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(User $user)
    {
        $currentUser = request()->user();
        // Vérification des permissions
        if ($currentUser->role !== 'admin') {
            return response()->json(['message' => 'Accès refusé.'], 403);
        }
        
        // Empêcher la suppression de son propre compte
        if ($currentUser->id === $user->id) {
            return response()->json(['message' => 'Vous ne pouvez pas supprimer votre propre compte.'], 403);
        }
        
        $user->delete();
        Log::record($currentUser, 'suppression', 'User', $user->id, 'Suppression d\'un utilisateur');
        return response()->json(['message' => 'Utilisateur supprimé avec succès']);
    }
} 