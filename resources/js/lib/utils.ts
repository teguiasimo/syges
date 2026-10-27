import type { InertiaLinkProps } from '@inertiajs/react';
import { clsx } from 'clsx';
import type { ClassValue } from 'clsx';
import { twMerge } from 'tailwind-merge';

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}

export function toUrl(url: NonNullable<InertiaLinkProps['href']>): string {
    return typeof url === 'string' ? url : url.url;
}

/**
 * Formate une date au format francophone standard jj-mm-aaaa (ex: 01-09-2026).
 * Règle globale SYGES : toutes les dates affichées doivent respecter ce format.
 *
 * @param dateStr Chaîne de date (YYYY-MM-DD ou ISO) ou instance Date.
 * @param separator Séparateur (défaut '-').
 * @returns Date formatée au format jj-mm-aaaa ou chaîne vide si non définie.
 */
export function formatDate(dateStr?: string | Date | null, separator: '-' | '/' = '-'): string {
    if (!dateStr) return '';
    if (dateStr instanceof Date) {
        const d = String(dateStr.getDate()).padStart(2, '0');
        const m = String(dateStr.getMonth() + 1).padStart(2, '0');
        const y = dateStr.getFullYear();
        return `${d}${separator}${m}${separator}${y}`;
    }
    const cleanDate = dateStr.substring(0, 10);
    const parts = cleanDate.split('-');
    if (parts.length === 3) {
        const [year, month, day] = parts;
        return `${day}${separator}${month}${separator}${year}`;
    }
    return cleanDate;
}

/**
 * Normalise une date pour un champ HTML <input type="date" /> qui requiert strictement YYYY-MM-DD.
 *
 * @param dateStr Chaîne de date brute ou instance Date.
 * @returns Format strict YYYY-MM-DD (10 caractères).
 */
export function formatDateForInput(dateStr?: string | Date | null): string {
    if (!dateStr) return '';
    if (dateStr instanceof Date) {
        const d = String(dateStr.getDate()).padStart(2, '0');
        const m = String(dateStr.getMonth() + 1).padStart(2, '0');
        const y = dateStr.getFullYear();
        return `${y}-${m}-${d}`;
    }
    return dateStr.substring(0, 10);
}
/**
 * Tronque une chaîne de caractères si sa longueur dépasse une limite maximale définie
 * et lui accole des points de suspension (...) pour préserver l'intégrité visuelle des composants
 * (modales, listes déroulantes, cellules de tableaux).
 *
 * @param text Chaîne de texte source à tronquer (accepte undefined ou null).
 * @param maxLength Nombre maximal de caractères avant troncature (défaut : 45).
 * @returns Chaîne tronquée avec '...' ou texte d'origine s'il respecte la limite.
 */
export function truncateText(text?: string | null, maxLength = 45): string {
    if (!text) return '';
    if (text.length <= maxLength) return text;
    return `${text.slice(0, maxLength).trimEnd()}...`;
}

/**
 * Palette de couleurs harmonieuses et alternées pour les avatars d'utilisateurs et de personnels.
 * Permet de diversifier visuellement l'affichage des listes et tableaux sans monotonie.
 */
export const AVATAR_PALETTES = [
    'bg-indigo-100 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-300 border-indigo-200 dark:border-indigo-800',
    'bg-sky-100 dark:bg-sky-950/60 text-sky-700 dark:text-sky-300 border-sky-200 dark:border-sky-800',
    'bg-amber-100 dark:bg-amber-950/60 text-amber-700 dark:text-amber-300 border-amber-200 dark:border-amber-800',
    'bg-rose-100 dark:bg-rose-950/60 text-rose-700 dark:text-rose-300 border-rose-200 dark:border-rose-800',
    'bg-purple-100 dark:bg-purple-950/60 text-purple-700 dark:text-purple-300 border-purple-200 dark:border-purple-800',
    'bg-blue-100 dark:bg-blue-950/60 text-blue-700 dark:text-blue-300 border-blue-200 dark:border-blue-800',
    'bg-emerald-100 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800',
    'bg-orange-100 dark:bg-orange-950/60 text-orange-700 dark:text-orange-300 border-orange-200 dark:border-orange-800',
];

/**
 * Calcule de manière déterministe une classe de couleur d'avatar basée sur une chaîne de texte (ex: nom complet).
 *
 * @param {string} text Nom ou identifiant de référence.
 * @returns {string} Classes CSS Tailwind correspondantes.
 */
export function getAvatarStyle(text: string): string {
    let hash = 0;
    for (let i = 0; i < text.length; i++) {
        hash = text.charCodeAt(i) + ((hash << 5) - hash);
    }
    const index = Math.abs(hash) % AVATAR_PALETTES.length;
    return AVATAR_PALETTES[index];
}

