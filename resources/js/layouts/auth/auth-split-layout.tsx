import React, { useState, useEffect, useCallback } from 'react';
import { Link, usePage } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { home } from '@/routes';
import type { AuthLayoutProps } from '@/types';
import {
    DEFAULT_LOGIN_BRANDING,
    DEFAULT_LOGIN_SCENES,
    type LoginAppearancePayload,
} from '@/types/login-appearance';

/**
 * Cadence de rotation par défaut du diaporama en millisecondes (4 secondes).
 */
const DEFAULT_ROTATION_INTERVAL_MS = 4000;

/**
 * Layout d'authentification scindé professionnel et dynamique (AuthSplitLayout).
 *
 * Section gauche (Écran 22" et Desktop) :
 * - Carrousel automatique et bouclé sur les N diapositives configurées dans Administration
 *   (ou les 10 photographies scolaires par défaut si aucune personnalisation active n'est définie).
 * - Transitions en fondu enchaîné et affichage conditionnel des contrôles selon le nombre réel de slides.
 *
 * Section droite :
 * - Formulaire de connexion épuré, centré et directement accessible.
 *
 * @param {AuthLayoutProps} props Les propriétés passées au layout.
 * @returns {JSX.Element}
 */
export default function AuthSplitLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    const { name, loginAppearance } = usePage<{
        name?: string;
        loginAppearance?: LoginAppearancePayload | null;
    }>().props;

    // Utilise uniquement les N slides configurés s'il y en a ; sinon retombe sur les 10 scènes par défaut SYGES
    const slides =
        loginAppearance?.slides && loginAppearance.slides.length > 0
            ? loginAppearance.slides
            : DEFAULT_LOGIN_SCENES;

    const rotationMs =
        loginAppearance?.rotation_interval_ms && loginAppearance.rotation_interval_ms >= 2000
            ? loginAppearance.rotation_interval_ms
            : DEFAULT_ROTATION_INTERVAL_MS;

    const branding = loginAppearance?.branding ?? DEFAULT_LOGIN_BRANDING;
    const brandName = branding.brand_name || name || DEFAULT_LOGIN_BRANDING.brand_name;
    const brandSubtitle = branding.brand_subtitle || DEFAULT_LOGIN_BRANDING.brand_subtitle;
    const logoUrl = branding.logo_url || DEFAULT_LOGIN_BRANDING.logo_url;
    const footerLeft = branding.footer_left || DEFAULT_LOGIN_BRANDING.footer_left;
    const footerRight = branding.footer_right || DEFAULT_LOGIN_BRANDING.footer_right;

    const [activeIdx, setActiveIdx] = useState(0);
    const [resolvedLogoUrl, setResolvedLogoUrl] = useState(logoUrl);

    useEffect(() => {
        setResolvedLogoUrl(logoUrl);
    }, [logoUrl]);

    // Réinitialise l'index si le nombre de slides évolue pour éviter tout dépassement de tableau
    useEffect(() => {
        if (activeIdx >= slides.length) {
            setActiveIdx(0);
        }
    }, [activeIdx, slides.length]);

    const nextScene = useCallback(() => {
        if (slides.length <= 1) return;
        setActiveIdx((prev) => (prev + 1) % slides.length);
    }, [slides.length]);

    const prevScene = useCallback(() => {
        if (slides.length <= 1) return;
        setActiveIdx((prev) => (prev - 1 + slides.length) % slides.length);
    }, [slides.length]);

    // Défilement automatique en boucle sur les slides actifs réels (pas de boucle si 1 seul slide)
    useEffect(() => {
        if (slides.length <= 1) return;

        const timer = setInterval(() => {
            setActiveIdx((prev) => (prev + 1) % slides.length);
        }, rotationMs);

        return () => clearInterval(timer);
    }, [activeIdx, slides.length, rotationMs]);

    // Préchargement des photographies actives en arrière-plan pour des transitions sans scintillement
    useEffect(() => {
        slides.forEach((scene) => {
            const img = new Image();
            img.src = scene.image;
        });
    }, [slides]);

    const safeIdx = activeIdx < slides.length ? activeIdx : 0;
    const activeScene = slides[safeIdx];

    return (
        <div className="relative min-h-screen w-full overflow-x-hidden bg-background">
            <div className="grid min-h-screen w-full grid-cols-1 lg:grid-cols-12">
                {/* ========================================================= */}
                {/* SECTION 1 (GAUCHE) : CARROUSEL PHOTOGRAPHIQUE CONFIGURABLE*/}
                {/* 58% sur grand écran (7/12)                                */}
                {/* ========================================================= */}
                <div
                    className="relative hidden min-h-screen flex-col justify-between overflow-hidden bg-slate-950 p-8 lg:flex lg:col-span-7 xl:col-span-7 xl:p-12 2xl:p-16 text-white border-r border-slate-800/60"
                >
                    {/* Fond d'ambiance avec dégradés doux */}
                    <div className="absolute inset-0 bg-linear-to-br from-slate-950 via-slate-900 to-indigo-950 pointer-events-none" />
                    <div className="absolute -top-32 -left-32 size-128 rounded-full bg-indigo-600/10 blur-3xl pointer-events-none" />
                    <div className="absolute -bottom-32 right-1/4 size-128 rounded-full bg-blue-600/10 blur-3xl pointer-events-none" />

                    {/* En-tête : Logo et identité de l'établissement/plateforme */}
                    <div className="relative z-20 min-w-0">
                        <Link
                            href={home()}
                            className="inline-flex max-w-full items-center gap-3 transition-opacity hover:opacity-90"
                        >
                            <div className="flex size-11 shrink-0 items-center justify-center rounded-2xl overflow-hidden bg-white/10 p-1.5 shadow-lg ring-1 ring-white/15 backdrop-blur-md">
                                <img
                                    src={resolvedLogoUrl}
                                    alt={`Logo ${brandName}`}
                                    onError={() => setResolvedLogoUrl(DEFAULT_LOGIN_BRANDING.logo_url)}
                                    className="size-full object-contain"
                                />
                            </div>
                            <div className="flex min-w-0 flex-col">
                                <span className="truncate text-xl xl:text-2xl font-bold tracking-tight text-white">
                                    {brandName}
                                </span>
                                <span className="truncate text-xs text-slate-400">
                                    {brandSubtitle}
                                </span>
                            </div>
                        </Link>
                    </div>

                    {/* Vitrine photographique animée (exactement slides.length diapositives en boucle) */}
                    <div className="relative z-20 my-auto py-4">
                        <div className="relative overflow-hidden rounded-3xl border border-white/10 bg-slate-900/50 shadow-2xl backdrop-blur-sm">
                            {/* Hauteur fluide proportionnelle à la diagonale de l'écran (portable 13", 22" ou très grand écran) */}
                            <div className="relative h-[clamp(20rem,52vh,38rem)] w-full overflow-hidden">
                                {slides.map((scene, idx) => (
                                    <div
                                        key={scene.id}
                                        className={`absolute inset-0 transition-opacity duration-1000 ease-in-out ${
                                            idx === safeIdx ? 'opacity-100 z-10' : 'opacity-0 z-0'
                                        }`}
                                    >
                                        <img
                                            src={scene.image}
                                            alt={scene.title}
                                            className="h-full w-full object-cover object-center transform scale-102 transition-transform duration-6000 ease-out"
                                        />
                                        {/* Dégradé doux pour faire ressortir la légende sans masquer l'image */}
                                        <div className="absolute inset-0 bg-gradient-to-t from-slate-950/90 via-slate-950/25 to-transparent" />
                                    </div>
                                ))}

                                {/* Boutons discrets précédent / suivant affichés si plus d'un slide */}
                                {slides.length > 1 && (
                                    <div className="absolute top-4 right-4 z-20 flex items-center gap-1.5">
                                        <button
                                            type="button"
                                            onClick={prevScene}
                                            className="flex size-8 items-center justify-center rounded-full bg-black/40 text-white backdrop-blur-md transition-colors hover:bg-black/70 cursor-pointer"
                                            aria-label="Image précédente"
                                        >
                                            <ChevronLeft className="size-4" />
                                        </button>
                                        <button
                                            type="button"
                                            onClick={nextScene}
                                            className="flex size-8 items-center justify-center rounded-full bg-black/40 text-white backdrop-blur-md transition-colors hover:bg-black/70 cursor-pointer"
                                            aria-label="Image suivante"
                                        >
                                            <ChevronRight className="size-4" />
                                        </button>
                                    </div>
                                )}

                                {/* Légende au bas de l'image */}
                                <div className="absolute bottom-0 inset-x-0 z-20 p-6 sm:p-8 space-y-1">
                                    <h3 className="text-xl font-bold text-white tracking-tight drop-shadow-sm">
                                        {activeScene.title}
                                    </h3>
                                    {activeScene.subtitle && (
                                        <p className="text-sm text-slate-300 drop-shadow-sm">
                                            {activeScene.subtitle}
                                        </p>
                                    )}
                                </div>
                            </div>
                        </div>

                        {/* Pastilles indicatrices strictement proportionnelles au nombre de slides configurés */}
                        {slides.length > 1 && (
                            <div className="flex items-center justify-center gap-1.5 pt-4">
                                {slides.map((scene, idx) => (
                                    <button
                                        key={scene.id}
                                        type="button"
                                        onClick={() => setActiveIdx(idx)}
                                        className={`h-1.5 rounded-full transition-all duration-300 cursor-pointer ${
                                            idx === safeIdx
                                                ? 'w-7 bg-white shadow-sm'
                                                : 'w-1.5 bg-white/25 hover:bg-white/50'
                                        }`}
                                        aria-label={`Photo ${idx + 1}: ${scene.title}`}
                                    />
                                ))}
                            </div>
                        )}
                    </div>

                    {/* Pied de page sobre */}
                    <div className="relative z-20 border-t border-slate-800/60 pt-4">
                        <div className="flex items-center justify-between text-xs text-slate-400">
                            <span>{footerLeft}</span>
                            <span>{footerRight}</span>
                        </div>
                    </div>
                </div>

                {/* ========================================================= */}
                {/* SECTION 2 (DROITE) : FORMULAIRE DE CONNEXION ÉPURÉ       */}
                {/* 42% sur grand écran (5/12)                                */}
                {/* ========================================================= */}
                <div className="relative flex min-h-screen w-full flex-col justify-between p-6 sm:p-10 lg:col-span-5 xl:col-span-5 xl:p-12 2xl:p-16">
                    {/* Logo mobile (affiché uniquement sur smartphone/tablette) */}
                    <div className="flex items-center justify-between w-full pb-4 lg:hidden">
                        <Link href={home()} className="flex min-w-0 max-w-full items-center gap-2.5">
                            <div className="flex size-9 shrink-0 items-center justify-center rounded-xl overflow-hidden shadow-xs">
                                <img
                                    src={resolvedLogoUrl}
                                    alt={`Logo ${brandName}`}
                                    onError={() => setResolvedLogoUrl(DEFAULT_LOGIN_BRANDING.logo_url)}
                                    className="size-9 object-contain"
                                />
                            </div>
                            <div className="flex min-w-0 flex-col">
                                <span className="truncate text-base sm:text-lg font-bold tracking-tight text-foreground">
                                    {brandName}
                                </span>
                                <span className="truncate text-[11px] text-muted-foreground">
                                    {brandSubtitle}
                                </span>
                            </div>
                        </Link>
                    </div>

                    {/* Carte de connexion centrée, fluide sur mobile/tablette comme sur grand écran */}
                    <div className="my-auto mx-auto w-full max-w-md sm:max-w-lg lg:max-w-md xl:max-w-[29rem] 2xl:max-w-[31rem]">
                        <div className="rounded-3xl border border-border/70 bg-card/95 p-8 sm:p-10 shadow-xl backdrop-blur-xl">
                            {/* Titre sobre */}
                            <div className="space-y-1.5 text-center mb-7">
                                <h1 className="text-2xl font-bold tracking-tight text-foreground">
                                    {title || 'Connexion'}
                                </h1>
                                <p className="text-sm text-muted-foreground">
                                    {description || 'Entrez vos identifiants pour continuer'}
                                </p>
                            </div>

                            {/* Formulaire de connexion */}
                            {children}
                        </div>
                    </div>

                    {/* Bas de page minimaliste */}
                    <div className="pt-4 text-center text-xs text-muted-foreground/70">
                        <span>Connexion sécurisée SSL/TLS</span>
                    </div>
                </div>
            </div>
        </div>
    );
}
