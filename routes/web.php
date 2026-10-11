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
use App\Http\Controllers\Administration\CarteScolaireController;
use App\Http\Controllers\Administration\CompetencePeriodiqueController;
use App\Http\Controllers\Administration\DispenseEleveController;
use App\Http\Controllers\Administration\ReleveNoteController;
use Illuminate\Support\Facades\Route;

// Redirection par défaut de la racine vers la page de connexion dès le lancement
Route::redirect('/', 'login')->name('home');

/**
 * Route publique de contrôle d'authenticité des cartes scolaires par scan QR code.
 */
Route::get('cartes-scolaires/verifier/{matricule}', [CarteScolaireController::class, 'verifier'])
    ->name('cartes-scolaires.verifier')
    ->middleware('throttle:60,1');

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
    /**
     * Bascule l'établissement actif pour la session de l'utilisateur (SaaS Multi-Tenant).
     * Les Super Admins peuvent basculer librement vers n'importe quel campus de l'institution.
     * Le personnel scolaire local est restreint à son établissement de rattachement.
     */
    Route::post('switch-etablissement', function (\Illuminate\Http\Request $request) {
        $user = $request->user();
        $isSuperAdmin = $user ? ($user->hasRole('super-admin') || $user->email === 'admin@gmail.com') : false;

        $validated = $request->validate([
            'etablissement_id' => ['required', 'exists:etablissements,id'],
        ]);

        if (! $isSuperAdmin) {
            $effectiveEtabId = $user ? $user->getEffectiveEtablissementId() : null;
            if ($effectiveEtabId && (int) $effectiveEtabId !== (int) $validated['etablissement_id']) {
                abort(403, 'Accès non autorisé : vous êtes assigné à un établissement spécifique.');
            }
        }

        $etab = \App\Models\Etablissement::findOrFail($validated['etablissement_id']);
        $request->session()->put('current_etablissement_id', $etab->id);

        return back()->with('success', "Campus actif basculé vers « {$etab->nom} » avec succès.");
    })->name('context.switch-etablissement');

    // Page du Tableau de bord exécutif (Module 15)
    Route::get('dashboard', [\App\Http\Controllers\Administration\TableauDeBordController::class, 'index'])->name('dashboard');

    // Page d'accueil du module d'Administration
    Route::inertia('administration', 'administration')->name('administration');

    // Modules d'Administration
    Route::prefix('administration')->name('administration.')->group(function () {
        // Institution & Structure Pédagogique
        Route::get('institution', [InstitutionController::class, 'index'])->name('institution.index');
        Route::get('institution/edit', [InstitutionController::class, 'editInstitution'])->name('institution.edit');
        Route::match(['put', 'post'], 'institution', [InstitutionController::class, 'updateInstitution'])->name('institution.update');

        // Établissements
        Route::post('etablissements', [InstitutionController::class, 'storeEtablissement'])->name('etablissements.store');
        Route::get('etablissements/{etablissement}/settings', [InstitutionController::class, 'editEtablissementSettings'])->name('etablissements.settings');
        Route::put('etablissements/{etablissement}/settings', [InstitutionController::class, 'updateEtablissementSettings'])->name('etablissements.settings.update');
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

        // Séries pédagogiques créées par l'utilisateur
        Route::post('series', [InstitutionController::class, 'storeSerie'])->name('series.store');
        Route::put('series/{serie}', [InstitutionController::class, 'updateSerie'])->name('series.update');
        Route::delete('series/{serie}', [InstitutionController::class, 'destroySerie'])->name('series.destroy');

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
        Route::post('matieres/coefficients/duplicate', [MatiereController::class, 'duplicateCoefficients'])->name('matieres.coefficients.duplicate');
        Route::post('matieres/coefficients', [MatiereController::class, 'storeCoefficient'])->name('matieres.coefficients.store');
        Route::put('matieres/coefficients/{coefficient}', [MatiereController::class, 'updateCoefficient'])->name('matieres.coefficients.update');
        Route::delete('matieres/coefficients/{coefficient}', [MatiereController::class, 'destroyCoefficient'])->name('matieres.coefficients.destroy');

        // Module 5 : Gestion des Élèves & Inscriptions (Gesco 5 & SAGEES)
        Route::get('eleves/search-anciens', [EleveController::class, 'searchAnciens'])->name('eleves.search-anciens');
        Route::get('eleves/classe-anciens', [EleveController::class, 'getClasseElevesForReinscription'])->name('eleves.classe-anciens');
        Route::get('/api/search-anciens-eleves', [EleveController::class, 'searchAnciens'])->name('eleves.api-search-anciens');
        Route::get('eleves', [EleveController::class, 'index'])->name('eleves.index');
        Route::get('eleves/create', [EleveController::class, 'create'])->name('eleves.create');
        Route::get('eleves/reinscription', [EleveController::class, 'reinscriptionPage'])->name('eleves.reinscription');
        Route::post('eleves', [EleveController::class, 'store'])->name('eleves.store');
        Route::put('eleves/{eleve}', [EleveController::class, 'update'])->name('eleves.update');
        Route::delete('eleves/{eleve}', [EleveController::class, 'destroy'])->name('eleves.destroy');
        Route::post('eleves/reinscrire', [EleveController::class, 'reinscrire'])->name('eleves.reinscrire');
        Route::post('eleves/reinscrire-lot', [EleveController::class, 'reinscrireLot'])->name('eleves.reinscrire-lot');
        Route::patch('inscriptions/{inscription}/statut', [EleveController::class, 'updateStatut'])->name('inscriptions.statut');
        Route::post('inscriptions/transferer', [EleveController::class, 'transferer'])->name('inscriptions.transferer');
        Route::post('eleves/import-csv', [EleveController::class, 'importCsv'])->name('eleves.import-csv');
        Route::get('eleves/export-csv', [EleveController::class, 'exportCsv'])->name('eleves.export-csv');

        // Cartes Scolaires & Badges d'Identité (Phase 2)
        Route::get('eleves/cartes-scolaires', [CarteScolaireController::class, 'index'])->name('eleves.cartes-scolaires');
        Route::post('eleves/cartes-scolaires/imprimer-planche', [CarteScolaireController::class, 'imprimerPlanche'])->name('eleves.cartes-scolaires.imprimer-planche');
        Route::get('eleves/{inscription}/carte-scolaire-pdf', [CarteScolaireController::class, 'imprimerUnitaire'])->name('eleves.carte-scolaire-pdf');

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
        Route::resource('personnels', PersonnelController::class)->only(['index', 'create', 'store', 'update', 'destroy']);

        // Module 9 : Attribution des Classes & Matières (Gesco 9)
        Route::get('attributions/grille', [AttributionController::class, 'grille'])->name('attributions.grille');
        Route::patch('attributions/classes/{classe}/professeur-principal', [AttributionController::class, 'setProfesseurPrincipal'])->name('attributions.classes.professeur-principal');
        Route::post('attributions/classes/{classe}/batch', [AttributionController::class, 'batchAssign'])->name('attributions.classes.batch');
        Route::resource('attributions', AttributionController::class)->only(['index', 'store', 'update', 'destroy']);

        // Module Suivi Pédagogique : Cahier de Texte & Fiches de Progression (Aurore 5.1.8)
        Route::get('progression-pedagogique', [\App\Http\Controllers\Administration\ProgressionPedagogiqueController::class, 'index'])->name('progression.index');
        Route::post('progression-pedagogique', [\App\Http\Controllers\Administration\ProgressionPedagogiqueController::class, 'store'])->name('progression.store');
        Route::put('progression-pedagogique/{progression}', [\App\Http\Controllers\Administration\ProgressionPedagogiqueController::class, 'update'])->name('progression.update');
        Route::delete('progression-pedagogique/{progression}', [\App\Http\Controllers\Administration\ProgressionPedagogiqueController::class, 'destroy'])->name('progression.destroy');
        Route::post('progression-pedagogique/{progression}/viser', [\App\Http\Controllers\Administration\ProgressionPedagogiqueController::class, 'viser'])->name('progression.viser');
        Route::get('progression-pedagogique/imprimer/{attribution}', [\App\Http\Controllers\Administration\ProgressionPedagogiqueController::class, 'imprimerFiche'])->name('progression.imprimer');


        // Module 10 : Saisie des Notes & Calcul des Bulletins (Gesco 10)
        Route::get('notes', [NoteController::class, 'index'])->name('notes.index');
        Route::get('notes/grille', [NoteController::class, 'getGrille'])->name('notes.grille');
        Route::post('notes/sauvegarder', [NoteController::class, 'sauvegarder'])->name('notes.sauvegarder');
        Route::post('notes/verrouiller', [NoteController::class, 'verrouiller'])->name('notes.verrouiller');
        Route::post('notes/deverrouiller', [NoteController::class, 'deverrouiller'])->name('notes.deverrouiller');

        // Relevés de Notes & Fiches Officielles de Notation
        Route::get('notes/releves', [ReleveNoteController::class, 'index'])->name('notes.releves.index');
        Route::get('notes/releves/pdf', [ReleveNoteController::class, 'exportPdf'])->name('notes.releves.pdf');

        Route::get('bulletins', [BulletinController::class, 'index'])->name('bulletins.index');
        Route::get('bulletins/previsualiser/{inscription}/{periode?}', [BulletinController::class, 'previsualiser'])->name('bulletins.previsualiser');
        Route::get('bulletins/pdf/{inscription}/{periode?}', [BulletinController::class, 'telechargerPdf'])->name('bulletins.pdf');
        Route::get('bulletins/classe-pdf/{classe}/{periode?}', [BulletinController::class, 'telechargerClassePdf'])->name('bulletins.classe-pdf');
        Route::post('bulletins/discipline', [BulletinController::class, 'sauvegarderDiscipline'])->name('bulletins.discipline');

        // Module APC : Pédagogie par Compétences & Cotes (Phase 3)
        Route::get('pedagogie/competences', [CompetencePeriodiqueController::class, 'index'])->name('pedagogie.competences.index');
        Route::post('pedagogie/competences', [CompetencePeriodiqueController::class, 'store'])->name('pedagogie.competences.store');
        Route::put('pedagogie/competences/{competence}', [CompetencePeriodiqueController::class, 'update'])->name('pedagogie.competences.update');
        Route::delete('pedagogie/competences/{competence}', [CompetencePeriodiqueController::class, 'destroy'])->name('pedagogie.competences.destroy');
        Route::post('pedagogie/competences/transferer', [CompetencePeriodiqueController::class, 'transferer'])->name('pedagogie.competences.transferer');
        Route::post('pedagogie/competences/cotes', [CompetencePeriodiqueController::class, 'storeCote'])->name('pedagogie.competences.cotes.store');
        Route::delete('pedagogie/competences/cotes/{cote}', [CompetencePeriodiqueController::class, 'destroyCote'])->name('pedagogie.competences.cotes.destroy');

        // Module Dérogations & Dispenses Officielles (Phase 4)
        Route::get('pedagogie/dispenses', [DispenseEleveController::class, 'index'])->name('pedagogie.dispenses.index');
        Route::post('pedagogie/dispenses', [DispenseEleveController::class, 'store'])->name('pedagogie.dispenses.store');
        Route::put('pedagogie/dispenses/{dispense}', [DispenseEleveController::class, 'update'])->name('pedagogie.dispenses.update');
        Route::delete('pedagogie/dispenses/{dispense}', [DispenseEleveController::class, 'destroy'])->name('pedagogie.dispenses.destroy');
        Route::patch('pedagogie/dispenses/{dispense}/statut', [DispenseEleveController::class, 'changerStatut'])->name('pedagogie.dispenses.statut');
        Route::get('pedagogie/dispenses/pv-pdf', [DispenseEleveController::class, 'exporterPv'])->name('pedagogie.dispenses.pv-pdf');

        // Module 11 : Gestion de la Caisse & Paiements (Gesco 11 & Aurore)
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

        // Caisses multiples (Aurore §3.20)
        Route::post('finances/caisses', [\App\Http\Controllers\Administration\FinanceController::class, 'storeCaisse'])->name('finances.caisses.store');
        Route::put('finances/caisses/{caisse}', [\App\Http\Controllers\Administration\FinanceController::class, 'updateCaisse'])->name('finances.caisses.update');
        Route::delete('finances/caisses/{caisse}', [\App\Http\Controllers\Administration\FinanceController::class, 'destroyCaisse'])->name('finances.caisses.destroy');

        // Règlements divers hors scolarité (Aurore §4.2)
        Route::post('finances/reglements-divers', [\App\Http\Controllers\Administration\FinanceController::class, 'storeReglementDivers'])->name('finances.reglements-divers.store');
        Route::post('finances/reglements-divers/{reglement}/annuler', [\App\Http\Controllers\Administration\FinanceController::class, 'annulerReglementDivers'])->name('finances.reglements-divers.annuler');
        Route::get('finances/reglements-divers/{reglement}/recu', [\App\Http\Controllers\Administration\FinanceController::class, 'imprimerRecuDivers'])->name('finances.reglements-divers.recu');

        // Bourses & Remises (Aurore §3.19, §3.21, §3.25, §4.1.6)
        Route::post('finances/bourses-remises', [\App\Http\Controllers\Administration\FinanceController::class, 'storeBourseRemise'])->name('finances.bourses-remises.store');
        Route::put('finances/bourses-remises/{bourseRemise}', [\App\Http\Controllers\Administration\FinanceController::class, 'updateBourseRemise'])->name('finances.bourses-remises.update');
        Route::delete('finances/bourses-remises/{bourseRemise}', [\App\Http\Controllers\Administration\FinanceController::class, 'destroyBourseRemise'])->name('finances.bourses-remises.destroy');
        Route::post('finances/bourses-remises/attribuer', [\App\Http\Controllers\Administration\FinanceController::class, 'attribuerBourseRemise'])->name('finances.bourses-remises.attribuer');
        Route::delete('finances/bourses-remises/attributions/{attribution}', [\App\Http\Controllers\Administration\FinanceController::class, 'retirerBourseRemise'])->name('finances.bourses-remises.retirer');

        // Modèles d'échéances calendaires (Aurore §3.24)
        Route::post('finances/modeles-echeances', [\App\Http\Controllers\Administration\FinanceController::class, 'storeModeleEcheance'])->name('finances.modeles-echeances.store');
        Route::delete('finances/modeles-echeances/{modeleEcheance}', [\App\Http\Controllers\Administration\FinanceController::class, 'destroyModeleEcheance'])->name('finances.modeles-echeances.destroy');

        // Pénalités financières de retard (Aurore §3.17)
        Route::post('finances/penalites-configurations', [\App\Http\Controllers\Administration\FinanceController::class, 'storePenaliteConfiguration'])->name('finances.penalites-configurations.store');
        Route::post('finances/penalites/appliquer-echeances', [\App\Http\Controllers\Administration\FinanceController::class, 'appliquerPenalitesRetard'])->name('finances.penalites.appliquer-echeances');
        Route::post('finances/penalites/{penalite}/annuler', [\App\Http\Controllers\Administration\FinanceController::class, 'annulerPenaliteEleve'])->name('finances.penalites.annuler');

        // Module Dépenses, Fiches de Décharge & Bilan Financier (Phase 1)
        Route::get('finances/depenses', [\App\Http\Controllers\Administration\DepenseController::class, 'index'])->name('finances.depenses.index');
        Route::post('finances/depenses', [\App\Http\Controllers\Administration\DepenseController::class, 'store'])->name('finances.depenses.store');
        Route::put('finances/depenses/{depense}', [\App\Http\Controllers\Administration\DepenseController::class, 'update'])->name('finances.depenses.update');
        Route::delete('finances/depenses/{depense}', [\App\Http\Controllers\Administration\DepenseController::class, 'destroy'])->name('finances.depenses.destroy');
        Route::post('finances/depenses/categories', [\App\Http\Controllers\Administration\DepenseController::class, 'storeCategorie'])->name('finances.depenses.categories.store');
        Route::get('finances/depenses/{depense}/decharge-pdf', [\App\Http\Controllers\Administration\DepenseController::class, 'imprimerDecharge'])->name('finances.depenses.decharge-pdf');
        Route::get('finances/bilan-pdf', [\App\Http\Controllers\Administration\DepenseController::class, 'imprimerBilan'])->name('finances.bilan-pdf');

        // Module 12 : Absences, Retards & Discipline (Gesco 12)
        Route::get('discipline', [\App\Http\Controllers\Administration\DisciplineController::class, 'index'])->name('discipline.index');
        Route::post('discipline/assiduites', [\App\Http\Controllers\Administration\DisciplineController::class, 'storeAssiduite'])->name('discipline.assiduites.store');
        Route::match(['post', 'patch'], 'discipline/assiduites/{assiduite}/justifier', [\App\Http\Controllers\Administration\DisciplineController::class, 'justifierAssiduite'])->name('discipline.assiduites.justifier');
        Route::delete('discipline/assiduites/{assiduite}', [\App\Http\Controllers\Administration\DisciplineController::class, 'destroyAssiduite'])->name('discipline.assiduites.destroy');
        Route::post('discipline/sanctions', [\App\Http\Controllers\Administration\DisciplineController::class, 'storeSanction'])->name('discipline.sanctions.store');
        Route::delete('discipline/sanctions/{sanction}', [\App\Http\Controllers\Administration\DisciplineController::class, 'destroySanction'])->name('discipline.sanctions.destroy');

        // Conseil de Discipline & Heures de Consigne (Phase 5)
        Route::get('discipline/conseil', [\App\Http\Controllers\Administration\ConseilDisciplineController::class, 'index'])->name('discipline.conseil.index');
        Route::post('discipline/conseil/sessions', [\App\Http\Controllers\Administration\ConseilDisciplineController::class, 'storeSession'])->name('discipline.conseil.sessions.store');
        Route::put('discipline/conseil/sessions/{conseil}', [\App\Http\Controllers\Administration\ConseilDisciplineController::class, 'updateSession'])->name('discipline.conseil.sessions.update');
        Route::delete('discipline/conseil/sessions/{conseil}', [\App\Http\Controllers\Administration\ConseilDisciplineController::class, 'destroySession'])->name('discipline.conseil.sessions.destroy');
        Route::get('discipline/conseil/sessions/{conseil}/pv-pdf', [\App\Http\Controllers\Administration\ConseilDisciplineController::class, 'imprimerPv'])->name('discipline.conseil.sessions.pv-pdf');

        Route::post('discipline/conseil/sessions/{conseil}/dossiers', [\App\Http\Controllers\Administration\ConseilDisciplineController::class, 'storeDossier'])->name('discipline.conseil.dossiers.store');
        Route::put('discipline/conseil/dossiers/{dossier}', [\App\Http\Controllers\Administration\ConseilDisciplineController::class, 'updateDossier'])->name('discipline.conseil.dossiers.update');
        Route::delete('discipline/conseil/dossiers/{dossier}', [\App\Http\Controllers\Administration\ConseilDisciplineController::class, 'destroyDossier'])->name('discipline.conseil.dossiers.destroy');
        Route::get('discipline/conseil/dossiers/{dossier}/convocation-pdf', [\App\Http\Controllers\Administration\ConseilDisciplineController::class, 'imprimerConvocation'])->name('discipline.conseil.dossiers.convocation-pdf');

        Route::post('discipline/heures-consigne', [\App\Http\Controllers\Administration\ConseilDisciplineController::class, 'storeHeureConsigne'])->name('discipline.heures-consigne.store');
        Route::put('discipline/heures-consigne/{consigne}', [\App\Http\Controllers\Administration\ConseilDisciplineController::class, 'updateHeureConsigne'])->name('discipline.heures-consigne.update');
        Route::delete('discipline/heures-consigne/{consigne}', [\App\Http\Controllers\Administration\ConseilDisciplineController::class, 'destroyHeureConsigne'])->name('discipline.heures-consigne.destroy');

        // Module 13 : Emplois du Temps & Gestion des Salles (Gesco 13)
        Route::get('emplois-du-temps', [\App\Http\Controllers\Administration\EmploiDuTempsController::class, 'index'])->name('emplois-du-temps.index');
        Route::post('emplois-du-temps/seances', [\App\Http\Controllers\Administration\EmploiDuTempsController::class, 'storeSeance'])->name('emplois-du-temps.seances.store');
        Route::put('emplois-du-temps/seances/{seance}', [\App\Http\Controllers\Administration\EmploiDuTempsController::class, 'updateSeance'])->name('emplois-du-temps.seances.update');
        Route::delete('emplois-du-temps/seances/{seance}', [\App\Http\Controllers\Administration\EmploiDuTempsController::class, 'destroySeance'])->name('emplois-du-temps.seances.destroy');
        Route::post('emplois-du-temps/seances/{seance}/annuler', [\App\Http\Controllers\Administration\EmploiDuTempsController::class, 'annulerSeance'])->name('emplois-du-temps.seances.annuler');
        Route::post('emplois-du-temps/seances/{seance}/remplacer', [\App\Http\Controllers\Administration\EmploiDuTempsController::class, 'remplacerSeance'])->name('emplois-du-temps.seances.remplacer');
        Route::post('emplois-du-temps/seances/{seance}/retablir', [\App\Http\Controllers\Administration\EmploiDuTempsController::class, 'retablirSeance'])->name('emplois-du-temps.seances.retablir');
        Route::post('emplois-du-temps/salles', [\App\Http\Controllers\Administration\EmploiDuTempsController::class, 'storeSalle'])->name('emplois-du-temps.salles.store');
        Route::put('emplois-du-temps/salles/{salle}', [\App\Http\Controllers\Administration\EmploiDuTempsController::class, 'updateSalle'])->name('emplois-du-temps.salles.update');
        Route::delete('emplois-du-temps/salles/{salle}', [\App\Http\Controllers\Administration\EmploiDuTempsController::class, 'destroySalle'])->name('emplois-du-temps.salles.destroy');

        // Module 14 : Communication & Notifications SMS (Gesco 14)
        Route::get('communication', [\App\Http\Controllers\Administration\CommunicationSmsController::class, 'index'])->name('communication.index');
        Route::post('communication/campagnes', [\App\Http\Controllers\Administration\CommunicationSmsController::class, 'storeCampagne'])->name('communication.campagnes.store');
        Route::post('communication/sms-direct', [\App\Http\Controllers\Administration\CommunicationSmsController::class, 'storeSmsDirect'])->name('communication.sms-direct.store');
        Route::put('communication/config', [\App\Http\Controllers\Administration\CommunicationSmsController::class, 'updateConfig'])->name('communication.config.update');
        Route::post('communication/recharger', [\App\Http\Controllers\Administration\CommunicationSmsController::class, 'rechargerCredits'])->name('communication.recharger');

        // Module 15 : Tableau de Bord, Statistiques & Conseil de Classe (Gesco 15 & 2.6)
        Route::get('statistiques', [\App\Http\Controllers\Administration\TableauDeBordController::class, 'statistiquesAvancees'])->name('statistiques.index');
        Route::get('statistiques/bordereau', [\App\Http\Controllers\Administration\TableauDeBordController::class, 'exportBordereau'])->name('statistiques.bordereau');
        Route::get('deliberations', [\App\Http\Controllers\Administration\ConseilClasseController::class, 'index'])->name('deliberations.index');
        Route::post('deliberations/sauvegarder', [\App\Http\Controllers\Administration\ConseilClasseController::class, 'sauvegarder'])->name('deliberations.sauvegarder');
        Route::post('deliberations/valider-promotion', [\App\Http\Controllers\Administration\ConseilClasseController::class, 'validerPromotion'])->name('deliberations.valider-promotion');
        Route::get('deliberations/pv-pdf', [\App\Http\Controllers\Administration\ConseilClasseController::class, 'exportPv'])->name('deliberations.pv-pdf');

        // Module : Centre d'Archivage Administratif & Reconduction Annuelle (Aurore §3.15, §3.16, §3.27.8, §3.28.3)
        Route::get('archivage', [\App\Http\Controllers\Administration\ArchiveController::class, 'index'])->name('archivage.index');
        Route::post('archivage', [\App\Http\Controllers\Administration\ArchiveController::class, 'store'])->name('archivage.store');
        Route::post('archivage/{archive}/restaurer', [\App\Http\Controllers\Administration\ArchiveController::class, 'restaurer'])->name('archivage.restaurer');
        Route::put('archivage/numerotation/{configuration}', [\App\Http\Controllers\Administration\ArchiveController::class, 'updateNumerotation'])->name('archivage.numerotation.update');
        Route::post('archivage/dupliquer-annee', [\App\Http\Controllers\Administration\ArchiveController::class, 'dupliquerAnnee'])->name('archivage.dupliquer-annee');

        // Module : Bibliothèque d'États Imprimables & Documents Officiels (Aurore §6.1)
        Route::get('documents/certificat-scolarite/{inscription}', [\App\Http\Controllers\Administration\DocumentOfficielController::class, 'certificatScolarite'])->name('documents.certificat-scolarite');
        Route::get('documents/certificat-radiation/{inscription}', [\App\Http\Controllers\Administration\DocumentOfficielController::class, 'certificatRadiation'])->name('documents.certificat-radiation');
        Route::get('documents/babillard/{classe}/{periode?}', [\App\Http\Controllers\Administration\DocumentOfficielController::class, 'babillard'])->name('documents.babillard');
        Route::get('documents/situation-financiere/{inscription}', [\App\Http\Controllers\Administration\DocumentOfficielController::class, 'situationFinanciereEleve'])->name('documents.situation-financiere');
        Route::get('documents/fiche-appel/{classe}', [\App\Http\Controllers\Administration\DocumentOfficielController::class, 'ficheAppel'])->name('documents.fiche-appel');
        Route::get('documents/liste-eleves/{classe}', [\App\Http\Controllers\Administration\DocumentOfficielController::class, 'listeEleves'])->name('documents.liste-eleves');
        Route::get('documents/liste-classes', [\App\Http\Controllers\Administration\DocumentOfficielController::class, 'listeClasses'])->name('documents.liste-classes');
        Route::get('documents/liste-personnel', [\App\Http\Controllers\Administration\DocumentOfficielController::class, 'listePersonnel'])->name('documents.liste-personnel');
    });



    // Module 16 : Portail Parents & Élèves (Gesco 16)
    Route::prefix('portail')->name('portail.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Portail\PortailParentController::class, 'index'])->name('index');
        Route::get('enfant/{eleve}', [\App\Http\Controllers\Portail\PortailParentController::class, 'selectEnfant'])->name('select-enfant');
        Route::get('bulletin/{inscription}', [\App\Http\Controllers\Portail\PortailParentController::class, 'telechargerBulletin'])->name('bulletin.download');
        Route::get('recu/{versement}', [\App\Http\Controllers\Portail\PortailParentController::class, 'telechargerRecu'])->name('recu.download');
    });
});

require __DIR__.'/settings.php';
