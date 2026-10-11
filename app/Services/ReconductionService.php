<?php

namespace App\Services;

use App\Models\AnneeScolaire;
use App\Models\Attribution;
use App\Models\BourseRemise;
use App\Models\Evaluation;
use App\Models\FraisScolaire;
use App\Models\ModeleEcheance;
use App\Models\Periode;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Service de Reconduction & Duplication Annuelle (Aurore §3.16 & Gesco V1.1).
 * 
 * Permet à l'administration de cloner en un clic la configuration pédagogique,
 * structurelle et financière d'une année scolaire précédente vers une nouvelle année scolaire.
 */
class ReconductionService
{
    /**
     * Exécute la duplication de la configuration d'une année scolaire source vers une année cible.
     *
     * @param int $sourceAnneeId Identifiant de l'année scolaire d'origine
     * @param int $cibleAnneeId Identifiant de la nouvelle année scolaire cible
     * @param array $options Options sélectionnées (periodes, frais_scolaires, modeles_echeances, bourses, attributions)
     * @param int $etablissementId Identifiant du campus / établissement
     * @return array Résumé des éléments clonés
     * @throws Exception En cas d'erreur de transaction
     */
    public function dupliquerConfiguration(
        int $sourceAnneeId,
        int $cibleAnneeId,
        array $options,
        int $etablissementId
    ): array {
        $source = AnneeScolaire::findOrFail($sourceAnneeId);
        $cible = AnneeScolaire::findOrFail($cibleAnneeId);

        return DB::transaction(function () use ($source, $cible, $options, $etablissementId) {
            $stats = [
                'periodes' => 0,
                'evaluations' => 0,
                'frais_scolaires' => 0,
                'modeles_echeances' => 0,
                'bourses_remises' => 0,
                'attributions' => 0,
            ];

            // 1. Duplication des Périodes & Séquences/Évaluations
            if (!empty($options['periodes'])) {
                $periodesSource = Periode::with('evaluations')
                    ->where('annee_scolaire_id', $source->id)
                    ->where(function ($q) use ($etablissementId) {
                        $q->whereNull('etablissement_id')->orWhere('etablissement_id', $etablissementId);
                    })
                    ->get();

                foreach ($periodesSource as $perSource) {
                    $existing = Periode::where('annee_scolaire_id', $cible->id)
                        ->where('code', $perSource->code)
                        ->where(function ($q) use ($etablissementId) {
                            $q->whereNull('etablissement_id')->orWhere('etablissement_id', $etablissementId);
                        })
                        ->first();

                    $targetPeriode = $existing;
                    if (!$existing) {
                        $targetPeriode = Periode::create([
                            'etablissement_id' => $perSource->etablissement_id,
                            'annee_scolaire_id' => $cible->id,
                            'code' => $perSource->code,
                            'nom' => $perSource->nom,
                            'nom_en' => $perSource->nom_en,
                            'ordre' => $perSource->ordre,
                            'date_debut' => $cible->date_debut,
                            'date_fin' => $cible->date_fin,
                            'statut' => 'a_venir',
                        ]);
                        $stats['periodes']++;
                    }

                    // Cloner les évaluations associées
                    foreach ($perSource->evaluations as $evalSource) {
                        $existingEval = Evaluation::where('periode_id', $targetPeriode->id)
                            ->where('code', $evalSource->code)
                            ->first();

                        if (!$existingEval) {
                            Evaluation::create([
                                'periode_id' => $targetPeriode->id,
                                'code' => $evalSource->code,
                                'nom' => $evalSource->nom,
                                'nom_en' => $evalSource->nom_en,
                                'ordre' => $evalSource->ordre,
                                'coefficient' => $evalSource->coefficient,
                                'is_locked' => false,
                            ]);
                            $stats['evaluations']++;
                        }
                    }
                }
            }

            // 2. Duplication des Frais Scolaires (Tarifs par niveau et tranches)
            if (!empty($options['frais_scolaires'])) {
                $fraisSource = FraisScolaire::where('annee_scolaire_id', $source->id)
                    ->where('etablissement_id', $etablissementId)
                    ->get();

                foreach ($fraisSource as $fs) {
                    $exists = FraisScolaire::where('annee_scolaire_id', $cible->id)
                        ->where('etablissement_id', $etablissementId)
                        ->where('niveau_id', $fs->niveau_id)
                        ->where('type_frais_id', $fs->type_frais_id)
                        ->where('tranche_numero', $fs->tranche_numero)
                        ->exists();

                    if (!$exists) {
                        FraisScolaire::create([
                            'annee_scolaire_id' => $cible->id,
                            'etablissement_id' => $etablissementId,
                            'niveau_id' => $fs->niveau_id,
                            'serie' => $fs->serie,
                            'type_frais_id' => $fs->type_frais_id,
                            'tranche_numero' => $fs->tranche_numero,
                            'libelle_tranche' => $fs->libelle_tranche,
                            'montant' => $fs->montant,
                            'date_limite' => null, // À reprogrammer par l'intendant
                        ]);
                        $stats['frais_scolaires']++;
                    }
                }
            }

            // 3. Duplication des Modèles d'Échéances & Tranches
            if (!empty($options['modeles_echeances'])) {
                $modelesSource = ModeleEcheance::with('tranches')
                    ->where('annee_scolaire_id', $source->id)
                    ->where('etablissement_id', $etablissementId)
                    ->get();

                foreach ($modelesSource as $mod) {
                    $exists = ModeleEcheance::where('annee_scolaire_id', $cible->id)
                        ->where('etablissement_id', $etablissementId)
                        ->where('code', $mod->code)
                        ->first();

                    if (!$exists) {
                        $nouveauModele = ModeleEcheance::create([
                            'etablissement_id' => $etablissementId,
                            'annee_scolaire_id' => $cible->id,
                            'nom' => $mod->nom,
                            'code' => $mod->code,
                            'niveau_id' => $mod->niveau_id,
                            'type_frais_id' => $mod->type_frais_id,
                            'description' => $mod->description,
                            'is_actif' => true,
                        ]);

                        foreach ($mod->tranches as $tr) {
                            $nouveauModele->tranches()->create([
                                'numero_tranche' => $tr->numero_tranche,
                                'libelle' => $tr->libelle,
                                'pourcentage' => $tr->pourcentage,
                                'montant_fixe' => $tr->montant_fixe,
                                'date_echeance' => null, // À renseigner pour la nouvelle année
                                'ordre' => $tr->ordre,
                            ]);
                        }
                        $stats['modeles_echeances']++;
                    }
                }
            }

            // 4. Duplication des Modèles de Bourses & Remises
            if (!empty($options['bourses_remises'])) {
                $boursesSource = BourseRemise::where('annee_scolaire_id', $source->id)
                    ->where('etablissement_id', $etablissementId)
                    ->get();

                foreach ($boursesSource as $br) {
                    $exists = BourseRemise::where('annee_scolaire_id', $cible->id)
                        ->where('etablissement_id', $etablissementId)
                        ->where('code', $br->code)
                        ->exists();

                    if (!$exists) {
                        BourseRemise::create([
                            'etablissement_id' => $etablissementId,
                            'annee_scolaire_id' => $cible->id,
                            'nom' => $br->nom,
                            'code' => $br->code,
                            'type' => $br->type,
                            'mode_calcul' => $br->mode_calcul,
                            'valeur' => $br->valeur,
                            'description' => $br->description,
                            'tranches_applicables' => $br->tranches_applicables,
                            'cumulable' => $br->cumulable,
                            'is_active' => true,
                        ]);
                        $stats['bourses_remises']++;
                    }
                }
            }

            // 5. Duplication des Attributions (Affectations Enseignants -> Classes -> Matières)
            if (!empty($options['attributions'])) {
                $attributionsSource = Attribution::where('annee_scolaire_id', $source->id)
                    ->whereHas('classe', fn($q) => $q->where('etablissement_id', $etablissementId))
                    ->where('is_active', true)
                    ->get();

                foreach ($attributionsSource as $attr) {
                    $exists = Attribution::where('annee_scolaire_id', $cible->id)
                        ->where('classe_id', $attr->classe_id)
                        ->where('matiere_id', $attr->matiere_id)
                        ->exists();

                    if (!$exists) {
                        Attribution::create([
                            'annee_scolaire_id' => $cible->id,
                            'personnel_id' => $attr->personnel_id,
                            'classe_id' => $attr->classe_id,
                            'matiere_id' => $attr->matiere_id,
                            'volume_horaire_reel' => $attr->volume_horaire_reel,
                            'is_titulaire' => $attr->is_titulaire,
                            'is_active' => true,
                        ]);
                        $stats['attributions']++;
                    }
                }
            }

            Log::info("Reconduction annuelle exécutée de l'année {$source->code} vers {$cible->code} : ", $stats);

            return $stats;
        });
    }
}
