<?php

namespace App\Http\Controllers\Administration;

use App\Concerns\ScopesEtablissement;
use App\Http\Controllers\Controller;
use App\Models\AnneeScolaire;
use App\Models\Attribution;
use App\Models\Classe;
use App\Models\Evaluation;
use App\Models\Inscription;
use App\Models\MatiereNiveau;
use App\Models\Note;
use App\Models\Periode;
use App\Models\Personnel;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * Contrôleur exécutif pour le Module Relevés de Notes & Fiches de Notation.
 *
 * Conforme au Cahier des Charges Gesco V1.1 (Section 10) et au principe du moindre privilège (Least Privilege) :
 * - Cloisonnement SaaS Multi-Tenant hermétique par campus (ScopesEtablissement).
 * - Cloisonnement strict Enseignant : un enseignant n'accède qu'à ses propres attributions (classes et matières).
 * - Permet à l'enseignant ou à la direction de visualiser la liste complète des élèves d'une classe attribuée.
 * - Génération et impression PDF officielle des fiches de notation (vierges pour report manuscrit ou pré-remplies).
 */
class ReleveNoteController extends Controller implements HasMiddleware
{
    use ScopesEtablissement;

    /**
     * Définition des middlewares d'autorisation granulaires Spatie.
     *
     * @return array<int, Middleware>
     */
    public static function middleware(): array
    {
        return [
            new Middleware('can:releves_notes.view', only: ['index']),
            new Middleware('can:releves_notes.print|can:releves_notes.view', only: ['exportPdf', 'exportEtatRemplissage']),
        ];
    }

