<?php

namespace App\Services;

use App\Models\AnneeScolaire;
use App\Models\Attribution;
use App\Models\Classe;
use App\Models\DisciplineBulletin;
use App\Models\Evaluation;
use App\Models\Inscription;
use App\Models\MatiereNiveau;
use App\Models\Note;
use App\Models\Periode;
use Illuminate\Support\Str;

/**
 * Service centralisé pour le Moteur de Calcul Pédagogique et des Bulletins Scolaires.
 *
 * Implémente l'algorithme officiel conforme au Cahier des Charges Gesco V1.1 (Section 10)
 * et aux directives MINESEC / MINEDUB du Cameroun :
 * - Calcul des moyennes de matières (arithmétique simple ou pondérée).
 * - Prise en compte des coefficients, matières facultatives et notes éliminatoires.
 * - Regroupement par blocs pédagogiques (groupes_matieres).
 * - Calcul des rangs généraux et par matière avec gestion rigoureuse des ex-aequo.
 * - Attribution des distinctions et mentions d'honneur (invalidées en cas de note éliminatoire).
 * - Statistiques globales de classe (moyenne classe, min, max, taux de réussite).
 * - Prise en charge des deux modes de bulletin : APC (avec compétences) et Standard (sans compétences).
 */
class BulletinCalculationService
{
    /**
     * Calcule l'ensemble des résultats et données de bulletin pour une classe et une période (Trimestre).
     *
     * @param  Classe  $classe  Classe concernée
     * @param  Periode  $periode  Période trimestrielle
     * @param  string|null  $modeCalcul  Règle de calcul ('arithmetique', 'pondere' ou null pour auto-détecter)
     * @param  string|null  $modeBulletin  Format de bulletin ('avec_competences', 'sans_competences' ou null)
     * @return array<string, mixed> Données calculées de la classe et des élèves
     */
    public function calculerTrimestre(
        Classe $classe,
        Periode $periode,
        ?string $modeCalcul = null,
        ?string $modeBulletin = null
    ): array {
        // Détermination du mode de calcul et de bulletin
        $etablissement = $classe->niveau->cycle->section->etablissement;
        $modeCalcul = $modeCalcul ?? $etablissement->type_calcul_trimestre ?? 'arithmetique';
        $modeBulletin = $modeBulletin ?? $etablissement->mode_bulletin_defaut ?? 'sans_competences';

        // 1. Récupération des inscriptions validées dans la classe
        $inscriptions = Inscription::with(['eleve'])
            ->where('classe_id', $classe->id)
            ->where('annee_scolaire_id', $periode->annee_scolaire_id)
            ->where('statut', 'validee')
            ->get()
            ->sortBy(fn ($i) => $i->eleve->nom.' '.$i->eleve->prenom);

        // 2. Récupération des évaluations (séquences) de la période
        $evaluations = Evaluation::where('periode_id', $periode->id)
            ->orderBy('ordre')
            ->get();

        // 3. Récupération des attributions de la classe (Matière, Enseignant, etc.)
        $attributions = Attribution::with(['matiere', 'personnel'])
            ->where('classe_id', $classe->id)
            ->where('annee_scolaire_id', $periode->annee_scolaire_id)
            ->where('is_active', true)
            ->get();

        // Grille de coefficients par matière pour le niveau et la série de la classe
        $coefficients = MatiereNiveau::with(['groupeMatiere'])
            ->where('niveau_id', $classe->niveau_id)
            ->where(function ($query) use ($classe) {
                $query->where('serie', $classe->serie)
                    ->orWhere('serie', 'Toutes')
                    ->orWhereNull('serie');
            })
            ->get()
            ->keyBy('matiere_id');

        // Récupération de toutes les notes existantes
        $evaluationIds = $evaluations->pluck('id')->toArray();
        $attributionIds = $attributions->pluck('id')->toArray();
        $inscriptionIds = $inscriptions->pluck('id')->toArray();

        $toutesLesNotes = Note::whereIn('inscription_id', $inscriptionIds)
            ->whereIn('attribution_id', $attributionIds)
            ->whereIn('evaluation_id', $evaluationIds)
            ->get()
            ->groupBy(['inscription_id', 'attribution_id']);

        // Récupération des synthèses disciplinaires
        $disciplines = DisciplineBulletin::whereIn('inscription_id', $inscriptionIds)
            ->where('periode_id', $periode->id)
            ->get()
            ->keyBy('inscription_id');

        // 4. Calcul par élève et par matière
        $elevesData = [];
        $matieresStats = []; // Pour calculer [Min, Max, Moyenne classe] par matière

        // Initialisation de la structure pour chaque matière
        foreach ($attributions as $attr) {
            $matieresStats[$attr->id] = [
                'notes_classe' => [],
                'min' => null,
                'max' => null,
                'moyenne' => null,
            ];
        }

        foreach ($inscriptions as $insc) {
            $lignesMatieres = [];
            $totalPoints = 0.0;
            $totalCoefficients = 0.0;
            $aNoteEliminatoire = false;

            foreach ($attributions as $attr) {
                $coefConfig = $coefficients->get($attr->matiere_id);
                $coef = $coefConfig ? (float) $coefConfig->coefficient : 1.0;
                $noteEliminatoireSeuil = $coefConfig && $coefConfig->note_eliminatoire !== null
                    ? (float) $coefConfig->note_eliminatoire
                    : null;
                $isComptabilisee = $coefConfig ? (bool) $coefConfig->is_comptabilisee : true;
                $groupe = $coefConfig?->groupeMatiere;

                // Notes de l'élève pour cette matière aux différentes séquences
                $notesParEvaluation = [];
                $competencesEvaluees = [];
                $sommePondereeEval = 0.0;
                $sommePonderations = 0.0;
                $sommeArithmetique = 0.0;
                $nbNotesPresentes = 0;
                $derniereAppreciation = '';

                $notesEleveMatiere = $toutesLesNotes->get($insc->id)?->get($attr->id) ?? collect();

                foreach ($evaluations as $eval) {
                    $noteObj = $notesEleveMatiere->firstWhere('evaluation_id', $eval->id);
                    $valNote = $noteObj ? $noteObj->valeur_note : null;
                    $statutPres = $noteObj ? $noteObj->statut_presence : 'non_evalue';

                    $notesParEvaluation[$eval->id] = [
                        'evaluation_id' => $eval->id,
                        'code' => $eval->code,
                        'nom' => $eval->nom,
                        'note' => $valNote !== null ? (float) $valNote : null,
                        'statut_presence' => $statutPres,
                        'appreciation' => $noteObj?->appreciation,
                        'competence' => $noteObj?->competence_evaluee,
                    ];

                    if ($noteObj && ! empty($noteObj->competence_evaluee)) {
                        $competencesEvaluees[] = $noteObj->competence_evaluee;
                    }
                    if ($noteObj && ! empty($noteObj->appreciation)) {
                        $derniereAppreciation = $noteObj->appreciation;
                    }

                    if ($valNote !== null && $statutPres === 'present') {
                        $valFloat = (float) $valNote;
                        $sommeArithmetique += $valFloat;
                        $sommePondereeEval += $valFloat * ((float) $eval->ponderation);
                        $sommePonderations += (float) $eval->ponderation;
                        $nbNotesPresentes++;
                    }
                }

                // Calcul de la moyenne de la matière pour ce trimestre
                $moyenneMatiere = null;
                if ($nbNotesPresentes > 0) {
                    if ($modeCalcul === 'pondere' && $sommePonderations > 0) {
                        $moyenneMatiere = round($sommePondereeEval / $sommePonderations, 2);
                    } else {
                        $moyenneMatiere = round($sommeArithmetique / $nbNotesPresentes, 2);
                    }
                }

                // Test de la note éliminatoire (Gesco 4.3)
                $estEliminatoire = false;
                if ($moyenneMatiere !== null && $noteEliminatoireSeuil !== null) {
                    if ($moyenneMatiere < $noteEliminatoireSeuil) {
                        $estEliminatoire = true;
                        $aNoteEliminatoire = true;
                    }
                }

                // Cote APC de la matière
                $coteAPC = $moyenneMatiere !== null ? Note::determinerCoteAPC($moyenneMatiere) : null;

                // Total de points (Moyenne x Coef) si comptabilisée
                $pointsMatiere = 0.0;
                if ($moyenneMatiere !== null && $isComptabilisee) {
                    $pointsMatiere = round($moyenneMatiere * $coef, 2);
                    $totalPoints += $pointsMatiere;
                    $totalCoefficients += $coef;

                    // Ajout pour statistiques de classe de la matière
                    $matieresStats[$attr->id]['notes_classe'][$insc->id] = $moyenneMatiere;
                }

                $lignesMatieres[$attr->id] = [
                    'attribution_id' => $attr->id,
                    'matiere_id' => $attr->matiere_id,
                    'matiere_code' => $attr->matiere->code,
                    'matiere_nom' => $attr->matiere->libelle_bulletin ?: $attr->matiere->nom,
                    'enseignant_nom' => $attr->personnel ? $attr->personnel->nom_complet : 'Non assigné',
                    'groupe_id' => $groupe ? $groupe->id : 0,
                    'groupe_nom' => $groupe ? $groupe->nom : 'Autres Enseignements',
                    'groupe_ordre' => $groupe ? $groupe->ordre : 99,
                    'coefficient' => $coef,
                    'note_eliminatoire' => $noteEliminatoireSeuil,
                    'est_eliminatoire' => $estEliminatoire,
                    'notes_evaluations' => $notesParEvaluation,
                    'moyenne' => $moyenneMatiere,
                    'points' => $pointsMatiere,
                    'cote_apc' => $coteAPC,
                    'competences' => Str::limit(implode(' ; ', array_filter(array_unique($competencesEvaluees))), 75, '...'),
                    'appreciation' => $derniereAppreciation ?: self::genererAppreciationParDefaut($moyenneMatiere),
                    'rang_matiere' => null, // Rempli après calcul global
                ];
            }

            // Moyenne Générale Trimestrielle (M.G.T.)
            $moyenneGenerale = $totalCoefficients > 0 ? round($totalPoints / $totalCoefficients, 2) : 0.0;
            $coteGlobale = Note::determinerCoteAPC($moyenneGenerale);

            // Données disciplinaires
            $disciplineRec = $disciplines->get($insc->id);

            $elevesData[$insc->id] = [
                'inscription_id' => $insc->id,
                'matricule' => $insc->eleve->matricule,
                'matricule_ministere' => $insc->eleve->matricule_national,
                'nom_complet' => $insc->eleve->nom.' '.$insc->eleve->prenom,
                'date_naissance' => $insc->eleve->date_naissance?->format('d/m/Y'),
                'lieu_naissance' => $insc->eleve->lieu_naissance,
                'genre' => $insc->eleve->sexe,
                'photo_url' => $insc->eleve->photo_url,
                'is_redoublant' => $insc->is_redoublant,
                'lignes_matieres' => $lignesMatieres,
                'total_points' => round($totalPoints, 2),
                'total_coefficients' => $totalCoefficients,
                'moyenne_generale' => $moyenneGenerale,
                'cote_globale' => $coteGlobale,
                'a_note_eliminatoire' => $aNoteEliminatoire,
                'rang_general' => null, // Rempli après tri
                'mention' => null,      // Rempli après tri
                'discipline' => [
                    'absences_justifiees' => $disciplineRec?->absences_justifiees ?? 0,
                    'absences_non_justifiees' => $disciplineRec?->absences_non_justifiees ?? 0,
                    'retards' => $disciplineRec?->retards ?? 0,
                    'consignes' => $disciplineRec?->consignes ?? 0,
                    'avertissements' => $disciplineRec?->avertissements_conduite ?? 0,
                    'blames' => $disciplineRec?->blames_conduite ?? 0,
                    'exclusions' => $disciplineRec?->exclusions_jours ?? 0,
                    'decision_conseil' => $disciplineRec?->decision_conseil ?? '',
                ],
            ];
        }

        // 5. Calcul des rangs et extrêmes par matière
        foreach ($attributions as $attr) {
            $notesClasse = $matieresStats[$attr->id]['notes_classe'];
            if (! empty($notesClasse)) {
                $matieresStats[$attr->id]['min'] = min($notesClasse);
                $matieresStats[$attr->id]['max'] = max($notesClasse);
                $matieresStats[$attr->id]['moyenne'] = round(array_sum($notesClasse) / count($notesClasse), 2);

                // Calcul du rang avec ex-aequo
                arsort($notesClasse);
                $rangs = self::attribuerRangsAvecExAequo($notesClasse);

                foreach ($notesClasse as $inscId => $noteVal) {
                    if (isset($elevesData[$inscId]['lignes_matieres'][$attr->id])) {
                        $elevesData[$inscId]['lignes_matieres'][$attr->id]['rang_matiere'] = $rangs[$inscId];
                        $elevesData[$inscId]['lignes_matieres'][$attr->id]['min_classe'] = $matieresStats[$attr->id]['min'];
                        $elevesData[$inscId]['lignes_matieres'][$attr->id]['max_classe'] = $matieresStats[$attr->id]['max'];
                        $elevesData[$inscId]['lignes_matieres'][$attr->id]['moyenne_classe'] = $matieresStats[$attr->id]['moyenne'];
                    }
                }
            }
        }

        // 6. Calcul des rangs généraux et des distinctions honorifiques
        $moyennesClasseGenerale = [];
        foreach ($elevesData as $inscId => $data) {
            $moyennesClasseGenerale[$inscId] = $data['moyenne_generale'];
        }
        arsort($moyennesClasseGenerale);
        $rangsGeneraux = self::attribuerRangsAvecExAequo($moyennesClasseGenerale);

        $nbMoyennes = 0;
        foreach ($elevesData as $inscId => &$data) {
            $data['rang_general'] = $rangsGeneraux[$inscId] ?? 'N/A';

            // Distinction / Mention
            $moy = $data['moyenne_generale'];
            if ($moy >= 10.0) {
                $nbMoyennes++;
            }

            if ($data['a_note_eliminatoire']) {
                $data['mention'] = 'Non éligible aux mentions (Note éliminatoire)';
                $data['distinction'] = 'Aucune (Note éliminatoire)';
            } else {
                if ($moy >= 16.0) {
                    $data['mention'] = 'Très Bien';
                    $data['distinction'] = 'Tableau d\'Honneur avec Félicitations';
                } elseif ($moy >= 14.0) {
                    $data['mention'] = 'Bien';
                    $data['distinction'] = 'Tableau d\'Honneur avec Encouragements';
                } elseif ($moy >= 12.0) {
                    $data['mention'] = 'Assez Bien';
                    $data['distinction'] = 'Tableau d\'Honneur';
                } elseif ($moy >= 10.0) {
                    $data['mention'] = 'Passable';
                    $data['distinction'] = 'Encourageant';
                } else {
                    $data['mention'] = 'Insuffisant';
                    $data['distinction'] = 'Avertissement travail';
                }
            }

            // Regroupement des matières par blocs pour affichage structuré
            $groupesOrganises = [];
            foreach ($data['lignes_matieres'] as $ligne) {
                $gid = $ligne['groupe_id'];
                if (! isset($groupesOrganises[$gid])) {
                    $groupesOrganises[$gid] = [
                        'groupe_id' => $gid,
                        'groupe_nom' => $ligne['groupe_nom'],
                        'groupe_ordre' => $ligne['groupe_ordre'],
                        'matieres' => [],
                        'total_points' => 0.0,
                        'total_coef' => 0.0,
                        'moyenne_groupe' => 0.0,
                    ];
                }
                $groupesOrganises[$gid]['matieres'][] = $ligne;
                $groupesOrganises[$gid]['total_points'] += $ligne['points'];
                $groupesOrganises[$gid]['total_coef'] += $ligne['coefficient'];
            }

            // Calcul de la moyenne sectorielle de chaque groupe
            foreach ($groupesOrganises as &$grp) {
                $grp['moyenne_groupe'] = $grp['total_coef'] > 0
                    ? round($grp['total_points'] / $grp['total_coef'], 2)
                    : 0.0;
            }
            uasort($groupesOrganises, fn ($a, $b) => $a['groupe_ordre'] <=> $b['groupe_ordre']);
            $data['groupes_matieres'] = array_values($groupesOrganises);
        }
        unset($data);

        // 7. Statistiques globales de la classe
        $effectifTotal = count($inscriptions);
        $toutesLesMoyennes = array_values($moyennesClasseGenerale);
        // 7. Statistiques globales de la classe avec clés harmonisées (trimestre, séquence, annuel)
        $statsClasse = [
            'effectif_total' => $effectifTotal,
            'effectif' => $effectifTotal,
            'effectif_evalue' => count($toutesLesMoyennes),
            'moyenne_generale' => count($toutesLesMoyennes) > 0 ? round(array_sum($toutesLesMoyennes) / count($toutesLesMoyennes), 2) : 0.0,
            'moyenne_classe' => count($toutesLesMoyennes) > 0 ? round(array_sum($toutesLesMoyennes) / count($toutesLesMoyennes), 2) : 0.0,
            'min_generale' => count($toutesLesMoyennes) > 0 ? min($toutesLesMoyennes) : 0.0,
            'moyenne_min' => count($toutesLesMoyennes) > 0 ? min($toutesLesMoyennes) : 0.0,
            'max_generale' => count($toutesLesMoyennes) > 0 ? max($toutesLesMoyennes) : 0.0,
            'moyenne_max' => count($toutesLesMoyennes) > 0 ? max($toutesLesMoyennes) : 0.0,
            'nb_moyennes' => $nbMoyennes,
            'nombre_moyennes' => $nbMoyennes,
            'taux_reussite' => $effectifTotal > 0 ? round(($nbMoyennes / $effectifTotal) * 100, 1) : 0.0,
        ];

        return [
            'classe' => [
                'id' => $classe->id,
                'nom' => $classe->nom,
                'code' => $classe->code,
                'effectif' => $effectifTotal,
                'professeur_principal' => $classe->professeurPrincipal ? $classe->professeurPrincipal->nom_complet : null,
                'niveau' => $classe->niveau->nom,
                'etablissement' => [
                    'id' => $etablissement->id,
                    'nom' => $etablissement->nom,
                    'nom_en' => $etablissement->nom_en,
                    'code' => $etablissement->code,
                    'logo' => $etablissement->logo ?: $etablissement->institution->logo,
                    'boite_postale' => $etablissement->institution->boite_postale,
                    'telephone' => $etablissement->telephone_1 ?: $etablissement->institution->telephone_1,
                    'email' => $etablissement->email ?: $etablissement->institution->email,
                    'region' => $etablissement->institution->region,
                    'departement' => $etablissement->institution->departement,
                    'delegation_regionale' => $etablissement->institution?->delegation_regionale,
                    'delegation_regionale_en' => $etablissement->institution?->delegation_regionale_en,
                    'delegation_departementale' => $etablissement->institution?->delegation_departementale,
                    'delegation_departementale_en' => $etablissement->institution?->delegation_departementale_en,
                    'nom_chef' => $etablissement->nom_chef_etablissement,
                ],
            ],
            'periode' => [
                'id' => $periode->id,
                'nom' => $periode->nom,
                'nom_en' => $periode->nom_en,
                'code' => $periode->code,
                'evaluations' => $evaluations->map(fn ($e) => ['id' => $e->id, 'code' => $e->code, 'nom' => $e->nom]),
            ],
            'annee_scolaire' => [
                'id' => $periode->anneeScolaire->id,
                'nom' => $periode->anneeScolaire->nom,
            ],
            'mode_bulletin' => $modeBulletin,
            'mode_calcul' => $modeCalcul,
            'stats_classe' => $statsClasse,
            'eleves' => array_values($elevesData),
        ];
    }

