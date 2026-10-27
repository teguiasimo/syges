<?php

namespace Tests\Unit;

use App\Models\Etablissement;
use PHPUnit\Framework\TestCase;

/**
 * Test unitaire validant la détection et la personnalisation des titres officiels du chef d'établissement.
 */
class EtablissementTitreChefTest extends TestCase
{
    /**
     * Teste que les CES (Collège d'Enseignement Secondaire) et CETIC reçoivent automatiquement le titre 'Le Directeur'.
     */
    public function test_ces_et_cetic_sont_automatiquement_diriges_par_un_directeur(): void
    {
        $cesMegueu = new Etablissement(['nom' => "COLLÈGE D'ENSEIGNEMENT SECONDAIRE DE MEGUEU"]);
        $this->assertEquals('Le Directeur', $cesMegueu->titre_chef);
        $this->assertEquals('The Director', $cesMegueu->titre_chef_en);

        $cesShort = new Etablissement(['nom' => "CES DE BAFOUSSA"]);
        $this->assertEquals('Le Directeur', $cesShort->titre_chef);

        $cetic = new Etablissement(['nom' => "CETIC DE NGAOUNDÉRÉ"]);
        $this->assertEquals('Le Directeur', $cetic->titre_chef);
    }

    /**
     * Teste que les Lycées reçoivent automatiquement le titre 'Le Proviseur'.
     */
    public function test_lycees_sont_automatiquement_diriges_par_un_proviseur(): void
    {
        $lycee = new Etablissement(['nom' => 'LYCÉE CLASSIQUE DE FOUMBAN']);
        $this->assertEquals('Le Proviseur', $lycee->titre_chef);
        $this->assertEquals('The Principal', $lycee->titre_chef_en);
    }

    /**
     * Teste que les Collèges généraux ou privés reçoivent 'Le Principal'.
     */
    public function test_colleges_classiques_recoivent_le_principal(): void
    {
        $college = new Etablissement(['nom' => 'COLLÈGE CATHOLIQUE SAINT JOSEPH']);
        $this->assertEquals('Le Principal', $college->titre_chef);
        $this->assertEquals('The Principal', $college->titre_chef_en);
    }

    /**
     * Teste que la valeur personnalisée configurée en base de données prime sur la détection automatique.
     */
    public function test_valeur_personnalisee_est_prioritaire(): void
    {
        $etab = new Etablissement([
            'nom' => 'LYCÉE POLYVALENT',
            'titre_chef' => 'Le Directeur Général',
            'titre_chef_en' => 'The Managing Director',
        ]);

        $this->assertEquals('Le Directeur Général', $etab->titre_chef);
        $this->assertEquals('The Managing Director', $etab->titre_chef_en);
    }
}
