<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::get('/dashboard', function () {
    $user = auth()->user();
    $data = [];
    
    if ($user->role === 'admin' || $user->role === 'validateur') {
        $data['sites'] = $user->role === 'admin'
            ? \App\Models\Site::all()
            : $user->sites;

        // Les brouillons ne sont pas encore soumis : exclus de toutes les statistiques.
        $statsBase = \App\Models\Demande::query()->horsBrouillon()->forStatsViewer($user);

        $totalDemandes = (clone $statsBase)->count();
        $demandesValidees = (clone $statsBase)->whereHas('statut', fn ($q) => $q->where('code', 'validee'))->count();
        $demandesEnAttente = (clone $statsBase)->whereHas('statut', fn ($q) => $q->where('code', 'soumise')->orWhere('code', 'en_analyse'))->count();

        // Délai = soumission → décision finale (historique), et non updated_at qui bouge à chaque modification.
        $delaiMoyen = (clone $statsBase)
            ->join('historique_statuts', 'historique_statuts.demande_id', '=', 'demandes.id')
            ->join('statuts as statut_decision', 'statut_decision.id', '=', 'historique_statuts.nouveau_statut_id')
            ->whereIn('statut_decision.code', ['validee', 'rejetee'])
            ->whereNotNull('demandes.date_soumission')
            ->selectRaw('AVG(DATEDIFF(historique_statuts.date_changement, demandes.date_soumission)) as avg_days')
            ->value('avg_days');

        $data['stats'] = [
            'total_demandes'      => $totalDemandes,
            'demandes_en_attente' => $demandesEnAttente,
            'demandes_validees'   => $demandesValidees,
            'pct_validees'        => $totalDemandes > 0 ? round(($demandesValidees / $totalDemandes) * 100) : 0,
            'delai_moyen'         => $delaiMoyen !== null ? round($delaiMoyen, 1) : null,
            'total_users'         => $user->role === 'admin' ? \App\Models\User::count() : null,
        ];

        $data['stats_par_site'] = (clone $statsBase)
            ->join('sites', 'demandes.site_id', '=', 'sites.id')
            ->selectRaw('sites.nom as nom, COUNT(demandes.id) as total')
            ->groupBy('sites.id', 'sites.nom')
            ->orderByDesc('total')
            ->limit(5)
            ->get()
            ->toArray();

        $data['stats_par_type'] = (clone $statsBase)
            ->join('type_demandes', 'demandes.type_demande_id', '=', 'type_demandes.id')
            ->selectRaw('type_demandes.libelle as nom, COUNT(demandes.id) as total')
            ->groupBy('type_demandes.id', 'type_demandes.libelle')
            ->orderByDesc('total')
            ->get()
            ->toArray();

        $data['alertes_retard'] = (clone $statsBase)
            ->whereHas('statut', fn ($q) => $q->where('code', 'soumise')->orWhere('code', 'en_analyse'))
            ->whereNotNull('date_soumission')
            ->where('date_soumission', '<', now()->subDays(7))
            ->with(['user:id,name', 'site:id,nom'])
            ->select('demandes.id', 'demandes.user_id', 'demandes.site_id', 'demandes.date_soumission')
            ->orderBy('demandes.date_soumission')
            ->limit(5)
            ->get()
            ->toArray();

        // resultats_analyse est une liste de chevauchements {type, message} : non vide = conflit détecté.
        $data['alertes_conflits'] = (clone $statsBase)
            ->whereHas('statut', fn ($q) => $q->whereIn('code', ['soumise', 'en_analyse']))
            ->whereNotNull('resultats_analyse')
            ->whereJsonLength('resultats_analyse', '>', 0)
            ->with(['user:id,name', 'site:id,nom'])
            ->select('demandes.id', 'demandes.user_id', 'demandes.site_id', 'demandes.date_soumission')
            ->limit(5)
            ->get()
            ->toArray();

        $data['chart_data'] = (clone $statsBase)
            ->selectRaw('MONTH(demandes.created_at) as month, COUNT(*) as count')
            ->whereYear('demandes.created_at', date('Y'))
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->pluck('count', 'month')
            ->toArray();
    } else {
        $data['demandes_count'] = $user->demandes()->count();
        $data['demandes_validees'] = $user->demandes()->whereHas('statut', function($q) {
            $q->where('code', 'validee');
        })->count();
    }

    return Inertia::render('Dashboard', $data);
})->middleware(['auth', 'verified'])->name('dashboard');

use App\Http\Controllers\DemandeController;
use App\Http\Controllers\NotificationController;

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    // Le demandeur consulte ses dossiers, modifie ses brouillons / compléments et supprime ses brouillons.
    Route::resource('demandes', DemandeController::class);
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markAsRead'])->name('notifications.read');
    Route::get('/documents/{document}', [App\Http\Controllers\DocumentController::class, 'show'])->name('documents.show');
    Route::get('/demandes/{demande}/pdf/autorisation', [App\Http\Controllers\PdfController::class, 'autorisation'])->name('demandes.pdf.autorisation');
    Route::get('/demandes/{demande}/pdf/cahier-charges', [App\Http\Controllers\PdfController::class, 'cahierDesCharges'])->name('demandes.pdf.cahier-charges');
});

use App\Http\Controllers\Admin\SiteController;
use App\Http\Controllers\Admin\PeriodeSensibleController;
use App\Http\Controllers\Admin\TypeDemandeController;
use App\Http\Controllers\Admin\UserController;

require __DIR__.'/auth.php';

Route::middleware(['auth', 'can:is-admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::resource('sites', SiteController::class)->except(['show']);
    Route::resource('periode-sensibles', PeriodeSensibleController::class)->except(['show']);
    Route::resource('type-demandes', TypeDemandeController::class)->except(['show']);
    Route::resource('users', UserController::class)->only(['index', 'edit', 'update']);
});

use App\Http\Controllers\Validateur\DemandeController as ValidateurDemandeController;

Route::middleware(['auth', 'can:is-validateur'])->prefix('validateur')->name('validateur.')->group(function () {
    Route::get('demandes', [ValidateurDemandeController::class, 'index'])->name('demandes.index');
    Route::get('demandes/export', [ValidateurDemandeController::class, 'export'])->name('demandes.export');
    Route::get('demandes/{demande}', [ValidateurDemandeController::class, 'show'])->name('demandes.show');
    Route::post('demandes/{demande}/statut', [ValidateurDemandeController::class, 'updateStatut'])->name('demandes.update-statut');
});
