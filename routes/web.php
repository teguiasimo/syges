<?php

use App\Http\Controllers\Administration\AttributionController;
use App\Http\Controllers\Administration\EleveController;
use App\Http\Controllers\Administration\BulletinController;
use App\Http\Controllers\Administration\InstitutionController;
use App\Http\Controllers\Administration\MatiereController;
use App\Http\Controllers\Administration\NoteController;
use App\Http\Controllers\Administration\PeriodeEvaluationController;
use App\Http\Controllers\Administration\PersonnelController;
use App\Http\Controllers\Administration\RolePermissionController;
use App\Http\Controllers\Administration\UserController;
use Illuminate\Support\Facades\Route;

// Redirection par défaut de la racine vers la page de connexion dès le lancement
Route::redirect('/', 'login')->name('home');

/**
 * Route permettant de basculer la langue de l'interface utilisateur.
 * Accessible aux utilisateurs connectés comme aux visiteurs (sur la page de connexion).
 * Met à jour la session utilisateur et dépose un cookie longue durée.
 */
Route::post('locale', function (\Illuminate\Http\Request $request) {
    $validated = $request->validate([
        'locale' => ['required', 'string', 'in:fr,en'],
    ]);

    $locale = $validated['locale'];
    session(['locale' => $locale]);

    return back()->withCookie(cookie()->forever('locale', $locale));
})->name('locale.update');

