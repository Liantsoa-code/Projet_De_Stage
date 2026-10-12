<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'sites' => \App\Models\Site::orderBy('nom')->get(['id', 'nom', 'description', 'polygone_coordonnees']),
    ]);
});

// Tableau de bord adapté au rôle (demandeur, validateur, admin)
Route::get('/dashboard', App\Http\Controllers\DashboardController::class)->middleware(['auth', 'verified'])->name('dashboard');

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
