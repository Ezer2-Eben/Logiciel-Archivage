<?php

namespace App\Http\Controllers;

use App\Models\Audit;
use Illuminate\Http\Request;
use App\Models\Log;

class AuditController extends Controller
{
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            $user = $request->user();
            $adminOrArchivist = in_array($user->role, ['admin', 'archiviste']);
            $route = $request->route()->getActionMethod();
            if (in_array($route, ['store', 'update', 'destroy'])) {
                if (! $adminOrArchivist) {
                    return response()->json(['message' => 'Accès refusé.'], 403);
                }
            }
            return $next($request);
        });
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        Log::record($user, 'consultation_liste', 'Audit', null, 'Consultation de la liste des audits');
        $query = Audit::with(['user', 'document']);
        if ($request->filled('document_id')) {
            $query->where('document_id', $request->document_id);
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
        $audits = $query->orderBy('created_at', 'desc')->paginate(15);
        return response()->json($audits);
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
        $validated = $request->validate([
            'document_id' => 'required|exists:documents,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'date' => 'required|date',
        ]);
        $audit = Audit::create([
            'document_id' => $validated['document_id'],
            'user_id' => $user->id,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'date' => $validated['date'],
        ]);
        Log::record($user, 'creation', 'Audit', $audit->id, 'Création d\'un enregistrement d\'audience');
        return response()->json($audit, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Audit $audit)
    {
        $user = request()->user();
        Log::record($user, 'consultation', 'Audit', $audit->id, 'Consultation d\'un enregistrement d\'audience');
        $audit->load(['user', 'document']);
        return response()->json($audit);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Audit $audit)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Audit $audit)
    {
        $user = $request->user();
        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'date' => 'sometimes|required|date',
        ]);
        $audit->update($validated);
        Log::record($user, 'modification', 'Audit', $audit->id, 'Modification d\'un enregistrement d\'audience');
        return response()->json($audit);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Audit $audit)
    {
        $user = request()->user();
        $audit->delete();
        Log::record($user, 'suppression', 'Audit', $audit->id, 'Suppression d\'un enregistrement d\'audience');
        return response()->json(['message' => 'Enregistrement d’audience supprimé.']);
    }
}
