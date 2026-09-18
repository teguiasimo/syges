import { Check, ChevronDown, Search, X } from 'lucide-react';
import React, { useState, useRef, useEffect, useMemo } from 'react';
import { cn } from '@/lib/utils';

/**
 * Option individuelle pour le composant SearchableSelect.
 */
export interface SearchableOption {
    /** Valeur unique ou code technique (ex: 'BEPC', 'BACCALAURÉAT', 'AUCUN') */
    value: string;
    /** Libellé convivial complet affiché à l'utilisateur */
    label: string;
    /** Groupe thématique ou catégorie pédagogique */
    group?: string;
    /** Description ou précision textuelle optionnelle */
    description?: string;
}

/**
 * Propriétés du composant SearchableSelect.
 */
export interface SearchableSelectProps {
    /** Liste ordonnée de toutes les options disponibles */
    options: SearchableOption[];
    /** Valeur actuellement sélectionnée */
    value: string;
    /** Callback déclenché lors du changement de sélection */
    onChange: (value: string) => void;
    /** Texte affiché dans le bouton de déclenchement lorsqu'aucune option n'est active */
    placeholder?: string;
    /** Texte indicatif dans la zone de recherche */
    searchPlaceholder?: string;
    /** Classes CSS supplémentaires pour le déclencheur */
    className?: string;
    /** Désactive l'interaction avec le composant */
    disabled?: boolean;
}

/**
 * Composant de sélection simple avec champ de recherche en temps réel et regroupement thématique.
 *
 * Conçu pour faciliter la sélection dans des listes fournies (ex: référentiel des diplômes officiels),
 * avec recherche instantanée par mot-clé, sigle ou catégorie.
 */
