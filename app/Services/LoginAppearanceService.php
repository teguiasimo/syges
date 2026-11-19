<?php

namespace App\Services;

use App\Models\LoginSetting;
use App\Models\LoginSlide;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Service centralisant la résolution, la mise en cache et le stockage média
 * de l'apparence et du carrousel dynamique de la page de connexion (`/login`).
 *
 * Garantit que :
 * - Par défaut (aucune personnalisation active), les 10 photos officielles SYGES restent affichées.
 * - Dès que le mode personnalisé est activé avec N >= 1 slides actifs (ex. 3), seuls ces N slides tournent en boucle.
 * - Le payload public est mis en cache afin de ne peser aucun coût SQL sur les visites de `/login`.
 */
class LoginAppearanceService
{
    /**
     * Clé de cache Laravel du payload public partagé sur les vues d'authentification.
     */
    public const CACHE_KEY = 'syges.login_appearance.v1';

    /**
     * Clé de cache Laravel de l'identité visuelle partagée dans l'espace connecté et l'onglet navigateur.
     */
    public const APP_BRANDING_CACHE_KEY = 'syges.app_branding.v1';

    /**
     * Cadence par défaut du diaporama en secondes.
     */
    public const DEFAULT_ROTATION_SECONDS = 4;

    /**
     * Les 10 scènes scolaires par défaut de SYGES conservées intactes en l'absence de personnalisation active.
     *
     * @var array<int, array{id: string, image: string, title: string, subtitle: string}>
     */
    public const DEFAULT_SCENES = [
        [
            'id' => 'classe-pedagogie',
            'image' => '/images/auth/real-classroom-cameroon.jpg',
            'title' => 'Apprentissage & Pédagogie Active',
            'subtitle' => 'Séance de cours et travail pratique en classe au Cameroun',
        ],
        [
            'id' => 'classe-eleves',
            'image' => '/images/auth/real-students-class.jpg',
            'title' => 'Travail en Classe & Réflexion',
            'subtitle' => 'Élèves studieux, cahiers et transmission des savoirs fondamentaux',
        ],
        [
            'id' => 'ecole-urbaine',
            'image' => '/images/auth/real-school-douala.jpg',
            'title' => 'Établissements Scolaires Urbains',
            'subtitle' => 'Élèves réunis au sein de l’École Publique de Bonadowito (Douala)',
        ],
        [
            'id' => 'ecole-village',
            'image' => '/images/auth/village-school.jpg',
            'title' => 'L’École au Village',
            'subtitle' => 'Bâtiment et cour d’école en zone rurale (Bamena, Ouest Cameroun)',
        ],
        [
            'id' => 'ecoliers-cameroun',
            'image' => '/images/auth/school-scene-1.jpg',
            'title' => 'Écoliers du Cameroun',
            'subtitle' => 'La jeunesse camerounaise et l’accès universel à l’éducation (Akok Bekoe)',
        ],
        [
            'id' => 'defile-jeunesse',
            'image' => '/images/auth/school-scene-4.jpg',
            'title' => 'Fête de la Jeunesse & Civisme',
            'subtitle' => 'Défilé scolaire traditionnel du 11 février et cohésion nationale',
        ],
        [
            'id' => 'batiment-rural',
            'image' => '/images/auth/rural-school-west.jpg',
            'title' => 'Infrastructures Scolaires de Proximité',
            'subtitle' => 'Établissements scolaires au cœur des terroirs locaux (Bapeng-Bameka)',
        ],
        [
            'id' => 'rassemblement-nord',
            'image' => '/images/auth/school-scene-3.jpg',
            'title' => 'Rassemblement & Camaraderie',
            'subtitle' => 'Élèves réunies dans la cour (Maroua, Grand Nord Cameroun)',
        ],
        [
            'id' => 'patrimoine-bankim',
            'image' => '/images/auth/real-schoolhouse-bankim.jpg',
            'title' => 'Patrimoine Éducatif Régional',
            'subtitle' => 'Bâtiment scolaire historique et cadre d’études (Bankim, Adamaoua)',
        ],
        [
            'id' => 'cour-recreation',
            'image' => '/images/auth/school-scene-8.jpg',
            'title' => 'Cérémonie & Discipline Scolaire',
            'subtitle' => 'Cérémonie de levée des couleurs et rassemblement dans la cour',
        ],
    ];

