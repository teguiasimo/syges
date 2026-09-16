<?php

namespace App\Services;

use App\Models\Attribution;
use App\Models\Classe;
use App\Models\Personnel;
use App\Models\Salle;
use App\Models\SeanceEmploiTemps;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Service Métier pour le Module 13 : Emplois du Temps & Gestion des Salles (Gesco V1.1).
 *
 * Coordonne :
 * - Le moteur anti-conflits systématique (Enseignant, Salle, Classe, Capacité).
 * - La planification de la grille hebdomadaire du lundi au samedi.
 * - La gestion des indisponibilités, annulations et remplacements d'enseignants.
 * - L'interconnexion RH pour le décompte des heures réelles dispensées (vacations).
 */
class EmploiDuTempsService
{
    /**
     * Vérifie systématiquement l'absence de conflits d'emploi du temps (Gesco 13.3).
     *
     * @param array{
     *     annee_scolaire_id: int,
     *     classe_id: int,
     *     attribution_id: int,
     *     salle_id?: int|null,
     *     jour_semaine: string,
     *     heure_debut: string,
     *     heure_fin: string,
     * } $data Données de la séance proposée
     * @param int|null $excludeId Identifiant de la séance à exclure en cas de modification
     * @return array{has_conflict: bool, errors: array<string, string>, warnings: array<string, string>}
     */
    public function detecterConflits(array $data, ?int $excludeId = null): array
    {
        $errors = [];
        $warnings = [];

        $anneeId = $data['annee_scolaire_id'];
        $classeId = $data['classe_id'];
        $attributionId = $data['attribution_id'];
        $salleId = $data['salle_id'] ?? null;
        $jour = $data['jour_semaine'];
        $debut = $data['heure_debut'];
        $fin = $data['heure_fin'];

        // Validation temporelle élémentaire
        if ($debut >= $fin) {
            $errors['heure_fin'] = "L'heure de fin ({$fin}) doit être postérieure à l'heure de début ({$debut}).";
            return ['has_conflict' => true, 'errors' => $errors, 'warnings' => $warnings];
        }

        // Récupérer l'enseignant de l'attribution
        $attribution = Attribution::with(['personnel', 'matiere'])->find($attributionId);
        $enseignantId = $attribution?->personnel_id;

        // Requête de base pour les séances actives en chevauchement horaire sur le même jour et la même année
        // Deux intervalles [A, B] et [C, D] se chevauchent ssi A < D et B > C
        $baseQuery = SeanceEmploiTemps::query()
            ->where('annee_scolaire_id', $anneeId)
            ->where('jour_semaine', $jour)
            ->where('statut', '!=', 'annule')
            ->where(function ($q) use ($debut, $fin) {
                $q->where('heure_debut', '<', $fin)
                  ->where('heure_fin', '>', $debut);
            });

        if ($excludeId) {
            $baseQuery->where('id', '!=', $excludeId);
        }

        // 1. Conflit Classe (La classe ne peut pas avoir deux cours en même temps)
        $conflitClasse = (clone $baseQuery)
            ->where('classe_id', $classeId)
            ->with(['attribution.matiere'])
            ->first();

        if ($conflitClasse) {
            $matiereNom = $conflitClasse->attribution?->matiere?->nom ?? 'un cours';
            $errors['classe_id'] = "Conflit Classe : cette classe a déjà cours de {$matiereNom} de {$conflitClasse->heure_debut} à {$conflitClasse->heure_fin}.";
        }

        // 2. Conflit Enseignant (L'enseignant ne peut pas être à deux endroits à la fois)
        if ($enseignantId) {
            $conflitEnseignant = (clone $baseQuery)
                ->whereHas('attribution', function ($q) use ($enseignantId) {
                    $q->where('personnel_id', $enseignantId);
                })
                ->with(['classe', 'attribution.matiere'])
                ->first();

            if ($conflitEnseignant) {
                $profNom = $attribution?->personnel?->nom ?? 'L\'enseignant';
                $classeNom = $conflitEnseignant->classe?->nom ?? 'une autre classe';
                $errors['attribution_id'] = "Conflit Enseignant : {$profNom} dispense déjà un cours de {$conflitEnseignant->attribution?->matiere?->nom} en {$classeNom} sur ce même créneau ({$conflitEnseignant->heure_debut} - {$conflitEnseignant->heure_fin}).";
            }
        }

        // 3. Conflit Salle (La salle physique ne peut pas héberger deux cours simultanés)
        if ($salleId) {
            $conflitSalle = (clone $baseQuery)
                ->where('salle_id', $salleId)
                ->with(['classe', 'salle'])
                ->first();

            if ($conflitSalle) {
                $salleNom = $conflitSalle->salle?->nom ?? 'Cette salle';
                $classeNom = $conflitSalle->classe?->nom ?? 'une autre classe';
                $errors['salle_id'] = "Conflit Salle : {$salleNom} est déjà réservée par la classe {$classeNom} de {$conflitSalle->heure_debut} à {$conflitSalle->heure_fin}.";
            }

            // 4. Contrôle de Capacité (Alerte si la classe dépasse la capacité de la salle)
            $salle = Salle::find($salleId);
            $classe = Classe::withCount(['inscriptions' => fn ($q) => $q->where('annee_scolaire_id', $anneeId)->where('statut', 'validee')])->find($classeId);

            if ($salle && $classe) {
                $effectif = $classe->inscriptions_count ?: ($classe->capacite_max ?? 0);
                if ($effectif > $salle->capacite_places) {
                    $warnings['capacite'] = "Alerte Capacité : la classe compte {$effectif} élèves alors que la salle {$salle->nom} ne dispose que de {$salle->capacite_places} places assises.";
                }
            }
        }

        return [
            'has_conflict' => count($errors) > 0,
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    /**
     * Planifie et enregistre une séance d'emploi du temps avec validation anti-conflit stricte.
     *
     * @param array{
     *     annee_scolaire_id: int,
     *     classe_id: int,
     *     attribution_id: int,
     *     salle_id?: int|null,
     *     jour_semaine: string,
     *     heure_debut: string,
     *     heure_fin: string,
     * } $data
     * @return SeanceEmploiTemps
     * @throws ValidationException
     */
    public function planifierSeance(array $data): SeanceEmploiTemps
    {
        $conflits = $this->detecterConflits($data);

        if ($conflits['has_conflict']) {
            throw ValidationException::withMessages($conflits['errors']);
        }

        return SeanceEmploiTemps::create([
            'annee_scolaire_id' => $data['annee_scolaire_id'],
            'classe_id' => $data['classe_id'],
            'attribution_id' => $data['attribution_id'],
            'salle_id' => $data['salle_id'] ?? null,
            'jour_semaine' => $data['jour_semaine'],
            'heure_debut' => $data['heure_debut'],
            'heure_fin' => $data['heure_fin'],
            'statut' => 'actif',
        ]);
    }

    /**
     * Met à jour une séance existante avec contrôle anti-conflits.
     *
     * @param SeanceEmploiTemps $seance
     * @param array<string, mixed> $data
     * @return SeanceEmploiTemps
     * @throws ValidationException
     */
    public function modifierSeance(SeanceEmploiTemps $seance, array $data): SeanceEmploiTemps
    {
        $mergedData = array_merge([
            'annee_scolaire_id' => $seance->annee_scolaire_id,
            'classe_id' => $seance->classe_id,
            'attribution_id' => $seance->attribution_id,
            'salle_id' => $seance->salle_id,
            'jour_semaine' => $seance->jour_semaine,
            'heure_debut' => $seance->heure_debut,
            'heure_fin' => $seance->heure_fin,
        ], $data);

        $conflits = $this->detecterConflits($mergedData, $seance->id);

        if ($conflits['has_conflict']) {
            throw ValidationException::withMessages($conflits['errors']);
        }

        $seance->update($mergedData);

        return $seance;
    }

    /**
     * Annule une séance d'emploi du temps pour cause d'absence ou d'événement exceptionnel (Gesco 13.4).
     *
     * @param SeanceEmploiTemps $seance
     * @param string $motif
     * @return SeanceEmploiTemps
     */
    public function annulerSeance(SeanceEmploiTemps $seance, string $motif): SeanceEmploiTemps
    {
        $seance->update([
            'statut' => 'annule',
            'motif_annulation' => $motif,
            'remplace_par_personnel_id' => null,
        ]);

        return $seance;
    }

    /**
     * Affecte un enseignant remplaçant disponible sur une séance (Gesco 13.4).
     *
     * @param SeanceEmploiTemps $seance
     * @param int $remplacantPersonnelId
     * @param string|null $motif
     * @return SeanceEmploiTemps
     * @throws ValidationException
     */
    public function affecterRemplacant(SeanceEmploiTemps $seance, int $remplacantPersonnelId, ?string $motif = null): SeanceEmploiTemps
    {
        // Vérifier que le remplaçant n'a pas lui-même un cours sur ce créneau
        $conflitRemplacant = SeanceEmploiTemps::query()
            ->where('annee_scolaire_id', $seance->annee_scolaire_id)
            ->where('jour_semaine', $seance->jour_semaine)
            ->where('statut', '!=', 'annule')
            ->where('id', '!=', $seance->id)
            ->where(function ($q) use ($seance) {
                $q->where('heure_debut', '<', $seance->heure_fin)
                  ->where('heure_fin', '>', $seance->heure_debut);
            })
            ->where(function ($q) use ($remplacantPersonnelId) {
                $q->whereHas('attribution', fn ($sq) => $sq->where('personnel_id', $remplacantPersonnelId))
                  ->orWhere('remplace_par_personnel_id', $remplacantPersonnelId);
            })
            ->first();

        if ($conflitRemplacant) {
            $remplacant = Personnel::find($remplacantPersonnelId);
            $nom = $remplacant?->nom ?? 'L\'enseignant remplaçant';
            throw ValidationException::withMessages([
                'remplace_par_personnel_id' => "{$nom} n'est pas disponible sur ce créneau (il a déjà un cours de {$conflitRemplacant->heure_debut} à {$conflitRemplacant->heure_fin}).",
            ]);
        }

        $seance->update([
            'statut' => 'remplace',
            'remplace_par_personnel_id' => $remplacantPersonnelId,
            'motif_annulation' => $motif,
        ]);

        return $seance;
    }

    /**
     * Rétablit le statut actif normal d'une séance.
     *
     * @param SeanceEmploiTemps $seance
     * @return SeanceEmploiTemps
     */
    public function retablirSeance(SeanceEmploiTemps $seance): SeanceEmploiTemps
    {
        $seance->update([
            'statut' => 'actif',
            'remplace_par_personnel_id' => null,
            'motif_annulation' => null,
        ]);

        return $seance;
    }

    /**
     * Calcule le volume hebdomadaire réel d'heures enseignées par classe ou par enseignant (Gesco 13.5).
     *
     * @param int $anneeId
     * @param int|null $personnelId
     * @return array{heures_regulieres: float, heures_remplacement: float, heures_annulees: float, total_heures_effectives: float}
     */
    public function calculerDecompteHeuresEnseignant(int $anneeId, int $personnelId): array
    {
        $seancesTitulaires = SeanceEmploiTemps::query()
            ->where('annee_scolaire_id', $anneeId)
            ->whereHas('attribution', fn ($q) => $q->where('personnel_id', $personnelId))
            ->get();

        $heuresRegulieres = 0.0;
        $heuresAnnulees = 0.0;

        foreach ($seancesTitulaires as $s) {
            if ($s->statut === 'annule' || $s->statut === 'remplace') {
                $heuresAnnulees += $s->duree_heures;
            } else {
                $heuresRegulieres += $s->duree_heures;
            }
        }

        // Heures effectuées comme remplaçant
        $seancesRemplacant = SeanceEmploiTemps::query()
            ->where('annee_scolaire_id', $anneeId)
            ->where('remplace_par_personnel_id', $personnelId)
            ->where('statut', 'remplace')
            ->get();

        $heuresRemplacement = 0.0;
        foreach ($seancesRemplacant as $s) {
            $heuresRemplacement += $s->duree_heures;
        }

        return [
            'heures_regulieres' => round($heuresRegulieres, 2),
            'heures_remplacement' => round($heuresRemplacement, 2),
            'heures_annulees' => round($heuresAnnulees, 2),
            'total_heures_effectives' => round($heuresRegulieres + $heuresRemplacement, 2),
        ];
    }
}