    /**
     * Attribue les rangs ordonnés en tenant compte rigoureusement des ex-aequo.
     *
     * Exemple : [18 => 1er, 16 => 2e ex, 16 => 2e ex, 14 => 4e]
     *
     * @param  array<int|string, float>  $elementsTableau  Tableau id => valeur (trié descendant)
     * @return array<int|string, string> Tableau id => rang textuel (ex: "1er", "2e ex")
     */
    public static function attribuerRangsAvecExAequo(array $elementsTableau): array
    {
        $rangs = [];
        $valeurs = array_values($elementsTableau);
        $cles = array_keys($elementsTableau);
        $total = count($valeurs);

        for ($i = 0; $i < $total; $i++) {
            $val = $valeurs[$i];
            $cle = $cles[$i];

            // Compte des occurrences identiques
            $occurence = 0;
            $premierIndex = $i;
            for ($j = 0; $j < $total; $j++) {
                if ($valeurs[$j] === $val) {
                    $occurence++;
                    if ($j < $premierIndex) {
                        $premierIndex = $j;
                    }
                }
            }

            $numRang = $premierIndex + 1;
            if ($numRang === 1) {
                $suffix = 'er';
            } else {
                $suffix = 'e';
            }

            if ($occurence > 1) {
                $rangs[$cle] = "{$numRang}{$suffix} ex";
            } else {
                $rangs[$cle] = "{$numRang}{$suffix}";
            }
        }

        return $rangs;
    }

