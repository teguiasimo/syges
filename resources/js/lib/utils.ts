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

