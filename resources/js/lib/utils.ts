import type { InertiaLinkProps } from '@inertiajs/react';
import { clsx } from 'clsx';
import type { ClassValue } from 'clsx';
import { twMerge } from 'tailwind-merge';
import type { ClasseRef, EtablissementRef } from '@/types/eleve';

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}

export function toUrl(url: NonNullable<InertiaLinkProps['href']>): string {
    return typeof url === 'string' ? url : url.url;
}

/**
 * Formate une date au format francophone standard jj-mm-aaaa (ex: 01-09-2026).
 * Règle globale SYGES : toutes les dates affichées doivent respecter ce format.
 * Gère de façon idempotente aussi bien les chaînes ISO (YYYY-MM-DD) que les chaînes déjà au format DD-MM-YYYY.
 *
 * @param dateStr Chaîne de date (YYYY-MM-DD, DD-MM-YYYY ou ISO) ou instance Date.
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
    const parts = cleanDate.split(/[-/]/);
    if (parts.length === 3) {
        if (parts[0].length === 4) {
            const [year, month, day] = parts;
            return `${day.padStart(2, '0')}${separator}${month.padStart(2, '0')}${separator}${year}`;
        }
        if (parts[2].length === 4) {
            const [day, month, year] = parts;
            return `${day.padStart(2, '0')}${separator}${month.padStart(2, '0')}${separator}${year}`;
        }
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
 * Formate le libellé d'une année scolaire pour éliminer les tautologies (ex: "Année Scolaire 2026/2027" -> "2026/2027").
 * Garantit un affichage uniforme et concis dans les listes déroulantes de filtres temporels.
 *
 * @param {string} [nom] Nom officiel complet de l'année scolaire.
 * @param {string} [code] Code court de l'année scolaire (ex: "2026/2027").
 * @param {string} [session] Session spécifique de l'année scolaire (ex: "2026/2027").
 * @returns {string} Année scolaire épurée (ex: "2026/2027").
 */
