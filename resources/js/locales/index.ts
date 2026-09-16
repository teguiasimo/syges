import { fr, type Translations } from './fr';
import { en } from './en';

/**
 * Types des langues prises en charge par la plateforme SYGES.
 */
export type Locale = 'fr' | 'en';

/**
 * Registre des dictionnaires disponibles indexés par code de langue.
 */
export const translations: Record<Locale, Translations> = {
    fr,
    en,
};

/**
 * Type utilitaire récursif pour extraire les clés imbriquées sous la forme 'section.cle'.
 */
type NestedKeyOf<ObjectType extends object> = {
    [Key in keyof ObjectType & (string | number)]: ObjectType[Key] extends object
        ? `${Key}.${NestedKeyOf<ObjectType[Key]>}`
        : `${Key}`;
}[keyof ObjectType & (string | number)];

/**
 * Type de toutes les clés de traduction valides du dictionnaire SYGES.
 */
export type TranslationKey = NestedKeyOf<Translations>;

/**
 * Résout une clé de traduction au format chaîne de caractères (ex: 'admin.title')
 * dans le dictionnaire pour une locale donnée, avec repli automatique sur le français.
 *
 * @param {Locale} locale Code de la langue cible ('fr' ou 'en').
 * @param {string} path Chemin d'accès à la clé séparé par des points (ex: 'common.save').
 * @param {Record<string, string | number>} [params] Paramètres dynamiques de remplacement (ex: { name: 'Paul' }).
 * @returns {string} Le texte traduit ou la clé brute si non trouvée.
 */
export function translate(
    locale: Locale,
    path: string,
    params?: Record<string, string | number>
): string {
    const keys = path.split('.');
    
    // 1. Tentative dans la langue courante
    let current: any = translations[locale];
    for (const key of keys) {
        if (current && typeof current === 'object' && key in current) {
            current = current[key];
        } else {
            current = null;
            break;
        }
    }

    // 2. Repli de secours (fallback) sur la langue française si introuvable dans la langue demandée
    if (typeof current !== 'string' && locale !== 'fr') {
        let fallback: any = translations.fr;
        for (const key of keys) {
            if (fallback && typeof fallback === 'object' && key in fallback) {
                fallback = fallback[key];
            } else {
                fallback = null;
                break;
            }
        }
        if (typeof fallback === 'string') {
            current = fallback;
        }
    }

    // Si la clé n'existe dans aucun dictionnaire, retourne le chemin de la clé
    if (typeof current !== 'string') {
        return path;
    }

    let result = current;

    // 3. Remplacement des variables dynamiques {{key}} ou :key
    if (params) {
        Object.entries(params).forEach(([k, v]) => {
            result = result.replace(new RegExp(`{{${k}}}`, 'g'), String(v));
            result = result.replace(new RegExp(`:${k}`, 'g'), String(v));
        });
    }

    return result;
}
