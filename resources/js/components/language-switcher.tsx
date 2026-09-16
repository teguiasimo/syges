import React from 'react';
import { Languages, Check } from 'lucide-react';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useTranslation } from '@/contexts/language-context';
import type { Locale } from '@/locales';
import { cn } from '@/lib/utils';

/**
 * Propriétés du sélecteur de langue.
 */
interface LanguageSwitcherProps {
    /**
     * Classes CSS additionnelles pour le bouton déclencheur.
     */
    className?: string;
    /**
     * Alignement du menu déroulant (par défaut 'end').
     */
    align?: 'start' | 'center' | 'end';
}

/**
 * Options de langues disponibles avec leurs drapeaux et intitulés natifs.
 */
const languageOptions: { code: Locale; label: string; flag: string; nativeName: string }[] = [
    {
        code: 'fr',
        label: 'Français',
        nativeName: 'Français (FR)',
        flag: '🇫🇷',
    },
    {
        code: 'en',
        label: 'English',
        nativeName: 'English (UK/US)',
        flag: '🇬🇧',
    },
];

/**
 * Composant de sélection de langue (LanguageSwitcher).
 * Affiche l'icône de traduction `Languages` ("文A") issue de Lucide React,
 * ouvrant un menu déroulant élégant permettant de basculer instantanément
 * entre le français et l'anglais.
 *
 * @param {LanguageSwitcherProps} props Les propriétés du composant.
 * @returns {JSX.Element} Le bouton et son menu de sélection de langue.
 */
export function LanguageSwitcher({ className, align = 'end' }: LanguageSwitcherProps) {
    const { locale, setLocale, t, isChangingLocale } = useTranslation();

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <button
                    type="button"
                    title={t('language.switch_language')}
                    aria-label={t('language.switch_language')}
                    className={cn(
                        'relative flex size-9 items-center justify-center rounded-lg text-muted-foreground transition-colors hover:bg-accent hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring cursor-pointer',
                        isChangingLocale && 'opacity-60 cursor-wait',
                        className
                    )}
                >
                    <Languages className="size-5 transition-transform duration-200 hover:scale-110" />
                    <span className="sr-only">
                        {t('language.current_language')}
                    </span>
                </button>
            </DropdownMenuTrigger>

            <DropdownMenuContent align={align} side="bottom" className="w-44 rounded-xl p-1.5 shadow-md">
                <DropdownMenuLabel className="px-2 py-1.5 text-xs font-semibold text-muted-foreground">
                    {t('language.select_language')}
                </DropdownMenuLabel>
                <DropdownMenuSeparator className="my-1" />
                {languageOptions.map((opt) => {
                    const isSelected = locale === opt.code;
                    return (
                        <DropdownMenuItem
                            key={opt.code}
                            onClick={() => setLocale(opt.code)}
                            className={cn(
                                'flex items-center justify-between px-2.5 py-2 rounded-lg text-sm font-medium cursor-pointer transition-colors',
                                isSelected
                                    ? 'bg-accent text-accent-foreground font-semibold'
                                    : 'text-foreground hover:bg-muted'
                            )}
                        >
                            <div className="flex items-center gap-2.5">
                                <span className="text-base" role="img" aria-label={opt.label}>
                                    {opt.flag}
                                </span>
                                <span>{opt.label}</span>
                            </div>
                            {isSelected && (
                                <Check className="size-4 text-primary shrink-0" />
                            )}
                        </DropdownMenuItem>
                    );
                })}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