export function formatAnneeLabel(nom?: string | null, code?: string | null, session?: string | null): string {
    // 1. Si code, session ou nom contient le motif YYYY/YYYY ou YYYY-YYYY, extraire directement l'année scolaire concise
    const candidates = [session, code, nom];
    for (const c of candidates) {
        if (!c) continue;
        const match = c.trim().match(/\b(\d{4}[/-]\d{4})\b/);
        if (match) {
            return match[1];
        }
    }

    // 2. Si aucun motif numérique n'est identifié, épurer les préfixes habituels
    const raw = session || code || nom || '';
    if (!raw) return '';
    return raw
        .replace(/^\s*Ann[ée]e\s+scolaire\s*[:-]?\s*/i, '')
        .trim();
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
    'bg-indigo-100 text-indigo-700 border-indigo-200',
    'bg-sky-100 text-sky-700 border-sky-200',
    'bg-amber-100 text-amber-700 border-amber-200',
    'bg-rose-100 text-rose-700 border-rose-200',
    'bg-purple-100 text-purple-700 border-purple-200',
    'bg-blue-100 text-blue-700 border-blue-200',
    'bg-emerald-100 text-emerald-700 border-emerald-200',
    'bg-orange-100 text-orange-700 border-orange-200',
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

/**
 * Normalise l'URL d'un média public stocké en base (photo élève, logo, visuel)
 * afin de garantir son affichage quel que soit l'hôte actif (localhost, syges.test, IP)
 * et d'éviter les doubles slashs (ex: `//storage/...` interprétés comme un domaine externe).
 *
 * @param {string | null} [url] Chemin relatif, URL complète ou Data URI du média.
 * @returns {string | null} URL prête pour l'attribut `src` d'une balise `<img>`, ou `null` si vide.
 */
export function resolveMediaUrl(url?: string | null): string | null {
    if (!url) return null;
    const trimmed = url.trim();
    if (!trimmed) return null;

    if (trimmed.startsWith('data:') || trimmed.startsWith('blob:')) {
        return trimmed;
    }

    // Si une URL absolue pointe vers `/storage/...` (ex: APP_URL différent de l'hôte courant),
    // conserver uniquement `/storage/...` pour résoudre le fichier sur l'origine active.
    const storageMatch = trimmed.match(/^https?:\/\/[^/]+(\/storage\/.+)$/i);
    if (storageMatch) {
        return storageMatch[1];
    }

    if (/^https?:\/\//i.test(trimmed)) {
        return trimmed;
    }

    const normalized = trimmed.replace(/^\/+/, '');
    if (normalized.startsWith('storage/')) {
        return `/${normalized}`;
    }

    if (normalized.startsWith('photos_eleves/') || normalized.startsWith('logos/')) {
        return `/storage/${normalized}`;
    }

    return `/${normalized}`;
}

/**
 * Aplatit les classes rattachées aux établissements (filtrable par établissement) en conservant
 * la hiérarchie `niveau.cycle.section.etablissement` nécessaire aux sélecteurs transversaux.
 *
 * @param {EtablissementRef[]} etablissements Liste des établissements chargés.
 * @param {string} [filterEtabId] Identifiant optionnel d'un campus pour restreindre l'extraction.
 * @returns {ClasseRef[]} Liste plate des classes avec leur contexte hiérarchique complet.
 */
export function flattenClassesFromEtablissements(
    etablissements: EtablissementRef[],
    filterEtabId?: string
): ClasseRef[] {
    const list: ClasseRef[] = [];

    etablissements.forEach((etab) => {
        if (filterEtabId && String(etab.id) !== filterEtabId) {
            return;
        }

        etab.sections?.forEach((sec) => {
            sec.cycles?.forEach((cyc) => {
                cyc.niveaux?.forEach((niv) => {
                    niv.classes?.forEach((cls) => {
                        list.push({
                            ...cls,
                            niveau: {
                                id: niv.id,
                                nom: niv.nom,
                                code: niv.code,
                                cycle: {
                                    id: cyc.id,
                                    nom: cyc.nom,
                                    section: {
                                        id: sec.id,
                                        nom: sec.nom,
                                        etablissement: {
                                            id: etab.id,
                                            nom: etab.nom,
                                            code: etab.code,
                                            sigle: etab.sigle,
                                        },
                                    },
                                },
                            },
                        });
                    });
                });
            });
        });
    });

    return list;
}

/**
 * Construit les options d'un sélecteur `SearchableSelect` pour une liste de classes.
 * Garde un libellé principal concis (`Classe (Niveau)`) et place le nom complet de l'établissement
 * en description secondaire (toujours indexée par la recherche) afin d'éviter la troncature brutale
 * et le débordement des noms longs d'établissements.
 *
 * @param {ClasseRef[]} classes Liste plate des classes disponibles.
 * @returns {Array<{ value: string; label: string; group: string; description?: string }>} Options prêtes pour SearchableSelect.
 */
export function buildClasseSearchOptions(classes: ClasseRef[]) {
    return classes.map((cls) => {
        const niveauNom = cls.niveau?.nom || 'Niveau';
        const etabNom = cls.niveau?.cycle?.section?.etablissement?.nom || '';

        return {
            value: String(cls.id),
            label: `${cls.nom} (${niveauNom})`,
            group: niveauNom,
            description: etabNom || undefined,
        };
    });
}

/**
 * Construit les options `SearchableSelect` pour une liste d'établissements / campus,
 * en affichant le code court et le nom complet sans coupure ambiguë.
 *
 * @param {EtablissementRef[]} etablissements Liste des établissements disponibles.
 * @returns {Array<{ value: string; label: string; description?: string }>} Options prêtes pour SearchableSelect.
 */
export function buildEtablissementSearchOptions(etablissements: EtablissementRef[]) {
    return etablissements.map((etab) => ({
        value: String(etab.id),
        label: etab.nom,
        description: etab.code ? `Code : ${etab.code}` : undefined,
    }));
}