export function SearchableSelect({
    options,
    value,
    onChange,
    placeholder = 'Sélectionner une option...',
    searchPlaceholder = 'Rechercher...',
    className,
    disabled = false,
}: SearchableSelectProps) {
    const [isOpen, setIsOpen] = useState(false);
    const [searchQuery, setSearchQuery] = useState('');
    const containerRef = useRef<HTMLDivElement>(null);
    const searchInputRef = useRef<HTMLInputElement>(null);

    // Retrouve l'option active pour l'afficher dans le déclencheur
    const selectedOption = useMemo(
        () => options.find((opt) => opt.value === value),
        [options, value]
    );

    // Fermeture automatique lors d'un clic en dehors du composant
    useEffect(() => {
        const handleClickOutside = (event: MouseEvent | TouchEvent) => {
            if (containerRef.current && !containerRef.current.contains(event.target as Node)) {
                setIsOpen(false);
                setSearchQuery('');
            }
        };

        if (isOpen) {
            document.addEventListener('mousedown', handleClickOutside);
            document.addEventListener('touchstart', handleClickOutside);
        }

        return () => {
            document.removeEventListener('mousedown', handleClickOutside);
            document.removeEventListener('touchstart', handleClickOutside);
        };
    }, [isOpen]);

    // Donne le focus au champ de recherche dès l'ouverture du menu déroulant
    useEffect(() => {
        if (isOpen) {
            setTimeout(() => {
                searchInputRef.current?.focus();
            }, 50);
        } else {
            setSearchQuery('');
        }
    }, [isOpen]);

    // Filtrage dynamique des options en fonction de la saisie de l'utilisateur
    const filteredOptions = useMemo(() => {
        const query = searchQuery.trim().toLowerCase();
        if (!query) return options;

        return options.filter((opt) => {
            const matchLabel = opt.label.toLowerCase().includes(query);
            const matchValue = opt.value.toLowerCase().includes(query);
            const matchGroup = opt.group ? opt.group.toLowerCase().includes(query) : false;
            const matchDesc = opt.description ? opt.description.toLowerCase().includes(query) : false;
            return matchLabel || matchValue || matchGroup || matchDesc;
        });
    }, [options, searchQuery]);

    // Regroupement des options filtrées en conservant strictement l'ordre d'origine
    const groupedOptions = useMemo(() => {
        const groupsMap = new Map<string, SearchableOption[]>();

        filteredOptions.forEach((opt) => {
            const groupName = opt.group || 'Général';
            if (!groupsMap.has(groupName)) {
                groupsMap.set(groupName, []);
            }
            groupsMap.get(groupName)!.push(opt);
        });

        return Array.from(groupsMap.entries()).map(([group, items]) => ({
            group,
            items,
        }));
    }, [filteredOptions]);

    /**
     * Sélectionne une option et referme le menu déroulant.
     */
    const handleSelect = (val: string) => {
        onChange(val);
        setIsOpen(false);
        setSearchQuery('');
    };

    /**
     * Réinitialise la sélection actuelle.
     */
    const handleClear = (e: React.MouseEvent) => {
        e.stopPropagation();
        onChange('');
    };

    return (
        <div ref={containerRef} className="relative w-full">
            {/* Déclencheur du sélecteur */}
            <button
                type="button"
                disabled={disabled}
                onClick={() => setIsOpen(!isOpen)}
                className={cn(
                    'border-input focus-visible:border-ring focus-visible:ring-ring/50 flex h-9 w-full items-center justify-between gap-2 rounded-md border bg-transparent px-3 py-2 text-sm shadow-xs transition-colors outline-none focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50 cursor-pointer text-left',
                    isOpen && 'border-ring ring-[3px] ring-ring/50',
                    className
                )}
            >
                <span className={cn('truncate', !selectedOption && 'text-muted-foreground')}>
                    {selectedOption ? selectedOption.label : placeholder}
                </span>

                <div className="flex items-center gap-1 shrink-0 text-muted-foreground">
                    {selectedOption && !disabled && (
                        <span
                            role="button"
                            tabIndex={0}
                            onClick={handleClear}
                            className="p-0.5 rounded-sm hover:text-foreground hover:bg-muted cursor-pointer transition-colors"
                            title="Effacer la sélection"
                        >
                            <X className="size-3.5" />
                        </span>
                    )}
                    <ChevronDown
                        className={cn(
                            'size-4 opacity-50 transition-transform duration-200',
                            isOpen && 'rotate-180 opacity-100 text-primary'
                        )}
                    />
                </div>
            </button>

            {/* Menu déroulant avec zone de recherche intégrée */}
            {isOpen && (
                <div className="absolute top-full left-0 mt-1.5 w-full min-w-[280px] max-w-[calc(100vw-2rem)] z-50 rounded-lg border bg-popover text-popover-foreground shadow-xl animate-in fade-in-0 zoom-in-95 overflow-hidden">
                    {/* Zone de recherche */}
                    <div className="p-2 border-b border-border bg-muted/20">
                        <div className="relative flex items-center">
                            <Search className="size-4 absolute left-2.5 text-muted-foreground pointer-events-none" />
                            <input
                                ref={searchInputRef}
                                type="text"
                                value={searchQuery}
                                onChange={(e) => setSearchQuery(e.target.value)}
                                onKeyDown={(e) => {
                                    if (e.key === 'Escape') {
                                        setIsOpen(false);
                                    }
                                }}
                                placeholder={searchPlaceholder}
                                className="w-full pl-8 pr-7 py-1.5 text-xs bg-background border border-input rounded-md focus:outline-none focus:ring-1 focus:ring-primary focus:border-primary placeholder:text-muted-foreground"
                            />
                            {searchQuery && (
                                <button
                                    type="button"
                                    onClick={() => {
                                        setSearchQuery('');
                                        searchInputRef.current?.focus();
                                    }}
                                    className="absolute right-2 text-muted-foreground hover:text-foreground p-0.5 rounded cursor-pointer"
                                    title="Effacer la recherche"
                                >
                                    <X className="size-3" />
                                </button>
                            )}
                        </div>
                    </div>

                    {/* Liste des options découpées par groupes */}
                    <div className="max-h-64 overflow-y-auto p-1.5 space-y-2">
                        {groupedOptions.length === 0 ? (
                            <div className="py-6 text-center text-xs text-muted-foreground">
                                Aucun diplôme ne correspond à « <span className="font-medium text-foreground">{searchQuery}</span> »
                            </div>
                        ) : (
                            groupedOptions.map(({ group, items }) => (
                                <div key={group} className="space-y-0.5">
                                    {/* En-tête de catégorie / groupe */}
                                    <div className="px-2 py-1 text-[11px] font-bold text-muted-foreground uppercase tracking-wider bg-muted/50 rounded-sm">
                                        {group}
                                    </div>

                                    {/* Éléments du groupe */}
                                    {items.map((item) => {
                                        const isSelected = item.value === value;

                                        return (
                                            <button
                                                key={item.value}
                                                type="button"
                                                onClick={() => handleSelect(item.value)}
                                                className={cn(
                                                    'w-full flex items-center justify-between gap-2 px-2.5 py-1.5 text-xs rounded-md text-left transition-colors cursor-pointer',
                                                    isSelected
                                                        ? 'bg-emerald-50 text-emerald-900 font-semibold dark:bg-emerald-950/50 dark:text-emerald-300'
                                                        : 'hover:bg-accent hover:text-accent-foreground text-foreground'
                                                )}
                                            >
                                                <div className="flex flex-col min-w-0 flex-1">
                                                    <span className="truncate">{item.label}</span>
                                                    {item.description && (
                                                        <span className="text-[10px] text-muted-foreground truncate">
                                                            {item.description}
                                                        </span>
                                                    )}
                                                </div>

                                                {isSelected && (
                                                    <Check className="size-4 shrink-0 text-emerald-600 dark:text-emerald-400" />
                                                )}
                                            </button>
                                        );
                                    })}
                                </div>
                            ))
                        )}
                    </div>
                </div>
            )}
        </div>
    );
}