Route::middleware(['auth', 'verified'])->group(function () {
    // Page du Tableau de bord utilisateur
    Route::inertia('dashboard', 'dashboard')->name('dashboard');

    // Page d'accueil du module d'Administration
    Route::inertia('administration', 'administration')->name('administration');

    // Modules d'Administration
    Route::prefix('administration')->name('administration.')->group(function () {
        // Institution & Structure Pédagogique
        Route::get('institution', [InstitutionController::class, 'index'])->name('institution.index');
        Route::get('institution/edit', [InstitutionController::class, 'editInstitution'])->name('institution.edit');
        Route::put('institution', [InstitutionController::class, 'updateInstitution'])->name('institution.update');

        // Établissements
        Route::post('etablissements', [InstitutionController::class, 'storeEtablissement'])->name('etablissements.store');
        Route::put('etablissements/{etablissement}', [InstitutionController::class, 'updateEtablissement'])->name('etablissements.update');
        Route::delete('etablissements/{etablissement}', [InstitutionController::class, 'destroyEtablissement'])->name('etablissements.destroy');

        // Sections
        Route::post('sections', [InstitutionController::class, 'storeSection'])->name('sections.store');
        Route::put('sections/{section}', [InstitutionController::class, 'updateSection'])->name('sections.update');
        Route::delete('sections/{section}', [InstitutionController::class, 'destroySection'])->name('sections.destroy');

        // Cycles
        Route::post('cycles', [InstitutionController::class, 'storeCycle'])->name('cycles.store');
        Route::put('cycles/{cycle}', [InstitutionController::class, 'updateCycle'])->name('cycles.update');
        Route::delete('cycles/{cycle}', [InstitutionController::class, 'destroyCycle'])->name('cycles.destroy');

        // Niveaux
        Route::post('niveaux', [InstitutionController::class, 'storeNiveau'])->name('niveaux.store');
        Route::put('niveaux/{niveau}', [InstitutionController::class, 'updateNiveau'])->name('niveaux.update');
        Route::delete('niveaux/{niveau}', [InstitutionController::class, 'destroyNiveau'])->name('niveaux.destroy');

        // Classes
        Route::patch('classes/{classe}/toggle-active', [InstitutionController::class, 'toggleClasseStatus'])->name('classes.toggle-active');
        Route::post('classes', [InstitutionController::class, 'storeClasse'])->name('classes.store');
        Route::put('classes/{classe}', [InstitutionController::class, 'updateClasse'])->name('classes.update');
        Route::delete('classes/{classe}', [InstitutionController::class, 'destroyClasse'])->name('classes.destroy');

        // Module 3 : Périodes & Paramètres d'Évaluation (Gesco 3)
        Route::get('periodes', [PeriodeEvaluationController::class, 'index'])->name('periodes.index');
        Route::post('periodes/annees', [PeriodeEvaluationController::class, 'storeAnnee'])->name('periodes.annees.store');
        Route::put('periodes/annees/{anneeScolaire}', [PeriodeEvaluationController::class, 'updateAnnee'])->name('periodes.annees.update');
        Route::patch('periodes/annees/{anneeScolaire}/set-current', [PeriodeEvaluationController::class, 'setCurrentAnnee'])->name('periodes.annees.set-current');
        Route::patch('periodes/annees/{anneeScolaire}/mode-calcul', [PeriodeEvaluationController::class, 'updateModeCalcul'])->name('periodes.annees.mode-calcul');
        Route::post('periodes/annees/{anneeScolaire}/generate-standard', [PeriodeEvaluationController::class, 'generateStandardStructure'])->name('periodes.annees.generate-standard');

        Route::post('periodes/trimestres', [PeriodeEvaluationController::class, 'storeTrimestre'])->name('periodes.trimestres.store');
        Route::put('periodes/trimestres/{periode}', [PeriodeEvaluationController::class, 'updateTrimestre'])->name('periodes.trimestres.update');
        Route::delete('periodes/trimestres/{periode}', [PeriodeEvaluationController::class, 'destroyTrimestre'])->name('periodes.trimestres.destroy');

        Route::post('periodes/evaluations', [PeriodeEvaluationController::class, 'storeEvaluation'])->name('periodes.evaluations.store');
        Route::put('periodes/evaluations/{evaluation}', [PeriodeEvaluationController::class, 'updateEvaluation'])->name('periodes.evaluations.update');
        Route::delete('periodes/evaluations/{evaluation}', [PeriodeEvaluationController::class, 'destroyEvaluation'])->name('periodes.evaluations.destroy');
        Route::patch('periodes/evaluations/{evaluation}/toggle-lock', [PeriodeEvaluationController::class, 'toggleLockEvaluation'])->name('periodes.evaluations.toggle-lock');

        // Module 4 : Gestion des Matières & Coefficients (Gesco 4)
        Route::get('matieres', [MatiereController::class, 'index'])->name('matieres.index');
        Route::post('matieres/standards', [MatiereController::class, 'seedStandards'])->name('matieres.seed-standards');

        // Groupes de Matières (Blocs Pédagogiques)
        Route::post('matieres/groupes', [MatiereController::class, 'storeGroupe'])->name('matieres.groupes.store');
        Route::put('matieres/groupes/{groupe}', [MatiereController::class, 'updateGroupe'])->name('matieres.groupes.update');
        Route::delete('matieres/groupes/{groupe}', [MatiereController::class, 'destroyGroupe'])->name('matieres.groupes.destroy');

        // Catalogue Général des Matières
        Route::post('matieres/catalogue', [MatiereController::class, 'storeMatiere'])->name('matieres.catalogue.store');
        Route::put('matieres/catalogue/{matiere}', [MatiereController::class, 'updateMatiere'])->name('matieres.catalogue.update');
        Route::delete('matieres/catalogue/{matiere}', [MatiereController::class, 'destroyMatiere'])->name('matieres.catalogue.destroy');

        // Configuration des Coefficients
        Route::post('matieres/coefficients', [MatiereController::class, 'storeCoefficient'])->name('matieres.coefficients.store');
        Route::put('matieres/coefficients/{coefficient}', [MatiereController::class, 'updateCoefficient'])->name('matieres.coefficients.update');
        Route::delete('matieres/coefficients/{coefficient}', [MatiereController::class, 'destroyCoefficient'])->name('matieres.coefficients.destroy');

        // Module 5 : Gestion des Élèves & Inscriptions (Gesco 5 & SAGEES)
        Route::get('eleves/search-anciens', [EleveController::class, 'searchAnciens'])->name('eleves.search-anciens');
        Route::get('eleves', [EleveController::class, 'index'])->name('eleves.index');
        Route::post('eleves', [EleveController::class, 'store'])->name('eleves.store');
        Route::put('eleves/{eleve}', [EleveController::class, 'update'])->name('eleves.update');
        Route::delete('eleves/{eleve}', [EleveController::class, 'destroy'])->name('eleves.destroy');
        Route::post('eleves/reinscrire', [EleveController::class, 'reinscrire'])->name('eleves.reinscrire');
        Route::patch('inscriptions/{inscription}/statut', [EleveController::class, 'updateStatut'])->name('inscriptions.statut');
        Route::post('inscriptions/transferer', [EleveController::class, 'transferer'])->name('inscriptions.transferer');
        Route::post('eleves/import-csv', [EleveController::class, 'importCsv'])->name('eleves.import-csv');

        // Rôles & Permissions
        Route::resource('roles', RolePermissionController::class)->only(['index', 'store', 'update', 'destroy']);

        // Gestion des Utilisateurs
        Route::patch('users/{user}/toggle-status', [UserController::class, 'toggleStatus'])->name('users.toggle-status');
        Route::resource('users', UserController::class)->only(['index', 'store', 'update']);

        // Module 8 : Personnel & Ressources Humaines (Gesco 8)
        Route::patch('personnels/{personnel}/toggle-status', [PersonnelController::class, 'toggleStatus'])->name('personnels.toggle-status');
        Route::post('personnels/departements', [PersonnelController::class, 'storeDepartement'])->name('personnels.departements.store');
        Route::put('personnels/departements/{departement}', [PersonnelController::class, 'updateDepartement'])->name('personnels.departements.update');
        Route::delete('personnels/departements/{departement}', [PersonnelController::class, 'destroyDepartement'])->name('personnels.departements.destroy');
        Route::resource('personnels', PersonnelController::class)->only(['index', 'store', 'update', 'destroy']);

        // Module 9 : Attribution des Classes & Matières (Gesco 9)
        Route::patch('attributions/classes/{classe}/professeur-principal', [AttributionController::class, 'setProfesseurPrincipal'])->name('attributions.classes.professeur-principal');
        Route::post('attributions/classes/{classe}/batch', [AttributionController::class, 'batchAssign'])->name('attributions.classes.batch');
        Route::resource('attributions', AttributionController::class)->only(['index', 'store', 'update', 'destroy']);

        // Module 10 : Saisie des Notes & Calcul des Bulletins (Gesco 10)
        Route::get('notes', [NoteController::class, 'index'])->name('notes.index');
        Route::get('notes/grille', [NoteController::class, 'getGrille'])->name('notes.grille');
        Route::post('notes/sauvegarder', [NoteController::class, 'sauvegarder'])->name('notes.sauvegarder');
        Route::post('notes/verrouiller', [NoteController::class, 'verrouiller'])->name('notes.verrouiller');
        Route::post('notes/deverrouiller', [NoteController::class, 'deverrouiller'])->name('notes.deverrouiller');

        Route::get('bulletins', [BulletinController::class, 'index'])->name('bulletins.index');
        Route::get('bulletins/previsualiser/{inscription}/{periode?}', [BulletinController::class, 'previsualiser'])->name('bulletins.previsualiser');
        Route::get('bulletins/pdf/{inscription}/{periode?}', [BulletinController::class, 'telechargerPdf'])->name('bulletins.pdf');
        Route::get('bulletins/classe-pdf/{classe}/{periode?}', [BulletinController::class, 'telechargerClassePdf'])->name('bulletins.classe-pdf');
        Route::post('bulletins/discipline', [BulletinController::class, 'sauvegarderDiscipline'])->name('bulletins.discipline');

        // Module 11 : Gestion de la Caisse & Paiements (Gesco 11)
        Route::get('finances', [\App\Http\Controllers\Administration\FinanceController::class, 'index'])->name('finances.index');
        Route::post('finances/versements', [\App\Http\Controllers\Administration\FinanceController::class, 'storeVersement'])->name('finances.versements.store');
        Route::get('finances/versements/{versement}/recu', [\App\Http\Controllers\Administration\FinanceController::class, 'imprimerRecu'])->name('finances.versements.recu');
        Route::post('finances/versements/{versement}/annuler', [\App\Http\Controllers\Administration\FinanceController::class, 'annulerVersement'])->name('finances.versements.annuler');
        Route::post('finances/comptes/{compte}/ajuster', [\App\Http\Controllers\Administration\FinanceController::class, 'ajusterCompte'])->name('finances.comptes.ajuster');
        Route::post('finances/cloturer-journee', [\App\Http\Controllers\Administration\FinanceController::class, 'cloturerJournee'])->name('finances.cloturer-journee');
        Route::post('finances/types-frais', [\App\Http\Controllers\Administration\FinanceController::class, 'storeTypeFrais'])->name('finances.types-frais.store');
        Route::delete('finances/types-frais/{typeFrais}', [\App\Http\Controllers\Administration\FinanceController::class, 'destroyTypeFrais'])->name('finances.types-frais.destroy');
        Route::post('finances/tarifs', [\App\Http\Controllers\Administration\FinanceController::class, 'storeTarif'])->name('finances.tarifs.store');
        Route::delete('finances/tarifs/{fraisScolaire}', [\App\Http\Controllers\Administration\FinanceController::class, 'destroyTarif'])->name('finances.tarifs.destroy');
        Route::post('finances/etablissements/{etablissement}/verrou-solvabilite', [\App\Http\Controllers\Administration\FinanceController::class, 'toggleVerrouSolvabilite'])->name('finances.verrou-solvabilite');

        // Module 12 : Absences, Retards & Discipline (Gesco 12)
        Route::get('discipline', [\App\Http\Controllers\Administration\DisciplineController::class, 'index'])->name('discipline.index');
        Route::post('discipline/assiduites', [\App\Http\Controllers\Administration\DisciplineController::class, 'storeAssiduite'])->name('discipline.assiduites.store');
        Route::match(['post', 'patch'], 'discipline/assiduites/{assiduite}/justifier', [\App\Http\Controllers\Administration\DisciplineController::class, 'justifierAssiduite'])->name('discipline.assiduites.justifier');
        Route::delete('discipline/assiduites/{assiduite}', [\App\Http\Controllers\Administration\DisciplineController::class, 'destroyAssiduite'])->name('discipline.assiduites.destroy');
        Route::post('discipline/sanctions', [\App\Http\Controllers\Administration\DisciplineController::class, 'storeSanction'])->name('discipline.sanctions.store');
        Route::delete('discipline/sanctions/{sanction}', [\App\Http\Controllers\Administration\DisciplineController::class, 'destroySanction'])->name('discipline.sanctions.destroy');
    });
});

require __DIR__.'/settings.php';
