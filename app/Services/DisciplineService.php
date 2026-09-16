<?php

namespace App\Services;

use App\Models\Assiduite;
use App\Models\DisciplineBulletin;
use App\Models\Inscription;
use App\Models\Periode;
use App\Models\SanctionDisciplinaire;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Service Métier pour le Module 12 : Absences, Retards & Discipline (Gesco V1.1).
 *
 * Gère le cycle de vie complet de la discipline scolaire :
 * - Saisie et justification des absences et retards horodatés.
 * - Prononcé et suivi des sanctions disciplinaires.
 * - Agrégation automatique et synchrone vers la table disciplines_bulletins du Module 10.
 * - Contrôle d'incompatibilité des absences avec les mentions honorifiques du conseil de classe.
 */
class DisciplineService
{
    /**
     * Enregistre un événement d'assiduité (absence ou retard) et actualise la synthèse du bulletin.
     *
     * @param array{
     *     inscription_id: int,
     *     periode_id: int,
     *     type_evenement: string,
     *     date_evenement: string,
     *     nombre_heures?: float,
     *     matiere_id?: int|null,
     *     statut_justification?: string,
     *     motif_justification?: string|null,
     *     justificatif_path?: string|null
     * } $data Données de l'assiduité
     * @param User|null $user Utilisateur déclarant l'événement
     * @return Assiduite
     */
    public function enregistrerAssiduite(array $data, ?User $user = null): Assiduite
    {
        return DB::transaction(function () use ($data, $user) {
            $assiduite = Assiduite::create([
                'inscription_id' => $data['inscription_id'],
                'periode_id' => $data['periode_id'],
                'type_evenement' => $data['type_evenement'] ?? 'absence',
                'date_evenement' => $data['date_evenement'],
                'nombre_heures' => $data['nombre_heures'] ?? 1.0,
                'matiere_id' => $data['matiere_id'] ?? null,
                'statut_justification' => $data['statut_justification'] ?? 'non_justifiee',
                'motif_justification' => $data['motif_justification'] ?? null,
                'justificatif_path' => $data['justificatif_path'] ?? null,
                'signale_par' => $user?->id,
            ]);

            // Actualisation automatique de la synthèse trimestrielle pour le bulletin
            $this->actualiserSyntheseBulletin($assiduite->inscription_id, $assiduite->periode_id);

            return $assiduite;
        });
    }

    /**
     * Justifie a posteriori une absence ou un retard.
     *
     * @param Assiduite $assiduite
     * @param string $motif
     * @param string|null $justificatifPath
     * @return Assiduite
     */
    public function justifierAssiduite(Assiduite $assiduite, string $motif, ?string $justificatifPath = null): Assiduite
    {
        return DB::transaction(function () use ($assiduite, $motif, $justificatifPath) {
            $assiduite->update([
                'statut_justification' => 'justifiee',
                'motif_justification' => $motif,
                'justificatif_path' => $justificatifPath ?? $assiduite->justificatif_path,
            ]);

            $this->actualiserSyntheseBulletin($assiduite->inscription_id, $assiduite->periode_id);

            return $assiduite;
        });
    }

    /**
     * Enregistre une sanction disciplinaire prononcée contre un élève.
     *
     * @param array{
     *     inscription_id: int,
     *     periode_id: int,
     *     date_decision: string,
     *     type_sanction: string,
     *     motif: string,
     *     nombre_jours_ou_heures?: int,
     *     decide_par?: string,
     *     document_notification_path?: string|null,
     *     is_notified_parent?: bool
     * } $data
     * @return SanctionDisciplinaire
     */
    public function enregistrerSanction(array $data): SanctionDisciplinaire
    {
        return DB::transaction(function () use ($data) {
            $sanction = SanctionDisciplinaire::create([
                'inscription_id' => $data['inscription_id'],
                'periode_id' => $data['periode_id'],
                'date_decision' => $data['date_decision'],
                'type_sanction' => $data['type_sanction'],
                'motif' => $data['motif'],
                'nombre_jours_ou_heures' => $data['nombre_jours_ou_heures'] ?? 1,
                'decide_par' => $data['decide_par'] ?? 'Censeur',
                'document_notification_path' => $data['document_notification_path'] ?? null,
                'is_notified_parent' => $data['is_notified_parent'] ?? false,
            ]);

            $this->actualiserSyntheseBulletin($sanction->inscription_id, $sanction->periode_id);

            return $sanction;
        });
    }

