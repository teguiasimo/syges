<?php

namespace App\Console\Commands;

use App\Services\SystemUpdateService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;

/**
 * Commande Artisan d'assistance à la publication de versions SemVer validées (`vX.Y.Z`) côté développeur.
 *
 * Permet de calculer automatiquement le prochain tag SemVer selon le type de changement :
 * - `major` : version majeure (`v(X+1).0.0`) pour les refontes et fonctionnalités lourdes,
 * - `minor` : version mineure (`vX.(Y+1).0`) pour les nouvelles fonctionnalités,
 * - `patch` : correctif (`vX.Y.(Z+1)`) pour les corrections de bugs.
 */
class SygesReleaseCommand extends Command
{
    /**
     * Signature CLI de la commande Artisan.
     *
     * @var string
     */
    protected $signature = 'syges:release
                            {type=patch : Type de version SemVer à générer (major, minor ou patch)}
                            {--m|message= : Note de version descriptive attachée au tag annoté}
                            {--dry-run : Simule le calcul du prochain tag sans modifier Git}';

    /**
     * Description affichée dans `php artisan list`.
     *
     * @var string
     */
    protected $description = 'Calcule et crée un tag Git SemVer annoté officiel (vX.Y.Z) pour le gestionnaire de mises à jour SYGES.';

    /**
     * Exécute le calcul SemVer et génère optionnellement le tag Git annoté.
     *
     * @param  SystemUpdateService  $updateService  Service de gestion des versions.
     * @return int Code de sortie CLI (`SUCCESS` ou `FAILURE`).
     */
    public function handle(SystemUpdateService $updateService): int
    {
        $type = strtolower(trim((string) $this->argument('type')));

        if (! in_array($type, ['major', 'minor', 'patch'], true)) {
            $this->error("Type de version invalide « {$type} ». Valeurs autorisées : major, minor, patch.");

            return self::FAILURE;
        }

        // Déterminer la dernière version SemVer existante sur le dépôt
        $catalog = $updateService->getTagsCatalog();
        $baseNumber = $catalog[0]['version_number']
            ?? ($updateService->normalizeSemver((string) config('app.version', '1.0.0')) ?? '1.0.0');

        // Si aucun tag Git n'existe encore et qu'on demande une initialisation, proposer la création
        $hasExistingTags = count($catalog) > 0;
        [$major, $minor, $patch] = array_map('intval', explode('.', $baseNumber));

        if ($hasExistingTags) {
            match ($type) {
                'major' => [$major, $minor, $patch] = [$major + 1, 0, 0],
                'minor' => [$major, $minor, $patch] = [$major, $minor + 1, 0],
                default => [$major, $minor, $patch] = [$major, $minor, $patch + 1],
            };
        }

        $nextVersionNumber = "{$major}.{$minor}.{$patch}";
        $nextTag = "v{$nextVersionNumber}";
        $typeLabel = $updateService->getReleaseTypeLabel($type);

        $defaultNote = match ($type) {
            'major' => "Version majeure {$nextTag} — Évolutions structurelles SYGES",
            'minor' => "Version mineure {$nextTag} — Nouvelles fonctionnalités SYGES",
            default => "Correctif {$nextTag} — Résolution d'anomalies et optimisations SYGES",
        };

        $releaseMessage = trim((string) ($this->option('message') ?: $defaultNote));

        $this->info('=== Gestionnaire de Publication SemVer SYGES ===');
        $this->line('Version précédente détectée : '.($hasExistingTags ? "v{$baseNumber}" : 'Aucun tag (initialisation)'));
        $this->line("Catégorie sélectionnée      : {$typeLabel}");
        $this->line("Nouveau tag validé calculé  : {$nextTag}");
        $this->line("Note de version             : {$releaseMessage}");

        if ((bool) $this->option('dry-run')) {
            $this->comment('[Mode simulation --dry-run] Aucun tag Git créé.');
            $this->line("Commandes recommandées avant publication :\n  1. npm run build\n  2. git add .\n  3. git commit -m \"release: {$nextTag} - {$releaseMessage}\"\n  4. git tag -a {$nextTag} -m \"{$releaseMessage}\"\n  5. git push origin main --tags");

            return self::SUCCESS;
        }

        // Vérifier que public/build/manifest.json est bien présent avant de taguer une release client
        if (! file_exists(public_path('build/manifest.json'))) {
            $this->warn('Attention : public/build/manifest.json est introuvable. Exécutez d\'abord `npm run build` avant de créer un tag client !');

            return self::FAILURE;
        }

        $tagResult = Process::path(base_path())->run(['git', 'tag', '-a', $nextTag, '-m', $releaseMessage]);

        if (! $tagResult->successful()) {
            $this->error('Échec de la création du tag Git : '.trim($tagResult->errorOutput() ?: $tagResult->output()));

            return self::FAILURE;
        }

        $this->info("Tag officiel {$nextTag} créé avec succès !");
        $this->comment("Pour publier cette version auprès du serveur client, exécutez :\n  git push origin main --tags");

        return self::SUCCESS;
    }
}
