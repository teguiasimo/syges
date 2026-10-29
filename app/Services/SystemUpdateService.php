<?php

namespace App\Services;

use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Symfony\Component\Process\PhpExecutableFinder;

/**
 * Service professionnel de gestion des versions SemVer et de mise à jour distante SYGES via GitHub.
 *
 * Ce moteur garantit que :
 * 1. Seuls les tags Git SemVer validés (`vX.Y.Z`) poussés sur GitHub sont considérés comme des versions
 *    téléchargeables (les simples commits sur `main` sans tag validé sont strictement ignorés).
 * 2. Chaque version est catégorisée selon la norme SemVer :
 *    - `major` (Majeure : refontes et fonctionnalités lourdes du système),
 *    - `minor` (Mineure : nouvelles fonctionnalités rétrocompatibles),
 *    - `patch` (Correctif : correction de bugs et ajustements de stabilité).
 * 3. Le pipeline de mise à jour exécute dans l'ordre strict :
 *    Vérification Internet -> Sauvegarde SQL + Email développeur -> Mode maintenance (`down`) ->
 *    Récupération et bascule sur le tag validé -> Migrations (`migrate --force`) ->
 *    Purge et reconstruction des caches (`optimize:clear` + `optimize`) -> Réouverture (`up` garanti).
 */
class SystemUpdateService
{
    /**
     * Chemin racine du dépôt Git cible (par défaut `base_path()`).
     */
    protected string $repoPath;

    /**
     * Chemin du journal JSON historisant les mises à jour appliquées sur le serveur.
     */
    protected string $historyFile;

    /**
     * Initialise le service de mise à jour système.
     *
     * @param  DatabaseBackupService  $backupService  Service de sauvegarde SQL autonome.
     * @param  string|null  $repoPath  Chemin personnalisé du dépôt Git (utile pour les tests isolés).
     * @param  string|null  $historyFile  Chemin personnalisé du journal d'historique JSON.
     */
    public function __construct(
        protected DatabaseBackupService $backupService,
        ?string $repoPath = null,
        ?string $historyFile = null,
    ) {
        $this->repoPath = $repoPath ?? base_path();
        $this->historyFile = $historyFile ?? storage_path('app/update_history.json');
    }

    /**
     * Vérifie si le serveur dispose d'une connexion Internet active vers GitHub (port HTTPS/SSH).
     *
     * @param  string  $host  Hôte distant de référence (`github.com` par défaut).
     * @param  int  $port  Port TCP à sonder (`443` par défaut).
     * @param  float  $timeout  Délai maximal d'attente en secondes.
     * @return bool `true` si la connexion réseau aboutit, `false` sinon.
     */
    public function hasInternetConnection(string $host = 'github.com', int $port = 443, float $timeout = 4.0): bool
    {
        $errno = 0;
        $errstr = '';
        $socket = @fsockopen($host, $port, $errno, $errstr, $timeout);

        if (is_resource($socket)) {
            fclose($socket);

            return true;
        }

        return false;
    }

    /**
     * Vérifie si une étiquette Git correspond strictement à un tag SemVer officiel validé (`vX.Y.Z`).
     * Exclut volontairement les pré-versions (`-alpha`, `-beta`, `-rc`), les branches et les tags libres.
     *
     * @param  string  $tag  Nom du tag Git à contrôler (ex: `v1.2.0`).
     * @return bool `true` si le tag respecte strictement `vMAJOR.MINOR.PATCH`.
     */
    public function isValidSemverTag(string $tag): bool
    {
        return preg_match('/^v(\d+)\.(\d+)\.(\d+)$/', trim($tag)) === 1;
    }

    /**
     * Normalise une chaîne de version (`v1.2.3` ou `1.2.3`) au format numérique pur `1.2.3`
     * compatible avec la fonction native PHP `version_compare()`.
     *
     * @param  string|null  $version  Chaîne de version entrante.
     * @return string|null Version normalisée `X.Y.Z` ou `null` si invalide.
     */
    public function normalizeSemver(?string $version): ?string
    {
        if ($version === null) {
            return null;
        }

        $clean = ltrim(trim($version), 'vV');

        if (preg_match('/^(\d+)\.(\d+)\.(\d+)$/', $clean) !== 1) {
            return null;
        }

        return $clean;
    }

