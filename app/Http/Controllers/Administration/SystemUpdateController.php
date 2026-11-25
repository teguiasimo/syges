<?php

namespace App\Http\Controllers\Administration;

use App\Http\Controllers\Controller;
use App\Services\DatabaseBackupService;
use App\Services\SystemUpdateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Contrôleur du Gestionnaire de Versions SemVer et de Mise à Jour Système distante (GitHub).
 *
 * Accessible au Super Administrateur racine (`super-admin` / `superadmin`) ainsi qu'aux rôles
 * disposant explicitement de la permission `system.update` accordée par le Super Administrateur.
 */
class SystemUpdateController extends Controller
{
    /**
     * Injecte les services de mise à jour Git SemVer et de sauvegarde SQL autonome.
     *
     * @param  SystemUpdateService  $updateService  Service d'orchestration des versions et mises à jour.
     * @param  DatabaseBackupService  $backupService  Service d'export et d'archivage SQL.
     */
    public function __construct(
        protected SystemUpdateService $updateService,
        protected DatabaseBackupService $backupService,
    ) {}

    /**
     * Vérifie que l'utilisateur connecté est Super Administrateur ou dispose explicitement
     * de la permission `system.update`.
     *
     * @param  Request  $request  Requête HTTP entrante.
     */
    protected function authorizeSuperAdmin(Request $request): void
    {
        $user = $request->user();

        abort_unless(
            $user !== null && ($user->isSuperAdmin() || $user->can('system.update')),
            403,
            'Accès non autorisé : la permission « system.update » ou le rôle Super Administrateur est requis.'
        );
    }

    /**
     * Affiche le tableau de bord du gestionnaire de versions SemVer, de vérification Internet,
     * de mise à jour en 1 clic et des sauvegardes SQL.
     *
     * Optimisations de latence :
     * - Les métadonnées Git locales (`versionInfo`, `tagsCatalog`) sont mises en cache 5 minutes
     *   avec invalidation automatique dès que `.git/HEAD` change ou après `check()` / `update()`.
     * - La sonde réseau distante (`hasInternet` vers GitHub:443) est différée via `Inertia::defer()`
     *   et mise en cache 2 minutes avec un timeout court (1,5 s) afin de ne jamais bloquer le rendu initial.
     *
     * @param  Request  $request  Requête HTTP entrante.
     * @return Response Vue Inertia `administration/system-update/index`.
     */
    public function index(Request $request): Response
    {
        $this->authorizeSuperAdmin($request);

        $headStamp = $this->resolveGitHeadFingerprint();
        $versionInfo = Cache::remember(
            "syges.system_update.version_info.{$headStamp}",
            now()->addMinutes(5),
            fn (): array => $this->updateService->getLocalVersionInfo(),
        );

        $tagsCatalog = Cache::remember(
            "syges.system_update.tags_catalog.{$headStamp}",
            now()->addMinutes(5),
            fn (): array => $this->updateService->getTagsCatalog($versionInfo['version']),
        );

        return Inertia::render('administration/system-update/index', [
            'versionInfo' => $versionInfo,
            'tagsCatalog' => $tagsCatalog,
            'backups' => $this->backupService->getBackupsList(),
            'history' => $this->updateService->getUpdateHistory(),
            'hasInternet' => Inertia::defer(
                fn (): bool => Cache::remember(
                    'syges.system_update.has_internet',
                    now()->addMinutes(2),
                    fn (): bool => $this->updateService->hasInternetConnection(timeout: 1.5),
                )
            ),
            'developerEmail' => (string) config('app.developer_email', 'teguiasimo@gmail.com'),
            'csrfToken' => csrf_token(),
        ]);
    }

    /**
     * Calcule une empreinte rapide (sans processus shell `git`) de l'état local `.git/HEAD`
     * afin d'invalider instantanément le cache des versions après tout checkout ou commit.
     *
     * @return string Empreinte MD5 courte de `.git/HEAD` (ou `'no-git'`).
     */
    protected function resolveGitHeadFingerprint(): string
    {
        $headFile = base_path('.git/HEAD');
        if (! is_file($headFile)) {
            return 'no-git';
        }

        $headContent = trim((string) @file_get_contents($headFile));
        $refMtime = (string) @filemtime($headFile);
        if (str_starts_with($headContent, 'ref:')) {
            $refPath = base_path('.git/'.trim(substr($headContent, 4)));
            if (is_file($refPath)) {
                $headContent .= '|'.trim((string) @file_get_contents($refPath));
            }
        }

        return md5($headContent.'|'.$refMtime);
    }

