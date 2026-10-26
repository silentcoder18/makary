<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/** Journal d'audit en lecture seule (M12, §9) : connexions, publications, droits, transitions, consultations. */
class JournalController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('journal.consulter');
        $action = (string) $request->query('action');

        return view('admin.journal.index', [
            'lignes' => DB::table('journal_audit')->leftJoin('utilisateur', 'utilisateur.id', '=', 'journal_audit.utilisateur_id')
                ->when($action !== '', fn ($q) => $q->where('journal_audit.action', 'like', addcslashes($action, '%_\\').'%'))
                ->orderByDesc('journal_audit.id')
                ->select(['journal_audit.*', 'utilisateur.nom_affiche'])->paginate(50)->withQueryString(),
            'actions' => DB::table('journal_audit')->distinct()->orderBy('action')->pluck('action'),
            'action' => $action,
        ]);
    }
}