    /**
     * Détermine la catégorie SemVer (`major`, `minor` ou `patch`) entre deux versions,
     * ou intrinsèquement pour une version initiale si aucune version précédente n'est fournie.
     *
     * @param  string|null  $fromVersion  Version de départ (ex: `v1.0.0` ou `null`).
     * @param  string  $toVersion  Version cible (ex: `v1.1.0`).
     * @return 'major'|'minor'|'patch' Catégorie de la version.
     */
    public function classifyReleaseType(?string $fromVersion, string $toVersion): string
    {
        $normalizedTo = $this->normalizeSemver($toVersion) ?? '1.0.0';
        [$toMajor, $toMinor, $toPatch] = array_map('intval', explode('.', $normalizedTo));

        $normalizedFrom = $this->normalizeSemver($fromVersion);
        if ($normalizedFrom === null) {
            if ($toMinor === 0 && $toPatch === 0) {
                return 'major';
            }
            if ($toPatch === 0) {
                return 'minor';
            }

            return 'patch';
        }

        [$fromMajor, $fromMinor] = array_map('intval', explode('.', $normalizedFrom));

        if ($toMajor > $fromMajor) {
            return 'major';
        }

        if ($toMinor > $fromMinor) {
            return 'minor';
        }

        return 'patch';
    }

    /**
     * Retourne le libellé francophone lisible correspondant à un type de version SemVer.
     *
     * @param  string  $type  Code du type (`major`, `minor`, `patch`).
     * @return string Libellé descriptif en français.
     */
    public function getReleaseTypeLabel(string $type): string
    {
        return match ($type) {
            'major' => 'Majeure (Fonctionnalités lourdes)',
            'minor' => 'Mineure (Nouvelles fonctionnalités)',
            default => 'Patch (Correction de bugs)',
        };
    }

    /**
     * Récupère les informations détaillées de la version locale actuellement installée sur le serveur.
     *
     * @return array{
     *     version: string,
     *     version_number: string,
     *     is_tagged_release: bool,
     *     branch: string,
     *     commit_short: string,
     *     commit_full: string,
     *     commit_date: string,
     *     commit_time: string,
     *     commit_message: string,
     *     remote_url: string
     * }
     */
    public function getLocalVersionInfo(): array
    {
        $fallbackNumber = $this->normalizeSemver((string) config('app.version', '1.0.0')) ?? '1.0.0';
        $fallbackTag = 'v'.$fallbackNumber;

        // 1. Vérifier si HEAD pointe directement sur un tag SemVer validé
        $exactTagsResult = $this->runGit(['tag', '--points-at', 'HEAD']);
        $exactSemverTags = $this->extractSortedSemverTags(
            $exactTagsResult->successful() ? $exactTagsResult->output() : ''
        );

        $isTaggedRelease = count($exactSemverTags) > 0;
        $resolvedTag = $exactSemverTags[0] ?? null;

        // 2. Sinon, rechercher le tag SemVer validé le plus élevé dans l'ascendance de HEAD
        if ($resolvedTag === null) {
            $mergedTagsResult = $this->runGit(['tag', '--merged', 'HEAD']);
            $mergedSemverTags = $this->extractSortedSemverTags(
                $mergedTagsResult->successful() ? $mergedTagsResult->output() : ''
            );
            $resolvedTag = $mergedSemverTags[0] ?? $fallbackTag;
        }

        $versionNumber = $this->normalizeSemver($resolvedTag) ?? $fallbackNumber;

        // Métadonnées Git complémentaires (branche, commit, date, remote)
        $branchRes = $this->runGit(['rev-parse', '--abbrev-ref', 'HEAD']);
        $rawBranch = $branchRes->successful() ? trim($branchRes->output()) : 'main';
        $branch = ($rawBranch === 'HEAD' || $rawBranch === '') ? "Tag {$resolvedTag}" : $rawBranch;

        $shortRes = $this->runGit(['rev-parse', '--short', 'HEAD']);
        $commitShort = $shortRes->successful() ? trim($shortRes->output()) : 'inconnu';

        $fullRes = $this->runGit(['rev-parse', 'HEAD']);
        $commitFull = $fullRes->successful() ? trim($fullRes->output()) : '';

        $dateRes = $this->runGit(['log', '-1', '--format=%ci']);
        $commitDate = date('Y-m-d');
        $commitTime = date('H:i');
        if ($dateRes->successful() && trim($dateRes->output()) !== '') {
            $ts = strtotime(trim($dateRes->output()));
            if ($ts !== false) {
                $commitDate = date('Y-m-d', $ts);
                $commitTime = date('H:i', $ts);
            }
        }

        $msgRes = $this->runGit(['log', '-1', '--format=%s']);
        $commitMessage = $msgRes->successful() ? trim($msgRes->output()) : '';

        $remoteRes = $this->runGit(['remote', 'get-url', 'origin']);
        $remoteUrl = $remoteRes->successful() ? $this->sanitizeRemoteUrl(trim($remoteRes->output())) : '';

        return [
            'version' => 'v'.$versionNumber,
            'version_number' => $versionNumber,
            'is_tagged_release' => $isTaggedRelease,
            'branch' => $branch,
            'commit_short' => $commitShort,
            'commit_full' => $commitFull,
            'commit_date' => $commitDate,
            'commit_time' => $commitTime,
            'commit_message' => $commitMessage,
            'remote_url' => $remoteUrl,
        ];
    }

