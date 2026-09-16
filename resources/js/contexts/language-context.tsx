import React, { createContext, useContext, useEffect, useState, useTransition, useCallback } from 'react';
import { router, usePage } from '@inertiajs/react';
import { translate, type Locale, type TranslationKey } from '@/locales';

/**
 * Interface du contexte d'internationalisation de SYGES.
 */
interface LanguageContextType {
    /**
     * Code de la langue actuellement active ('fr' ou 'en').
     */
    locale: Locale;

    /**
     * Fonction permettant de changer la langue de l'interface.
     * Met à jour l'état local immédiatement et synchronise avec le serveur Laravel.
     *
     * @param {Locale} newLocale La nouvelle langue à appliquer.
     */
    setLocale: (newLocale: Locale) => void;

    /**
     * Fonction de traduction de clés du dictionnaire.
     *
     * @param {TranslationKey | string} key Clé sous forme dot-notation (ex: 'admin.title').
     * @param {Record<string, string | number>} [params] Paramètres dynamiques de remplacement.
     * @returns {string} Le texte traduit.
     */
    t: (key: TranslationKey | string, params?: Record<string, string | number>) => string;

    /**
     * Formate une date selon la langue active avec l'API standard Intl.
     *
     * @param {Date | string | number} date Date à formater.
     * @param {Intl.DateTimeFormatOptions} [options] Options Intl personnalisées.
     * @returns {string}
     */
    formatDate: (date: Date | string | number, options?: Intl.DateTimeFormatOptions) => string;

    /**
     * Formate un nombre selon la langue active avec l'API standard Intl.
     *
     * @param {number} value Valeur numérique à formater.
     * @param {Intl.NumberFormatOptions} [options] Options Intl de formatage.
     * @returns {string}
     */
    formatNumber: (value: number, options?: Intl.NumberFormatOptions) => string;

    /**
     * Indique si une requête de changement de langue est en cours d'envoi.
     */
    isChangingLocale: boolean;
}

const LanguageContext = createContext<LanguageContextType | undefined>(undefined);

/**
 * Fournisseur du contexte de langue (LanguageProvider) enveloppant l'application React.
 * Permet à tous les composants enfants d'accéder aux traductions et de réagir immédiatement
 * aux changements de langue.
 *
 * @param {{ children: React.ReactNode }} props
 * @returns {JSX.Element}
 */
export function LanguageProvider({ children }: { children: React.ReactNode }) {
    // Récupération défensive des props serveur de la page courante
    let serverLocale: Locale | undefined;
    try {
        const page = usePage();
        if (page && page.props) {
            serverLocale = (page.props as any).locale as Locale | undefined;
        }
    } catch {
        serverLocale = undefined;
    }

    // Initialisation de la langue : priorité serveur Inertia, puis localStorage, puis 'fr'
    const [locale, setLocaleState] = useState<Locale>(() => {
        if (serverLocale && (serverLocale === 'fr' || serverLocale === 'en')) {
            return serverLocale;
        }
        if (typeof window !== 'undefined') {
            const saved = localStorage.getItem('syges_locale');
            if (saved === 'fr' || saved === 'en') {
                return saved;
            }
        }
        return 'fr';
    });

    const [isChangingLocale, setIsChangingLocale] = useState(false);

    // Synchronisation si le serveur renvoie une nouvelle locale lors d'une navigation
    useEffect(() => {
        if (serverLocale && (serverLocale === 'fr' || serverLocale === 'en') && serverLocale !== locale) {
            setLocaleState(serverLocale);
            if (typeof document !== 'undefined') {
                document.documentElement.lang = serverLocale;
            }
        }
    }, [serverLocale]);

    // Met à jour la balise HTML lang à chaque changement
    useEffect(() => {
        if (typeof document !== 'undefined') {
            document.documentElement.lang = locale;
        }
    }, [locale]);

    /**
     * Change la langue de l'application et la persiste côté client et côté serveur.
     */
    const setLocale = useCallback((newLocale: Locale) => {
        if (newLocale === locale) return;

        // 1. Mise à jour instantanée de l'état React local pour un affichage fluide
        setLocaleState(newLocale);
        if (typeof window !== 'undefined') {
            localStorage.setItem('syges_locale', newLocale);
            document.documentElement.lang = newLocale;
        }

        // 2. Notification au serveur Laravel pour session + cookie
        setIsChangingLocale(true);
        router.post(
            '/locale',
            { locale: newLocale },
            {
                preserveScroll: true,
                preserveState: true,
                onFinish: () => {
                    setIsChangingLocale(false);
                },
            }
        );
    }, [locale]);

    /**
     * Traduction d'une clé.
     */
    const t = useCallback(
        (key: TranslationKey | string, params?: Record<string, string | number>) => {
            return translate(locale, key, params);
        },
        [locale]
    );

    /**
     * Formatage de date localisée.
     */
    const formatDate = useCallback(
        (date: Date | string | number, options?: Intl.DateTimeFormatOptions) => {
            const dateObj = typeof date === 'object' ? date : new Date(date);
            const localeStr = locale === 'en' ? 'en-US' : 'fr-FR';
            return new Intl.DateTimeFormat(localeStr, options).format(dateObj);
        },
        [locale]
    );

    /**
     * Formatage de nombre localisé.
     */
    const formatNumber = useCallback(
        (value: number, options?: Intl.NumberFormatOptions) => {
            const localeStr = locale === 'en' ? 'en-US' : 'fr-FR';
            return new Intl.NumberFormat(localeStr, options).format(value);
        },
        [locale]
    );

    return (
        <LanguageContext.Provider
            value={{
                locale,
                setLocale,
                t,
                formatDate,
                formatNumber,
                isChangingLocale,
            }}
        >
            {children}
        </LanguageContext.Provider>
    );
}

/**
 * Hook personnalisé permettant d'utiliser le système de traduction dans n'importe quel composant React.
 *
 * @example
 * const { t, locale, setLocale } = useTranslation();
 * return <h1>{t('admin.title')}</h1>;
 *
 * @returns {LanguageContextType} Les utilitaires d'internationalisation.
 */
export function useTranslation(): LanguageContextType {
    const context = useContext(LanguageContext);
    if (!context) {
        // Mode secours autonome si le hook est appelé hors du provider
        return {
            locale: 'fr',
            setLocale: () => {},
            t: (key: string, params?: Record<string, string | number>) => translate('fr', key, params),
            formatDate: (date: Date | string | number, options?: Intl.DateTimeFormatOptions) =>
                new Intl.DateTimeFormat('fr-FR', options).format(typeof date === 'object' ? date : new Date(date)),
            formatNumber: (value: number, options?: Intl.NumberFormatOptions) =>
                new Intl.NumberFormat('fr-FR', options).format(value),
            isChangingLocale: false,
        };
    }
    return context;
}