    /**
     * Génère une appréciation automatique par défaut basée sur le barème officiel.
     *
     * @param  float|null  $note  Note sur 20
     * @return string Appréciation qualitative
     */
    public static function genererAppreciationParDefaut(?float $note): string
    {
        if ($note === null) {
            return 'Non évalué';
        }

        if ($note >= 18.0) {
            return 'Excellent travail';
        } elseif ($note >= 16.0) {
            return 'Très bon travail';
        } elseif ($note >= 14.0) {
            return 'Bon travail';
        } elseif ($note >= 12.0) {
            return 'Travail satisfaisant';
        } elseif ($note >= 10.0) {
            return 'Juste la moyenne, poursuivez vos efforts';
        } elseif ($note >= 8.0) {
            return 'Insuffisant, des efforts s\'imposent';
        } else {
            return 'Très insuffisant, travaillez davantage';
        }
    }

    /**
     * Calcule l'ensemble des résultats et données de bulletin séquentiel pour une classe et une évaluation.
     *
     * @param  Classe  $classe  Classe concernée
     * @param  Evaluation  $evaluation  Évaluation séquentielle (ex: Évaluation 1)
     * @param  string|null  $modeBulletin  Format de bulletin ('avec_competences' ou 'sans_competences')
     * @return array<string, mixed> Données calculées de la séquence
     */
    public function calculerSequentiel(
        Classe $classe,
        Evaluation $evaluation,
        ?string $modeBulletin = null
    ): array {
        $etablissement = $classe->niveau->cycle->section->etablissement;
        $modeBulletin = $modeBulletin ?? $etablissement->mode_bulletin_defaut ?? 'sans_competences';

        $periode = $evaluation->periode;
        $anneeScolaire = $periode->anneeScolaire;

        // 1. Inscriptions validées
        $inscriptions = Inscription::with(['eleve'])
            ->where('classe_id', $classe->id)
            ->where('annee_scolaire_id', $anneeScolaire->id)
            ->where('statut', 'validee')
            ->get()
            ->sortBy(fn ($i) => $i->eleve->nom.' '.$i->eleve->prenom);

        // 2. Attributions
        $attributions = Attribution::with(['matiere', 'personnel'])
            ->where('classe_id', $classe->id)
            ->where('annee_scolaire_id', $anneeScolaire->id)
            ->where('is_active', true)
            ->get();

        // 3. Coefficients par matière
        $coefficients = MatiereNiveau::with(['groupeMatiere'])
            ->where('niveau_id', $classe->niveau_id)
            ->where(function ($query) use ($classe) {
                $query->where('serie', $classe->serie)
                    ->orWhere('serie', 'Toutes')
                    ->orWhereNull('serie');
            })
            ->get()
            ->keyBy('matiere_id');

        // 4. Notes existantes pour cette évaluation
        $notes = Note::whereIn('inscription_id', $inscriptions->pluck('id'))
            ->where('evaluation_id', $evaluation->id)
            ->whereIn('attribution_id', $attributions->pluck('id'))
            ->get()
            ->groupBy('inscription_id');

        $elevesData = [];
        $matieresStats = [];
        foreach ($attributions as $attr) {
            $matieresStats[$attr->id] = ['notes_classe' => []];
        }

        $rangsSequentielsPrepares = [];

        foreach ($inscriptions as $insc) {
            $notesEleve = $notes->get($insc->id, collect())->keyBy('attribution_id');
            $lignesMatieres = [];
            $totalPoints = 0.0;
            $totalCoefficients = 0.0;
            $aNoteEliminatoire = false;

            foreach ($attributions as $attr) {
                $coeffRecord = $coefficients->get($attr->matiere_id);
                $coef = $coeffRecord ? (float) $coeffRecord->coefficient : 1.0;
                $groupe = $coeffRecord?->groupeMatiere;
                $noteEliminatoireSeuil = $coeffRecord ? (float) $coeffRecord->note_eliminatoire : null;
                $isComptabilisee = $coeffRecord ? (bool) $coeffRecord->is_comptabilisee : true;

                $noteObj = $notesEleve->get($attr->id);
                $valeurNote = $noteObj ? $noteObj->valeur_note : null;
                $presence = $noteObj ? $noteObj->statut_presence : 'non_evalue';
                $cote = $noteObj ? $noteObj->cote_apc : ($valeurNote !== null ? Note::determinerCoteAPC($valeurNote) : null);
                $competence = $noteObj ? $noteObj->competence_evaluee : '';
                $appreciation = $noteObj ? $noteObj->appreciation : self::genererAppreciationParDefaut($valeurNote);

                $estEliminatoire = false;
                if ($valeurNote !== null && $noteEliminatoireSeuil !== null && $valeurNote <= $noteEliminatoireSeuil) {
                    $estEliminatoire = true;
                    $aNoteEliminatoire = true;
                }

                $pointsMatiere = 0.0;
                if ($valeurNote !== null && $isComptabilisee) {
                    $pointsMatiere = round($valeurNote * $coef, 2);
                    $totalPoints += $pointsMatiere;
                    $totalCoefficients += $coef;
                    $matieresStats[$attr->id]['notes_classe'][$insc->id] = (float) $valeurNote;
                }

                $lignesMatieres[$attr->id] = [
                    'attribution_id' => $attr->id,
                    'matiere_id' => $attr->matiere_id,
                    'matiere_code' => $attr->matiere->code,
                    'matiere_nom' => $attr->matiere->libelle_bulletin ?: $attr->matiere->nom,
                    'enseignant_nom' => $attr->personnel ? $attr->personnel->nom_complet : 'Non assigné',
                    'groupe_id' => $groupe ? $groupe->id : 0,
                    'groupe_nom' => $groupe ? $groupe->nom : 'Autres Enseignements',
                    'groupe_ordre' => $groupe ? $groupe->ordre : 99,
                    'coefficient' => $coef,
                    'note' => $valeurNote,
                    'statut_presence' => $presence,
                    'points' => $pointsMatiere,
                    'cote_apc' => $cote,
                    'competences' => Str::limit($competence, 75, '...'),
                    'appreciation' => $appreciation,
                    'note_eliminatoire' => $noteEliminatoireSeuil,
                    'est_eliminatoire' => $estEliminatoire,
                    'rang_matiere' => null,
                ];
            }

            $moyenneGenerale = $totalCoefficients > 0 ? round($totalPoints / $totalCoefficients, 2) : 0.0;
            $coteGlobale = Note::determinerCoteAPC($moyenneGenerale);

            $elevesData[$insc->id] = [
                'inscription_id' => $insc->id,
                'matricule' => $insc->eleve->matricule,
                'matricule_ministere' => $insc->eleve->matricule_national,
                'nom_complet' => $insc->eleve->nom.' '.$insc->eleve->prenom,
                'date_naissance' => $insc->eleve->date_naissance?->format('d/m/Y'),
                'lieu_naissance' => $insc->eleve->lieu_naissance,
                'genre' => $insc->eleve->sexe,
                'photo_url' => $insc->eleve->photo_url,
                'is_redoublant' => $insc->is_redoublant,
                'lignes_matieres' => $lignesMatieres,
                'total_points' => round($totalPoints, 2),
                'total_coefficients' => $totalCoefficients,
                'moyenne_generale' => $moyenneGenerale,
                'cote_globale' => $coteGlobale,
                'a_note_eliminatoire' => $aNoteEliminatoire,
                'rang_general' => null,
                'mention' => null,
                'discipline' => [
                    'absences_justifiees' => 0,
                    'absences_non_justifiees' => 0,
                    'retards' => 0,
                    'consignes' => 0,
                    'avertissements' => 0,
                    'blames' => 0,
                    'exclusions' => 0,
                    'decision_conseil' => '',
                ],
            ];

            $rangsSequentielsPrepares[$insc->id] = $moyenneGenerale;
        }

        // Calcul des rangs généraux
        arsort($rangsSequentielsPrepares);
        $rangsCalcules = self::attribuerRangsAvecExAequo($rangsSequentielsPrepares);
        foreach ($rangsCalcules as $inscId => $rang) {
            $elevesData[$inscId]['rang_general'] = $rang;
            $moy = $elevesData[$inscId]['moyenne_generale'];
            $hasElim = $elevesData[$inscId]['a_note_eliminatoire'];
            if (! $hasElim) {
                if ($moy >= 16.0) {
                    $elevesData[$inscId]['mention'] = 'Félicitations du Conseil';
                } elseif ($moy >= 14.0) {
                    $elevesData[$inscId]['mention'] = 'Encouragements';
                } elseif ($moy >= 12.0) {
                    $elevesData[$inscId]['mention'] = 'Tableau d\'Honneur';
                }
            }
        }

        // Rangs par matière et statistiques
        foreach ($matieresStats as $attrId => $statsMatiere) {
            $notesClasse = $statsMatiere['notes_classe'];
            arsort($notesClasse);
            $rangsMat = self::attribuerRangsAvecExAequo($notesClasse);
            $min = count($notesClasse) > 0 ? min($notesClasse) : 0.0;
            $max = count($notesClasse) > 0 ? max($notesClasse) : 0.0;
            $moyClasse = count($notesClasse) > 0 ? round(array_sum($notesClasse) / count($notesClasse), 2) : 0.0;

            foreach ($elevesData as $inscId => &$el) {
                if (isset($el['lignes_matieres'][$attrId])) {
                    $el['lignes_matieres'][$attrId]['rang_matiere'] = $rangsMat[$inscId] ?? '-';
                    $el['lignes_matieres'][$attrId]['note_min'] = $min;
                    $el['lignes_matieres'][$attrId]['note_max'] = $max;
                    $el['lignes_matieres'][$attrId]['moyenne_classe'] = $moyClasse;
                }
            }
            unset($el);
        }

        // Statistiques globales de classe
        $toutesMoyennes = array_values($rangsSequentielsPrepares);
        $effectifTotal = count($toutesMoyennes);
        $nbreMoyennes = count(array_filter($toutesMoyennes, fn ($m) => $m >= 10.0));
        $moyenneClasse = $effectifTotal > 0 ? round(array_sum($toutesMoyennes) / $effectifTotal, 2) : 0.0;
        $tauxReussite = $effectifTotal > 0 ? round(($nbreMoyennes / $effectifTotal) * 100, 1) : 0.0;
        $minClasse = $effectifTotal > 0 ? min($toutesMoyennes) : 0.0;
        $maxClasse = $effectifTotal > 0 ? max($toutesMoyennes) : 0.0;

        // Statistiques globales de classe avec clés harmonisées
        $statsClasse = [
            'effectif_total' => $effectifTotal,
            'effectif' => $effectifTotal,
            'effectif_evalue' => $effectifTotal,
            'moyenne_generale' => $moyenneClasse,
            'moyenne_classe' => $moyenneClasse,
            'min_generale' => $minClasse,
            'moyenne_min' => $minClasse,
            'max_generale' => $maxClasse,
            'moyenne_max' => $maxClasse,
            'nb_moyennes' => $nbreMoyennes,
            'nombre_moyennes' => $nbreMoyennes,
            'taux_reussite' => $tauxReussite,
        ];

        return [
            'type_bulletin' => 'sequentiel',
            'classe' => [
                'id' => $classe->id,
                'nom' => $classe->nom,
                'code' => $classe->code,
                'niveau' => $classe->niveau->nom,
                'effectif' => $effectifTotal,
                'professeur_principal' => $classe->professeurPrincipal?->nom_complet,
                'etablissement' => [
                    'nom' => $etablissement->nom,
                    'nom_en' => $etablissement->nom_en,
                    'sigle' => $etablissement->code,
                    'logo_url' => $etablissement->logo_url,
                    'logo' => $etablissement->logo ?: $etablissement->institution?->logo,
                    'ville' => $etablissement->ville,
                    'telephone' => $etablissement->telephone_1 ?: $etablissement->institution?->telephone_1,
                    'email' => $etablissement->email ?: $etablissement->institution?->email,
                    'devise' => $etablissement->devise ?: $etablissement->institution?->devise,
                    'devise_en' => $etablissement->institution?->devise_en,
                    'delegation_regionale' => $etablissement->institution?->delegation_regionale,
                    'delegation_regionale_en' => $etablissement->institution?->delegation_regionale_en,
                    'delegation_departementale' => $etablissement->institution?->delegation_departementale,
                    'delegation_departementale_en' => $etablissement->institution?->delegation_departementale_en,
                ],
            ],
            'evaluation' => [
                'id' => $evaluation->id,
                'nom' => $evaluation->nom,
                'code' => $evaluation->code,
                'date_debut' => $evaluation->date_debut?->format('d/m/Y'),
                'date_fin' => $evaluation->date_fin?->format('d/m/Y'),
            ],
            'periode' => [
                'id' => $periode->id,
                'nom' => $periode->nom,
                'code' => $periode->code,
            ],
            'annee_scolaire' => [
                'id' => $anneeScolaire->id,
                'nom' => $anneeScolaire->nom,
            ],
            'mode_bulletin' => $modeBulletin,
            'stats_classe' => $statsClasse,
            'eleves' => array_values($elevesData),
        ];
    }