    /**
     * Affiche l'interface de consultation et de configuration des relevés de notes.
     *
     * @param Request $request La requête HTTP entrante.
     * @return InertiaResponse Vue Inertia avec les données contextuelles filtrées.
     */
    public function index(Request $request): InertiaResponse
    {
        /** @var User|null $user */
        $user = Auth::user();
        $isSuperAdmin = $user ? $user->isSuperAdmin() : false;
        $isGestionnaire = $isSuperAdmin || ($user && ($user->hasRole(['admin', 'directeur-etudes', 'censeur', 'surveillant-general']) || $user->can('notes.manage')));

        // Récupération du contexte établissement (SaaS Multi-Tenant)
        $context = $this->resolveEtablissementContext($request, ['institution']);
        $etablissements = $context['etablissements'];
        $currentEtablissement = $context['selectedEtablissement'];
        $etablissementId = $context['etablissementId'] ?? 0;

        // Récupération du personnel associé si l'utilisateur est enseignant
        $personnel = $user->personnel ?? ($user->email ? Personnel::where('email', $user->email)->first() : null);

        // Années scolaires et sélection active
        $annees = AnneeScolaire::orderByDesc('date_debut')->get();
        $anneeActive = AnneeScolaire::current() ?? $annees->first();
        $anneeId = $request->integer('annee_id', $anneeActive?->id ?? 0);

        // Périodes d'évaluation du campus
        $periodes = Periode::with(['evaluations' => fn ($q) => $q->orderBy('ordre')])
            ->where('annee_scolaire_id', $anneeId)
            ->when($etablissementId, fn ($q) => $q->where('etablissement_id', $etablissementId))
            ->orderBy('date_debut')
            ->get();

        // Récupération de l'ensemble des classes disponibles pour cet établissement et cette année
        $classesQuery = Classe::with('niveau')
            ->where('is_active', true);

        if ($etablissementId) {
            $classesQuery->where('etablissement_id', $etablissementId);
        }

        // Si l'utilisateur est un simple enseignant, restreindre aux classes où il a des attributions actives
        if (! $isGestionnaire && $personnel) {
            $classesQuery->whereHas('attributions', function ($q) use ($personnel, $anneeId) {
                $q->where('personnel_id', $personnel->id)
                    ->where('annee_scolaire_id', $anneeId)
                    ->where('is_active', true);
            });
        }

        $classes = $classesQuery->orderBy('nom')->get();

        // Détermination de la classe et de l'attribution sélectionnées
        $classeId = $request->integer('classe_id', 0);
        $attributionId = $request->integer('attribution_id', 0);

        // Si une attribution est spécifiée sans classe_id explicite, retrouver la classe associée
        if ($classeId === 0 && $attributionId > 0) {
            $attrTemp = Attribution::find($attributionId);
            if ($attrTemp) {
                $classeId = $attrTemp->classe_id;
            }
        }

        // Si aucune classe n'est sélectionnée, prendre la première classe disponible
        if ($classeId === 0 && $classes->isNotEmpty()) {
            $classeId = $classes->first()->id;
        }

        $selectedClasse = $classes->firstWhere('id', $classeId);

        // Requête des attributions disponibles pour la classe sélectionnée
        $attributionsQuery = Attribution::with(['classe.niveau', 'matiere', 'personnel'])
            ->where('annee_scolaire_id', $anneeId)
            ->where('classe_id', $classeId)
            ->where('is_active', true);

        // Si l'utilisateur est un simple enseignant, restreindre exclusivement à ses attributions
        if (! $isGestionnaire && $personnel) {
            $attributionsQuery->where('personnel_id', $personnel->id);
        }

        $attributions = $attributionsQuery->get();

        // Attribution sélectionnée (0 signifie "Fiche vierge polyvalente multi-matières")
        $selectedAttribution = $attributionId > 0 ? $attributions->firstWhere('id', $attributionId) : null;

        // Évaluation ou Période sélectionnée
        $periodeId = $request->integer('periode_id', $periodes->first()?->id ?? 0);
        $evaluationId = $request->integer('evaluation_id', 0);

        // Si aucune attribution spécifique n'est choisie, le mode est obligatoirement vierge
        $mode = $selectedAttribution ? $request->input('mode', 'rempli') : 'vierge';

        $elevesData = [];
        $selectedEvaluations = collect([]);

        if ($selectedClasse) {
            // Déterminer les évaluations concernées
            if ($evaluationId > 0) {
                $selectedEvaluations = Evaluation::where('id', $evaluationId)->get();
            } elseif ($periodeId > 0) {
                $periode = $periodes->firstWhere('id', $periodeId);
                $selectedEvaluations = $periode ? $periode->evaluations : collect([]);
            } else {
                $selectedEvaluations = $periodes->flatMap->evaluations;
            }

            // Récupération des inscriptions actives de la classe
            $inscriptions = Inscription::with(['eleve'])
                ->where('classe_id', $selectedClasse->id)
                ->where('annee_scolaire_id', $anneeId)
                ->where('statut', 'validee')
                ->when($etablissementId, fn ($q) => $q->where('etablissement_id', $etablissementId))
                ->get()
                ->sortBy(fn ($ins) => ($ins->eleve?->nom ?? '') . ' ' . ($ins->eleve?->prenom ?? ''))
                ->values();

            // Notes existantes si mode rempli ET qu'une matière/attribution est sélectionnée
            $notesMap = collect([]);
            if ($mode === 'rempli' && $selectedAttribution && $inscriptions->isNotEmpty()) {
                $notes = Note::where('attribution_id', $selectedAttribution->id)
                    ->whereIn('inscription_id', $inscriptions->pluck('id'))
                    ->when($selectedEvaluations->isNotEmpty(), fn ($q) => $q->whereIn('evaluation_id', $selectedEvaluations->pluck('id')))
                    ->get();

                // Regroupement par [inscription_id][evaluation_id]
                foreach ($notes as $note) {
                    $key = $note->inscription_id . '_' . $note->evaluation_id;
                    $notesMap->put($key, [
                        'valeur_note' => $note->valeur_note !== null ? (float) $note->valeur_note : null,
                        'statut_presence' => $note->statut_presence,
                        'appreciation' => $note->appreciation,
                        'is_verrouillee' => (bool) $note->is_verrouillee,
                    ]);
                }
            }

            // Construction de la liste formatée des élèves pour TanStack Table
            $elevesData = $inscriptions->map(function ($ins, $index) use ($notesMap, $selectedEvaluations, $mode) {
                $notesParEval = [];
                $somme = 0;
                $compteur = 0;

                foreach ($selectedEvaluations as $eval) {
                    if ($mode === 'rempli') {
                        $noteData = $notesMap->get($ins->id . '_' . $eval->id);
                        $notesParEval[$eval->id] = $noteData;

                        if ($noteData && $noteData['valeur_note'] !== null && $noteData['statut_presence'] === 'P') {
                            $somme += (float) $noteData['valeur_note'];
                            $compteur++;
                        }
                    } else {
                        $notesParEval[$eval->id] = null;
                    }
                }

                $moyenne = $compteur > 0 ? round($somme / $compteur, 2) : null;

                return [
                    'numero' => $index + 1,
                    'inscription_id' => $ins->id,
                    'eleve_id' => $ins->eleve_id,
                    'matricule' => $ins->eleve?->matricule ?? '-',
                    'nom' => $ins->eleve?->nom ?? '',
                    'prenom' => $ins->eleve?->prenom ?? '',
                    'nom_complet' => trim(($ins->eleve?->nom ?? '') . ' ' . ($ins->eleve?->prenom ?? '')),
                    'sexe' => $ins->eleve?->sexe ?? 'M',
                    'date_naissance' => $ins->eleve?->date_naissance,
                    'notes' => $notesParEval,
                    'moyenne' => $moyenne,
                ];
            });
        }

        return Inertia::render('administration/notes/releves', [
            'etablissements' => $etablissements,
            'currentEtablissement' => $currentEtablissement,
            'annees' => $annees,
            'anneeActiveId' => (int) $anneeId,
            'periodes' => $periodes,
            'classes' => $classes,
            'selectedClasse' => $selectedClasse,
            'attributions' => $attributions,
            'selectedAttribution' => $selectedAttribution,
            'selectedEvaluations' => $selectedEvaluations,
            'eleves' => $elevesData,
            'isGestionnaire' => $isGestionnaire,
            'filters' => [
                'annee_id' => $anneeId,
                'classe_id' => $classeId,
                'attribution_id' => $attributionId,
                'periode_id' => $periodeId,
                'evaluation_id' => $evaluationId,
                'mode' => $mode,
            ],
        ]);
    }

