<?php

use App\Models\Etablissement;
use App\Models\Personnel;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Migration d'alignement et de sécurisation multi-tenant des comptes utilisateurs SYGES.
 *
 * Résout la faille de consistance où un compte utilisateur créé simultanément
 * lors de la saisie d'un membre du personnel pouvait conserver `etablissement_id = null`.
 * Aligne l'ensemble des comptes utilisateurs non super-administrateurs avec l'établissement
 * de leur fiche administrative de personnel.
 */
return new class extends Migration
{
    /**
     * Exécute les migrations pour synchroniser et corriger les identifiants d'établissement orphelins.
     *
     * @return void
     */
    public function up(): void
    {
        // 1. Synchronisation directe des utilisateurs liés à une fiche Personnel mais ayant etablissement_id = null
        // Approche compatible tous SGBD (MySQL / MariaDB / SQLite)
        $personnelsWithUsers = DB::table('personnels')
            ->whereNotNull('user_id')
            ->whereNotNull('etablissement_id')
            ->select('user_id', 'etablissement_id')
            ->get();

        foreach ($personnelsWithUsers as $personnel) {
            DB::table('users')
                ->where('id', $personnel->user_id)
                ->whereNull('etablissement_id')
                ->update(['etablissement_id' => $personnel->etablissement_id]);
        }

        // 2. Traitement défensif pour tout utilisateur non super-administrateur restant sans établissement
        $orphanUsers = User::whereNull('etablissement_id')
            ->whereDoesntHave('roles', function ($query) {
                $query->where('name', 'super-admin');
            })
            ->where('login', '!=', 'superadmin')
            ->where('email', '!=', 'admin@gmail.com')
            ->get();

        if ($orphanUsers->isNotEmpty()) {
            $defaultEtablissementId = Etablissement::first()?->id;

            if ($defaultEtablissementId) {
                foreach ($orphanUsers as $orphan) {
                    // Si une fiche personnel existe par email ou matricule
                    $personnel = Personnel::where('user_id', $orphan->id)
                        ->orWhere('email', $orphan->email)
                        ->orWhere('matricule', $orphan->login)
                        ->first();

                    $orphan->etablissement_id = $personnel?->etablissement_id ?? $defaultEtablissementId;
                    $orphan->save();
                }
            }
        }
    }

    /**
     * Annule les modifications apportées par la migration.
     *
     * @return void
     */
    public function down(): void
    {
        // Les corrections d'intégrité de données de sécurité ne nécessitent pas de remise à null.
    }
};