    /**
     * Recalcule et persiste de manière synchrone les cumuls disciplinaires trimestriels
     * dans la table `disciplines_bulletins` pour alimentation instantanée des bulletins PDF.
     *
     * @param int $inscriptionId
     * @param int $periodeId
     * @return DisciplineBulletin
     */
    public function actualiserSyntheseBulletin(int $inscriptionId, int $periodeId): DisciplineBulletin
    {
        // 1. Cumul des absences justifiées et non justifiées
        $absencesJustifiees = (float) Assiduite::where('inscription_id', $inscriptionId)
            ->where('periode_id', $periodeId)
            ->where('type_evenement', 'absence')
            ->where('statut_justification', 'justifiee')
            ->sum('nombre_heures');

        $absencesNonJustifiees = (float) Assiduite::where('inscription_id', $inscriptionId)
            ->where('periode_id', $periodeId)
            ->where('type_evenement', 'absence')
            ->where('statut_justification', 'non_justifiee')
            ->sum('nombre_heures');

        // 2. Nombre de retards
        $retards = Assiduite::where('inscription_id', $inscriptionId)
            ->where('periode_id', $periodeId)
            ->where('type_evenement', 'retard')
            ->count();

        // 3. Cumul des sanctions
        $sanctions = SanctionDisciplinaire::where('inscription_id', $inscriptionId)
            ->where('periode_id', $periodeId)
            ->get();

        $consignes = (int) $sanctions->where('type_sanction', 'retenue')->sum('nombre_jours_ou_heures');
        $avertissements = $sanctions->where('type_sanction', 'avertissement')->count();
        $blames = $sanctions->where('type_sanction', 'blame')->count();
        $exclusions = (int) $sanctions->where('type_sanction', 'exclusion_temporaire')->sum('nombre_jours_ou_heures');

        return DisciplineBulletin::updateOrCreate(
            [
                'inscription_id' => $inscriptionId,
                'periode_id' => $periodeId,
            ],
            [
                'absences_justifiees' => (int) round($absencesJustifiees),
                'absences_non_justifiees' => (int) round($absencesNonJustifiees),
                'retards' => $retards,
                'consignes' => $consignes,
                'avertissements_conduite' => $avertissements,
                'blames_conduite' => $blames,
                'exclusions_jours' => $exclusions,
            ]
        );
    }

    /**
     * Évalue si l'élève est déchu de son éligibilité aux mentions académiques
     * pour dépassement du seuil d'absences non justifiées (Gesco V1.1 - Module 12.4).
     *
     * @param Inscription $inscription
     * @param Periode $periode
     * @return array{eligible: bool, motif: string|null, cumul_non_justifiees: float, seuil: int}
     */
    public function evaluerEligibiliteMentions(Inscription $inscription, Periode $periode): array
    {
        $classe = $inscription->classe()->with('niveau.cycle.section.etablissement')->first();
        $etablissement = $classe?->niveau?->cycle?->section?->etablissement;
        $seuil = $etablissement?->seuil_absences_decheance_mentions ?? 15;

        $cumulNonJustifiees = (float) Assiduite::where('inscription_id', $inscription->id)
            ->where('periode_id', $periode->id)
            ->where('type_evenement', 'absence')
            ->where('statut_justification', 'non_justifiee')
            ->sum('nombre_heures');

        if ($cumulNonJustifiees >= $seuil) {
            return [
                'eligible' => false,
                'motif' => "Déchu des distinctions honorifiques : dépassement du seuil de tolérance des absences non justifiées ({$cumulNonJustifiees}h / max {$seuil}h).",
                'cumul_non_justifiees' => $cumulNonJustifiees,
                'seuil' => $seuil,
            ];
        }

        return [
            'eligible' => true,
            'motif' => null,
            'cumul_non_justifiees' => $cumulNonJustifiees,
            'seuil' => $seuil,
        ];
    }
}