    /**
     * Génère et télécharge le document PDF officiel du Relevé de Notes / Fiche de Notation.
     *
     * Permet l'export soit pour une attribution précise (cours d'un enseignant),
     * soit pour une classe entière sans matière prédéfinie (fiche vierge polyvalente
     * à photocopier pour tous les professeurs de la classe).
     *
     * @param Request $request La requête HTTP contenant les filtres de génération.
     * @return Response Document PDF prêt à l'impression.
     */
    public function exportPdf(Request $request): Response
    {
        /** @var User|null $user */
        $user = Auth::user();
        $isSuperAdmin = $user ? $user->isSuperAdmin() : false;
        $isGestionnaire = $isSuperAdmin || ($user && ($user->hasRole(['admin', 'directeur-etudes', 'censeur', 'surveillant-general']) || $user->can('notes.manage')));

        $request->validate([
            'attribution_id' => ['nullable', 'integer'],
            'classe_id' => ['nullable', 'integer'],
            'annee_id' => ['required', 'exists:annees_scolaires,id'],
            'mode' => ['nullable', 'in:rempli,vierge'],
        ]);

        $attributionId = (int) $request->input('attribution_id', 0);
        $classeId = (int) $request->input('classe_id', 0);
        $anneeId = (int) $request->input('annee_id');
        $periodeId = (int) $request->input('periode_id', 0);
        $evaluationId = (int) $request->input('evaluation_id', 0);
        $mode = $request->input('mode', 'rempli');

        $attribution = null;
        $classe = null;

        // Résolution de l'attribution et de la classe
        if ($attributionId > 0) {
            $attribution = Attribution::with(['classe.niveau', 'classe.etablissement', 'matiere', 'personnel'])
                ->findOrFail($attributionId);
            $classe = $attribution->classe;
        } elseif ($classeId > 0) {
            $classe = Classe::with(['niveau', 'etablissement'])->findOrFail($classeId);
            // S'il n'y a pas d'attribution sélectionnée, c'est obligatoirement une fiche de saisie manuscrite vierge
            $mode = 'vierge';
        } else {
            abort(400, 'Veuillez spécifier une classe ou une attribution pour générer le relevé.');
        }

        // Cloisonnement Enseignant : vérifier que l'enseignant a le droit d'accéder à ce cours ou à cette classe
        if (! $isGestionnaire) {
            $personnel = $user->personnel ?? ($user->email ? Personnel::where('email', $user->email)->first() : null);
            if (! $personnel) {
                abort(403, 'Accès interdit : profil enseignant introuvable.');
            }

            if ($attribution) {
                if ((int) $attribution->personnel_id !== (int) $personnel->id) {
                    abort(403, 'Accès interdit : vous ne pouvez exporter que les relevés de notes de vos propres cours.');
                }
            } else {
                // Vérifier que l'enseignant intervient bien dans cette classe cette année
                $enseigneDansClasse = Attribution::where('classe_id', $classe->id)
                    ->where('personnel_id', $personnel->id)
                    ->where('annee_scolaire_id', $anneeId)
                    ->where('is_active', true)
                    ->exists();

                if (! $enseigneDansClasse) {
                    abort(403, 'Accès interdit : vous n\'enseignez pas dans cette classe.');
                }
            }
        }

        // Contexte établissement
        $context = $this->resolveEtablissementContext($request, ['institution']);
        $etablissement = $classe?->etablissement ?? $context['selectedEtablissement'];
        $etablissementId = $etablissement?->id;

        // Année scolaire
        $anneeScolaire = AnneeScolaire::findOrFail($anneeId);

        // Évaluations ciblées
        if ($evaluationId > 0) {
            $evaluations = Evaluation::with('periode')->where('id', $evaluationId)->get();
            $titrePeriode = $evaluations->first()?->nom ?? 'Évaluation';
        } elseif ($periodeId > 0) {
            $periode = Periode::with(['evaluations' => fn ($q) => $q->orderBy('ordre')])->findOrFail($periodeId);
            $evaluations = $periode->evaluations;
            $titrePeriode = $periode->nom;
        } else {
            $evaluations = Evaluation::whereHas('periode', fn ($q) => $q->where('annee_scolaire_id', $anneeId)->when($etablissementId, fn ($sq) => $sq->where('etablissement_id', $etablissementId)))
                ->orderBy('ordre')
                ->get();
            $titrePeriode = 'Année Complète';
        }

        // Inscriptions actives de la classe
        $inscriptions = Inscription::with(['eleve'])
            ->where('classe_id', $classe->id)
            ->where('annee_scolaire_id', $anneeId)
            ->where('statut', 'validee')
            ->when($etablissementId, fn ($q) => $q->where('etablissement_id', $etablissementId))
            ->get()
            ->sortBy(fn ($ins) => ($ins->eleve?->nom ?? '') . ' ' . ($ins->eleve?->prenom ?? ''))
            ->values();

        // Récupération des notes si mode rempli et attribution présente
        $notesMap = collect([]);
        if ($mode === 'rempli' && $attribution && $inscriptions->isNotEmpty() && $evaluations->isNotEmpty()) {
            $notes = Note::where('attribution_id', $attribution->id)
                ->whereIn('inscription_id', $inscriptions->pluck('id'))
                ->whereIn('evaluation_id', $evaluations->pluck('id'))
                ->get();

            foreach ($notes as $note) {
                $notesMap->put($note->inscription_id . '_' . $note->evaluation_id, $note);
            }
        }

        // Construction des lignes élèves
        $eleves = $inscriptions->map(function ($ins, $index) use ($notesMap, $evaluations, $mode) {
            $notesList = [];
            $somme = 0;
            $compteur = 0;

            foreach ($evaluations as $eval) {
                if ($mode === 'rempli') {
                    $note = $notesMap->get($ins->id . '_' . $eval->id);
                    $valeur = $note?->valeur_note;
                    $presence = $note?->statut_presence ?? 'P';
                    $notesList[$eval->id] = [
                        'valeur' => $valeur !== null ? number_format((float) $valeur, 2) : ($presence !== 'P' ? $presence : '-'),
                        'presence' => $presence,
                    ];
                    if ($valeur !== null && $presence === 'P') {
                        $somme += (float) $valeur;
                        $compteur++;
                    }
                } else {
                    $notesList[$eval->id] = [
                        'valeur' => '',
                        'presence' => 'P',
                    ];
                }
            }

            $moyenne = ($compteur > 0) ? number_format($somme / $compteur, 2) : '-';

            return [
                'numero' => $index + 1,
                'matricule' => $ins->eleve?->matricule ?? '-',
                'nom_complet' => trim(($ins->eleve?->nom ?? '') . ' ' . ($ins->eleve?->prenom ?? '')),
                'sexe' => $ins->eleve?->sexe ?? 'M',
                'notes' => $notesList,
                'moyenne' => $moyenne,
            ];
        });

        $effectifTotal = $eleves->count();
        $effectifFilles = $eleves->where('sexe', 'F')->count();
        $effectifGarcons = $eleves->where('sexe', 'M')->count();

        $pdfData = [
            'etablissement' => $etablissement,
            'anneeScolaire' => $anneeScolaire,
            'classe' => $classe,
            'attribution' => $attribution,
            'evaluations' => $evaluations,
            'titrePeriode' => $titrePeriode,
            'eleves' => $eleves,
            'mode' => $mode,
            'stats' => [
                'total' => $effectifTotal,
                'filles' => $effectifFilles,
                'garcons' => $effectifGarcons,
            ],
            'dateImpression' => now()->format('d-m-Y H:i'),
        ];

        $pdf = Pdf::loadView('pdf.releve-notes', $pdfData)
            ->setPaper('a4', 'landscape');

        $filename = sprintf(
            'Releve_%s_%s_%s.pdf',
            str_replace(' ', '_', $classe->nom ?? 'Classe'),
            $attribution ? str_replace(' ', '_', $attribution->matiere?->nom ?? 'Matiere') : 'Fiche_Vierge_Polyvalente',
            $mode
        );

        return $pdf->stream($filename);
    }

