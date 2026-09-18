<?php

namespace Tests\Feature\Administration;

use App\Models\Etablissement;
use App\Models\Institution;
use App\Models\Personnel;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Tests de fonctionnalités pour le basculement de campus en session (SaaS Multi-Tenant).
 *
 * Vérifie :
 * - La capacité du Super Admin à basculer son campus de travail vers n'importe quel établissement.
 * - Le stockage sécurisé du campus actif en session ('current_etablissement_id').
 * - L'interdiction pour un utilisateur local de basculer vers un établissement auquel il n'appartient pas.
 * - Le rejet des identifiants d'établissement invalides ou inexistants.
 */
class EtablissementSwitchTest extends TestCase
{
    use \Illuminate\Foundation\Testing\RefreshDatabase;

    public ?Institution $institution = null;
    public ?Etablissement $etablissementA = null;
    public ?Etablissement $etablissementB = null;
    public ?User $superAdmin = null;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);

        $this->institution = Institution::firstOrCreate(
            ['code' => 'INST-SWITCH'],
            ['nom' => 'Groupe Scolaire Central', 'sigle' => 'GSC']
        );

        $this->etablissementA = Etablissement::create([
            'institution_id' => $this->institution->id,
            'nom' => 'Campus Primaire',
            'code' => 'CAMP-A',
            'statut_juridique' => 'Prive_Laic',
            'ordre_enseignement' => 'Primaire',
        ]);

        $this->etablissementB = Etablissement::create([
            'institution_id' => $this->institution->id,
            'nom' => 'Campus Secondaire',
            'code' => 'CAMP-B',
            'statut_juridique' => 'Prive_Laic',
            'ordre_enseignement' => 'Secondaire_General',
        ]);

        $this->superAdmin = User::factory()->create(['is_active' => true]);
        $this->superAdmin->assignRole('super-admin');
    }

    /**
     * Vérifie que le Super Admin peut basculer librement vers un autre campus.
     */
    public function test_super_admin_can_switch_active_etablissement(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->from('/dashboard')
            ->post(route('context.switch-etablissement'), [
                'etablissement_id' => $this->etablissementB->id,
            ]);

        $response->assertRedirect('/dashboard');
        $response->assertSessionHas('current_etablissement_id', $this->etablissementB->id);
        $response->assertSessionHas('success');
    }

    /**
     * Vérifie que la tentative de basculement vers un établissement inexistant échoue.
     */
    public function test_cannot_switch_to_non_existent_etablissement(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->from('/dashboard')
            ->post(route('context.switch-etablissement'), [
                'etablissement_id' => 999999,
            ]);

        $response->assertSessionHasErrors('etablissement_id');
    }

    /**
     * Vérifie qu'un personnel local ne peut pas basculer vers un établissement tiers.
     */
    public function test_campus_staff_cannot_switch_to_another_etablissement(): void
    {
        $staffUser = User::factory()->create(['is_active' => true]);
        Personnel::create([
            'user_id' => $staffUser->id,
            'etablissement_id' => $this->etablissementA->id,
            'nom' => 'DUPONT',
            'prenom' => 'Jean',
            'matricule' => 'STF-001',
            'telephone' => '+237 600 00 00 00',
            'statut' => 'Actif',
        ]);

        $response = $this->actingAs($staffUser)
            ->from('/dashboard')
            ->post(route('context.switch-etablissement'), [
                'etablissement_id' => $this->etablissementB->id,
            ]);

        $response->assertForbidden();
    }
}
