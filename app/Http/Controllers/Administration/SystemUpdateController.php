<?php

namespace App\Http\Controllers\Administration;

use App\Http\Controllers\Controller;
use App\Services\DatabaseBackupService;
use App\Services\SystemUpdateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Contrôleur exclusif du Gestionnaire de Versions SemVer et de Mise à Jour Système distante (GitHub).
 *
 * Strictement réservé au compte Super Administrateur (`super-admin` / `superadmin`).
 * Tout autre rôle (y compris le compte Administrateur d'établissement `admin`) reçoit un refus `403`.
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
     * Vérifie rigoureusement que l'utilisateur connecté est le Super Administrateur racine.
     *
     * @param  Request  $request  Requête HTTP entrante.
     */
    protected function authorizeSuperAdmin(Request $request): void
    {
        $user = $request->user();

        abort_unless(
            $user !== null && $user->isSuperAdmin(),
            403,
            'Accès strictement réservé au Super Administrateur de la plateforme SYGES.'
        );
    }

    /**
     * Affiche le tableau de bord du gestionnaire de versions SemVer, de vérification Internet,
     * de mise à jour en 1 clic et des sauvegardes SQL.
     *
     * @param  Request  $request  Requête HTTP entrante.
     * @return Response Vue Inertia `administration/system-update/index`.
     */
    public function index(Request $request): Response
    {
        $this->authorizeSuperAdmin($request);

        $versionInfo = $this->updateService->getLocalVersionInfo();

        return Inertia::render('administration/system-update/index', [
            'versionInfo' => $versionInfo,
            'tagsCatalog' => $this->updateService->getTagsCatalog($versionInfo['version']),
            'backups' => $this->backupService->getBackupsList(),
            'history' => $this->updateService->getUpdateHistory(),
            'hasInternet' => $this->updateService->hasInternetConnection(),
            'developerEmail' => (string) config('app.developer_email', 'teguiasimo@gmail.com'),
            'csrfToken' => csrf_token(),
        ]);
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
            'Content-Type' => 'application/sql',
        ]);
    }
}
