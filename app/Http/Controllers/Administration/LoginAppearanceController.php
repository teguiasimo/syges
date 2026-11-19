<?php

namespace App\Http\Controllers\Administration;

use App\Http\Controllers\Controller;
use App\Models\Institution;
use App\Models\LoginSetting;
use App\Models\LoginSlide;
use App\Services\LoginAppearanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Contrôleur de personnalisation du carrousel et de l'apparence de la page de connexion (`/login`).
 *
 * Permet aux administrateurs habilités (`institutions.edit`) d'importer des photos scolaires,
 * de rédiger leurs titres et messages, de régler la cadence de rotation, ou de basculer entre
 * les 10 photos officielles SYGES par défaut et la galerie personnalisée de l'établissement.
 */
class LoginAppearanceController extends Controller implements HasMiddleware
{
    /**
     * Service métier de gestion des réglages et médias de connexion.
     */
    protected LoginAppearanceService $appearanceService;

    /**
     * Constructeur injectant le service d'apparence de connexion.
     *
     * @param LoginAppearanceService $appearanceService Service de configuration login
     */
    public function __construct(LoginAppearanceService $appearanceService)
    {
        $this->appearanceService = $appearanceService;
    }

    /**
     * Déclare le middleware RBAC requis pour accéder ou modifier la personnalisation du login.
     *
     * @return array<int, Middleware>
     */
    public static function middleware(): array
    {
        return [
            new Middleware('can:institutions.edit'),
        ];
    }

    /**
     * Affiche l'interface de configuration du carrousel et du branding de la page de connexion.
     *
     * @param Request $request Requête HTTP entrante
     * @return Response Vue Inertia de configuration
     */
    public function index(Request $request): Response
    {
        $settings = $this->appearanceService->getOrCreateSettings();
        $slides = LoginSlide::orderBy('ordre')->orderBy('id')->get();
        $institution = Institution::first();

        return Inertia::render('administration/institution/login-appearance', [
            'settings' => $settings,
            'slides' => $slides,
            'defaultScenes' => LoginAppearanceService::DEFAULT_SCENES,
            'defaultBranding' => LoginAppearanceService::DEFAULT_BRANDING,
            'institution' => $institution ? [
                'nom' => $institution->nom,
                'sigle' => $institution->sigle,
                'devise' => $institution->devise,
                'logo' => $institution->logo,
                'email' => $institution->email,
                'telephone_1' => $institution->telephone_1,
            ] : null,
            'effectivePreview' => $this->appearanceService->getPublicPayload(),
        ]);
    }

