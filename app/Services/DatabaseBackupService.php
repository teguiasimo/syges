<?php

namespace App\Services;

use App\Mail\DatabaseBackupMail;
use App\Models\Institution;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Service autonome de sauvegarde et de gestion des archives SQL de la base de données SYGES.
 *
 * Fonctionne intégralement en PHP/PDO natif afin de garantir une fiabilité totale sur les
 * serveurs clients Windows (Laragon) ou Linux sans dépendre de l'exécutable externe `mysqldump`.
 * Prend également en charge l'expédition automatique de la sauvegarde par courriel au développeur.
 */
class DatabaseBackupService
{
    /**
     * Chemin absolu du répertoire de stockage des sauvegardes SQL.
     */
    protected string $backupDir;

    /**
     * Initialise le service et configure le dossier cible (`storage/app/backups` par défaut).
     *
     * @param  string|null  $backupDir  Répertoire personnalisé optionnel (utile pour les tests isolés).
     */
    public function __construct(?string $backupDir = null)
    {
        $this->backupDir = $backupDir ?? storage_path('app/backups');
    }

    /**
     * S'assure que le répertoire des sauvegardes existe et est isolé de Git.
     */
    protected function ensureDirectoryExists(): void
    {
        if (! File::isDirectory($this->backupDir)) {
            File::makeDirectory($this->backupDir, 0755, true);
        }

        $gitignorePath = "{$this->backupDir}/.gitignore";
        if (! File::exists($gitignorePath)) {
            File::put($gitignorePath, "*\n!.gitignore\n");
        }
    }

    /**
     * Génère un export SQL complet et horodaté de la base de données active.
     *
     * @param  string|null  $versionTag  Tag de version courant à inclure dans le nom du fichier (ex: `v1.0.0`).
     * @return array{
     *     success: bool,
     *     path: string|null,
     *     filename: string|null,
     *     size: int,
     *     size_formatted: string,
     *     message: string
     * }
     */
    public function createBackup(?string $versionTag = null): array
    {
        $this->ensureDirectoryExists();

        $cleanVersion = $versionTag !== null && $versionTag !== ''
            ? (preg_replace('/[^a-zA-Z0-9._\-]/', '', $versionTag) ?: 'v1.0.0')
            : 'v'.ltrim((string) config('app.version', '1.0.0'), 'v');

        $timestamp = date('Y-m-d_His');
        $filename = "backup_syges_{$cleanVersion}_{$timestamp}.sql";
        $filepath = "{$this->backupDir}/{$filename}";

        try {
            $connection = DB::connection();
            $driver = $connection->getDriverName();

            if ($driver === 'sqlite') {
                return $this->backupSqlite($filepath, $filename);
            }

            if ($driver === 'mysql' || $driver === 'mariadb') {
                return $this->backupMysql($filepath, $filename);
            }

            return [
                'success' => false,
                'path' => null,
                'filename' => null,
                'size' => 0,
                'size_formatted' => '0 octets',
                'message' => "Pilote de base de données non pris en charge pour l'export SQL : {$driver}.",
            ];
        } catch (\Throwable $e) {
            $rawMsg = (string) $e->getMessage();
            $safeMsg = mb_convert_encoding($rawMsg, 'UTF-8', ['UTF-8', 'Windows-1252', 'ISO-8859-1']);

            Log::error('Échec de la sauvegarde SQL SYGES : '.$safeMsg, [
                'exception' => $e,
            ]);

            if (File::exists($filepath)) {
                @File::delete($filepath);
            }

            return [
                'success' => false,
                'path' => null,
                'filename' => null,
                'size' => 0,
                'size_formatted' => '0 octets',
                'message' => 'Erreur lors de la génération de la sauvegarde SQL : '.$safeMsg,
            ];
        }
    }

