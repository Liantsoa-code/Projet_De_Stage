<?php

use App\Http\Controllers\AccueilController;
use App\Http\Controllers\Admin\PeriodeSensibleController;
use App\Http\Controllers\Admin\SiteController;
use App\Http\Controllers\Admin\TypeDemandeController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DemandeController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\DocumentReferenceController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PdfController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ModeleReferenceController;
use App\Http\Controllers\ReferenceController;
use App\Http\Controllers\Validateur\DemandeController as ValidateurDemandeController;
use Illuminate\Support\Facades\Route;

// Page d'accueil publique : présentation et carte des aires protégées
Route::get('/', AccueilController::class);

// Tableau de bord adapté au rôle (demandeur, validateur, admin)
Route::get('/dashboard', DashboardController::class)->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    // Le demandeur consulte ses dossiers, modifie ses brouillons / compléments et supprime ses brouillons.
    // Création et modification (avec envoi de fichiers) limitées par le limiteur « demandes » (AppServiceProvider).
    Route::resource('demandes', DemandeController::class)->middlewareFor(['store', 'update'], 'throttle:demandes');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead'])->name('notifications.read-all');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markAsRead'])->name('notifications.read');
    Route::get('/documents/{document}', [DocumentController::class, 'show'])->name('documents.show');
    Route::get('/demandes/{demande}/pdf/autorisation', [PdfController::class, 'autorisation'])->name('demandes.pdf.autorisation');
    Route::get('/demandes/{demande}/pdf/cahier-charges', [PdfController::class, 'cahierDesCharges'])->name('demandes.pdf.cahier-charges');
});

require __DIR__.'/auth.php';

Route::middleware(['auth', 'can:is-admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::resource('sites', SiteController::class)->except(['show']);
    Route::resource('periode-sensibles', PeriodeSensibleController::class)->except(['show']);
    Route::resource('type-demandes', TypeDemandeController::class)->except(['show']);
    Route::resource('users', UserController::class)->only(['index', 'edit', 'update']);
});

Route::middleware(['auth', 'can:is-validateur'])->prefix('validateur')->name('validateur.')->group(function () {
    Route::get('demandes', [ValidateurDemandeController::class, 'index'])->name('demandes.index');
    Route::get('demandes/export', [ValidateurDemandeController::class, 'export'])->name('demandes.export');
    Route::get('demandes/{demande}', [ValidateurDemandeController::class, 'show'])->name('demandes.show');
    Route::post('demandes/{demande}/statut', [ValidateurDemandeController::class, 'updateStatut'])->name('demandes.update-statut');
    // Validation en deux temps : générer les documents, les relire et modifier, puis signer (update-statut)
    Route::post('demandes/{demande}/documents', [ValidateurDemandeController::class, 'genererDocuments'])->name('demandes.documents.generer');
    Route::put('demandes/{demande}/documents', [ValidateurDemandeController::class, 'modifierDocuments'])->name('demandes.documents.modifier');
    Route::delete('demandes/{demande}/documents', [ValidateurDemandeController::class, 'annulerDocuments'])->name('demandes.documents.annuler');
    Route::get('demandes/{demande}/documents/{type}/apercu', [ValidateurDemandeController::class, 'apercuDocument'])
        ->whereIn('type', ['autorisation', 'cahier_charges'])->name('demandes.documents.apercu');
});

// Références : modèles des documents officiels (avis favorable, autorisation, refus…)
// et documents déposés par l'équipe (dépôt limité par le limiteur « demandes »).
Route::middleware(['auth', 'can:is-validateur'])->prefix('references')->name('references.')->group(function () {
    Route::get('/', [ReferenceController::class, 'index'])->name('index');
    Route::get('modeles/{modele}', [ModeleReferenceController::class, 'show'])->whereNumber('modele')->name('modeles.show');
    Route::post('documents', [DocumentReferenceController::class, 'store'])->middleware('throttle:demandes')->name('documents.store');
    Route::get('documents/{document}', [DocumentReferenceController::class, 'show'])->name('documents.show');
    Route::delete('documents/{document}', [DocumentReferenceController::class, 'destroy'])->name('documents.destroy');
});

// Gestion des modèles de la page Références : admins uniquement
Route::middleware(['auth', 'can:is-admin'])->prefix('references/modeles')->name('references.modeles.')->group(function () {
    Route::get('create', [ModeleReferenceController::class, 'create'])->name('create');
    Route::post('/', [ModeleReferenceController::class, 'store'])->name('store');
    Route::post('apercu', [ModeleReferenceController::class, 'apercu'])->name('apercu');
    Route::get('{modele}/edit', [ModeleReferenceController::class, 'edit'])->name('edit');
    Route::put('{modele}', [ModeleReferenceController::class, 'update'])->name('update');
    Route::delete('{modele}', [ModeleReferenceController::class, 'destroy'])->name('destroy');
});