    /**
     * Met à jour les paramètres généraux d'apparence et le mode du carrousel de connexion.
     *
     * @param Request $request Requête HTTP validée (avec logo optionnel multipart)
     * @return RedirectResponse
     */
    public function updateSettings(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'use_custom_slides' => ['required', 'boolean'],
            'rotation_interval_seconds' => ['required', 'integer', 'min:2', 'max:30'],
            'brand_name' => ['nullable', 'string', 'max:120'],
            'brand_subtitle' => ['nullable', 'string', 'max:160'],
            'logo_path' => ['nullable', 'string', 'max:255'],
            'logo_file' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,svg,webp', 'max:5120'],
            'footer_left' => ['nullable', 'string', 'max:160'],
            'footer_right' => ['nullable', 'string', 'max:80'],
            'support_label' => ['nullable', 'string', 'max:80'],
            'support_phone' => ['nullable', 'string', 'max:40'],
            'support_email' => ['nullable', 'string', 'max:120'],
            'apply_branding_to_app' => ['sometimes', 'boolean'],
            'app_brand_name' => ['nullable', 'string', 'max:80'],
            'app_brand_subtitle' => ['nullable', 'string', 'max:120'],
            'app_logo_path' => ['nullable', 'string', 'max:255'],
            'app_logo_file' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,svg,webp', 'max:5120'],
            'sync_favicon' => ['sometimes', 'boolean'],
        ]);

        $settings = $this->appearanceService->getOrCreateSettings();
        $oldLogoPath = $settings->logo_path;
        $oldAppLogoPath = $settings->app_logo_path;

        if ($request->hasFile('logo_file')) {
            $newLogoUrl = $this->appearanceService->storeOptimizedImage(
                $request->file('logo_file'),
                'login-branding',
                512
            );
            $validated['logo_path'] = $newLogoUrl;
            if ($oldLogoPath && $oldLogoPath !== $oldAppLogoPath) {
                $this->appearanceService->deleteManagedStorageFile($oldLogoPath);
            }
        } elseif ($request->boolean('reset_logo_to_default')) {
            if ($oldLogoPath && $oldLogoPath !== $oldAppLogoPath) {
                $this->appearanceService->deleteManagedStorageFile($oldLogoPath);
            }
            $validated['logo_path'] = LoginAppearanceService::DEFAULT_BRANDING['logo_url'];
        }

        if ($request->hasFile('app_logo_file')) {
            $newAppLogoUrl = $this->appearanceService->storeOptimizedImage(
                $request->file('app_logo_file'),
                'login-branding',
                512
            );
            $validated['app_logo_path'] = $newAppLogoUrl;
            if ($oldAppLogoPath && $oldAppLogoPath !== ($validated['logo_path'] ?? $oldLogoPath)) {
                $this->appearanceService->deleteManagedStorageFile($oldAppLogoPath);
            }
        } elseif ($request->boolean('reset_app_logo_to_shared')) {
            if ($oldAppLogoPath && $oldAppLogoPath !== ($validated['logo_path'] ?? $oldLogoPath)) {
                $this->appearanceService->deleteManagedStorageFile($oldAppLogoPath);
            }
            $validated['app_logo_path'] = null;
        }

        unset($validated['logo_file'], $validated['app_logo_file']);
        $settings->update($validated);
        $this->appearanceService->clearCache();

        return back()->with('success', 'Paramètres d’apparence et d’identité mis à jour avec succès.');
    }

    /**
     * Réinitialise l'apparence globale aux réglages par défaut SYGES (10 slides + branding d'origine).
     *
     * @return RedirectResponse
     */
    public function resetToDefaults(): RedirectResponse
    {
        $settings = $this->appearanceService->getOrCreateSettings();
        $this->appearanceService->deleteManagedStorageFile($settings->logo_path);
        $this->appearanceService->deleteManagedStorageFile($settings->app_logo_path);

        $settings->update([
            'use_custom_slides' => false,
            'rotation_interval_seconds' => LoginAppearanceService::DEFAULT_ROTATION_SECONDS,
            'brand_name' => LoginAppearanceService::DEFAULT_BRANDING['brand_name'],
            'brand_subtitle' => LoginAppearanceService::DEFAULT_BRANDING['brand_subtitle'],
            'logo_path' => LoginAppearanceService::DEFAULT_BRANDING['logo_url'],
            'footer_left' => LoginAppearanceService::DEFAULT_BRANDING['footer_left'],
            'footer_right' => LoginAppearanceService::DEFAULT_BRANDING['footer_right'],
            'support_label' => LoginAppearanceService::DEFAULT_BRANDING['support_label'],
            'support_phone' => LoginAppearanceService::DEFAULT_BRANDING['support_phone'],
            'support_email' => LoginAppearanceService::DEFAULT_BRANDING['support_email'],
            'apply_branding_to_app' => false,
            'app_brand_name' => null,
            'app_brand_subtitle' => null,
            'app_logo_path' => null,
            'sync_favicon' => true,
        ]);

        $this->appearanceService->clearCache();

        return back()->with('success', 'Apparence rétablie aux valeurs SYGES par défaut.');
    }

    /**
     * Ajoute un nouveau slide personnalisé (photo importée + titre + message).
     * Active automatiquement le mode personnalisé lorsque ce slide est actif afin que l'effet soit immédiat.
     *
     * @param Request $request Requête multipart contenant l'image et ses textes
     * @return RedirectResponse
     */
    public function storeSlide(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'titre' => ['required', 'string', 'max:120'],
            'message' => ['nullable', 'string', 'max:255'],
            'image_file' => ['required', 'image', 'mimes:jpeg,png,jpg,webp', 'max:10240'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $isActive = array_key_exists('is_active', $validated) ? (bool) $validated['is_active'] : true;
        $imageUrl = $this->appearanceService->storeOptimizedImage(
            $request->file('image_file'),
            'login-slides',
            1600
        );

        DB::transaction(function () use ($validated, $imageUrl, $isActive): void {
            $nextOrder = ((int) LoginSlide::max('ordre')) + 1;

            LoginSlide::create([
                'titre' => trim($validated['titre']),
                'message' => isset($validated['message']) ? trim((string) $validated['message']) : null,
                'image_path' => $imageUrl,
                'ordre' => $nextOrder,
                'is_active' => $isActive,
            ]);

            if ($isActive) {
                $settings = $this->appearanceService->getOrCreateSettings();
                if (! $settings->use_custom_slides) {
                    $settings->update(['use_custom_slides' => true]);
                }
            }
        });

        $this->appearanceService->clearCache();

        return back()->with('success', 'Slide ajouté au carrousel de connexion avec succès.');
    }

    /**
     * Met à jour un slide existant (textes, activation ou remplacement de la photo).
     *
     * @param Request $request Requête HTTP entrante
     * @param LoginSlide $slide Diapositive ciblée
     * @return RedirectResponse
     */
    public function updateSlide(Request $request, LoginSlide $slide): RedirectResponse
    {
        $validated = $request->validate([
            'titre' => ['required', 'string', 'max:120'],
            'message' => ['nullable', 'string', 'max:255'],
            'image_file' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:10240'],
            'is_active' => ['required', 'boolean'],
        ]);

        $newImagePath = $slide->image_path;
        if ($request->hasFile('image_file')) {
            $newImagePath = $this->appearanceService->storeOptimizedImage(
                $request->file('image_file'),
                'login-slides',
                1600
            );
            $this->appearanceService->deleteManagedStorageFile($slide->image_path);
        }

        $slide->update([
            'titre' => trim($validated['titre']),
            'message' => isset($validated['message']) ? trim((string) $validated['message']) : null,
            'image_path' => $newImagePath,
            'is_active' => (bool) $validated['is_active'],
        ]);

        $this->appearanceService->clearCache();

        return back()->with('success', 'Slide mis à jour avec succès.');
    }

    /**
     * Déplace un slide vers le haut ou vers le bas dans l'ordre d'affichage du carrousel.
     *
     * @param Request $request Requête contenant `direction` ('up' ou 'down')
     * @param LoginSlide $slide Diapositive à déplacer
     * @return RedirectResponse
     */
    public function reorderSlide(Request $request, LoginSlide $slide): RedirectResponse
    {
        $validated = $request->validate([
            'direction' => ['required', 'string', 'in:up,down'],
        ]);

        $ordered = LoginSlide::orderBy('ordre')->orderBy('id')->get()->values();
        $currentIndex = $ordered->search(fn (LoginSlide $item): bool => $item->id === $slide->id);

        if ($currentIndex !== false) {
            $swapIndex = $validated['direction'] === 'up' ? $currentIndex - 1 : $currentIndex + 1;
            if (isset($ordered[$swapIndex])) {
                $slidesArray = $ordered->all();
                [$slidesArray[$currentIndex], $slidesArray[$swapIndex]] = [$slidesArray[$swapIndex], $slidesArray[$currentIndex]];

                DB::transaction(function () use ($slidesArray): void {
                    foreach ($slidesArray as $index => $item) {
                        $item->update(['ordre' => $index + 1]);
                    }
                });

                $this->appearanceService->clearCache();
            }
        }

        return back();
    }

    /**
     * Supprime définitivement un slide personnalisé et nettoie son fichier image associé.
     * Si plus aucun slide actif ne subsiste, l'écran `/login` rebascule automatiquement sur les 10 photos SYGES par défaut.
     *
     * @param LoginSlide $slide Diapositive à supprimer
     * @return RedirectResponse
     */
    public function destroySlide(LoginSlide $slide): RedirectResponse
    {
        $imagePath = $slide->image_path;

        DB::transaction(function () use ($slide): void {
            $slide->delete();

            // Renumérote proprement les slides restants
            $remaining = LoginSlide::orderBy('ordre')->orderBy('id')->get();
            foreach ($remaining as $idx => $item) {
                if ($item->ordre !== ($idx + 1)) {
                    $item->update(['ordre' => $idx + 1]);
                }
            }

            if ($remaining->where('is_active', true)->isEmpty()) {
                LoginSetting::query()->update(['use_custom_slides' => false]);
            }
        });

        $this->appearanceService->deleteManagedStorageFile($imagePath);
        $this->appearanceService->clearCache();

        return back()->with('success', 'Slide supprimé du carrousel.');
    }
}