    /**
     * Valeurs d'identité et d'assistance par défaut affichées sur l'écran de connexion.
     *
     * @var array{
     *     brand_name: string,
     *     brand_subtitle: string,
     *     logo_url: string,
     *     footer_left: string,
     *     footer_right: string,
     *     support_label: string,
     *     support_phone: string,
     *     support_email: string
     * }
     */
    public const DEFAULT_BRANDING = [
        'brand_name' => 'SYGES',
        'brand_subtitle' => 'Gestion des Établissements Scolaires',
        'logo_url' => '/images/syges-icon.png',
        'footer_left' => 'SYGES • Système Éducatif Camerounais',
        'footer_right' => '© 2026',
        'support_label' => 'Besoin d\'aide ?',
        'support_phone' => '(+237) 697 44 90 03',
        'support_email' => 'teguiasimo@gmail.com',
    ];

    /**
     * Retourne les données publiques destinées au layout et à la page de connexion (mises en cache).
     *
     * Si aucun slide personnalisé actif n'existe ou si `use_custom_slides` est désactivé,
     * retourne automatiquement les 10 slides SYGES par défaut.
     *
     * @return array{
     *     slides: array<int, array{id: string, image: string, title: string, subtitle: string}>,
     *     using_custom_slides: bool,
     *     rotation_interval_ms: int,
     *     branding: array{
     *         brand_name: string,
     *         brand_subtitle: string,
     *         logo_url: string,
     *         footer_left: string,
     *         footer_right: string,
     *         support_label: string,
     *         support_phone: string,
     *         support_email: string
     *     }
     * }
     */
    public function getPublicPayload(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function (): array {
            return $this->buildPublicPayloadFromDatabase();
        });
    }

    /**
     * Construit le payload public en base de données avec repli sécurisé sur les valeurs par défaut.
     *
     * @return array{
     *     slides: array<int, array{id: string, image: string, title: string, subtitle: string}>,
     *     using_custom_slides: bool,
     *     rotation_interval_ms: int,
     *     branding: array{
     *         brand_name: string,
     *         brand_subtitle: string,
     *         logo_url: string,
     *         footer_left: string,
     *         footer_right: string,
     *         support_label: string,
     *         support_phone: string,
     *         support_email: string
     *     }
     * }
     */
    private function buildPublicPayloadFromDatabase(): array
    {
        try {
            if (! Schema::hasTable('login_settings') || ! Schema::hasTable('login_slides')) {
                return $this->defaultPayload();
            }

            $setting = LoginSetting::first();
            $useCustom = $setting instanceof LoginSetting && (bool) $setting->use_custom_slides;

            $activeSlides = $useCustom
                ? LoginSlide::where('is_active', true)
                    ->orderBy('ordre')
                    ->orderBy('id')
                    ->get()
                : collect();

            $usingCustomSlides = $useCustom && $activeSlides->isNotEmpty();

            $slides = $usingCustomSlides
                ? $activeSlides->map(fn (LoginSlide $slide): array => [
                    'id' => 'custom-'.$slide->id,
                    'image' => $slide->image_path,
                    'title' => $slide->titre,
                    'subtitle' => $slide->message ?? '',
                ])->values()->all()
                : self::DEFAULT_SCENES;

            $seconds = $setting instanceof LoginSetting
                ? (int) $setting->rotation_interval_seconds
                : self::DEFAULT_ROTATION_SECONDS;
            $seconds = max(2, min(30, $seconds));

            return [
                'slides' => $slides,
                'using_custom_slides' => $usingCustomSlides,
                'rotation_interval_ms' => $seconds * 1000,
                'branding' => [
                    'brand_name' => $this->resolveSettingString($setting?->brand_name, self::DEFAULT_BRANDING['brand_name']),
                    'brand_subtitle' => $this->resolveSettingString($setting?->brand_subtitle, self::DEFAULT_BRANDING['brand_subtitle']),
                    'logo_url' => $this->resolveSettingString($setting?->logo_path, self::DEFAULT_BRANDING['logo_url']),
                    'footer_left' => $this->resolveSettingString($setting?->footer_left, self::DEFAULT_BRANDING['footer_left']),
                    'footer_right' => $this->resolveSettingString($setting?->footer_right, self::DEFAULT_BRANDING['footer_right']),
                    'support_label' => $this->resolveSettingString($setting?->support_label, self::DEFAULT_BRANDING['support_label']),
                    // Lorsque les réglages ont été enregistrés, un téléphone/email volontairement vidé masque ce canal
                    'support_phone' => $setting ? trim((string) ($setting->support_phone ?? '')) : self::DEFAULT_BRANDING['support_phone'],
                    'support_email' => $setting ? trim((string) ($setting->support_email ?? '')) : self::DEFAULT_BRANDING['support_email'],
                ],
            ];
        } catch (Throwable) {
            return $this->defaultPayload();
        }
    }

    /**
     * Retourne le payload SYGES par défaut complet.
     *
     * @return array{
     *     slides: array<int, array{id: string, image: string, title: string, subtitle: string}>,
     *     using_custom_slides: bool,
     *     rotation_interval_ms: int,
     *     branding: array{
     *         brand_name: string,
     *         brand_subtitle: string,
     *         logo_url: string,
     *         footer_left: string,
     *         footer_right: string,
     *         support_label: string,
     *         support_phone: string,
     *         support_email: string
     *     }
     * }
     */
    private function defaultPayload(): array
    {
        return [
            'slides' => self::DEFAULT_SCENES,
            'using_custom_slides' => false,
            'rotation_interval_ms' => self::DEFAULT_ROTATION_SECONDS * 1000,
            'branding' => self::DEFAULT_BRANDING,
        ];
    }

    /**
     * Retourne la configuration d'identité de la barre latérale (après connexion) et du favicon navigateur (mise en cache).
     *
     * Si `apply_branding_to_app` est désactivé, conserve la marque SYGES dans la barre latérale après connexion ;
     * si activé, réutilise les informations personnalisées (ou leurs surcharges dédiées à la barre latérale).
     * Le favicon suit automatiquement le logo personnalisé dès lors que `sync_favicon` est activé et qu'un logo client est défini.
     *
     * @return array{
     *     use_custom_app_branding: bool,
     *     brand_name: string,
     *     brand_subtitle: string,
     *     logo_url: string,
     *     sync_favicon: bool,
     *     favicon_url: string|null
     * }
     */
    public function getAppBranding(): array
    {
        return Cache::rememberForever(self::APP_BRANDING_CACHE_KEY, function (): array {
            return $this->buildAppBrandingFromDatabase();
        });
    }

    /**
     * Construit le payload d'identité connectée et favicon avec repli sécurisé sur SYGES.
     *
     * @return array{
     *     use_custom_app_branding: bool,
     *     brand_name: string,
     *     brand_subtitle: string,
     *     logo_url: string,
     *     sync_favicon: bool,
     *     favicon_url: string|null
     * }
     */
    private function buildAppBrandingFromDatabase(): array
    {
        $defaultAppName = (string) (config('app.name') ?: self::DEFAULT_BRANDING['brand_name']);

        try {
            if (! Schema::hasTable('login_settings')) {
                return $this->defaultAppBranding($defaultAppName);
            }

            $setting = LoginSetting::first();
            // Repli immédiat si aucune personnalisation n'a encore été enregistrée en base
            if (! $setting instanceof LoginSetting) {
                return $this->defaultAppBranding($defaultAppName);
            }

            $useCustomApp = (bool) $setting->apply_branding_to_app;
            $syncFavicon = (bool) $setting->sync_favicon;

            $loginLogoUrl = $this->resolveSettingString($setting->logo_path, self::DEFAULT_BRANDING['logo_url']);
            $resolvedAppLogo = $this->resolveSettingString($setting->app_logo_path, $loginLogoUrl);

            $resolvedBrandName = $useCustomApp
                ? $this->resolveSettingString(
                    $setting->app_brand_name,
                    $this->resolveSettingString($setting->brand_name, $defaultAppName)
                )
                : $defaultAppName;

            $resolvedBrandSubtitle = $useCustomApp
                ? $this->resolveSettingString(
                    $setting->app_brand_subtitle,
                    $this->resolveSettingString($setting->brand_subtitle, 'Gestion Scolaire')
                )
                : 'Gestion Scolaire';

            $resolvedLogoUrl = $useCustomApp ? $resolvedAppLogo : self::DEFAULT_BRANDING['logo_url'];

            // Lorsque le favicon synchronisé est activé et qu'un logo personnalisé existe, l'applique à l'onglet
            $faviconCandidate = $useCustomApp ? $resolvedAppLogo : $loginLogoUrl;
            $faviconUrl = ($syncFavicon && $faviconCandidate !== self::DEFAULT_BRANDING['logo_url'])
                ? $faviconCandidate
                : null;

            return [
                'use_custom_app_branding' => $useCustomApp,
                'brand_name' => $resolvedBrandName,
                'brand_subtitle' => $resolvedBrandSubtitle,
                'logo_url' => $resolvedLogoUrl,
                'sync_favicon' => $syncFavicon,
                'favicon_url' => $faviconUrl,
            ];
        } catch (Throwable) {
            return $this->defaultAppBranding($defaultAppName);
        }
    }

    /**
     * Retourne l'identité par défaut SYGES pour l'espace connecté et le favicon.
     *
     * @param string $defaultAppName Nom applicatif par défaut issu de la configuration
     * @return array{
     *     use_custom_app_branding: bool,
     *     brand_name: string,
     *     brand_subtitle: string,
     *     logo_url: string,
     *     sync_favicon: bool,
     *     favicon_url: string|null
     * }
     */
    private function defaultAppBranding(string $defaultAppName): array
    {
        return [
            'use_custom_app_branding' => false,
            'brand_name' => $defaultAppName,
            'brand_subtitle' => 'Gestion Scolaire',
            'logo_url' => self::DEFAULT_BRANDING['logo_url'],
            'sync_favicon' => true,
            'favicon_url' => null,
        ];
    }

    /**
     * Invalide le cache afin que la prochaine visite de `/login` et des vues connectées reflète immédiatement les modifications.
     *
     * @return void
     */
    public function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
        Cache::forget(self::APP_BRANDING_CACHE_KEY);
    }

    /**
     * Retourne ou initialise l'unique enregistrement de configuration globale de connexion.
     *
     * @return LoginSetting
     */
    public function getOrCreateSettings(): LoginSetting
    {
        return LoginSetting::first() ?? LoginSetting::create([
            'use_custom_slides' => false,
            'rotation_interval_seconds' => self::DEFAULT_ROTATION_SECONDS,
            'brand_name' => self::DEFAULT_BRANDING['brand_name'],
            'brand_subtitle' => self::DEFAULT_BRANDING['brand_subtitle'],
            'logo_path' => self::DEFAULT_BRANDING['logo_url'],
            'footer_left' => self::DEFAULT_BRANDING['footer_left'],
            'footer_right' => self::DEFAULT_BRANDING['footer_right'],
            'support_label' => self::DEFAULT_BRANDING['support_label'],
            'support_phone' => self::DEFAULT_BRANDING['support_phone'],
            'support_email' => self::DEFAULT_BRANDING['support_email'],
            'apply_branding_to_app' => false,
            'app_brand_name' => null,
            'app_brand_subtitle' => null,
            'app_logo_path' => null,
            'sync_favicon' => true,
        ]);
    }

    /**
     * Enregistre une image téléversée sur le disque public en l'optimisant doucement si possible
     * (redimensionnement au maximum à `maxDim` pixels pour éviter de charger des photos de téléphone
     * brutes de plusieurs mégaoctets sur la page de login).
     *
     * @param UploadedFile $file Fichier image reçu
     * @param string $directory Sous-répertoire du disque `public` (ex: `login-slides`)
     * @param int $maxDim Dimension maximale en pixels (largeur ou hauteur)
     * @return string URL publique prête pour l'affichage (`Storage::url(...)`)
     */
    public function storeOptimizedImage(UploadedFile $file, string $directory, int $maxDim = 1600): string
    {
        $ext = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'jpg');

        // SVG ou environnements sans GD : stockage direct sans transformation
        if ($ext === 'svg' || ! extension_loaded('gd')) {
            return $this->storeRawPublicFile($file, $directory);
        }

        $optimized = $this->tryOptimizeImageBytes($file, $ext, $maxDim);
        if ($optimized === null) {
            return $this->storeRawPublicFile($file, $directory);
        }

        $filename = $directory.'/'.Str::uuid()->toString().'.'.$optimized['ext'];
        Storage::disk('public')->put($filename, $optimized['bytes']);

        return Storage::url($filename);
    }

    /**
     * Supprime physiquement une image précédemment stockée dans `/storage/login-*` lors d'un remplacement ou d'une suppression.
     *
     * @param string|null $publicUrl URL publique enregistrée en base
     * @return void
     */
    public function deleteManagedStorageFile(?string $publicUrl): void
    {
        if (empty($publicUrl) || ! str_starts_with($publicUrl, '/storage/')) {
            return;
        }

        $relativePath = Str::after($publicUrl, '/storage/');
        if (str_starts_with($relativePath, 'login-slides/') || str_starts_with($relativePath, 'login-branding/')) {
            Storage::disk('public')->delete($relativePath);
        }
    }

    /**
     * Tente de redimensionner et compresser l'image en mémoire via GD sans écrire hors disque `public`.
     *
     * @param UploadedFile $file Fichier source
     * @param string $ext Extension détectée
     * @param int $maxDim Dimension max autorisée
     * @return array{bytes: string, ext: string}|null
     */
    private function tryOptimizeImageBytes(UploadedFile $file, string $ext, int $maxDim): ?array
    {
        $realPath = $file->getRealPath();
        if (! $realPath || ! is_file($realPath)) {
            return null;
        }

        $raw = @file_get_contents($realPath);
        if ($raw === false) {
            return null;
        }

        $imageInfo = @getimagesizefromstring($raw);
        if (! $imageInfo || empty($imageInfo[0]) || empty($imageInfo[1])) {
            return null;
        }

        [$origW, $origH, $imageType] = $imageInfo;

        $src = @imagecreatefromstring($raw);
        if (! $src) {
            return null;
        }

        $src = $this->applyExifOrientationIfNeeded($src, $realPath, $imageType);
        $curW = imagesx($src);
        $curH = imagesy($src);

        // Conserve les dimensions exactes si déjà inférieures à maxDim et légères
        if ($curW <= $maxDim && $curH <= $maxDim && strlen($raw) <= 600 * 1024) {
            imagedestroy($src);

            return null;
        }

        $ratio = min($maxDim / max(1, $curW), $maxDim / max(1, $curH), 1.0);
        $targetW = max(1, (int) round($curW * $ratio));
        $targetH = max(1, (int) round($curH * $ratio));

        $dst = imagecreatetruecolor($targetW, $targetH);
        if (! $dst) {
            imagedestroy($src);

            return null;
        }

        $preserveAlpha = in_array($imageType, [IMAGETYPE_PNG, IMAGETYPE_WEBP, IMAGETYPE_GIF], true)
            && $this->hasAlphaChannel($src, $imageType, $curW, $curH);

        if ($preserveAlpha) {
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
            $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
            if ($transparent !== false) {
                imagefill($dst, 0, 0, $transparent);
            }
        }

        imagecopyresampled($dst, $src, 0, 0, 0, 0, $targetW, $targetH, $curW, $curH);
        imagedestroy($src);

        ob_start();
        $outExt = 'jpg';
        if ($preserveAlpha) {
            // Préserve la transparence (utile pour les logos PNG/WebP)
            imagepng($dst, null, 7);
            $outExt = 'png';
        } else {
            // Convertit les lourdes photos caméra PNG/JPG opaques en JPEG optimisé haute qualité
            imagejpeg($dst, null, 86);
            $outExt = 'jpg';
        }
        $bytes = ob_get_clean();
        imagedestroy($dst);

        if ($bytes === false || $bytes === '') {
            return null;
        }

        return ['bytes' => $bytes, 'ext' => $outExt];
    }

    /**
     * Enregistre un fichier image brut sur le disque public en vérifiant le succès de l'écriture.
     *
     * @param UploadedFile $file Fichier uploadé à sauvegarder tel quel
     * @param string $directory Répertoire cible sur le disque `public`
     * @return string URL publique générée pour le fichier enregistré
     */
    private function storeRawPublicFile(UploadedFile $file, string $directory): string
    {
        $path = $file->store($directory, 'public');
        if ($path === false) {
            throw new RuntimeException('Impossible d’enregistrer le fichier image sur le stockage public.');
        }

        return Storage::url($path);
    }

    /**
     * Corrige automatiquement l'orientation EXIF des photographies JPEG issues de smartphones.
     *
     * @param \GdImage $image Ressource GD source
     * @param string $realPath Chemin physique temporaire
     * @param int $imageType Type `IMAGETYPE_*`
     * @return \GdImage
     */
    private function applyExifOrientationIfNeeded(\GdImage $image, string $realPath, int $imageType): \GdImage
    {
        if ($imageType !== IMAGETYPE_JPEG || ! function_exists('exif_read_data')) {
            return $image;
        }

        $exif = @exif_read_data($realPath);
        $orientation = (int) ($exif['Orientation'] ?? 1);

        $rotated = match ($orientation) {
            3 => imagerotate($image, 180, 0),
            6 => imagerotate($image, -90, 0),
            8 => imagerotate($image, 90, 0),
            default => $image,
        };

        if ($rotated instanceof \GdImage && $rotated !== $image) {
            imagedestroy($image);

            return $rotated;
        }

        return $image;
    }

    /**
     * Détecte sommairement si une image PNG/WebP comporte de la transparence afin de ne pas l'aplatir en JPEG.
     *
     * @param \GdImage $image Ressource GD
     * @param int $imageType Constante IMAGETYPE_*
     * @param int $width Largeur
     * @param int $height Hauteur
     * @return bool
     */
    private function hasAlphaChannel(\GdImage $image, int $imageType, int $width, int $height): bool
    {
        if ($imageType === IMAGETYPE_GIF) {
            return true;
        }

        $stepX = max(1, (int) floor($width / 12));
        $stepY = max(1, (int) floor($height / 12));

        for ($x = 0; $x < $width; $x += $stepX) {
            for ($y = 0; $y < $height; $y += $stepY) {
                $rgba = imagecolorat($image, $x, $y);
                $alpha = ($rgba >> 24) & 0x7F;
                if ($alpha > 0) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Retourne une chaîne nettoyée ou sa valeur par défaut si nulle/vide.
     *
     * @param string|null $value Valeur configurée
     * @param string $fallback Valeur par défaut
     * @return string
     */
    private function resolveSettingString(?string $value, string $fallback): string
    {
        $trimmed = trim((string) ($value ?? ''));

        return $trimmed !== '' ? $trimmed : $fallback;
    }
}