    /**
     * Expédie la sauvegarde générée à l'adresse email du développeur configurée dans l'application.
     * Conçue en mode tolérant aux pannes : un défaut de configuration SMTP sur le serveur client
     * ne bloque jamais le pipeline de mise à jour.
     *
     * @param  string  $filepath  Chemin complet du fichier `.sql`.
     * @param  string  $filename  Nom du fichier `.sql`.
     * @param  string  $currentVersion  Version actuelle du système.
     * @param  string|null  $targetVersion  Version cible de la mise à jour (optionnelle).
     * @return array{sent: bool, recipient: string|null, message: string}
     */
    public function sendBackupToDeveloper(
        string $filepath,
        string $filename,
        string $currentVersion,
        ?string $targetVersion = null
    ): array {
        $developerEmail = trim((string) config('app.developer_email', 'teguiasimo@gmail.com'));

        if ($developerEmail === '' || ! filter_var($developerEmail, FILTER_VALIDATE_EMAIL)) {
            return [
                'sent' => false,
                'recipient' => null,
                'message' => 'Aucune adresse email développeur valide configurée.',
            ];
        }

        $mailer = (string) config('mail.default', 'log');
        if (in_array($mailer, ['log', 'array'], true) && ! app()->runningUnitTests()) {
            return [
                'sent' => false,
                'recipient' => $developerEmail,
                'message' => "Sauvegarde conservée localement (transport mail actuel : {$mailer}). Configurez SMTP dans .env pour l'envoi externe vers {$developerEmail}.",
            ];
        }

        try {
            $institutionName = config('app.name', 'SYGES');
            try {
                $inst = Institution::query()->select('nom', 'sigle')->first();
                if ($inst && $inst->nom) {
                    $institutionName = $inst->sigle ? "{$inst->sigle} - {$inst->nom}" : $inst->nom;
                }
            } catch (\Throwable) {
                // Table non initialisée ou indisponible : repli sur APP_NAME
            }

            $sizeFormatted = File::exists($filepath) ? $this->formatBytes(File::size($filepath)) : '0 KB';

            Mail::to($developerEmail)->send(new DatabaseBackupMail(
                backupPath: $filepath,
                backupFilename: $filename,
                institutionName: $institutionName,
                currentVersion: $currentVersion,
                targetVersion: $targetVersion,
                formattedSize: $sizeFormatted,
            ));

            return [
                'sent' => true,
                'recipient' => $developerEmail,
                'message' => "Copie de sauvegarde transmise par email à {$developerEmail}.",
            ];
        } catch (\Throwable $e) {
            $rawMsg = (string) $e->getMessage();
            $safeMsg = mb_convert_encoding($rawMsg, 'UTF-8', ['UTF-8', 'Windows-1252', 'ISO-8859-1']);

            Log::warning("Envoi email de la sauvegarde SQL vers {$developerEmail} non abouti : {$safeMsg}");

            // Diagnostic précis et propre pour le mode hors-ligne sans pollution par les erreurs de sockets brutes
            $isOfflineOrNetwork = preg_match(
                '/Connection could not be established|stream_socket_client|php_network_getaddresses|timed out|timeout|Network is unreachable|refused/i',
                $rawMsg
            ) === 1;

            $diagnostic = $isOfflineOrNetwork
                ? 'envoi email ignoré car aucune connexion Internet active pour joindre le serveur de messagerie'
                : 'envoi email non abouti : ' . mb_substr($safeMsg, 0, 160);

            return [
                'sent' => false,
                'recipient' => $developerEmail,
                'message' => "Sauvegarde enregistrée sur le serveur local ({$diagnostic}).",
            ];
        }
    }