    /**
     * Construit le catalogue complet des versions (tags Git SemVer validés) connues du dépôt,
     * trié de la version la plus récente à la plus ancienne, avec qualification `major`/`minor`/`patch`
     * et statut par rapport à la version installée (`installed`, `available`, `older`).
     *
     * @param  string|null  $currentVersion  Version locale de référence (ex: `v1.0.0`).
     * @return array<int, array{
     *     tag: string,
     *     version_number: string,
     *     type: 'major'|'minor'|'patch',
     *     type_label: string,
     *     date: string,
     *     subject: string,
     *     body: string,
     *     commit_short: string,
     *     status: 'installed'|'available'|'older'
     * }>
     */
    public function getTagsCatalog(?string $currentVersion = null): array
    {
        $localNumber = $this->normalizeSemver($currentVersion)
            ?? $this->getLocalVersionInfo()['version_number'];

        $format = '%(refname:short)|||%(creatordate:short)|||%(contents:subject)|||%(objectname:short)';
        $result = $this->runGit(['for-each-ref', 'refs/tags', "--format={$format}"]);

        if (! $result->successful() || trim($result->output()) === '') {
            return [];
        }

        $rawEntries = [];
        $lines = preg_split('/\r\n|\r|\n/', trim($result->output())) ?: [];

        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }

            $parts = explode('|||', $line);
            $tag = trim($parts[0]);

            if (! $this->isValidSemverTag($tag)) {
                continue;
            }

            $versionNumber = $this->normalizeSemver($tag);
            if ($versionNumber === null) {
                continue;
            }

            $date = trim($parts[1] ?? '');
            $subject = trim($parts[2] ?? '');

            // Récupérer le hash du commit cible déréférencé (pour les tags annotés)
            $commitShort = trim($parts[3] ?? '');
            $derefRes = $this->runGit(['rev-list', '-n', '1', '--abbrev-commit', "refs/tags/{$tag}"]);
            if ($derefRes->successful() && trim($derefRes->output()) !== '') {
                $commitShort = trim($derefRes->output());
            }

            // Si le tag léger n'a pas d'annotation propre, lire le sujet du commit pointé
            if ($subject === '') {
                $commitMsgRes = $this->runGit(['log', '-1', '--format=%s', "refs/tags/{$tag}"]);
                $subject = $commitMsgRes->successful() ? trim($commitMsgRes->output()) : "Version {$tag}";
            }

            if ($date === '') {
                $commitDateRes = $this->runGit(['log', '-1', '--format=%cs', "refs/tags/{$tag}"]);
                $date = $commitDateRes->successful() ? trim($commitDateRes->output()) : date('Y-m-d');
            }

            // Corps complet de la note de version (tag annoté)
            $bodyRes = $this->runGit(['for-each-ref', "refs/tags/{$tag}", '--format=%(contents)']);
            $body = $bodyRes->successful() ? trim($bodyRes->output()) : $subject;

