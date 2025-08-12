<?php

namespace App\Http\Controllers;

use App\Models\Audit;
use Illuminate\Http\Request;
use App\Models\Log;
use Illuminate\Support\Facades\Validator;

class AuditController extends Controller
{
    // ❌ Supprimez tout le __construct()

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
        if ($request->filled('q')) {
            $q = $request->q;
            $query->where(function ($sub) use ($q) {
                $sub->where('action', 'LIKE', "%{$q}%")
                    ->orWhere('details', 'LIKE', "%{$q}%");
            });
        }

        $audits = $query->orderBy('created_at', 'desc')->paginate(15);
        return response()->json($audits);
    }

    public function store(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'action' => 'required|string|max:255',
            'details' => 'required|string',
            'document_id' => 'required|exists:documents,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation échouée', 'errors' => $validator->errors()], 422);
        }

        $audit = Audit::create([
            'action' => $request->action,
            'details' => $request->details,
            'document_id' => $request->document_id,
            'user_id' => $user->id,
            'ip_address' => $request->ip(),
        ]);

        Log::record($user, 'creation', 'Audit', $audit->id, 'Création d\'un audit: ' . $audit->action);
        return response()->json($audit, 201);
    }

    public function show(Audit $audit)
    {
        $user = request()->user();
        Log::record($user, 'consultation', 'Audit', $audit->id, 'Consultation d\'un audit');
        $audit->load(['user', 'document']);
        return response()->json($audit);
    }

    public function update(Request $request, Audit $audit)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'action' => 'required|string|max:255',
            'details' => 'required|string',
            'document_id' => 'required|exists:documents,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation échouée', 'errors' => $validator->errors()], 422);
        }

        $audit->update([
            'action' => $request->action,
            'details' => $request->details,
            'document_id' => $request->document_id,
        ]);

        Log::record($user, 'modification', 'Audit', $audit->id, 'Modification d\'un audit');
        return response()->json($audit);
    }

    public function destroy(Audit $audit)
    {
        $user = request()->user();
        $audit->delete();
        Log::record($user, 'suppression', 'Audit', $audit->id, 'Suppression d\'un audit');
        return response()->json(['message' => 'Audit supprimé avec succès']);
    }
}