    /**
     * Sauvegarde une base SQLite (fichier physique ou base `:memory:` lors des tests automatisés).
     *
     * @param  string  $filepath  Chemin complet du fichier de sortie `.sql`.
     * @param  string  $filename  Nom du fichier de sortie.
     * @return array{success: bool, path: string, filename: string, size: int, size_formatted: string, message: string}
     */
    protected function backupSqlite(string $filepath, string $filename): array
    {
        $dbPath = (string) config('database.connections.sqlite.database');

        // Cas d'un fichier SQLite physique sur disque
        if ($dbPath !== '' && $dbPath !== ':memory:' && File::exists($dbPath)) {
            File::copy($dbPath, $filepath);
            $size = File::size($filepath);

            return [
                'success' => true,
                'path' => $filepath,
                'filename' => $filename,
                'size' => $size,
                'size_formatted' => $this->formatBytes($size),
                'message' => "Sauvegarde SQLite effectuée avec succès ({$filename}).",
            ];
        }

        // Cas d'une base SQLite en mémoire (:memory:) : génération d'un dump SQL complet via PDO
        $pdo = DB::connection()->getPdo();
        $handle = fopen($filepath, 'w');
        if (! $handle) {
            throw new \RuntimeException("Impossible d'écrire dans {$filepath}");
        }

        fwrite($handle, "-- SYGES SQLite Database Backup\n");
        fwrite($handle, '-- Date: '.date('Y-m-d H:i:s')."\n\n");
        fwrite($handle, "PRAGMA foreign_keys=OFF;\n\n");

        $tablesStmt = $pdo->query("SELECT name, sql FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'");
        $tables = $tablesStmt ? $tablesStmt->fetchAll(\PDO::FETCH_ASSOC) : [];

        foreach ($tables as $tableInfo) {
            $tableName = $tableInfo['name'];
            $createSql = $tableInfo['sql'];
            fwrite($handle, "DROP TABLE IF EXISTS \"{$tableName}\";\n{$createSql};\n");

            $rowsStmt = $pdo->query("SELECT * FROM \"{$tableName}\"");
            if ($rowsStmt !== false) {
                while ($row = $rowsStmt->fetch(\PDO::FETCH_NUM)) {
                    $values = array_map(fn ($v) => $this->quoteValue($pdo, $v), $row);
                    fwrite($handle, "INSERT INTO \"{$tableName}\" VALUES (".implode(', ', $values).");\n");
                }
            }
            fwrite($handle, "\n");
        }

        fwrite($handle, "PRAGMA foreign_keys=ON;\n");
        fclose($handle);

        $size = File::size($filepath);

        return [
            'success' => true,
            'path' => $filepath,
            'filename' => $filename,
            'size' => $size,
            'size_formatted' => $this->formatBytes($size),
            'message' => "Sauvegarde SQL effectuée avec succès ({$filename}).",
        ];
    }

    /**
     * Exporte l'intégralité de la structure et des données MySQL / MariaDB via PDO natif par lots.
     * Préserve scrupuleusement l'encodage UTF-8 (`utf8mb4`), les clés étrangères et les zéros initiaux
     * (numéros de téléphone, matricules, codes de classes tels que `6E1`).
     *
     * @param  string  $filepath  Chemin complet du fichier `.sql`.
     * @param  string  $filename  Nom du fichier `.sql`.
     * @return array{success: bool, path: string, filename: string, size: int, size_formatted: string, message: string}
     */
    protected function backupMysql(string $filepath, string $filename): array
    {
        $pdo = DB::connection()->getPdo();
        $dbName = DB::connection()->getDatabaseName();

        $handle = fopen($filepath, 'w');
        if (! $handle) {
            throw new \RuntimeException("Impossible de créer le fichier de sauvegarde SQL dans {$filepath}");
        }

        fwrite($handle, "-- ========================================================\n");
        fwrite($handle, "-- SYGES - Sauvegarde Intégrale Base de Données MySQL\n");
        fwrite($handle, "-- Base de données : `{$dbName}`\n");
        fwrite($handle, '-- Générée le : '.date('d-m-Y H:i:s')."\n");
        fwrite($handle, "-- ========================================================\n\n");
        fwrite($handle, "SET NAMES utf8mb4;\n");
        fwrite($handle, "SET FOREIGN_KEY_CHECKS = 0;\n");
        fwrite($handle, "SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';\n");
        fwrite($handle, "SET time_zone = '+00:00';\n\n");

        // Récupération uniquement des tables physiques (exclusion des vues SQL)
        $tables = [];
        $stmt = $pdo->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'");
        if ($stmt !== false) {
            while ($row = $stmt->fetch(\PDO::FETCH_NUM)) {
                $tables[] = (string) $row[0];
            }
        }

        foreach ($tables as $table) {
            $escapedTable = str_replace('`', '``', $table);

            fwrite($handle, "-- --------------------------------------------------------\n");
            fwrite($handle, "-- Structure de la table `{$escapedTable}`\n");
            fwrite($handle, "-- --------------------------------------------------------\n\n");
            fwrite($handle, "DROP TABLE IF EXISTS `{$escapedTable}`;\n");

            $createStmt = $pdo->query("SHOW CREATE TABLE `{$escapedTable}`");
            if ($createStmt !== false) {
                $createRow = $createStmt->fetch(\PDO::FETCH_NUM);
                if (is_array($createRow) && isset($createRow[1])) {
                    fwrite($handle, $createRow[1].";\n\n");
                }
            }

            // Export des lignes par lots de 200 pour maîtriser l'empreinte mémoire RAM
            $dataStmt = $pdo->query("SELECT * FROM `{$escapedTable}`");
            if ($dataStmt === false) {
                continue;
            }
            $columnCount = $dataStmt->columnCount();

            $batchSize = 200;
            $batchValues = [];

            while ($row = $dataStmt->fetch(\PDO::FETCH_NUM)) {
                $escapedValues = [];
                for ($col = 0; $col < $columnCount; $col++) {
                    $escapedValues[] = $this->quoteValue($pdo, $row[$col]);
                }

                $batchValues[] = '('.implode(', ', $escapedValues).')';

                if (count($batchValues) >= $batchSize) {
                    fwrite($handle, "INSERT INTO `{$escapedTable}` VALUES\n".implode(",\n", $batchValues).";\n");
                    $batchValues = [];
                }
            }

            if (! empty($batchValues)) {
                fwrite($handle, "INSERT INTO `{$escapedTable}` VALUES\n".implode(",\n", $batchValues).";\n");
            }

            fwrite($handle, "\n");
        }

        fwrite($handle, "SET FOREIGN_KEY_CHECKS = 1;\n");
        fwrite($handle, "-- Fin de la sauvegarde SYGES\n");
        fclose($handle);

        $size = File::size($filepath);

        return [
            'success' => true,
            'path' => $filepath,
            'filename' => $filename,
            'size' => $size,
            'size_formatted' => $this->formatBytes($size),
            'message' => "Sauvegarde SQL de la base « {$dbName} » réussie ({$filename} — ".$this->formatBytes($size).').',
        ];
    }