            $rawEntries[] = [
                'tag' => $tag,
                'version_number' => $versionNumber,
                'date' => $date,
                'subject' => $subject,
                'body' => $body,
                'commit_short' => $commitShort,
            ];
        }

        // Tri ascendant d'abord pour qualifier chaque version par rapport à la précédente
        usort(
            $rawEntries,
            fn (array $a, array $b): int => version_compare($a['version_number'], $b['version_number'])
        );

        $enriched = [];
        $previousVersion = null;

        foreach ($rawEntries as $entry) {
            $type = $this->classifyReleaseType($previousVersion, $entry['tag']);
            $previousVersion = $entry['tag'];

            $cmp = version_compare($entry['version_number'], $localNumber);
            $status = match (true) {
                $cmp === 0 => 'installed',
                $cmp > 0 => 'available',
                default => 'older',
            };

            $enriched[] = [
                'tag' => $entry['tag'],
                'version_number' => $entry['version_number'],
                'type' => $type,
                'type_label' => $this->getReleaseTypeLabel($type),
                'date' => $entry['date'],
                'subject' => $entry['subject'],
                'body' => $entry['body'],
                'commit_short' => $entry['commit_short'],
                'status' => $status,
            ];
        }

        // Inverser pour présenter la version la plus récente en tête de tableau
        return array_reverse($enriched);
    }

    /**
     * Interroge le dépôt distant GitHub (`origin`), synchronise uniquement les tags officiels
     * et détermine si une version SemVer validée supérieure à la version locale est disponible.
     *
     * @param  bool  $skipInternetCheck  Permet d'ignorer le test réseau `github.com` lors des tests locaux isolés.
     * @return array<string, mixed> Résultat complet du diagnostic de mise à jour.
     */
    public function checkForUpdates(bool $skipInternetCheck = false): array
    {
        if (! $skipInternetCheck && ! $this->hasInternetConnection()) {
            return [
                'success' => false,
                'online' => false,
                'has_update' => false,
                'is_up_to_date' => false,
                'message' => "La connexion Internet ne passe pas actuellement. Veuillez vérifier l'accès Internet du serveur puis réessayer.",
            ];
        }

        // Synchroniser les tags publiés sur le dépôt distant GitHub sans modifier les fichiers de travail
        $fetchResult = $this->runGit(['fetch', 'origin', '--tags', '--force'], 90);

        if (! $fetchResult->successful()) {
            $rawError = trim($fetchResult->errorOutput() ?: $fetchResult->output());
            Log::warning('Échec git fetch origin --tags : '.$rawError);

            return [
                'success' => false,
                'online' => true,
                'has_update' => false,
                'is_up_to_date' => false,
                'message' => $this->translateGitError($rawError),
                'technical_error' => $rawError,
            ];
        }

        $localInfo = $this->getLocalVersionInfo();
        $catalog = $this->getTagsCatalog($localInfo['version']);

        // Rechercher les versions validées strictement supérieures à la version locale
        $availableReleases = array_values(array_filter(
            $catalog,
            fn (array $item): bool => $item['status'] === 'available'
        ));

        if (count($availableReleases) === 0) {
            return [
                'success' => true,
                'online' => true,
                'has_update' => false,
                'is_up_to_date' => true,
                'current_version' => $localInfo['version'],
                'latest_version' => $localInfo['version'],
                'available_count' => 0,
                'tags_catalog' => $catalog,
                'version_info' => $localInfo,
                'message' => "Connexion Internet opérationnelle. Votre application SYGES est déjà à jour ({$localInfo['version']}).",
            ];
        }

        // La première entrée filtrée est la version SemVer validée la plus récente
        $latest = $availableReleases[0];
        $releaseType = $this->classifyReleaseType($localInfo['version'], $latest['tag']);

        // Journal des commits compris entre l'état local et le tag validé cible
        $changelog = [];
        $logRes = $this->runGit([
            'log',
            '--no-decorate',
            '--max-count=30',
            '--format=%h|||%cs|||%s',
            "HEAD..refs/tags/{$latest['tag']}",
        ]);

        if ($logRes->successful() && trim($logRes->output()) !== '') {
            $lines = preg_split('/\r\n|\r|\n/', trim($logRes->output())) ?: [];
            foreach ($lines as $line) {
                $parts = explode('|||', $line);
                if (count($parts) >= 3) {
                    $changelog[] = [
                        'hash' => trim($parts[0]),
                        'date' => trim($parts[1]),
                        'message' => trim($parts[2]),
                    ];
                }
            }
        }

        return [
            'success' => true,
            'online' => true,
            'has_update' => true,
            'is_up_to_date' => false,
            'current_version' => $localInfo['version'],
            'latest_version' => $latest['tag'],
            'release_type' => $releaseType,
            'release_type_label' => $this->getReleaseTypeLabel($releaseType),
            'release_date' => $latest['date'],
            'release_subject' => $latest['subject'],
            'release_notes' => $latest['body'],
            'available_count' => count($availableReleases),
            'changelog' => $changelog,
            'tags_catalog' => $catalog,
            'version_info' => $localInfo,
            'message' => "Nouvelle version validée {$latest['tag']} ({$this->getReleaseTypeLabel($releaseType)}) prête à être installée.",
        ];
    }

    /**
     * Exécute le pipeline transactionnel complet de mise à jour vers un tag SemVer validé :
     * 1. Vérification de la connexion Internet et synchronisation des tags GitHub.
     * 2. Sauvegarde intégrale `.sql` de la base de données + expédition par email au développeur.
     * 3. Activation du mode maintenance (`php artisan down`).
     * 4. Bascule Git forcée vers le tag validé (`git checkout -f -B main refs/tags/{tag}`).
     * 5. Exécution des nouvelles migrations de base de données (`php artisan migrate --force`).
     * 6. Nettoyage et optimisation des caches (`optimize:clear` puis `optimize`).
     * 7. Désactivation garantie du mode maintenance (`php artisan up`) et journalisation.
     *
     * @param  string|null  $requestedTag  Tag SemVer cible optionnel (par défaut : le dernier tag validé disponible).
     * @param  string  $triggeredBy  Identifiant ou nom de l'administrateur ayant déclenché l'opération.
     * @param  bool  $skipInternetCheck  Permet les tests automatisés hors-ligne sur dépôt local.
     * @return array<string, mixed> Rapport détaillé de chaque étape du pipeline.
     */
    public function runUpdatePipeline(
        ?string $requestedTag = null,
        string $triggeredBy = 'superadmin',
        bool $skipInternetCheck = false
    ): array {
        @set_time_limit(300);

        $steps = [];

        // Étape 0 : Contrôle de la connectivité Internet
        if (! $skipInternetCheck && ! $this->hasInternetConnection()) {
            return [
                'success' => false,
                'online' => false,
                'steps' => [[
                    'key' => 'internet',
                    'label' => 'Vérification de la connexion Internet',
                    'status' => 'error',
                    'detail' => 'Connexion Internet indisponible vers GitHub.',
                ]],
                'message' => 'Mise à jour annulée : la connexion Internet ne passe pas. Connectez le serveur à Internet avant de lancer la mise à jour.',
            ];
        }

        $steps[] = [
            'key' => 'internet',
            'label' => 'Connexion Internet & accès GitHub',
            'status' => 'success',
            'detail' => 'Connexion réseau vérifiée avec succès.',
        ];

        // Synchronisation des tags distants
        $fetchRes = $this->runGit(['fetch', 'origin', '--tags', '--force'], 90);
        if (! $fetchRes->successful()) {
            $rawErr = trim($fetchRes->errorOutput() ?: $fetchRes->output());

            return [
                'success' => false,
                'online' => true,
                'steps' => array_merge($steps, [[
                    'key' => 'fetch',
                    'label' => 'Synchronisation des versions GitHub',
                    'status' => 'error',
                    'detail' => $this->translateGitError($rawErr),
                ]]),
                'message' => $this->translateGitError($rawErr),
            ];
        }

        $localInfo = $this->getLocalVersionInfo();
        $catalog = $this->getTagsCatalog($localInfo['version']);

        // Détermination et validation stricte du tag cible
        $targetTag = $requestedTag !== null && trim($requestedTag) !== ''
            ? trim($requestedTag)
            : null;

        if ($targetTag === null) {
            $available = array_values(array_filter(
                $catalog,
                fn (array $item): bool => $item['status'] === 'available'
            ));

            if (count($available) === 0) {
                return [
                    'success' => false,
                    'online' => true,
                    'steps' => $steps,
                    'message' => "Aucune nouvelle version validée n'est disponible au-delà de {$localInfo['version']}.",
                ];
            }

            $targetTag = $available[0]['tag'];
        }

        if (! $this->isValidSemverTag($targetTag)) {
            return [
                'success' => false,
                'online' => true,
                'steps' => $steps,
                'message' => "Le tag « {$targetTag} » n'est pas un tag SemVer officiel validé (format attendu : vX.Y.Z).",
            ];
        }

        $verifyTagRes = $this->runGit(['rev-parse', '--verify', "refs/tags/{$targetTag}"]);
        if (! $verifyTagRes->successful()) {
            return [
                'success' => false,
                'online' => true,
                'steps' => $steps,
                'message' => "La version demandée « {$targetTag} » est introuvable sur le dépôt GitHub.",
            ];
        }

        $targetNumber = $this->normalizeSemver($targetTag) ?? '0.0.0';
        if (version_compare($targetNumber, $localInfo['version_number'], '<=')) {
            return [
                'success' => false,
                'online' => true,
                'steps' => $steps,
                'message' => "La version {$targetTag} est déjà installée ou antérieure à la version actuelle ({$localInfo['version']}).",
            ];
        }

        $releaseType = $this->classifyReleaseType($localInfo['version'], $targetTag);

        // Étape 1 : Sauvegarde préalable de la base de données (.sql) + envoi email au développeur
        $backupResult = $this->backupService->createBackup($localInfo['version']);
        if (! $backupResult['success'] || $backupResult['path'] === null || $backupResult['filename'] === null) {
            return [
                'success' => false,
                'online' => true,
                'steps' => array_merge($steps, [[
                    'key' => 'backup',
                    'label' => 'Sauvegarde SQL de la base de données',
                    'status' => 'error',
                    'detail' => $backupResult['message'],
                ]]),
                'message' => 'Mise à jour interrompue par sécurité : la sauvegarde SQL préalable a échoué ('.$backupResult['message'].').',
            ];
        }

        $emailResult = $this->backupService->sendBackupToDeveloper(
            filepath: $backupResult['path'],
            filename: $backupResult['filename'],
            currentVersion: $localInfo['version'],
            targetVersion: $targetTag,
        );

        $steps[] = [
            'key' => 'backup',
            'label' => 'Sauvegarde SQL & copie développeur',
            'status' => 'success',
            'detail' => "{$backupResult['filename']} ({$backupResult['size_formatted']}) — {$emailResult['message']}",
        ];

        $enteredMaintenance = false;

        try {
            // Étape 2 : Passage en mode maintenance temporaire
            $this->runArtisanCommand(['down', '--refresh=15']);
            $enteredMaintenance = true;

            $steps[] = [
                'key' => 'maintenance_down',
                'label' => 'Activation du mode maintenance sécurisé',
                'status' => 'success',
                'detail' => 'Accès utilisateurs temporairement suspendu pendant la mise à niveau.',
            ];

            // Étape 3 : Téléchargement & bascule sur le tag validé (ignore les permissions Windows)
            $this->runGit(['config', 'core.fileMode', 'false']);
            $checkoutRes = $this->runGit(['checkout', '-f', '-B', 'main', "refs/tags/{$targetTag}"], 120);

            if (! $checkoutRes->successful()) {
                $gitErr = trim($checkoutRes->errorOutput() ?: $checkoutRes->output());
                throw new \RuntimeException("Échec du déploiement Git vers {$targetTag} : {$gitErr}");
            }

            $this->syncEnvAppVersion($targetNumber);

            $steps[] = [
                'key' => 'git_checkout',
                'label' => "Installation du code source ({$targetTag})",
                'status' => 'success',
                'detail' => "Code source et assets pré-compilés alignés sur le tag validé {$targetTag}.",
            ];

            // Étape 4 : Exécution des migrations de base de données
            $migrateRes = $this->runArtisanCommand(['migrate', '--force']);
            if (! $migrateRes['success']) {
                throw new \RuntimeException('Erreur lors de la migration de la base de données : '.$migrateRes['output']);
            }

            $steps[] = [
                'key' => 'migrate',
                'label' => 'Migration des tables de la base de données',
                'status' => 'success',
                'detail' => $migrateRes['output'] !== '' ? $migrateRes['output'] : 'Schéma SQL vérifié et migré avec succès.',
            ];

            // Étape 5 : Nettoyage complet et optimisation des caches Laravel & Spatie Permission
            $this->runArtisanCommand(['optimize:clear']);
            $this->runArtisanCommand(['permission:cache-reset']);

            $optimizeDetail = 'Caches applicatifs vidés (optimize:clear) et permissions rechargées.';
            if (! app()->runningUnitTests()) {
                $optRes = $this->runArtisanCommand(['optimize']);
                if ($optRes['success']) {
                    $optimizeDetail = 'Caches vidés (optimize:clear) puis reconstruits (optimize) avec succès.';
                } else {
                    // Si une fermeture empêche route:cache, garantir un état propre sans cache corrompu
                    $this->runArtisanCommand(['optimize:clear']);
                    $this->runArtisanCommand(['config:cache']);
                    $this->runArtisanCommand(['view:cache']);
                    $optimizeDetail = 'Caches de configuration et de vues optimisés avec succès.';
                }
            }

            $steps[] = [
                'key' => 'optimize',
                'label' => 'Purge et optimisation du serveur',
                'status' => 'success',
                'detail' => $optimizeDetail,
            ];

            // Étape 6 : Sortie du mode maintenance
            $this->bringApplicationUp();
            $enteredMaintenance = false;

            $steps[] = [
                'key' => 'maintenance_up',
                'label' => 'Réouverture de la plateforme SYGES',
                'status' => 'success',
                'detail' => 'Application remise en ligne et prête à être utilisée.',
            ];

            $historyEntry = [
                'from_version' => $localInfo['version'],
                'to_version' => $targetTag,
                'release_type' => $releaseType,
                'release_type_label' => $this->getReleaseTypeLabel($releaseType),
                'backup_filename' => $backupResult['filename'],
                'email_sent' => $emailResult['sent'],
                'triggered_by' => $triggeredBy,
                'status' => 'success',
                'updated_date' => date('Y-m-d'),
                'updated_time' => date('H:i'),
                'message' => "Mise à jour réussie de {$localInfo['version']} vers {$targetTag}.",
            ];

            $this->appendHistory($historyEntry);

            $updatedVersionInfo = $this->getLocalVersionInfo();

            return [
                'success' => true,
                'online' => true,
                'previous_version' => $localInfo['version'],
                'new_version' => $targetTag,
                'release_type' => $releaseType,
                'release_type_label' => $this->getReleaseTypeLabel($releaseType),
                'backup' => $backupResult,
                'email_notification' => $emailResult,
                'steps' => $steps,
                'version_info' => $updatedVersionInfo,
                'tags_catalog' => $this->getTagsCatalog($targetTag),
                'history' => $this->getUpdateHistory(),
                'backups' => $this->backupService->getBackupsList(),
                'message' => "La mise à jour vers {$targetTag} est terminée avec succès ! Veuillez actualiser la page pour charger les nouvelles fonctionnalités.",
            ];
        } catch (\Throwable $e) {
            Log::error('Échec du pipeline de mise à jour SYGES : '.$e->getMessage(), [
                'exception' => $e,
                'target_tag' => $targetTag,
            ]);

            $steps[] = [
                'key' => 'pipeline_error',
                'label' => 'Interruption sécurisée du déploiement',
                'status' => 'error',
                'detail' => $e->getMessage(),
            ];

            $this->appendHistory([
                'from_version' => $localInfo['version'],
                'to_version' => $targetTag,
                'release_type' => $releaseType,
                'release_type_label' => $this->getReleaseTypeLabel($releaseType),
                'backup_filename' => $backupResult['filename'],
                'email_sent' => $emailResult['sent'],
                'triggered_by' => $triggeredBy,
                'status' => 'failed',
                'updated_date' => date('Y-m-d'),
                'updated_time' => date('H:i'),
                'message' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'online' => true,
                'steps' => $steps,
                'backup' => $backupResult,
                'message' => 'Une erreur est survenue pendant la mise à jour : '.$e->getMessage().'. Votre sauvegarde SQL a été préservée et la plateforme a été remise en ligne.',
            ];
        } finally {
            if ($enteredMaintenance) {
                $this->bringApplicationUp();
            }
        }
    }

    /**
     * Retourne l'historique chronologique inversé des mises à jour exécutées sur ce serveur.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getUpdateHistory(): array
    {
        if (! File::exists($this->historyFile)) {
            return [];
        }

        $raw = File::get($this->historyFile);
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Ajoute une entrée en tête du fichier d'historique JSON (`storage/app/update_history.json`).
     *
     * @param  array<string, mixed>  $entry  Détails de l'opération de mise à jour.
     */
    protected function appendHistory(array $entry): void
    {
        try {
            $history = $this->getUpdateHistory();
            array_unshift($history, $entry);
            $history = array_slice($history, 0, 50);

            $dir = dirname($this->historyFile);
            if (! File::isDirectory($dir)) {
                File::makeDirectory($dir, 0755, true);
            }

            File::put(
                $this->historyFile,
                (string) json_encode($history, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
            );
        } catch (\Throwable $e) {
            Log::warning("Impossible d'enregistrer l'historique de mise à jour : ".$e->getMessage());
        }
    }

    /**
     * Exécute une commande Git de manière non interactive (sans invite de mot de passe bloquante).
     *
     * @param  array<int, string>  $args  Arguments passés à l'exécutable `git`.
     * @param  int  $timeout  Délai maximal en secondes.
     * @return ProcessResult Résultat du processus système.
     */
    protected function runGit(array $args, int $timeout = 60): ProcessResult
    {
        return Process::path($this->repoPath)
            ->timeout($timeout)
            ->env([
                'GIT_TERMINAL_PROMPT' => '0',
                'GCM_INTERACTIVE' => 'never',
                'GIT_SSH_COMMAND' => 'ssh -o BatchMode=yes -o StrictHostKeyChecking=accept-new -o ConnectTimeout=10',
            ])
            ->run(array_merge(['git'], $args));
    }

    /**
     * Exécute une commande Artisan dans un sous-processus PHP CLI frais afin de charger les nouveaux
     * fichiers PHP extraits par Git (nouvelles migrations, nouvelles routes), avec repli automatique
     * sur `Artisan::call()` en mémoire lors des tests unitaires ou si le binaire CLI n'est pas résolu.
     *
     * @param  array<int, string>  $arguments  Arguments Artisan (ex: `['migrate', '--force']`).
     * @return array{success: bool, output: string}
     */
    protected function runArtisanCommand(array $arguments): array
    {
        // En test automatisé (SQLite :memory:) ou dépôt temporaire isolé, utiliser Artisan::call()
        if (app()->runningUnitTests() || realpath($this->repoPath) !== realpath(base_path())) {
            return $this->runArtisanInMemory($arguments);
        }

        $phpBinary = $this->resolvePhpCliBinary();
        if ($phpBinary !== null && File::exists($this->repoPath.'/artisan')) {
            $proc = Process::path($this->repoPath)
                ->timeout(180)
                ->run(array_merge([$phpBinary, 'artisan'], $arguments, ['--no-interaction']));

            if ($proc->successful()) {
                return [
                    'success' => true,
                    'output' => trim($proc->output()),
                ];
            }

            return [
                'success' => false,
                'output' => trim($proc->errorOutput() ?: $proc->output()),
            ];
        }

        return $this->runArtisanInMemory($arguments);
    }

    /**
     * Exécute une commande Artisan en mémoire via la façade Laravel.
     *
     * @param  array<int, string>  $arguments  Arguments de la commande Artisan.
     * @return array{success: bool, output: string}
     */
    protected function runArtisanInMemory(array $arguments): array
    {
        try {
            $commandLine = implode(' ', $arguments);
            $exitCode = Artisan::call($commandLine);

            return [
                'success' => $exitCode === 0,
                'output' => trim(Artisan::output()),
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'output' => $e->getMessage(),
            ];
        }
    }

    /**
     * Force la désactivation du mode maintenance (`up`) par sous-processus ET suppression directe
     * du fichier verrou `storage/framework/down` afin d'éviter tout blocage en erreur 503.
     */
    protected function bringApplicationUp(): void
    {
        try {
            $this->runArtisanCommand(['up']);
        } catch (\Throwable) {
            // Repli de sécurité ci-dessous
        }

        $downFile = storage_path('framework/down');
        if (File::exists($downFile)) {
            @File::delete($downFile);
        }
    }

    /**
     * Résout le chemin de l'exécutable PHP CLI (`php.exe` sous Windows Laragon ou `php` sous Linux)
     * en évitant le piège `httpd.exe` de `mod_php` sous Apache Windows.
     *
     * @return string|null Chemin du binaire PHP CLI ou `null` si introuvable.
     */
    protected function resolvePhpCliBinary(): ?string
    {
        $finder = new PhpExecutableFinder;
        $found = $finder->find(false);
        if (is_string($found) && $found !== '' && stripos(basename($found), 'php') === 0) {
            return $found;
        }

        $candidateWin = PHP_BINDIR.DIRECTORY_SEPARATOR.'php.exe';
        if (File::exists($candidateWin)) {
            return $candidateWin;
        }

        $candidateUnix = PHP_BINDIR.DIRECTORY_SEPARATOR.'php';
        if (File::exists($candidateUnix)) {
            return $candidateUnix;
        }

        return null;
    }

    /**
     * Met à jour la clé `APP_VERSION` dans le fichier `.env` principal après un changement de version.
     *
     * @param  string  $versionNumber  Numéro SemVer normalisé (ex: `1.2.0`).
     */
    protected function syncEnvAppVersion(string $versionNumber): void
    {
        if (realpath($this->repoPath) !== realpath(base_path())) {
            return;
        }

        $envPath = $this->repoPath.DIRECTORY_SEPARATOR.'.env';
        if (! File::exists($envPath) || ! File::isWritable($envPath)) {
            return;
        }

        $content = File::get($envPath);
        if (preg_match('/^APP_VERSION=.*$/m', $content) === 1) {
            $updated = preg_replace('/^APP_VERSION=.*$/m', "APP_VERSION={$versionNumber}", $content);
            if (is_string($updated)) {
                File::put($envPath, $updated);
            }
        } else {
            File::append($envPath, "\nAPP_VERSION={$versionNumber}\n");
        }
    }

    /**
     * Extrait, valide et trie par ordre décroissant (`version_compare`) une liste brute de tags Git.
     *
     * @param  string  $rawOutput  Sortie brute d'une commande `git tag`.
     * @return array<int, string> Liste des tags SemVer validés du plus récent au plus ancien.
     */
    protected function extractSortedSemverTags(string $rawOutput): array
    {
        if (trim($rawOutput) === '') {
            return [];
        }

        $lines = preg_split('/\r\n|\r|\n/', trim($rawOutput)) ?: [];
        $validTags = [];

        foreach ($lines as $line) {
            $tag = trim($line);
            if ($this->isValidSemverTag($tag)) {
                $validTags[] = $tag;
            }
        }

        usort($validTags, function (string $a, string $b): int {
            $normA = $this->normalizeSemver($a) ?? '0.0.0';
            $normB = $this->normalizeSemver($b) ?? '0.0.0';

            return version_compare($normB, $normA);
        });

        return array_values(array_unique($validTags));
    }

    /**
     * Masque tout jeton ou mot de passe éventuel présent dans l'URL du dépôt distant.
     *
     * @param  string  $url  URL brute retournée par `git remote get-url origin`.
     * @return string URL assainie affichable dans l'interface d'administration.
     */
    protected function sanitizeRemoteUrl(string $url): string
    {
        $sanitized = preg_replace('#(https?://)([^/@:]+)(:[^/@]+)?@#i', '$1***@', $url);

        return is_string($sanitized) ? $sanitized : $url;
    }

    /**
     * Traduit les erreurs techniques Git/SSH courantes en messages actionnables en français.
     *
     * @param  string  $rawError  Sortie d'erreur brute de Git.
     * @return string Message explicite en français.
     */
    protected function translateGitError(string $rawError): string
    {
        $lower = strtolower($rawError);

        if (str_contains($lower, 'permission denied (publickey)') || str_contains($lower, 'authentication failed') || str_contains($lower, 'could not read username')) {
            return 'Authentification GitHub refusée sur ce serveur. Veuillez configurer la clé SSH de déploiement (Deploy Key) sans mot de passe conformément au Guide de Déploiement Serveur.';
        }

        if (str_contains($lower, 'could not resolve host') || str_contains($lower, 'unable to access') || str_contains($lower, 'connection timed out')) {
            return 'Impossible de joindre GitHub. Vérifiez que la connexion Internet passe correctement sur le serveur.';
        }

        if (str_contains($lower, 'does not appear to be a git repository') || str_contains($lower, 'no such remote')) {
            return "Aucun dépôt distant GitHub ('origin') n'est configuré sur ce serveur. Configurez `git remote add origin` selon le guide d'installation.";
        }

        return 'Erreur Git lors de la communication avec GitHub : '.($rawError !== '' ? $rawError : 'Erreur inconnue.');
    }
}