    /**
     * Calcule le bilan récapitulatif annuel de tous les trimestres d'une classe pour l'année scolaire.
     *
     * @param  Classe  $classe  Classe concernée
     * @param  AnneeScolaire  $annee  Année scolaire
     * @param  string|null  $modeBulletin  Format de bulletin ('avec_competences' ou 'sans_competences')
     * @return array<string, mixed> Données calculées de fin d'année
     */
    public function calculerAnnuel(
        Classe $classe,
        AnneeScolaire $annee,
        ?string $modeBulletin = null
    ): array {
        $etablissement = $classe->niveau->cycle->section->etablissement;
        $modeBulletin = $modeBulletin ?? $etablissement->mode_bulletin_defaut ?? 'sans_competences';

        // 1. Récupération des périodes ordonnées de l'année scolaire (filtrées par établissement de la classe)
        $periodes = Periode::where('annee_scolaire_id', $annee->id)
            ->when($etablissement, fn ($q) => $q->where('etablissement_id', $etablissement->id))
            ->orderBy('ordre')
            ->get();

        // 2. Calcul des données trimestrielles pour chaque période
        $resultatsTrimestres = [];
        foreach ($periodes as $p) {
            $resultatsTrimestres[$p->id] = $this->calculerTrimestre($classe, $p, null, $modeBulletin);
        }

        // 3. Inscriptions validées
        $inscriptions = Inscription::with(['eleve'])
            ->where('classe_id', $classe->id)
            ->where('annee_scolaire_id', $annee->id)
            ->where('statut', 'validee')
            ->get()
            ->sortBy(fn ($i) => $i->eleve->nom.' '.$i->eleve->prenom);

        // 4. Attributions de la classe
        $attributions = Attribution::with(['matiere', 'personnel'])
            ->where('classe_id', $classe->id)
            ->where('annee_scolaire_id', $annee->id)
            ->where('is_active', true)
            ->get();

        // 5. Coefficients par matière
        $coefficients = MatiereNiveau::with(['groupeMatiere'])
            ->where('niveau_id', $classe->niveau_id)
            ->where(function ($query) use ($classe) {
                $query->where('serie', $classe->serie)
                    ->orWhere('serie', 'Toutes')
                    ->orWhereNull('serie');
            })
            ->get()
            ->keyBy('matiere_id');

        $elevesData = [];
        $matieresStats = [];
        foreach ($attributions as $attr) {
            $matieresStats[$attr->id] = ['notes_classe' => []];
        }

        $rangsAnnuelsPrepares = [];

        foreach ($inscriptions as $insc) {
            $lignesMatieres = [];
            $totalPoints = 0.0;
            $totalCoefficients = 0.0;
            $aNoteEliminatoire = false;

            // Cumul de discipline sur les trimestres
            $cumulDiscipline = [
                'absences_justifiees' => 0,
                'absences_non_justifiees' => 0,
                'retards' => 0,
                'consignes' => 0,
                'avertissements' => 0,
                'blames' => 0,
                'exclusions' => 0,
                'decision_conseil' => '',
            ];

            foreach ($resultatsTrimestres as $resTrim) {
                $eleveTrim = collect($resTrim['eleves'])->firstWhere('inscription_id', $insc->id);
                if ($eleveTrim && isset($eleveTrim['discipline'])) {
                    $cumulDiscipline['absences_justifiees'] += $eleveTrim['discipline']['absences_justifiees'] ?? 0;
                    $cumulDiscipline['absences_non_justifiees'] += $eleveTrim['discipline']['absences_non_justifiees'] ?? 0;
                    $cumulDiscipline['retards'] += $eleveTrim['discipline']['retards'] ?? 0;
                    $cumulDiscipline['consignes'] += $eleveTrim['discipline']['consignes'] ?? 0;
                    $cumulDiscipline['avertissements'] += $eleveTrim['discipline']['avertissements'] ?? 0;
                    $cumulDiscipline['blames'] += $eleveTrim['discipline']['blames'] ?? 0;
                    $cumulDiscipline['exclusions'] += $eleveTrim['discipline']['exclusions'] ?? 0;
                    if (! empty($eleveTrim['discipline']['decision_conseil'])) {
                        $cumulDiscipline['decision_conseil'] = $eleveTrim['discipline']['decision_conseil'];
                    }
                }
            }

            foreach ($attributions as $attr) {
                $coeffRecord = $coefficients->get($attr->matiere_id);
                $coef = $coeffRecord ? (float) $coeffRecord->coefficient : 1.0;
                $groupe = $coeffRecord?->groupeMatiere;
                $noteEliminatoireSeuil = $coeffRecord ? (float) $coeffRecord->note_eliminatoire : null;
                $isComptabilisee = $coeffRecord ? (bool) $coeffRecord->is_comptabilisee : true;

                // Récupération des moyennes trimestrielles de cette matière pour cet élève
                $notesTrimestres = [];
                $competencesSynthese = [];
                foreach ($periodes as $p) {
                    $resTrim = $resultatsTrimestres[$p->id] ?? null;
                    $elTrim = $resTrim ? collect($resTrim['eleves'])->firstWhere('inscription_id', $insc->id) : null;
                    $ligneMat = $elTrim['lignes_matieres'][$attr->id] ?? null;

                    $notesTrimestres[$p->id] = $ligneMat ? $ligneMat['moyenne'] : null;
                    if (! empty($ligneMat['competences'])) {
                        $competencesSynthese[] = $ligneMat['competences'];
                    }
                }

                $notesDispos = array_filter($notesTrimestres, fn ($n) => $n !== null);
                $moyenneAnnuelleMatiere = count($notesDispos) > 0 ? round(array_sum($notesDispos) / count($notesDispos), 2) : null;

                $estEliminatoire = false;
                if ($moyenneAnnuelleMatiere !== null && $noteEliminatoireSeuil !== null && $moyenneAnnuelleMatiere <= $noteEliminatoireSeuil) {
                    $estEliminatoire = true;
                    $aNoteEliminatoire = true;
                }

                $pointsMatiere = 0.0;
                if ($moyenneAnnuelleMatiere !== null && $isComptabilisee) {
                    $pointsMatiere = round($moyenneAnnuelleMatiere * $coef, 2);
                    $totalPoints += $pointsMatiere;
                    $totalCoefficients += $coef;
                    $matieresStats[$attr->id]['notes_classe'][$insc->id] = (float) $moyenneAnnuelleMatiere;
                }

                $coteAPC = $moyenneAnnuelleMatiere !== null ? Note::determinerCoteAPC($moyenneAnnuelleMatiere) : null;

                $lignesMatieres[$attr->id] = [
                    'attribution_id' => $attr->id,
                    'matiere_id' => $attr->matiere_id,
                    'matiere_code' => $attr->matiere->code,
                    'matiere_nom' => $attr->matiere->libelle_bulletin ?: $attr->matiere->nom,
                    'enseignant_nom' => $attr->personnel ? $attr->personnel->nom_complet : 'Non assigné',
                    'groupe_id' => $groupe ? $groupe->id : 0,
                    'groupe_nom' => $groupe ? $groupe->nom : 'Autres Enseignements',
                    'groupe_ordre' => $groupe ? $groupe->ordre : 99,
                    'coefficient' => $coef,
                    'notes_trimestres' => $notesTrimestres,
                    'moyenne' => $moyenneAnnuelleMatiere,
                    'points' => $pointsMatiere,
                    'cote_apc' => $coteAPC,
                    'competences' => Str::limit(implode(' ; ', array_filter(array_unique($competencesSynthese))), 75, '...'),
                    'appreciation' => self::genererAppreciationParDefaut($moyenneAnnuelleMatiere),
                    'note_eliminatoire' => $noteEliminatoireSeuil,
                    'est_eliminatoire' => $estEliminatoire,
                    'rang_matiere' => null,
                ];
            }

            // Moyenne Générale Annuelle (M.G.A.)
            $mga = $totalCoefficients > 0 ? round($totalPoints / $totalCoefficients, 2) : 0.0;
            $coteGlobale = Note::determinerCoteAPC($mga);

            // Décision automatique du conseil de fin d'année
            $decisionAuto = $mga >= 10.0
                ? 'Admis(e) en classe supérieure'
                : ($mga >= 8.5 ? 'Proposé(e) au redoublement' : 'Non admis(e) / Exclu(e)');

            $elevesData[$insc->id] = [
                'inscription_id' => $insc->id,
                'matricule' => $insc->eleve->matricule,
                'matricule_ministere' => $insc->eleve->matricule_national,
                'nom_complet' => $insc->eleve->nom.' '.$insc->eleve->prenom,
                'date_naissance' => $insc->eleve->date_naissance?->format('d/m/Y'),
                'lieu_naissance' => $insc->eleve->lieu_naissance,
                'genre' => $insc->eleve->sexe,
                'photo_url' => $insc->eleve->photo_url,
                'is_redoublant' => $insc->is_redoublant,
                'lignes_matieres' => $lignesMatieres,
                'total_points' => round($totalPoints, 2),
                'total_coefficients' => $totalCoefficients,
                'moyenne_generale' => $mga,
                'cote_globale' => $coteGlobale,
                'a_note_eliminatoire' => $aNoteEliminatoire,
                'rang_general' => null,
                'mention' => null,
                'discipline' => $cumulDiscipline,
                'decision_finale' => ! empty($cumulDiscipline['decision_conseil']) ? $cumulDiscipline['decision_conseil'] : $decisionAuto,
            ];

            $rangsAnnuelsPrepares[$insc->id] = $mga;
        }

        // Calcul des rangs généraux annuels
        arsort($rangsAnnuelsPrepares);
        $rangsCalcules = self::attribuerRangsAvecExAequo($rangsAnnuelsPrepares);
        foreach ($rangsCalcules as $inscId => $rang) {
            $elevesData[$inscId]['rang_general'] = $rang;
            $moy = $elevesData[$inscId]['moyenne_generale'];
            $hasElim = $elevesData[$inscId]['a_note_eliminatoire'];
            if (! $hasElim) {
                if ($moy >= 16.0) {
                    $elevesData[$inscId]['mention'] = 'Félicitations du Conseil';
                } elseif ($moy >= 14.0) {
                    $elevesData[$inscId]['mention'] = 'Encouragements';
                } elseif ($moy >= 12.0) {
                    $elevesData[$inscId]['mention'] = 'Tableau d\'Honneur';
                }
            }
        }

        // Rangs par matière et statistiques
        foreach ($matieresStats as $attrId => $statsMatiere) {
            $notesClasse = $statsMatiere['notes_classe'];
            arsort($notesClasse);
            $rangsMat = self::attribuerRangsAvecExAequo($notesClasse);
            $min = count($notesClasse) > 0 ? min($notesClasse) : 0.0;
            $max = count($notesClasse) > 0 ? max($notesClasse) : 0.0;
            $moyClasse = count($notesClasse) > 0 ? round(array_sum($notesClasse) / count($notesClasse), 2) : 0.0;

            foreach ($elevesData as $inscId => &$el) {
                if (isset($el['lignes_matieres'][$attrId])) {
                    $el['lignes_matieres'][$attrId]['rang_matiere'] = $rangsMat[$inscId] ?? '-';
                    $el['lignes_matieres'][$attrId]['note_min'] = $min;
                    $el['lignes_matieres'][$attrId]['note_max'] = $max;
                    $el['lignes_matieres'][$attrId]['moyenne_classe'] = $moyClasse;
                }
            }
            unset($el);
        }

        // Statistiques globales annuelles de classe
        $toutesMoyennes = array_values($rangsAnnuelsPrepares);
        $effectifTotal = count($toutesMoyennes);
        $nbreMoyennes = count(array_filter($toutesMoyennes, fn ($m) => $m >= 10.0));
        $moyenneClasse = $effectifTotal > 0 ? round(array_sum($toutesMoyennes) / $effectifTotal, 2) : 0.0;
        $tauxReussite = $effectifTotal > 0 ? round(($nbreMoyennes / $effectifTotal) * 100, 1) : 0.0;
        $minClasse = $effectifTotal > 0 ? min($toutesMoyennes) : 0.0;
        $maxClasse = $effectifTotal > 0 ? max($toutesMoyennes) : 0.0;

        // Statistiques globales annuelles avec clés harmonisées
        $statsClasse = [
            'effectif_total' => $effectifTotal,
            'effectif' => $effectifTotal,
            'effectif_evalue' => $effectifTotal,
            'moyenne_generale' => $moyenneClasse,
            'moyenne_classe' => $moyenneClasse,
            'min_generale' => $minClasse,
            'moyenne_min' => $minClasse,
            'max_generale' => $maxClasse,
            'moyenne_max' => $maxClasse,
            'nb_moyennes' => $nbreMoyennes,
            'nombre_moyennes' => $nbreMoyennes,
            'taux_reussite' => $tauxReussite,
        ];

        return [
            'type_bulletin' => 'annuel',
            'classe' => [
                'id' => $classe->id,
                'nom' => $classe->nom,
                'code' => $classe->code,
                'niveau' => $classe->niveau->nom,
                'effectif' => $effectifTotal,
                'professeur_principal' => $classe->professeurPrincipal?->nom_complet,
                'etablissement' => [
                    'nom' => $etablissement->nom,
                    'nom_en' => $etablissement->nom_en,
                    'sigle' => $etablissement->code,
                    'logo_url' => $etablissement->logo_url,
                    'logo' => $etablissement->logo ?: $etablissement->institution?->logo,
                    'ville' => $etablissement->ville,
                    'telephone' => $etablissement->telephone_1 ?: $etablissement->institution?->telephone_1,
                    'email' => $etablissement->email ?: $etablissement->institution?->email,
                    'devise' => $etablissement->devise ?: $etablissement->institution?->devise,
                    'devise_en' => $etablissement->institution?->devise_en,
                    'delegation_regionale' => $etablissement->institution?->delegation_regionale,
                    'delegation_regionale_en' => $etablissement->institution?->delegation_regionale_en,
                    'delegation_departementale' => $etablissement->institution?->delegation_departementale,
                    'delegation_departementale_en' => $etablissement->institution?->delegation_departementale_en,
                ],
            ],
            'periodes' => $periodes->map(fn ($p) => [
                'id' => $p->id,
                'nom' => $p->nom,
                'code' => $p->code,
                'ordre' => $p->ordre,
            ])->toArray(),
            'annee_scolaire' => [
                'id' => $annee->id,
                'nom' => $annee->nom,
            ],
            'mode_bulletin' => $modeBulletin,
            'stats_classe' => $statsClasse,
            'eleves' => array_values($elevesData),
        ];
    }
}