    /**
     * Invalide les caches du gestionnaire de mises à jour (après contrôle distant ou déploiement).
     */
    protected function flushUpdateMetadataCache(): void
    {
        $headStamp = $this->resolveGitHeadFingerprint();
        Cache::forget("syges.system_update.version_info.{$headStamp}");
        Cache::forget("syges.system_update.tags_catalog.{$headStamp}");
        Cache::forget('syges.system_update.has_internet');
    }

    /**
     * Vérifie l'état de la connexion Internet, interroge GitHub (`git fetch origin --tags`)
     * et retourne la disponibilité éventuelle d'un nouveau tag SemVer validé (`vX.Y.Z`).
     *
     * @param  Request  $request  Requête HTTP entrante.
     * @return JsonResponse Diagnostic JSON détaillé.
     */
    public function check(Request $request): JsonResponse
    {
        $this->authorizeSuperAdmin($request);

        $this->flushUpdateMetadataCache();
        $result = $this->updateService->checkForUpdates();

        return response()->json($result);
    }

    /**
     * Lance le pipeline complet de mise à jour vers le dernier tag SemVer validé (ou un tag choisi) :
     * Sauvegarde SQL + Email développeur -> Maintenance -> Git Checkout Tag -> Migrate -> Optimize -> Up.
     *
     * @param  Request  $request  Requête HTTP contenant optionnellement `tag`.
     * @return JsonResponse Rapport d'exécution étape par étape.
     */
    public function update(Request $request): JsonResponse
    {
        $this->authorizeSuperAdmin($request);

        $validated = $request->validate([
            'tag' => ['nullable', 'string', 'max:40'],
        ]);

        $user = $request->user();
        $actor = $user ? ($user->login ?: $user->name) : 'superadmin';

        $result = $this->updateService->runUpdatePipeline(
            requestedTag: $validated['tag'] ?? null,
            triggeredBy: (string) $actor,
        );

        $this->flushUpdateMetadataCache();

        return response()->json($result, $result['success'] ? 200 : 422);
    }

    /**
     * Génère immédiatement une sauvegarde SQL manuelle de la base de données et tente de l'expédier
     * par email à l'adresse du développeur.
     *
     * @param  Request  $request  Requête HTTP entrante.
     * @return JsonResponse Informations sur l'archive SQL générée et liste rafraîchie.
     */
    public function backup(Request $request): JsonResponse
    {
        $this->authorizeSuperAdmin($request);

        $versionInfo = $this->updateService->getLocalVersionInfo();
        $backupResult = $this->backupService->createBackup($versionInfo['version']);

        if (! $backupResult['success'] || $backupResult['path'] === null || $backupResult['filename'] === null) {
            return response()->json([
                'success' => false,
                'message' => $backupResult['message'],
            ], 422);
        }

        $emailResult = $this->backupService->sendBackupToDeveloper(
            filepath: $backupResult['path'],
            filename: $backupResult['filename'],
            currentVersion: $versionInfo['version'],
            targetVersion: null,
        );

        return response()->json([
            'success' => true,
            'backup' => $backupResult,
            'email_notification' => $emailResult,
            'backups' => $this->backupService->getBackupsList(),
            'message' => "{$backupResult['message']} {$emailResult['message']}",
        ]);
    }

    /**
     * Télécharge sur le poste du Super Administrateur un fichier de sauvegarde `.sql` existant.
     *
     * @param  Request  $request  Requête HTTP entrante.
     * @param  string  $filename  Nom du fichier `.sql` présent dans `storage/app/backups`.
     * @return BinaryFileResponse Flux de téléchargement binaire sécurisé.
     */
    public function downloadBackup(Request $request, string $filename): BinaryFileResponse
    {
        $this->authorizeSuperAdmin($request);

        $validPath = $this->backupService->resolveValidBackupPath($filename);

        abort_if($validPath === null, 404, 'Fichier de sauvegarde SQL introuvable ou nom invalide.');

        return response()->download($validPath, $filename, [
            'Content-Type' => 'application/octet-stream',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