    /**
     * Échappe de façon sûre une valeur SQL sans altérer les chaînes numériques à zéros initiaux ni la notation `6E1`.
     *
     * @param  \PDO  $pdo  Instance PDO active.
     * @param  mixed  $value  Valeur brute issue d'une colonne SQL.
     * @return string Valeur SQL prête pour une clause `VALUES (...)`.
     */
    protected function quoteValue(\PDO $pdo, mixed $value): string
    {
        if ($value === null) {
            return 'NULL';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        return $pdo->quote((string) $value);
    }

    /**
     * Retourne la liste complète des fichiers de sauvegarde `.sql` disponibles, du plus récent au plus ancien.
     *
     * @return array<int, array{
     *     filename: string,
     *     size: int,
     *     size_formatted: string,
     *     created_date: string,
     *     created_time: string,
     *     created_at: string
     * }>
     */
    public function getBackupsList(): array
    {
        $this->ensureDirectoryExists();

        $files = File::files($this->backupDir);
        $backups = [];

        foreach ($files as $file) {
            if (strtolower($file->getExtension()) !== 'sql') {
                continue;
            }

            $mtime = $file->getMTime();
            $size = $file->getSize();

            $backups[] = [
                'filename' => $file->getFilename(),
                'size' => $size,
                'size_formatted' => $this->formatBytes($size),
                'created_date' => date('Y-m-d', $mtime),
                'created_time' => date('H:i', $mtime),
                'created_at' => date('Y-m-d H:i:s', $mtime),
            ];
        }

        usort($backups, fn (array $a, array $b) => strcmp($b['created_at'], $a['created_at']));

        return $backups;
    }

    /**
     * Vérifie la validité stricte d'un nom de fichier de sauvegarde et retourne son chemin complet s'il existe.
     * Protège contre toute attaque de traversée de répertoires (Path Traversal).
     *
     * @param  string  $filename  Nom du fichier `.sql`.
     * @return string|null Chemin absolu vérifié ou `null` si invalide/introuvable.
     */
    public function resolveValidBackupPath(string $filename): ?string
    {
        if (! preg_match('/^backup_syges_[a-zA-Z0-9._\-]+\.sql$/', $filename)) {
            return null;
        }

        $filePath = "{$this->backupDir}/{$filename}";

        return File::exists($filePath) ? $filePath : null;
    }

    /**
     * Convertit un nombre d'octets en unité lisible (octets, Ko, Mo, Go).
     *
     * @param  int  $bytes  Taille brute en octets.
     * @return string Taille formatée en français.
     */
    public function formatBytes(int $bytes): string
    {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2, ',', ' ').' Go';
        }

        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2, ',', ' ').' Mo';
        }

        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 2, ',', ' ').' Ko';
        }

        return $bytes.' octets';
    }
}
