import React, { useState, useEffect, useCallback } from 'react';
import { Link, usePage } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { home } from '@/routes';
import type { AuthLayoutProps } from '@/types';

/**
 * 10 photographies 100% réelles, authentiques et libres de droits illustrant le contexte scolaire au Cameroun :
 * En classe, dans la cour, l'école au village, en ville, la jeunesse camerounaise et l'éducation générale.
 * Aucune image générée par IA.
 */
const SCENES = [
    {
        id: 'classe-pedagogie',
        image: '/images/auth/real-classroom-cameroon.jpg',
        title: 'Apprentissage & Pédagogie Active',
        subtitle: 'Séance de cours et travail pratique en classe au Cameroun',
    },
    {
        id: 'classe-eleves',
        image: '/images/auth/real-students-class.jpg',
        title: 'Travail en Classe & Réflexion',
        subtitle: 'Élèves studieux, cahiers et transmission des savoirs fondamentaux',
    },
    {
        id: 'ecole-urbaine',
        image: '/images/auth/real-school-douala.jpg',
        title: 'Établissements Scolaires Urbains',
        subtitle: 'Élèves réunis au sein de l’École Publique de Bonadowito (Douala)',
    },
    {
        id: 'ecole-village',
        image: '/images/auth/village-school.jpg',
        title: 'L’École au Village',
        subtitle: 'Bâtiment et cour d’école en zone rurale (Bamena, Ouest Cameroun)',
    },
    {
        id: 'ecoliers-cameroun',
        image: '/images/auth/school-scene-1.jpg',
        title: 'Écoliers du Cameroun',
        subtitle: 'La jeunesse camerounaise et l’accès universel à l’éducation (Akok Bekoe)',
    },
    {
        id: 'defile-jeunesse',
        image: '/images/auth/school-scene-4.jpg',
        title: 'Fête de la Jeunesse & Civisme',
        subtitle: 'Défilé scolaire traditionnel du 11 février et cohésion nationale',
    },
    {
        id: 'batiment-rural',
        image: '/images/auth/rural-school-west.jpg',
        title: 'Infrastructures Scolaires de Proximité',
        subtitle: 'Établissements scolaires au cœur des terroirs locaux (Bapeng-Bameka)',
    },
    {
        id: 'rassemblement-nord',
        image: '/images/auth/school-scene-3.jpg',
        title: 'Rassemblement & Camaraderie',
        subtitle: 'Élèves réunies dans la cour (Maroua, Grand Nord Cameroun)',
    },
    {
        id: 'patrimoine-bankim',
        image: '/images/auth/real-schoolhouse-bankim.jpg',
        title: 'Patrimoine Éducatif Régional',
        subtitle: 'Bâtiment scolaire historique et cadre d’études (Bankim, Adamaoua)',
    },
    {
        id: 'cour-recreation',
        image: '/images/auth/school-scene-8.jpg',
        title: 'Cérémonie & Discipline Scolaire',
        subtitle: 'Cérémonie de levée des couleurs et rassemblement dans la cour',
    },
];

/**
 * Fréquence de défilement automatique du diaporama (en millisecondes).
 * 4 secondes pour un rythme dynamique, agréable et régulier.
 */
const ROTATION_INTERVAL_MS = 4000;

