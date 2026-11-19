<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Migration de consolidation sans perte des niveaux pédagogiques du Primaire (ESP-PRI).
 *
 * Rectifie la structure initiale à 12 niveaux (1 niveau par classe : SIL..CM2 et Class 1..6)
 * vers les 6 niveaux officiels du complexe ESPOIR (MINEDUB) :
 * - Primaire Francophone : Niveau 1 (`SIL`, `CP`), Niveau 2 (`CE1`, `CE2`), Niveau 3 (`CM1`, `CM2`).
 * - Primaire Anglophone  : Level 1 (`CLASS 1`, `CLASS 2`), Level 2 (`CLASS 3`, `CLASS 4`), Level 3 (`CLASS 5`, `CLASS 6`).
 *
 * Garantit l'idempotence totale pour le déploiement sur serveur client sans écraser les encaissements,
 * ventilations, remises, dispenses, souscriptions optionnelles, coefficients ou campagnes SMS saisis
 * entre-temps.
 */
return new class extends Migration
{
    /**
     * Exécute la migration de regroupement transactionnelle et idempotente.
     *
     * @return void
     */
    public function up(): void
    {
        if (! Schema::hasTable('etablissements') || ! Schema::hasTable('niveaux') || ! Schema::hasTable('classes')) {
            return;
        }

        $primaire = DB::table('etablissements')->where('code', 'ESP-PRI')->first();
        if (! $primaire) {
            return;
        }

        DB::transaction(function () use ($primaire): void {
            $etabId = (int) $primaire->id;
            $frCycleId = $this->resoudreCycleId($etabId, 'CYC-PRI-FR', 'FR');
            $enCycleId = $this->resoudreCycleId($etabId, 'CYC-PRI-EN', 'EN');

            $groupes = [];

            if ($frCycleId !== null) {
                array_push(
                    $groupes,
                    [
                        'cycle_id' => $frCycleId,
                        'target_code' => 'NIV-1',
                        'target_nom' => 'Niveau 1',
                        'target_nom_en' => 'Level 1',
                        'ordre' => 1,
                        'old_codes' => ['SIL', 'CP'],
                        'old_names' => ['sil', 'cp'],
                    ],
                    [
                        'cycle_id' => $frCycleId,
                        'target_code' => 'NIV-2',
                        'target_nom' => 'Niveau 2',
                        'target_nom_en' => 'Level 2',
                        'ordre' => 2,
                        'old_codes' => ['CE1', 'CE2'],
                        'old_names' => ['ce1', 'ce2'],
                    ],
                    [
                        'cycle_id' => $frCycleId,
                        'target_code' => 'NIV-3',
                        'target_nom' => 'Niveau 3',
                        'target_nom_en' => 'Level 3',
                        'ordre' => 3,
                        'old_codes' => ['CM1', 'CM2'],
                        'old_names' => ['cm1', 'cm2'],
                    ]
                );
            }

            if ($enCycleId !== null) {
                array_push(
                    $groupes,
                    [
                        'cycle_id' => $enCycleId,
                        'target_code' => 'LVL-1',
                        'target_nom' => 'Level 1',
                        'target_nom_en' => 'Level 1',
                        'ordre' => 1,
                        'old_codes' => ['CLASS-1', 'CLASS-2'],
                        'old_names' => ['class 1', 'class 2'],
                    ],
                    [
                        'cycle_id' => $enCycleId,
                        'target_code' => 'LVL-2',
                        'target_nom' => 'Level 2',
                        'target_nom_en' => 'Level 2',
                        'ordre' => 2,
                        'old_codes' => ['CLASS-3', 'CLASS-4'],
                        'old_names' => ['class 3', 'class 4'],
                    ],
                    [
                        'cycle_id' => $enCycleId,
                        'target_code' => 'LVL-3',
                        'target_nom' => 'Level 3',
                        'target_nom_en' => 'Level 3',
                        'ordre' => 3,
                        'old_codes' => ['CLASS-5', 'CLASS-6'],
                        'old_names' => ['class 5', 'class 6'],
                    ]
                );
            }

            foreach ($groupes as $groupe) {
                $this->fusionnerGroupePaliers($etabId, $groupe);
            }
        });
    }

    /**
     * Annule la migration (no-op volontaire pour préserver l'intégrité des données fusionnées).
     *
     * @return void
     */
    public function down(): void
    {
        // Aucune scission destructive : la fusion regroupe les paliers officiels sans perte comptable.
    }

    /**
     * Résout l'identifiant du cycle Primaire par son code ou, en repli, par le code de sa section.
     *
     * @param  int  $etabId  Identifiant de l'établissement `ESP-PRI`
     * @param  string  $cycleCode  Code du cycle cible (`CYC-PRI-FR` ou `CYC-PRI-EN`)
     * @param  string  $sectionCode  Code de la section (`FR` ou `EN`)
     * @return int|null Identifiant du cycle ou `null` s'il n'existe pas encore
     */
    private function resoudreCycleId(int $etabId, string $cycleCode, string $sectionCode): ?int
    {
        $cycle = DB::table('cycles')
            ->where('etablissement_id', $etabId)
            ->where('code', $cycleCode)
            ->first(['id']);

        if ($cycle) {
            return (int) $cycle->id;
        }

        if (! Schema::hasTable('sections')) {
            return null;
        }

        $section = DB::table('sections')
            ->where('etablissement_id', $etabId)
            ->where('code', $sectionCode)
            ->first(['id']);

        if (! $section) {
            return null;
        }

        $fallbackCycle = DB::table('cycles')
            ->where('section_id', $section->id)
            ->orderBy('ordre')
            ->first(['id']);

        return $fallbackCycle ? (int) $fallbackCycle->id : null;
    }

    /**
     * Fusionne les niveaux candidats d'un palier vers le niveau conservé et met à jour ses métadonnées.
     *
     * @param  int  $etabId  Identifiant de l'établissement `ESP-PRI`
     * @param  array{
     *     cycle_id: int,
     *     target_code: string,
     *     target_nom: string,
     *     target_nom_en: string,
     *     ordre: int,
     *     old_codes: list<string>,
     *     old_names: list<string>
     * }  $groupe  Définition du palier cible et des codes/noms sources à fusionner
     * @return void
     */
    private function fusionnerGroupePaliers(int $etabId, array $groupe): void
    {
        $niveauxCycle = DB::table('niveaux')
            ->where('cycle_id', $groupe['cycle_id'])
            ->orderBy('id')
            ->get();

        $targetNomLower = mb_strtolower($groupe['target_nom'], 'UTF-8');

        $candidats = $niveauxCycle->filter(function (object $n) use ($groupe, $targetNomLower): bool {
            $code = strtoupper(trim((string) $n->code));
            $nom = mb_strtolower(trim((string) $n->nom), 'UTF-8');

            return $code === $groupe['target_code']
                || in_array($code, $groupe['old_codes'], true)
                || $nom === $targetNomLower
                || in_array($nom, $groupe['old_names'], true);
        })->values();

        if ($candidats->isEmpty()) {
            return;
        }

        // Prioriser un niveau déjà migré, sinon le 1er palier de la paire (ex: SIL, CE1, CM1, CLASS-1)
        // afin de préserver les identifiants de `frais_scolaires` déjà référencés par des encaissements.
        $targetRow = $candidats->first(fn (object $n): bool => strtoupper(trim((string) $n->code)) === $groupe['target_code'])
            ?? $candidats->first(fn (object $n): bool => strtoupper(trim((string) $n->code)) === $groupe['old_codes'][0])
            ?? $candidats->first();

        $targetId = (int) $targetRow->id;

        foreach ($candidats as $row) {
            if ((int) $row->id === $targetId) {
                continue;
            }

            $sourceId = (int) $row->id;
            $this->reattacherDependancesNiveau($sourceId, $targetId);
            DB::table('niveaux')->where('id', $sourceId)->delete();
        }

        // Sécuriser d'éventuelles collisions d'unicité `(etablissement_id, nom)` résiduelles hors cycle
        $collisionExterne = DB::table('niveaux')
            ->where('etablissement_id', $etabId)
            ->where('nom', $groupe['target_nom'])
            ->where('id', '!=', $targetId)
            ->exists();

        if ($collisionExterne) {
            // Cas anormal uniquement si un autre cycle portait déjà exactement ce libellé
            DB::table('niveaux')->where('id', $targetId)->update([
                'code' => $groupe['target_code'],
                'nom_en' => $groupe['target_nom_en'],
                'ordre' => $groupe['ordre'],
                'updated_at' => now(),
            ]);

            return;
        }

        DB::table('niveaux')->where('id', $targetId)->update([
            'etablissement_id' => $etabId,
            'code' => $groupe['target_code'],
            'nom' => $groupe['target_nom'],
            'nom_en' => $groupe['target_nom_en'],
            'ordre' => $groupe['ordre'],
            'updated_at' => now(),
        ]);
    }

    /**
     * Transfère toutes les relations dépendantes d'un niveau absorbé vers le niveau cible.
     *
     * @param  int  $sourceNiveauId  Identifiant du niveau absorbé (à supprimer ensuite)
     * @param  int  $targetNiveauId  Identifiant du niveau conservé
     * @return void
     */
    private function reattacherDependancesNiveau(int $sourceNiveauId, int $targetNiveauId): void
    {
        $this->reattacherClasses($sourceNiveauId, $targetNiveauId);
        $this->fusionnerFraisScolaires($sourceNiveauId, $targetNiveauId);
        $this->fusionnerMatiereNiveaux($sourceNiveauId, $targetNiveauId);

        if (Schema::hasTable('bourses_remises')) {
            DB::table('bourses_remises')
                ->where('niveau_id', $sourceNiveauId)
                ->update(['niveau_id' => $targetNiveauId, 'updated_at' => now()]);
        }

        if (Schema::hasTable('sms_campagnes')) {
            DB::table('sms_campagnes')
                ->where('cible_type', 'niveau')
                ->where('cible_id', $sourceNiveauId)
                ->update(['cible_id' => $targetNiveauId, 'updated_at' => now()]);
        }
    }

    /**
     * Réattribue les classes du niveau source vers le niveau cible en évitant tout conflit `(niveau_id, code)`.
     *
     * @param  int  $sourceNiveauId  Identifiant du niveau absorbé
     * @param  int  $targetNiveauId  Identifiant du niveau conservé
     * @return void
     */
    private function reattacherClasses(int $sourceNiveauId, int $targetNiveauId): void
    {
        $classes = DB::table('classes')
            ->where('niveau_id', $sourceNiveauId)
            ->orderBy('id')
            ->get();

        foreach ($classes as $classe) {
            $code = (string) $classe->code;
            $candidateCode = $code;
            $suffix = 2;

            while (
                DB::table('classes')
                    ->where('niveau_id', $targetNiveauId)
                    ->where('code', $candidateCode)
                    ->exists()
            ) {
                $candidateCode = mb_substr($code, 0, 26).'-'.$suffix;
                $suffix++;
            }

            DB::table('classes')
                ->where('id', $classe->id)
                ->update([
                    'niveau_id' => $targetNiveauId,
                    'code' => $candidateCode,
                    'updated_at' => now(),
                ]);
        }
    }

    /**
     * Fusionne les grilles `frais_scolaires` et remappe les ventilations, bourses et dispenses
     * pointant vers l'ancien niveau avant suppression des doublons tarifaires.
     *
     * @param  int  $sourceNiveauId  Identifiant du niveau absorbé
     * @param  int  $targetNiveauId  Identifiant du niveau conservé
     * @return void
     */
    private function fusionnerFraisScolaires(int $sourceNiveauId, int $targetNiveauId): void
    {
        if (! Schema::hasTable('frais_scolaires')) {
            return;
        }

        $sourceFraisList = DB::table('frais_scolaires')
            ->where('niveau_id', $sourceNiveauId)
            ->orderBy('id')
            ->get();

        foreach ($sourceFraisList as $srcFrais) {
            $destQuery = DB::table('frais_scolaires')
                ->where('annee_scolaire_id', $srcFrais->annee_scolaire_id)
                ->where('etablissement_id', $srcFrais->etablissement_id)
                ->where('niveau_id', $targetNiveauId)
                ->where('tranche_numero', $srcFrais->tranche_numero);

            if ($srcFrais->type_frais_id !== null) {
                $destQuery->where('type_frais_id', $srcFrais->type_frais_id);
            } else {
                $destQuery->whereNull('type_frais_id');
            }

            if ($srcFrais->serie !== null) {
                $destQuery->where('serie', $srcFrais->serie);
            } else {
                $destQuery->whereNull('serie');
            }

            $destFrais = $destQuery->first();

            // Si ce frais n'existe pas encore sur le niveau cible (ex: frais d'examen), on le conserve intact
            if (! $destFrais) {
                DB::table('frais_scolaires')
                    ->where('id', $srcFrais->id)
                    ->update(['niveau_id' => $targetNiveauId, 'updated_at' => now()]);

                continue;
            }

            $srcId = (int) $srcFrais->id;
            $destId = (int) $destFrais->id;

            if (Schema::hasTable('ventilation_versements')) {
                DB::table('ventilation_versements')
                    ->where('frais_scolaire_id', $srcId)
                    ->update(['frais_scolaire_id' => $destId, 'updated_at' => now()]);
            }

            if (Schema::hasTable('eleve_bourses_remises')) {
                DB::table('eleve_bourses_remises')
                    ->where('frais_scolaire_id', $srcId)
                    ->update(['frais_scolaire_id' => $destId, 'updated_at' => now()]);
            }

            $this->remapperTableUniqueParCompteFrais('dispenses_financieres', $srcId, $destId);
            $this->remapperTableUniqueParCompteFrais('souscriptions_frais_optionnels', $srcId, $destId);

            DB::table('frais_scolaires')->where('id', $srcId)->delete();
        }
    }

    /**
     * Remappe `frais_scolaire_id` sur une table soumise à l'unicité `(compte_financier_id, frais_scolaire_id)`.
     *
     * @param  string  $table  Nom de la table (`dispenses_financieres` ou `souscriptions_frais_optionnels`)
     * @param  int  $srcFraisId  Identifiant de la ligne `frais_scolaires` absorbée
     * @param  int  $destFraisId  Identifiant de la ligne `frais_scolaires` cible
     * @return void
     */
    private function remapperTableUniqueParCompteFrais(string $table, int $srcFraisId, int $destFraisId): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        $rows = DB::table($table)->where('frais_scolaire_id', $srcFraisId)->get();

        foreach ($rows as $row) {
            $alreadyExists = DB::table($table)
                ->where('compte_financier_id', $row->compte_financier_id)
                ->where('frais_scolaire_id', $destFraisId)
                ->exists();

            if ($alreadyExists) {
                DB::table($table)->where('id', $row->id)->delete();
            } else {
                DB::table($table)
                    ->where('id', $row->id)
                    ->update(['frais_scolaire_id' => $destFraisId, 'updated_at' => now()]);
            }
        }
    }

    /**
     * Réattribue les associations `matiere_niveaux` en respectant l'unicité `(niveau_id, matiere_id, serie)`.
     *
     * @param  int  $sourceNiveauId  Identifiant du niveau absorbé
     * @param  int  $targetNiveauId  Identifiant du niveau conservé
     * @return void
     */
    private function fusionnerMatiereNiveaux(int $sourceNiveauId, int $targetNiveauId): void
    {
        if (! Schema::hasTable('matiere_niveaux')) {
            return;
        }

        $rows = DB::table('matiere_niveaux')->where('niveau_id', $sourceNiveauId)->get();

        foreach ($rows as $row) {
            $dupQuery = DB::table('matiere_niveaux')
                ->where('niveau_id', $targetNiveauId)
                ->where('matiere_id', $row->matiere_id);

            if ($row->serie !== null) {
                $dupQuery->where('serie', $row->serie);
            } else {
                $dupQuery->whereNull('serie');
            }

            if ($dupQuery->exists()) {
                DB::table('matiere_niveaux')->where('id', $row->id)->delete();
            } else {
                DB::table('matiere_niveaux')
                    ->where('id', $row->id)
                    ->update(['niveau_id' => $targetNiveauId, 'updated_at' => now()]);
            }
        }
    }
};
