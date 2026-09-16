<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Migration ajoutant les colonnes 'login' (identifiant de connexion)
 * et 'is_active' (statut d'activation/suspension du compte) à la table des utilisateurs.
 */
return new class extends Migration
{
    /**
     * Exécute les modifications de la table users.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Identifiant technique unique de connexion (ex: admin, jdupont)
            $table->string('login')->nullable()->unique()->after('id');
            // Statut du compte (true: actif, false: bloqué/suspendu)
            $table->boolean('is_active')->default(true)->after('password');
        });

        // Attribution d'un login par défaut pour les comptes existants (ex: admin pour admin@gmail.com)
        DB::table('users')->where('email', 'admin@gmail.com')->whereNull('login')->update([
            'login' => 'admin',
            'is_active' => true,
        ]);
    }

    /**
     * Annule les modifications de la table users.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['login', 'is_active']);
        });
    }
};
