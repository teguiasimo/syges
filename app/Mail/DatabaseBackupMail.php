<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\File;

/**
 * Mailable chargé d'expédier automatiquement au développeur une copie de la sauvegarde SQL
 * de la base de données effectuée juste avant une mise à jour système ou une sauvegarde manuelle.
 */
class DatabaseBackupMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Initialise le message email de sauvegarde de base de données.
     *
     * @param  string  $backupPath  Chemin absolu vers le fichier `.sql` généré sur le serveur.
     * @param  string  $backupFilename  Nom du fichier de sauvegarde (ex: `backup_syges_v1.0.0_2026-10-28_040000.sql`).
     * @param  string  $institutionName  Nom de l'institution ou de l'établissement client.
     * @param  string  $currentVersion  Version installée au moment de la sauvegarde (ex: `v1.0.0`).
     * @param  string|null  $targetVersion  Version cible de la mise à jour (null s'il s'agit d'une sauvegarde manuelle).
     * @param  string  $formattedSize  Taille lisible du fichier SQL (ex: `2.45 MB`).
     */
    public function __construct(
        public string $backupPath,
        public string $backupFilename,
        public string $institutionName,
        public string $currentVersion,
        public ?string $targetVersion = null,
        public string $formattedSize = '0 KB',
    ) {}

    /**
     * Définit l'enveloppe du courriel (sujet explicite mentionnant l'école et la version).
     */
    public function envelope(): Envelope
    {
        $contextLabel = $this->targetVersion
            ? "Pré-mise à jour {$this->currentVersion} -> {$this->targetVersion}"
            : "Sauvegarde {$this->currentVersion}";

        return new Envelope(
            subject: "[SYGES Backup] {$this->institutionName} — {$contextLabel} (".date('d-m-Y H:i').')',
        );
    }

    /**
     * Définit le corps HTML léger et autonome du courriel (sans vue externe requise).
     */
    public function content(): Content
    {
        $appUrl = config('app.url', 'Non défini');
        $dateFormatted = date('d-m-Y à H:i:s');
        $operationType = $this->targetVersion
            ? "Sauvegarde automatique préalable à la mise à jour vers <strong>{$this->targetVersion}</strong>"
            : 'Sauvegarde manuelle déclenchée par le Super Administrateur';

        $html = <<<HTML
        <div style="font-family: Arial, sans-serif; color: #1e293b; line-height: 1.6; max-width: 640px;">
            <h2 style="color: #059669; margin-bottom: 8px;">Sauvegarde Base de Données SYGES</h2>
            <p style="margin-top: 0; color: #475569;">{$operationType}</p>
            <table style="width: 100%; border-collapse: collapse; margin: 16px 0; background: #f8fafc; border: 1px solid #e2e8f0;">
                <tr>
                    <td style="padding: 8px 12px; font-weight: bold; border-bottom: 1px solid #e2e8f0;">Institution / Serveur</td>
                    <td style="padding: 8px 12px; border-bottom: 1px solid #e2e8f0;">{$this->institutionName} ({$appUrl})</td>
                </tr>
                <tr>
                    <td style="padding: 8px 12px; font-weight: bold; border-bottom: 1px solid #e2e8f0;">Version actuelle</td>
                    <td style="padding: 8px 12px; border-bottom: 1px solid #e2e8f0;">{$this->currentVersion}</td>
                </tr>
                <tr>
                    <td style="padding: 8px 12px; font-weight: bold; border-bottom: 1px solid #e2e8f0;">Fichier SQL</td>
                    <td style="padding: 8px 12px; border-bottom: 1px solid #e2e8f0;">{$this->backupFilename} ({$this->formattedSize})</td>
                </tr>
                <tr>
                    <td style="padding: 8px 12px; font-weight: bold;">Date d'exécution</td>
                    <td style="padding: 8px 12px;">{$dateFormatted}</td>
                </tr>
            </table>
            <p style="font-size: 12px; color: #64748b;">
                La copie locale est également conservée sur le serveur client dans <code>storage/app/backups/{$this->backupFilename}</code>.
            </p>
        </div>
        HTML;

        return new Content(
            htmlString: $html,
        );
    }

    /**
     * Attache le fichier SQL (ou sa version compressée GZIP s'il dépasse 10 Mo) dans la limite des serveurs SMTP (20 Mo).
     *
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        if (! File::exists($this->backupPath)) {
            return [];
        }

        $fileSize = File::size($this->backupPath);
        $maxDirectBytes = 10 * 1024 * 1024; // 10 Mo en pièce jointe directe
        $maxCompressedBytes = 18 * 1024 * 1024; // 18 Mo max après compression GZIP (marge sécurité Gmail 25 Mo)

        if ($fileSize <= $maxDirectBytes) {
            return [
                Attachment::fromPath($this->backupPath)
                    ->as($this->backupFilename)
                    ->withMime('application/sql'),
            ];
        }

        if (function_exists('gzencode')) {
            $compressed = gzencode(File::get($this->backupPath), 9);
            if ($compressed !== false && strlen($compressed) <= $maxCompressedBytes) {
                return [
                    Attachment::fromData(fn () => $compressed, "{$this->backupFilename}.gz")
                        ->withMime('application/gzip'),
                ];
            }
        }

        return [];
    }
}