    /**
     * Génère et télécharge le document PDF officiel de l'État de Remplissage des Notes de la Classe.
     *
     * Désigné sous le nom de "Grand Babillard de Classe" ou "Grille Récapitulative Pré-Bulletins",
     * ce document matriciel croisé (Élèves en lignes x Toutes les matières en colonnes) permet :
     * 1. À la Direction et aux Censeurs de contrôler la complétude des saisies avant l'impression des bulletins.
     * 2. L'affichage mural en salle de classe pour vérification contradictoire par les élèves.
     *
     * @param Request $request La requête HTTP contenant classe_id, annee_id, et éventuellement periode_id ou evaluation_id.
     * @return Response Document PDF prêt à l'impression au format A4 Paysage.
     */
    public function exportEtatRemplissage(Request $request): Response
    {
        /** @var User|null $user */
        $user = Auth::user();
        $isSuperAdmin = $user ? $user->isSuperAdmin() : false;
        $isGestionnaire = $isSuperAdmin || ($user && ($user->hasRole(['admin', 'directeur-etudes', 'censeur', 'surveillant-general']) || $user->can('notes.manage')));

        $request->validate([
            'classe_id' => ['required', 'integer', 'exists:classes,id'],
            'annee_id' => ['required', 'integer', 'exists:annees_scolaires,id'],
            'periode_id' => ['nullable', 'integer'],
            'evaluation_id' => ['nullable', 'integer'],
        ]);

        $classeId = (int) $request->input('classe_id');
        $anneeId = (int) $request->input('annee_id');
        $periodeId = (int) $request->input('periode_id', 0);
        $evaluationId = (int) $request->input('evaluation_id', 0);

        $classe = Classe::with(['niveau', 'etablissement.institution'])->findOrFail($classeId);

        // Cloisonnement Enseignant : vérifier que l'enseignant intervient bien dans cette classe
        if (! $isGestionnaire) {
            $personnel = $user->personnel ?? ($user->email ? Personnel::where('email', $user->email)->first() : null);
            if (! $personnel) {
                abort(403, 'Accès interdit : profil enseignant introuvable.');
            }

            $intervient = Attribution::where('classe_id', $classe->id)
                ->where('personnel_id', $personnel->id)
                ->where('annee_scolaire_id', $anneeId)
                ->where('is_active', true)
                ->exists();

            if (! $intervient) {
                abort(403, 'Accès interdit : vous n\'enseignez pas dans cette classe.');
            }
        }

        // Contexte SaaS Multi-Tenant
        $context = $this->resolveEtablissementContext($request, ['institution']);
        $etablissement = $classe->etablissement ?? $context['selectedEtablissement'];
        $etablissementId = $etablissement?->id;
        $anneeScolaire = AnneeScolaire::findOrFail($anneeId);

        // Récupération de l'ensemble des matières actives enseignées dans la classe
        $attributions = Attribution::with(['matiere', 'personnel'])
            ->where('classe_id', $classe->id)
            ->where('annee_scolaire_id', $anneeId)
            ->where('is_active', true)
            ->get()
            ->sortBy(fn ($attr) => $attr->matiere?->ordre ?? $attr->matiere?->nom ?? '')
            ->values();

        // Récupération des inscriptions actives ordonnées
        $inscriptions = Inscription::with(['eleve'])
            ->where('classe_id', $classe->id)
            ->where('annee_scolaire_id', $anneeId)
            ->where('statut', 'validee')
            ->when($etablissementId, fn ($q) => $q->where('etablissement_id', $etablissementId))
            ->get()
            ->sortBy(fn ($ins) => ($ins->eleve?->nom ?? '') . ' ' . ($ins->eleve?->prenom ?? ''))
            ->values();

        // Résolution du libellé de l'évaluation ou de la période
        if ($evaluationId > 0) {
            $evaluations = Evaluation::where('id', $evaluationId)->get();
            $titrePeriode = $evaluations->first()?->nom ?? 'Évaluation';
        } elseif ($periodeId > 0) {
            $periode = Periode::with(['evaluations' => fn ($q) => $q->orderBy('ordre')])->findOrFail($periodeId);
            $evaluations = $periode->evaluations;
            $titrePeriode = $periode->nom;
        } else {
            $evaluations = Evaluation::whereHas('periode', fn ($q) => $q->where('annee_scolaire_id', $anneeId)->when($etablissementId, fn ($sq) => $sq->where('etablissement_id', $etablissementId)))
                ->orderBy('ordre')
                ->get();
            $titrePeriode = 'Toutes Évaluations';
        }

        // Récupération de la grille des coefficients officiels par matière pour le niveau et la série (MatiereNiveau)
        $coefficientsGrille = MatiereNiveau::where('niveau_id', $classe->niveau_id)
            ->where(function ($query) use ($classe) {
                $query->where('serie', $classe->serie)
                    ->orWhere('serie', 'Toutes')
                    ->orWhereNull('serie');
            })
            ->get()
            ->keyBy('matiere_id');

        // Association du coefficient officiel réel à chaque attribution
        foreach ($attributions as $attr) {
            $mn = $coefficientsGrille->get($attr->matiere_id);
            $attr->coefficient_officiel = $mn ? (float) $mn->coefficient : (float) ($attr->matiere?->coefficient_defaut ?? 1.0);
        }

        // Récupération globale des notes
        $notes = Note::whereIn('attribution_id', $attributions->pluck('id'))
            ->whereIn('inscription_id', $inscriptions->pluck('id'))
            ->when($evaluations->isNotEmpty(), fn ($q) => $q->whereIn('evaluation_id', $evaluations->pluck('id')))
            ->get();

        $notesGrouped = $notes->groupBy(fn ($n) => $n->inscription_id . '_' . $n->attribution_id);

        $totalCoeffClasse = (float) $attributions->sum('coefficient_officiel');

        // Initialisation des statistiques par matière
        $matieresStats = [];
        foreach ($attributions as $attr) {
            $matieresStats[$attr->id] = [
                'somme' => 0,
                'count' => 0,
                'saisies' => 0,
            ];
        }

        // Construction des lignes élèves
        $eleves = [];
        $notesSaisiesGlobal = 0;

        foreach ($inscriptions as $index => $ins) {
            $notesParMatiere = [];
            $totalPointsEleve = 0;
            $totalCoeffEleve = 0;

            foreach ($attributions as $attr) {
                $coeff = (float) ($attr->coefficient_officiel ?? 1.0);
                $key = $ins->id . '_' . $attr->id;
                $notesEleveMatiere = $notesGrouped->get($key, collect([]));

                if ($notesEleveMatiere->isNotEmpty()) {
                    $notesSaisiesGlobal++;
                    $matieresStats[$attr->id]['saisies']++;

                    // Calcul de la note sur la matière pour l'évaluation ou la période
                    $valeursNotes = $notesEleveMatiere->where('statut_presence', 'P')->pluck('valeur_note')->filter(fn ($v) => $v !== null);

                    if ($valeursNotes->isNotEmpty()) {
                        $noteVal = round($valeursNotes->avg(), 2);
                        $notesParMatiere[$attr->id] = [
                            'valeur' => $noteVal,
                            'presence' => 'P',
                        ];
                        $totalPointsEleve += ($noteVal * $coeff);
                        $totalCoeffEleve += $coeff;

                        $matieresStats[$attr->id]['somme'] += $noteVal;
                        $matieresStats[$attr->id]['count']++;
                    } else {
                        // Élève absent
                        $notesParMatiere[$attr->id] = [
                            'valeur' => null,
                            'presence' => 'A',
                        ];
                    }
                } else {
                    // Note non saisie / manquante
                    $notesParMatiere[$attr->id] = [
                        'valeur' => null,
                        'presence' => 'P',
                    ];
                }
            }

            $moyenneEleve = $totalCoeffEleve > 0 ? round($totalPointsEleve / $totalCoeffEleve, 2) : null;

            $eleves[] = [
                'numero' => $index + 1,
                'matricule' => $ins->eleve?->matricule ?? '-',
                'nom_complet' => trim(($ins->eleve?->nom ?? '') . ' ' . ($ins->eleve?->prenom ?? '')),
                'sexe' => $ins->eleve?->sexe ?? 'M',
                'notes' => $notesParMatiere,
                'total_points' => $totalPointsEleve,
                'total_coeff' => $totalCoeffEleve,
                'moyenne' => $moyenneEleve,
                'mention' => $moyenneEleve !== null ? self::getMention($moyenneEleve) : '-',
                'rang' => null,
            ];
        }

        // Tri pour attribution des rangs (les élèves sans notes classés en dernier)
        usort($eleves, function ($a, $b) {
            if ($a['moyenne'] === null && $b['moyenne'] === null) return 0;
            if ($a['moyenne'] === null) return 1;
            if ($b['moyenne'] === null) return -1;
            return $b['moyenne'] <=> $a['moyenne'];
        });

        // Attribution des rangs avec gestion des ex-aequo
        $currentRang = 1;
        $prevMoyenne = null;
        foreach ($eleves as $idx => &$el) {
            if ($el['moyenne'] !== null) {
                if ($prevMoyenne !== null && abs($el['moyenne'] - $prevMoyenne) < 0.001) {
                    $el['rang'] = $currentRang;
                } else {
                    $currentRang = $idx + 1;
                    $el['rang'] = $currentRang;
                    $prevMoyenne = $el['moyenne'];
                }
            }
        }
        unset($el);

        // Re-tri par ordre alphabétique pour faciliter la recherche nominative lors de l'affichage sur la porte
        usort($eleves, fn ($a, $b) => strcmp($a['nom_complet'], $b['nom_complet']));
        foreach ($eleves as $idx => &$el) {
            $el['numero'] = $idx + 1;
        }
        unset($el);

        // Synthèse des statistiques globales
        $nbEleves = count($eleves);
        $nbMatieres = count($attributions);
        $notesAttendues = $nbEleves * $nbMatieres;
        $tauxRemplissage = $notesAttendues > 0 ? round(($notesSaisiesGlobal / $notesAttendues) * 100, 1) : 0;

        $moyennesEleves = array_filter(array_column($eleves, 'moyenne'), fn ($m) => $m !== null);
        $nbAdmis = count(array_filter($moyennesEleves, fn ($m) => $m >= 10.0));
        $tauxReussite = count($moyennesEleves) > 0 ? round(($nbAdmis / count($moyennesEleves)) * 100, 1) : 0;
        $moyenneClasse = count($moyennesEleves) > 0 ? round(array_sum($moyennesEleves) / count($moyennesEleves), 2) : 0;
        $maxMoyenne = !empty($moyennesEleves) ? max($moyennesEleves) : 0;
        $minMoyenne = !empty($moyennesEleves) ? min($moyennesEleves) : 0;

        // Calcul des moyennes et taux de remplissage par matière
        foreach ($attributions as $attr) {
            $st = $matieresStats[$attr->id];
            $matieresStats[$attr->id]['moyenne'] = $st['count'] > 0 ? round($st['somme'] / $st['count'], 2) : null;
            $matieresStats[$attr->id]['taux'] = $nbEleves > 0 ? round(($st['saisies'] / $nbEleves) * 100, 1) : 0;
        }

        // Nettoyage de l'année scolaire pour éviter le doublon "Année Scolaire : Année Scolaire 2026-2027"
        $sessionAnnee = $anneeScolaire->session ?? $anneeScolaire->code ?? trim(preg_replace('/^Année\s+Scolaire\s*[:\-]?\s*/iu', '', $anneeScolaire->nom ?? ''));
        if (preg_match('/\b(\d{4}[\/\-]\d{4})\b/', $sessionAnnee, $m)) {
            $sessionAnnee = $m[1];
        }

        $pdf = Pdf::loadView('pdf.etat_remplissage_classe', [
            'etablissement' => $etablissement,
            'anneeScolaire' => $anneeScolaire,
            'sessionAnnee' => $sessionAnnee,
            'classe' => $classe,
            'titrePeriode' => $titrePeriode,
            'attributions' => $attributions,
            'eleves' => $eleves,
            'stats' => [
                'nb_eleves' => $nbEleves,
                'nb_matieres' => $nbMatieres,
                'total_coeff_classe' => $totalCoeffClasse,
                'notes_attendues' => $notesAttendues,
                'notes_saisies' => $notesSaisiesGlobal,
                'taux_remplissage' => $tauxRemplissage,
                'nb_admis' => $nbAdmis,
                'taux_reussite' => $tauxReussite,
                'moyenne_classe' => $moyenneClasse,
                'max_moyenne' => $maxMoyenne,
                'min_moyenne' => $minMoyenne,
                'matieres_stats' => $matieresStats,
            ],
            'dateGeneration' => now()->format('d/m/Y H:i'),
        ])->setPaper('a4', 'landscape');

        $fileName = sprintf(
            'Etat_Remplissage_%s_%s_%s.pdf',
            str_replace(' ', '_', $classe->code ?? ($classe->nom ?? 'Classe')),
            str_replace(' ', '_', $titrePeriode),
            now()->format('Ymd')
        );

        return $pdf->stream($fileName);
    }

    /**
     * Calcule la mention littéraire officielle à partir de la moyenne sur 20.
     *
     * @param float $moyenne Moyenne générale de l'élève.
     * @return string Mention officielle correspondante.
     */
    private static function getMention(float $moyenne): string
    {
        return match (true) {
            $moyenne >= 18 => 'Excellent',
            $moyenne >= 16 => 'Très Bien',
            $moyenne >= 14 => 'Bien',
            $moyenne >= 12 => 'Assez Bien',
            $moyenne >= 10 => 'Passable',
            $moyenne >= 8 => 'Insuffisant',
            $moyenne >= 6 => 'Faible',
            default => 'Très Faible',
        };
    }
}
