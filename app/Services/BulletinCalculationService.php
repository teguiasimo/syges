<?php

namespace App\Services;

use App\Models\AnneeScolaire;
use App\Models\Attribution;
use App\Models\Classe;
use App\Models\CompetencePeriodique;
use App\Models\CoteConfiguration;
use App\Models\DisciplineBulletin;
use App\Models\DispenseEleve;
use App\Models\Evaluation;
use App\Models\Inscription;
use App\Models\MatiereNiveau;
use App\Models\Niveau;
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
        $etablissement = $classe->etablissement ?? $classe->niveau?->cycle?->section?->etablissement;
        $modeCalcul = $modeCalcul ?? $etablissement?->type_calcul_trimestre ?? 'arithmetique';
        $modeBulletin = $modeBulletin ?? $etablissement?->mode_bulletin_defaut ?? 'sans_competences';

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

        // Récupération des dispenses médicales et dérogations officielles validées (Phase 4)
        $dispenses = DispenseEleve::validees()
            ->whereIn('inscription_id', $inscriptionIds)
            ->where(function ($q) use ($periode) {
                $q->whereNull('periode_id')->orWhere('periode_id', $periode->id);
            })
            ->get()
            ->groupBy('inscription_id');

        // Récupération des compétences officielles APC configurées pour la classe et la période (Phase 3)
        $competencesOfficielles = CompetencePeriodique::where('classe_id', $classe->id)
            ->where(function ($q) use ($periode, $evaluationIds) {
                $q->where('periode_id', $periode->id)
                  ->orWhereIn('evaluation_id', $evaluationIds);
            })
            ->where('is_active', true)
            ->get()
            ->groupBy('matiere_id');

        // Total théorique des coefficients de la classe et quota minimal pour être classé (MINESEC / Gesco)
        $totalCoefficientsTheoriqueClasse = (float) $attributions->sum(function ($attr) use ($coefficients) {
            $matiereNiveau = $coefficients->get($attr->matiere_id);
            return (float) ($matiereNiveau ? $matiereNiveau->coefficient : $attr->matiere->coefficient_defaut);
        });
        $seuilCoeffsMinimalClasse = $etablissement
            ? $etablissement->getSeuilCoefficientsMinimal($totalCoefficientsTheoriqueClasse)
            : round($totalCoefficientsTheoriqueClasse * 0.60, 2);

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

                // Vérification d'une dispense officielle active (Phase 4)
                $dispenseEleve = $dispenses->get($insc->id)?->firstWhere('matiere_id', $attr->matiere_id);
                $estDispense = $dispenseEleve !== null;
                if ($estDispense && $dispenseEleve->neutraliser_coefficient) {
                    $coef = 0.0;
                    $isComptabilisee = false;
                    $noteEliminatoireSeuil = null;
                }

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
                if (! $estDispense && $nbNotesPresentes > 0) {
                    if ($modeCalcul === 'pondere' && $sommePonderations > 0) {
                        $moyenneMatiere = round($sommePondereeEval / $sommePonderations, 2);
                    } else {
                        $moyenneMatiere = round($sommeArithmetique / $nbNotesPresentes, 2);
                    }
                }

                // Test de la note éliminatoire (Gesco 4.3)
                $estEliminatoire = false;
                if (! $estDispense && $moyenneMatiere !== null && $noteEliminatoireSeuil !== null) {
                    if ($moyenneMatiere < $noteEliminatoireSeuil) {
                        $estEliminatoire = true;
                        $aNoteEliminatoire = true;
                    }
                }

                // Cote APC de la matière (Phase 3 & barème configurable)
                $coteAPC = ($moyenneMatiere !== null && ! $estDispense)
                    ? CoteConfiguration::determinerCote($moyenneMatiere, $etablissement?->id)
                    : null;

                // Total de points (Moyenne x Coef) si comptabilisée
                $pointsMatiere = 0.0;
                if ($moyenneMatiere !== null && $isComptabilisee && ! $estDispense) {
                    $pointsMatiere = round($moyenneMatiere * $coef, 2);
                    $totalPoints += $pointsMatiere;
                    $totalCoefficients += $coef;

                    // Ajout pour statistiques de classe de la matière
                    $matieresStats[$attr->id]['notes_classe'][$insc->id] = $moyenneMatiere;
                }

                // Intitulé de compétence officiel ou saisi
                $texteCompetence = ! empty($competencesEvaluees)
                    ? implode(' ; ', array_filter(array_unique($competencesEvaluees)))
                    : ($competencesOfficielles->get($attr->matiere_id)?->pluck('libelle_competence')->implode(' ; ') ?: '');

                $appreciationFinale = $estDispense
                    ? 'Dispensé(e) (' . ucfirst($dispenseEleve->type_dispense) . ')'
                    : ($derniereAppreciation ?: self::genererAppreciationParDefaut($moyenneMatiere));

                $lignesMatieres[$attr->id] = [
                    'attribution_id' => $attr->id,
                    'matiere_id' => $attr->matiere_id,
                    'matiere_code' => $attr->matiere->code,
                    'matiere_nom' => $attr->matiere->libelle_bulletin ?: $attr->matiere->nom,
                    'enseignant_nom' => $attr->personnel ? ($attr->personnel->nom ?: $attr->personnel->nom_complet) : 'Non assigné',
                    'enseignant_nom_complet' => $attr->personnel ? $attr->personnel->nom_complet : 'Non assigné',
                    'groupe_id' => $groupe ? $groupe->id : 0,
                    'groupe_nom' => $groupe ? $groupe->nom : 'Autres Enseignements',
                    'groupe_ordre' => $groupe ? $groupe->getOrdrePourSerie($classe->serie) : 99,
                    'coefficient' => $coef,
                    'is_dispense' => $estDispense,
                    'dispense' => $dispenseEleve,
                    'note_eliminatoire' => $noteEliminatoireSeuil,
                    'est_eliminatoire' => $estEliminatoire,
                    'notes_evaluations' => $notesParEvaluation,
                    'moyenne' => $estDispense ? null : $moyenneMatiere,
                    'points' => $pointsMatiere,
                    'cote_apc' => $coteAPC,
                    'competences' => Str::limit($texteCompetence, 75, '...'),
                    'appreciation' => $appreciationFinale,
                    'rang_matiere' => null, // Rempli après calcul global
                ];
            }

            // Tri ordonné des matières par l'ordre contextuel du bloc selon la série, puis par code matière
            uasort($lignesMatieres, function ($a, $b) {
                if ($a['groupe_ordre'] === $b['groupe_ordre']) {
                    return strcmp($a['matiere_code'], $b['matiere_code']);
                }
                return $a['groupe_ordre'] <=> $b['groupe_ordre'];
            });

            // Moyenne Générale Trimestrielle (M.G.T.)
            $moyenneGenerale = $totalCoefficients > 0 ? round($totalPoints / $totalCoefficients, 2) : 0.0;
            $coteGlobale = CoteConfiguration::determinerCote($moyenneGenerale, $etablissement?->id);

            // Calcul de l'éligibilité au classement selon le quota de coefficients (MINESEC / Gesco)
            $dispensesEleve = $dispenses->get($insc->id) ?? collect();
            $coeffsDispensesEleve = (float) $dispensesEleve->where('neutraliser_coefficient', true)->sum(function ($disp) use ($coefficients, $attributions) {
                $attr = $attributions->firstWhere('matiere_id', $disp->matiere_id);
                if (! $attr) {
                    return 0.0;
                }
                $matiereNiveau = $coefficients->get($attr->matiere_id);
                return (float) ($matiereNiveau ? $matiereNiveau->coefficient : $attr->matiere->coefficient_defaut);
            });
            $totalTheoriqueEleve = max(0.0, $totalCoefficientsTheoriqueClasse - $coeffsDispensesEleve);
            $seuilRequisEleve = $etablissement
                ? $etablissement->getSeuilCoefficientsMinimal($totalTheoriqueEleve)
                : round($totalTheoriqueEleve * 0.60, 2);

            $isEligibleClassement = ($totalCoefficients >= $seuilRequisEleve) && ($totalCoefficients > 0);

            // Données disciplinaires
            $disciplineRec = $disciplines->get($insc->id);

            $elevesData[$insc->id] = [
                'inscription_id' => $insc->id,
                'matricule' => $insc->eleve->matricule,
                'matricule_ministere' => $insc->eleve->matricule_national,
                'matricule_national' => $insc->eleve->matricule_national,
                'nom_complet' => $insc->eleve->nom.' '.$insc->eleve->prenom,
                'date_naissance' => $insc->eleve->date_naissance?->format('d/m/Y'),
                'lieu_naissance' => $insc->eleve->lieu_naissance,
                'genre' => $insc->eleve->sexe,
                'sexe' => $insc->eleve->sexe,
                'photo_url' => $insc->eleve->photo_url,
                'is_redoublant' => $insc->is_redoublant,
                'lignes_matieres' => $lignesMatieres,
                'total_points' => round($totalPoints, 2),
                'total_coefficients' => $totalCoefficients,
                'total_coefficients_classe' => $totalCoefficientsTheoriqueClasse,
                'seuil_coefficients_requis' => $seuilRequisEleve,
                'is_eligible_classement' => $isEligibleClassement,
                'motif_non_classe' => ! $isEligibleClassement && $totalCoefficients > 0
                    ? "Coefficients insuffisants ({$totalCoefficients} / {$seuilRequisEleve} requis)"
                    : (! $isEligibleClassement ? 'Aucune matière composée' : null),
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

        // 6. Calcul des rangs généraux et des distinctions honorifiques (seuls les élèves éligibles sont classés)
        $moyennesClasseGenerale = [];
        foreach ($elevesData as $inscId => $data) {
            if ($data['is_eligible_classement']) {
                $moyennesClasseGenerale[$inscId] = $data['moyenne_generale'];
            }
        }
        arsort($moyennesClasseGenerale);
        $rangsGeneraux = self::attribuerRangsAvecExAequo($moyennesClasseGenerale);

        $nbMoyennes = 0;
        foreach ($elevesData as $inscId => &$data) {
            if (! $data['is_eligible_classement']) {
                $data['rang_general'] = 'NC';
                $data['mention'] = 'Non Classé (NC)';
                $data['distinction'] = 'Non éligible (Coefficients insuffisants)';
            } else {
                $data['rang_general'] = $rangsGeneraux[$inscId] ?? 'N/A';

                // Distinction / Mention
                $moy = $data['moyenne_generale'];
                if ($moy >= 10.0) {
                    $nbMoyennes++;
                }

                $hasElim = $data['a_note_eliminatoire'];
                $data['tableau_honneur'] = ($moy >= 12.0 && ! $hasElim);
                $data['encouragements'] = ($moy >= 14.0 && ! $hasElim);
                $data['felicitations'] = ($moy >= 16.0 && ! $hasElim);

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
            }

            // Identification des matières faibles (note < 10) pour l'orientation et l'effort
            $matieresFaibles = [];
            foreach ($data['lignes_matieres'] as $ligne) {
                if (isset($ligne['moyenne']) && $ligne['moyenne'] !== null && $ligne['moyenne'] < 10.0) {
                    $matieresFaibles[] = $ligne['matiere_nom'];
                }
            }
            $data['matieres_faibles'] = $matieresFaibles;

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
            'effectif_classe' => count($moyennesClasseGenerale),
            'effectif_non_classe' => $effectifTotal - count($moyennesClasseGenerale),
            'seuil_coefficients_classement' => $seuilCoeffsMinimalClasse,
            'total_coefficients_classe' => $totalCoefficientsTheoriqueClasse,
            'moyenne_generale' => count($toutesLesMoyennes) > 0 ? round(array_sum($toutesLesMoyennes) / count($toutesLesMoyennes), 2) : 0.0,
            'moyenne_classe' => count($toutesLesMoyennes) > 0 ? round(array_sum($toutesLesMoyennes) / count($toutesLesMoyennes), 2) : 0.0,
            'min_generale' => count($toutesLesMoyennes) > 0 ? min($toutesLesMoyennes) : 0.0,
            'moyenne_min' => count($toutesLesMoyennes) > 0 ? min($toutesLesMoyennes) : 0.0,
            'max_generale' => count($toutesLesMoyennes) > 0 ? max($toutesLesMoyennes) : 0.0,
            'moyenne_max' => count($toutesLesMoyennes) > 0 ? max($toutesLesMoyennes) : 0.0,
            'nb_moyennes' => $nbMoyennes,
            'nombre_moyennes' => $nbMoyennes,
            'taux_reussite' => $effectifTotal > 0 ? round(($nbMoyennes / $effectifTotal) * 100, 1) : 0.0,
            'ecart_type' => self::calculerEcartType($toutesLesMoyennes),
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
                    // Logo effectif de l'établissement avec repli automatique sur l'institution faîtière
                    'logo_url' => $etablissement->logo_url,
                    'logo' => $etablissement->effective_logo,
                    'boite_postale' => self::formaterBoitePostale($etablissement->institution?->boite_postale),
                    'telephone' => $etablissement->telephone_1 ?: $etablissement->institution->telephone_1,
                    'email' => $etablissement->email ?: $etablissement->institution->email,
                    'region' => $etablissement->institution->region,
                    'departement' => $etablissement->institution->departement,
                    'delegation_regionale' => $etablissement->institution?->delegation_regionale,
                    'delegation_regionale_en' => $etablissement->institution?->delegation_regionale_en,
                    'delegation_departementale' => $etablissement->institution?->delegation_departementale,
                    'delegation_departementale_en' => $etablissement->institution?->delegation_departementale_en,
                    'titre_chef' => $etablissement->titre_chef,
                    'titre_chef_en' => $etablissement->titre_chef_en,
                    'nom_chef' => $etablissement->nom_chef_etablissement,
                    'ville' => $etablissement->ville ?: $etablissement->institution?->ville,
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
     * Nettoie et formate la boîte postale pour supprimer tout préfixe redondant ("BP", "B.P.", "P.O. Box").
     *
     * @param  string|null  $bp  Valeur brute de la boîte postale.
     * @return string Valeur nettoyée sans préfixe redondant, ou '---' si vide.
     */
    public static function formaterBoitePostale(?string $bp): string
    {
        if (empty($bp) || trim($bp) === '---') {
            return '---';
        }

        $nettoyee = trim((string) preg_replace('/^(b\.?p\.?|p\.?o\.?\s*box)\s*:?\s*/i', '', trim($bp)));

        return $nettoyee !== '' ? $nettoyee : '---';
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
     * Calcule l'écart-type d'une série statistique de valeurs chiffrées (moyennes ou notes).
     *
     * Formule mathématique : racine carrée de la variance (somme des carrés des écarts à la moyenne divisée par N).
     *
     * @param  array<int, float>  $valeurs  Liste des notes ou moyennes numériques
     * @return float Écart-type arrondi à 2 décimales (ex: 2.21)
     */
    public static function calculerEcartType(array $valeurs): float
    {
        $count = count($valeurs);
        if ($count === 0) {
            return 0.0;
        }

        $moyenne = array_sum($valeurs) / $count;
        $sommeCarres = 0.0;
        foreach ($valeurs as $val) {
            $sommeCarres += pow((float) $val - $moyenne, 2);
        }

        return round(sqrt($sommeCarres / $count), 2);
    }

    /**
     * Génère une appréciation pédagogique automatique par défaut selon le barème officiel MINESEC / Gesco.
     *
     * Utilisée lorsque l'enseignant n'a pas renseigné d'appréciation textuelle individualisée lors de la saisie des notes.
     *
     * @param  float|null  $note  Note sur 20
     * @return string Mention qualitative concise ('Excellent', 'Très bien', 'Bien', 'Assez bien', 'Passable', 'Insuffisant', 'Faible' ou 'Non évalué')
     */
    public static function genererAppreciationParDefaut(?float $note): string
    {
        if ($note === null) {
            return 'Non évalué';
        }

        if ($note >= 18.0) {
            return 'Excellent';
        } elseif ($note >= 16.0) {
            return 'Très bien';
        } elseif ($note >= 14.0) {
            return 'Bien';
        } elseif ($note >= 12.0) {
            return 'Assez bien';
        } elseif ($note >= 10.0) {
            return 'Passable';
        } elseif ($note >= 8.0) {
            return 'Insuffisant';
        } else {
            return 'Faible';
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
        $etablissement = $classe->etablissement ?? $classe->niveau?->cycle?->section?->etablissement;
        $modeBulletin = $modeBulletin ?? $etablissement?->mode_bulletin_defaut ?? 'sans_competences';

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

        // Dispenses médicales et dérogations officielles
        $dispenses = DispenseEleve::validees()
            ->whereIn('inscription_id', $inscriptions->pluck('id'))
            ->where(function ($q) use ($periode) {
                $q->whereNull('periode_id')->orWhere('periode_id', $periode->id);
            })
            ->get()
            ->groupBy('inscription_id');

        // Récupération des synthèses disciplinaires de la période associée
        $disciplines = DisciplineBulletin::whereIn('inscription_id', $inscriptions->pluck('id'))
            ->where('periode_id', $periode->id)
            ->get()
            ->keyBy('inscription_id');

        $totalCoefficientsTheoriqueClasse = (float) $attributions->sum(function ($attr) use ($coefficients) {
            $matiereNiveau = $coefficients->get($attr->matiere_id);
            return (float) ($matiereNiveau ? $matiereNiveau->coefficient : $attr->matiere->coefficient_defaut);
        });
        $seuilCoeffsMinimalClasse = $etablissement
            ? $etablissement->getSeuilCoefficientsMinimal($totalCoefficientsTheoriqueClasse)
            : round($totalCoefficientsTheoriqueClasse * 0.60, 2);

        $elevesData = [];
        $matieresStats = [];
        foreach ($attributions as $attr) {
            $matieresStats[$attr->id] = ['notes_classe' => []];
        }

        $rangsSequentielsPrepares = [];

        foreach ($inscriptions as $insc) {
            $notesEleve = $notes->get($insc->id, collect())->keyBy('attribution_id');
            $lignesMatieres = [];
            $matieresFaibles = [];
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
                $appreciation = (! empty($noteObj?->appreciation))
                    ? $noteObj->appreciation
                    : self::genererAppreciationParDefaut($valeurNote);

                $estEliminatoire = false;
                if ($valeurNote !== null && $noteEliminatoireSeuil !== null && $valeurNote <= $noteEliminatoireSeuil) {
                    $estEliminatoire = true;
                    $aNoteEliminatoire = true;
                }

                if ($valeurNote !== null && $valeurNote < 10.0) {
                    $matieresFaibles[] = $attr->matiere->libelle_bulletin ?: $attr->matiere->nom;
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
                    'enseignant_nom' => $attr->personnel ? ($attr->personnel->nom ?: $attr->personnel->nom_complet) : 'Non assigné',
                    'enseignant_nom_complet' => $attr->personnel ? $attr->personnel->nom_complet : 'Non assigné',
                    'groupe_id' => $groupe ? $groupe->id : 0,
                    'groupe_nom' => $groupe ? $groupe->nom : 'Autres Enseignements',
                    'groupe_ordre' => $groupe ? $groupe->getOrdrePourSerie($classe->serie) : 99,
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

            // Tri ordonné des matières par l'ordre contextuel du bloc selon la série, puis par code matière
            uasort($lignesMatieres, function ($a, $b) {
                if ($a['groupe_ordre'] === $b['groupe_ordre']) {
                    return strcmp($a['matiere_code'], $b['matiere_code']);
                }
                return $a['groupe_ordre'] <=> $b['groupe_ordre'];
            });

            $moyenneGenerale = $totalCoefficients > 0 ? round($totalPoints / $totalCoefficients, 2) : 0.0;
            $coteGlobale = Note::determinerCoteAPC($moyenneGenerale);

            // Calcul de l'éligibilité au classement selon le quota de coefficients (MINESEC / Gesco)
            $dispensesEleve = $dispenses->get($insc->id) ?? collect();
            $coeffsDispensesEleve = (float) $dispensesEleve->where('neutraliser_coefficient', true)->sum(function ($disp) use ($coefficients, $attributions) {
                $attr = $attributions->firstWhere('matiere_id', $disp->matiere_id);
                if (! $attr) {
                    return 0.0;
                }
                $matiereNiveau = $coefficients->get($attr->matiere_id);
                return (float) ($matiereNiveau ? $matiereNiveau->coefficient : $attr->matiere->coefficient_defaut);
            });
            $totalTheoriqueEleve = max(0.0, $totalCoefficientsTheoriqueClasse - $coeffsDispensesEleve);
            $seuilRequisEleve = $etablissement
                ? $etablissement->getSeuilCoefficientsMinimal($totalTheoriqueEleve)
                : round($totalTheoriqueEleve * 0.60, 2);

            $isEligibleClassement = ($totalCoefficients >= $seuilRequisEleve) && ($totalCoefficients > 0);

            $disciplineRec = $disciplines->get($insc->id);

            $elevesData[$insc->id] = [
                'inscription_id' => $insc->id,
                'matricule' => $insc->eleve->matricule,
                'matricule_ministere' => $insc->eleve->matricule_national,
                'matricule_national' => $insc->eleve->matricule_national,
                'nom_complet' => $insc->eleve->nom.' '.$insc->eleve->prenom,
                'date_naissance' => $insc->eleve->date_naissance?->format('d/m/Y'),
                'lieu_naissance' => $insc->eleve->lieu_naissance,
                'genre' => $insc->eleve->sexe,
                'sexe' => $insc->eleve->sexe,
                'photo_url' => $insc->eleve->photo_url,
                'is_redoublant' => $insc->is_redoublant,
                'lignes_matieres' => $lignesMatieres,
                'matieres_faibles' => $matieresFaibles,
                'total_points' => round($totalPoints, 2),
                'total_coefficients' => $totalCoefficients,
                'total_coefficients_classe' => $totalCoefficientsTheoriqueClasse,
                'seuil_coefficients_requis' => $seuilRequisEleve,
                'is_eligible_classement' => $isEligibleClassement,
                'motif_non_classe' => ! $isEligibleClassement && $totalCoefficients > 0
                    ? "Coefficients insuffisants ({$totalCoefficients} / {$seuilRequisEleve} requis)"
                    : (! $isEligibleClassement ? 'Aucune matière composée' : null),
                'moyenne_generale' => $moyenneGenerale,
                'cote_globale' => $coteGlobale,
                'a_note_eliminatoire' => $aNoteEliminatoire,
                'rang_general' => null,
                'mention' => null,
                'discipline' => [
                    'absences_justifiees' => (int) ($disciplineRec?->absences_justifiees ?? 0),
                    'absences_non_justifiees' => (int) ($disciplineRec?->absences_non_justifiees ?? 0),
                    'retards' => (int) ($disciplineRec?->retards ?? 0),
                    'consignes' => (int) ($disciplineRec?->consignes ?? 0),
                    'avertissements' => (int) ($disciplineRec?->avertissements_conduite ?? 0),
                    'blames' => (int) ($disciplineRec?->blames_conduite ?? 0),
                    'exclusions' => (int) ($disciplineRec?->exclusions_jours ?? 0),
                    'decision_conseil' => $disciplineRec?->decision_conseil ?? '',
                ],
            ];

            if ($isEligibleClassement) {
                $rangsSequentielsPrepares[$insc->id] = $moyenneGenerale;
            }
        }

        // Calcul des rangs généraux (uniquement les élèves éligibles au classement)
        arsort($rangsSequentielsPrepares);
        $rangsCalcules = self::attribuerRangsAvecExAequo($rangsSequentielsPrepares);
        foreach ($elevesData as $inscId => &$data) {
            if (! $data['is_eligible_classement']) {
                $data['rang_general'] = 'NC';
                $data['mention'] = 'Non Classé (NC)';
                $data['distinction'] = 'Non éligible (Coefficients insuffisants)';
                $data['tableau_honneur'] = false;
                $data['encouragements'] = false;
                $data['felicitations'] = false;
            } else {
                $data['rang_general'] = $rangsCalcules[$inscId] ?? 'N/A';
                $moy = $data['moyenne_generale'];
                $hasElim = $data['a_note_eliminatoire'];
                $data['tableau_honneur'] = ($moy >= 12.0 && ! $hasElim);
                $data['encouragements'] = ($moy >= 14.0 && ! $hasElim);
                $data['felicitations'] = ($moy >= 16.0 && ! $hasElim);
                if (! $hasElim) {
                    if ($moy >= 16.0) {
                        $data['mention'] = 'Félicitations du Conseil';
                        $data['distinction'] = 'Tableau d\'Honneur avec Félicitations';
                    } elseif ($moy >= 14.0) {
                        $data['mention'] = 'Encouragements';
                        $data['distinction'] = 'Tableau d\'Honneur avec Encouragements';
                    } elseif ($moy >= 12.0) {
                        $data['mention'] = 'Tableau d\'Honneur';
                        $data['distinction'] = 'Tableau d\'Honneur';
                    } elseif ($moy >= 10.0) {
                        $data['mention'] = 'Passable';
                        $data['distinction'] = 'Encourageant';
                    } else {
                        $data['mention'] = 'Insuffisant';
                        $data['distinction'] = 'Avertissement travail';
                    }
                } else {
                    $data['mention'] = 'Non éligible (Note éliminatoire)';
                    $data['distinction'] = 'Aucune (Note éliminatoire)';
                }
            }
        }
        unset($data);

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

        $effectifInscrits = count($inscriptions);
        $effectifClasse = count($rangsSequentielsPrepares);
        $effectifNonClasse = $effectifInscrits - $effectifClasse;

        // Construction des rappels d'évaluations pour la période
        $evaluationsPeriode = Evaluation::where('periode_id', $periode->id)
            ->where('ordre', '<=', $evaluation->ordre)
            ->orderBy('ordre')
            ->get();

        foreach ($elevesData as $inscId => &$data) {
            $rappels = [];
            foreach ($evaluationsPeriode as $ev) {
                if ($ev->id === $evaluation->id) {
                    $rappels[] = [
                        'code' => $ev->code,
                        'nom' => $ev->nom,
                        'moyenne' => $data['moyenne_generale'],
                        'rang' => $data['rang_general'],
                    ];
                } else {
                    $rappels[] = [
                        'code' => $ev->code,
                        'nom' => $ev->nom,
                        'moyenne' => null,
                        'rang' => '-',
                    ];
                }
            }
            $data['rappels_evaluations'] = $rappels;
        }
        unset($data);

        // Statistiques globales de classe avec clés harmonisées
        $statsClasse = [
            'effectif_total' => $effectifInscrits,
            'effectif' => $effectifInscrits,
            'effectif_evalue' => $effectifInscrits,
            'effectif_classe' => $effectifClasse,
            'effectif_non_classe' => $effectifNonClasse,
            'seuil_coefficients_classement' => $seuilCoeffsMinimalClasse,
            'total_coefficients_classe' => $totalCoefficientsTheoriqueClasse,
            'moyenne_generale' => $moyenneClasse,
            'moyenne_classe' => $moyenneClasse,
            'min_generale' => $minClasse,
            'moyenne_min' => $minClasse,
            'max_generale' => $maxClasse,
            'moyenne_max' => $maxClasse,
            'nb_moyennes' => $nbreMoyennes,
            'nombre_moyennes' => $nbreMoyennes,
            'taux_reussite' => $tauxReussite,
            'ecart_type' => self::calculerEcartType($toutesMoyennes),
        ];

        return [
            'type_bulletin' => 'sequentiel',
            'classe' => [
                'id' => $classe->id,
                'nom' => $classe->nom,
                'code' => $classe->code,
                'niveau' => $classe->niveau->nom,
                'effectif' => $effectifInscrits,
                'professeur_principal' => $classe->professeurPrincipal ? ($classe->professeurPrincipal->nom ?: $classe->professeurPrincipal->nom_complet) : null,
                'etablissement' => [
                    'nom' => $etablissement->nom,
                    'nom_en' => $etablissement->nom_en,
                    'sigle' => $etablissement->code,
                    'logo_url' => $etablissement->logo_url,
                    'logo' => $etablissement->logo ?: $etablissement->institution?->logo,
                    'ville' => $etablissement->ville ?: $etablissement->institution?->ville,
                    'boite_postale' => self::formaterBoitePostale($etablissement->institution?->boite_postale),
                    'telephone' => $etablissement->telephone_1 ?: ($etablissement->institution?->telephone_1 ?: '---'),
                    'email' => $etablissement->email ?: $etablissement->institution?->email,
                    'devise' => $etablissement->devise ?: $etablissement->institution?->devise,
                    'devise_en' => $etablissement->institution?->devise_en,
                    'delegation_regionale' => $etablissement->institution?->delegation_regionale,
                    'delegation_regionale_en' => $etablissement->institution?->delegation_regionale_en,
                    'delegation_departementale' => $etablissement->institution?->delegation_departementale,
                    'delegation_departementale_en' => $etablissement->institution?->delegation_departementale_en,
                    'titre_chef' => $etablissement->titre_chef,
                    'titre_chef_en' => $etablissement->titre_chef_en,
                    'nom_chef' => $etablissement->nom_chef_etablissement,
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
        $etablissement = $classe->etablissement ?? $classe->niveau?->cycle?->section?->etablissement;
        $modeBulletin = $modeBulletin ?? $etablissement?->mode_bulletin_defaut ?? 'sans_competences';

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

        $totalCoefficientsTheoriqueClasse = (float) $attributions->sum(function ($attr) use ($coefficients) {
            $matiereNiveau = $coefficients->get($attr->matiere_id);
            return (float) ($matiereNiveau ? $matiereNiveau->coefficient : $attr->matiere->coefficient_defaut);
        });
        $seuilCoeffsMinimalClasse = $etablissement
            ? $etablissement->getSeuilCoefficientsMinimal($totalCoefficientsTheoriqueClasse)
            : round($totalCoefficientsTheoriqueClasse * 0.60, 2);

        // Détermination dynamique des paliers pédagogiques pour la promotion de fin d'année
        $niveauActuel = $classe->niveau;
        $nomNiveauActuel = $niveauActuel ? mb_strtoupper($niveauActuel->nom, 'UTF-8') : 'CLASSE ACTUELLE';
        $nomNiveauSuivant = 'CLASSE SUPÉRIEURE';
        if ($niveauActuel) {
            $niveauSuivant = Niveau::where('cycle_id', $niveauActuel->cycle_id)
                ->where('ordre', '>', $niveauActuel->ordre)
                ->orderBy('ordre')
                ->first();
            if (! $niveauSuivant) {
                $niveauSuivant = Niveau::where('ordre', '>', $niveauActuel->ordre)
                    ->orderBy('ordre')
                    ->first();
            }
            if ($niveauSuivant) {
                $nomNiveauSuivant = mb_strtoupper($niveauSuivant->nom, 'UTF-8');
            }
        }

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

            // Matrice et cumul de discipline sur les trimestres (format 5 colonnes)
            $disciplineParPeriode = [];
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

            foreach ($periodes as $p) {
                $resTrim = $resultatsTrimestres[$p->id] ?? null;
                $eleveTrim = $resTrim ? collect($resTrim['eleves'])->firstWhere('inscription_id', $insc->id) : null;
                $disc = $eleveTrim['discipline'] ?? [
                    'absences_justifiees' => 0,
                    'absences_non_justifiees' => 0,
                    'retards' => 0,
                    'consignes' => 0,
                    'avertissements' => 0,
                    'blames' => 0,
                    'exclusions' => 0,
                    'decision_conseil' => '',
                ];
                $disciplineParPeriode[$p->id] = $disc;
                $cumulDiscipline['absences_justifiees'] += (int) $disc['absences_justifiees'];
                $cumulDiscipline['absences_non_justifiees'] += (int) $disc['absences_non_justifiees'];
                $cumulDiscipline['retards'] += (int) $disc['retards'];
                $cumulDiscipline['consignes'] += (int) $disc['consignes'];
                $cumulDiscipline['avertissements'] += (int) $disc['avertissements'];
                $cumulDiscipline['blames'] += (int) $disc['blames'];
                $cumulDiscipline['exclusions'] += (int) $disc['exclusions'];
                if (! empty($disc['decision_conseil'])) {
                    $cumulDiscipline['decision_conseil'] = $disc['decision_conseil'];
                }
            }

            // Synthèse des moyennes trimestrielles de l'élève
            $moyennesTrimestres = [];
            foreach ($periodes as $p) {
                $resTrim = $resultatsTrimestres[$p->id] ?? null;
                $elTrim = $resTrim ? collect($resTrim['eleves'])->firstWhere('inscription_id', $insc->id) : null;
                $moyennesTrimestres[$p->id] = [
                    'id' => $p->id,
                    'code' => $p->code,
                    'nom' => $p->nom,
                    'moyenne' => $elTrim ? $elTrim['moyenne_generale'] : null,
                    'rang' => $elTrim ? $elTrim['rang_general'] : null,
                ];
            }

            $matieresFaibles = [];

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

                if ($moyenneAnnuelleMatiere !== null && $moyenneAnnuelleMatiere < 10.0) {
                    $matieresFaibles[] = $attr->matiere->libelle_bulletin ?: $attr->matiere->nom;
                }

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
                    'enseignant_nom' => $attr->personnel ? ($attr->personnel->nom ?: $attr->personnel->nom_complet) : 'Non assigné',
                    'enseignant_nom_complet' => $attr->personnel ? $attr->personnel->nom_complet : 'Non assigné',
                    'groupe_id' => $groupe ? $groupe->id : 0,
                    'groupe_nom' => $groupe ? $groupe->nom : 'Autres Enseignements',
                    'groupe_ordre' => $groupe ? $groupe->getOrdrePourSerie($classe->serie) : 99,
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

            // Tri ordonné des matières par l'ordre contextuel du bloc selon la série, puis par code matière
            uasort($lignesMatieres, function ($a, $b) {
                if ($a['groupe_ordre'] === $b['groupe_ordre']) {
                    return strcmp($a['matiere_code'], $b['matiere_code']);
                }
                return $a['groupe_ordre'] <=> $b['groupe_ordre'];
            });

            // Moyenne Générale Annuelle (M.G.A.)
            $mga = $totalCoefficients > 0 ? round($totalPoints / $totalCoefficients, 2) : 0.0;
            $coteGlobale = Note::determinerCoteAPC($mga);

            // Décision automatique du conseil de fin d'année orientée niveau supérieur
            $decisionAuto = $mga >= 10.0
                ? "Promu(e) en classe de {$nomNiveauSuivant}."
                : ($mga >= 8.5 ? "Redouble la classe de {$nomNiveauActuel}." : 'Exclu(e) / Non admis(e)');

            // Calcul de l'éligibilité au classement selon le quota de coefficients (MINESEC / Gesco)
            $dispensesEleve = DispenseEleve::validees()
                ->where('inscription_id', $insc->id)
                ->get();
            $coeffsDispensesEleve = (float) $dispensesEleve->where('neutraliser_coefficient', true)->sum(function ($disp) use ($coefficients, $attributions) {
                $attr = $attributions->firstWhere('matiere_id', $disp->matiere_id);
                if (! $attr) {
                    return 0.0;
                }
                $matiereNiveau = $coefficients->get($attr->matiere_id);
                return (float) ($matiereNiveau ? $matiereNiveau->coefficient : $attr->matiere->coefficient_defaut);
            });
            $totalTheoriqueEleve = max(0.0, $totalCoefficientsTheoriqueClasse - $coeffsDispensesEleve);
            $seuilRequisEleve = $etablissement
                ? $etablissement->getSeuilCoefficientsMinimal($totalTheoriqueEleve)
                : round($totalTheoriqueEleve * 0.60, 2);

            $isEligibleClassement = ($totalCoefficients >= $seuilRequisEleve) && ($totalCoefficients > 0);

            $elevesData[$insc->id] = [
                'inscription_id' => $insc->id,
                'matricule' => $insc->eleve->matricule,
                'matricule_ministere' => $insc->eleve->matricule_national,
                'matricule_national' => $insc->eleve->matricule_national,
                'nom_complet' => $insc->eleve->nom.' '.$insc->eleve->prenom,
                'date_naissance' => $insc->eleve->date_naissance?->format('d/m/Y'),
                'lieu_naissance' => $insc->eleve->lieu_naissance,
                'genre' => $insc->eleve->sexe,
                'sexe' => $insc->eleve->sexe,
                'photo_url' => $insc->eleve->photo_url,
                'is_redoublant' => $insc->is_redoublant,
                'lignes_matieres' => $lignesMatieres,
                'matieres_faibles' => $matieresFaibles,
                'moyennes_trimestres' => $moyennesTrimestres,
                'total_points' => round($totalPoints, 2),
                'total_coefficients' => $totalCoefficients,
                'total_coefficients_classe' => $totalCoefficientsTheoriqueClasse,
                'seuil_coefficients_requis' => $seuilRequisEleve,
                'is_eligible_classement' => $isEligibleClassement,
                'motif_non_classe' => ! $isEligibleClassement && $totalCoefficients > 0
                    ? "Coefficients insuffisants ({$totalCoefficients} / {$seuilRequisEleve} requis)"
                    : (! $isEligibleClassement ? 'Aucune matière composée' : null),
                'moyenne_generale' => $mga,
                'cote_globale' => $coteGlobale,
                'a_note_eliminatoire' => $aNoteEliminatoire,
                'rang_general' => null,
                'mention' => null,
                'discipline' => $cumulDiscipline,
                'discipline_matrice' => [
                    'par_periode' => $disciplineParPeriode,
                    'total' => $cumulDiscipline,
                ],
                'decision_finale' => ! empty($cumulDiscipline['decision_conseil']) ? $cumulDiscipline['decision_conseil'] : $decisionAuto,
            ];

            if ($isEligibleClassement) {
                $rangsAnnuelsPrepares[$insc->id] = $mga;
            }
        }

        // Calcul des rangs généraux annuels (uniquement les élèves éligibles au classement)
        arsort($rangsAnnuelsPrepares);
        $rangsCalcules = self::attribuerRangsAvecExAequo($rangsAnnuelsPrepares);
        foreach ($elevesData as $inscId => &$data) {
            if (! $data['is_eligible_classement']) {
                $data['rang_general'] = 'NC';
                $data['mention'] = 'Non Classé (NC)';
                $data['distinction'] = 'Non éligible (Coefficients insuffisants)';
            } else {
                $data['rang_general'] = $rangsCalcules[$inscId] ?? 'N/A';
                $moy = $data['moyenne_generale'];
                $hasElim = $data['a_note_eliminatoire'];
                $data['tableau_honneur'] = ($moy >= 12.0 && ! $hasElim);
                $data['encouragements'] = ($moy >= 14.0 && ! $hasElim);
                $data['felicitations'] = ($moy >= 16.0 && ! $hasElim);
                if (! $hasElim) {
                    if ($moy >= 16.0) {
                        $data['mention'] = 'Félicitations du Conseil';
                    } elseif ($moy >= 14.0) {
                        $data['mention'] = 'Encouragements';
                    } elseif ($moy >= 12.0) {
                        $data['mention'] = 'Tableau d\'Honneur';
                    }
                }
            }
        }
        unset($data);

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

        $effectifInscrits = count($inscriptions);
        $effectifClasse = count($rangsAnnuelsPrepares);
        $effectifNonClasse = $effectifInscrits - $effectifClasse;

        // Statistiques globales annuelles avec clés harmonisées
        $statsClasse = [
            'effectif_total' => $effectifInscrits,
            'effectif' => $effectifInscrits,
            'effectif_evalue' => $effectifInscrits,
            'effectif_classe' => $effectifClasse,
            'effectif_non_classe' => $effectifNonClasse,
            'seuil_coefficients_classement' => $seuilCoeffsMinimalClasse,
            'total_coefficients_classe' => $totalCoefficientsTheoriqueClasse,
            'moyenne_generale' => $moyenneClasse,
            'moyenne_classe' => $moyenneClasse,
            'min_generale' => $minClasse,
            'moyenne_min' => $minClasse,
            'max_generale' => $maxClasse,
            'moyenne_max' => $maxClasse,
            'nb_moyennes' => $nbreMoyennes,
            'nombre_moyennes' => $nbreMoyennes,
            'taux_reussite' => $tauxReussite,
            'ecart_type' => self::calculerEcartType($toutesMoyennes),
        ];

        return [
            'type_bulletin' => 'annuel',
            'classe' => [
                'id' => $classe->id,
                'nom' => $classe->nom,
                'code' => $classe->code,
                'niveau' => $classe->niveau->nom,
                'effectif' => $effectifInscrits,
                'professeur_principal' => $classe->professeurPrincipal ? ($classe->professeurPrincipal->nom ?: $classe->professeurPrincipal->nom_complet) : null,
                'etablissement' => [
                    'nom' => $etablissement->nom,
                    'nom_en' => $etablissement->nom_en,
                    'sigle' => $etablissement->code,
                    'logo_url' => $etablissement->logo_url,
                    'logo' => $etablissement->logo ?: $etablissement->institution?->logo,
                    'ville' => $etablissement->ville ?: $etablissement->institution?->ville,
                    'boite_postale' => self::formaterBoitePostale($etablissement->institution?->boite_postale),
                    'telephone' => $etablissement->telephone_1 ?: ($etablissement->institution?->telephone_1 ?: '---'),
                    'email' => $etablissement->email ?: $etablissement->institution?->email,
                    'devise' => $etablissement->devise ?: $etablissement->institution?->devise,
                    'devise_en' => $etablissement->institution?->devise_en,
                    'delegation_regionale' => $etablissement->institution?->delegation_regionale,
                    'delegation_regionale_en' => $etablissement->institution?->delegation_regionale_en,
                    'delegation_departementale' => $etablissement->institution?->delegation_departementale,
                    'delegation_departementale_en' => $etablissement->institution?->delegation_departementale_en,
                    'titre_chef' => $etablissement->titre_chef,
                    'titre_chef_en' => $etablissement->titre_chef_en,
                    'nom_chef' => $etablissement->nom_chef_etablissement,
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