/**
 * Layout d'authentification scindé professionnel et allégé (AuthSplitLayout).
 *
 * Section gauche (Écran 22" et Desktop) :
 * - Carrousel fluide et automatique de 10 photographies réelles d'écoles du Cameroun.
 * - Défilement automatique continu toutes les 4 secondes.
 * - Transitions en fondu enchaîné et légendes sobres sans encombrement.
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
    const { name } = usePage().props;

    const [activeIdx, setActiveIdx] = useState(0);

    const nextScene = useCallback(() => {
        setActiveIdx((prev) => (prev + 1) % SCENES.length);
    }, []);

    const prevScene = useCallback(() => {
        setActiveIdx((prev) => (prev - 1 + SCENES.length) % SCENES.length);
    }, []);

    // Défilement automatique continu : avance toutes les 4s et réinitialise le compte à rebours lors d'un changement manuel
    useEffect(() => {
        const timer = setInterval(() => {
            setActiveIdx((prev) => (prev + 1) % SCENES.length);
        }, ROTATION_INTERVAL_MS);

        return () => clearInterval(timer);
    }, [activeIdx]);

    // Préchargement des 10 photographies en arrière-plan pour des transitions instantanées
    useEffect(() => {
        SCENES.forEach((scene) => {
            const img = new Image();
            img.src = scene.image;
        });
    }, []);

    const activeScene = SCENES[activeIdx];

    return (
        <div className="relative min-h-screen w-full overflow-x-hidden bg-background">
            <div className="grid min-h-screen grid-cols-1 lg:grid-cols-12">
                {/* ========================================================= */}
                {/* SECTION 1 (GAUCHE) : 10 PHOTOS SCOLAIRES DU CAMEROUN       */}
                {/* 58% sur grand écran (7/12)                                */}
                {/* ========================================================= */}
                <div
                    className="relative hidden min-h-screen flex-col justify-between overflow-hidden bg-slate-950 p-8 lg:flex lg:col-span-7 xl:col-span-7 xl:p-12 2xl:p-16 text-white border-r border-slate-800/60"
                >
                    {/* Fond d'ambiance avec dégradés doux */}
                    <div className="absolute inset-0 bg-linear-to-br from-slate-950 via-slate-900 to-indigo-950 pointer-events-none" />
                    <div className="absolute -top-32 -left-32 size-128 rounded-full bg-indigo-600/10 blur-3xl pointer-events-none" />
                    <div className="absolute -bottom-32 right-1/4 size-128 rounded-full bg-blue-600/10 blur-3xl pointer-events-none" />

                    {/* En-tête : Logo SYGES épuré */}
                    <div className="relative z-20">
                        <Link
                            href={home()}
                            className="inline-flex items-center gap-3 transition-opacity hover:opacity-90"
                        >
                            <div className="flex size-11 items-center justify-center rounded-2xl overflow-hidden bg-white/10 p-1.5 shadow-lg ring-1 ring-white/15 backdrop-blur-md">
                                <img
                                    src="/images/syges-icon.png"
                                    alt="Logo SYGES"
                                    className="size-full object-contain"
                                />
                            </div>
                            <div className="flex flex-col">
                                <span className="text-2xl font-bold tracking-tight text-white">
                                    {name ?? 'SYGES'}
                                </span>
                                <span className="text-xs text-slate-400">
                                    Gestion des Établissements Scolaires
                                </span>
                            </div>
                        </Link>
                    </div>

                    {/* Vitrine photographique animée des 10 scènes scolaires */}
                    <div className="relative z-20 my-auto py-4">
                        <div className="relative overflow-hidden rounded-3xl border border-white/10 bg-slate-900/50 shadow-2xl backdrop-blur-sm">
                            {/* Hauteur équilibrée pour écran 22 pouces */}
                            <div className="relative h-80 xl:h-96 2xl:h-112 w-full overflow-hidden">
                                {SCENES.map((scene, idx) => (
                                    <div
                                        key={scene.id}
                                        className={`absolute inset-0 transition-opacity duration-1000 ease-in-out ${
                                            idx === activeIdx ? 'opacity-100 z-10' : 'opacity-0 z-0'
                                        }`}
                                    >
                                        <img
                                            src={scene.image}
                                            alt={scene.title}
                                            className="h-full w-full object-cover object-center transform scale-102 transition-transform duration-6000 ease-out"
                                        />
                                        {/* Dégradé doux pour faire ressortir la légende sans masquer les visages */}
                                        <div className="absolute inset-0 bg-gradient-to-t from-slate-950/90 via-slate-950/25 to-transparent" />
                                    </div>
                                ))}

                                {/* Boutons discrets précédent / suivant */}
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

                                {/* Légende en une ligne courte au bas de l'image */}
                                <div className="absolute bottom-0 inset-x-0 z-20 p-6 sm:p-8 space-y-1">
                                    <h3 className="text-xl font-bold text-white tracking-tight drop-shadow-sm">
                                        {activeScene.title}
                                    </h3>
                                    <p className="text-sm text-slate-300 drop-shadow-sm">
                                        {activeScene.subtitle}
                                    </p>
                                </div>
                            </div>
                        </div>

                        {/* Pastilles indicatrices pour les 10 photos */}
                        <div className="flex items-center justify-center gap-1.5 pt-4">
                            {SCENES.map((scene, idx) => (
                                <button
                                    key={scene.id}
                                    type="button"
                                    onClick={() => setActiveIdx(idx)}
                                    className={`h-1.5 rounded-full transition-all duration-300 cursor-pointer ${
                                        idx === activeIdx
                                            ? 'w-7 bg-white shadow-sm'
                                            : 'w-1.5 bg-white/25 hover:bg-white/50'
                                    }`}
                                    aria-label={`Photo ${idx + 1}: ${scene.title}`}
                                />
                            ))}
                        </div>
                    </div>

                    {/* Pied de page sobre */}
                    <div className="relative z-20 border-t border-slate-800/60 pt-4">
                        <div className="flex items-center justify-between text-xs text-slate-400">
                            <span>SYGES • Système Éducatif Camerounais</span>
                            <span>© 2026</span>
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
                        <Link href={home()} className="flex items-center gap-2.5">
                            <div className="flex size-9 items-center justify-center rounded-xl overflow-hidden shadow-xs">
                                <img
                                    src="/images/syges-icon.png"
                                    alt="Logo SYGES"
                                    className="size-9 object-contain"
                                />
                            </div>
                            <span className="text-lg font-bold tracking-tight text-foreground">
                                {name ?? 'SYGES'}
                            </span>
                        </Link>
                    </div>

                    {/* Carte de connexion centrée, épurée et aérée */}
                    <div className="my-auto mx-auto w-full max-w-md">
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
