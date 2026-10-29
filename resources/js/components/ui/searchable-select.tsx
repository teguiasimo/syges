import { Check, ChevronDown, Search, X } from 'lucide-react';
import React, { useState, useRef, useEffect, useMemo, useCallback } from 'react';
import { cn } from '@/lib/utils';

/**
 * Option individuelle pour le composant SearchableSelect.
 */
export interface SearchableOption {
    /** Valeur unique ou code technique (ex: '12', '6EME-A') */
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
    /** Identifiant HTML du déclencheur pour accessibilité */
    id?: string;
    /** Liste ordonnée de toutes les options disponibles */
    options: SearchableOption[];
    /** Valeur actuellement sélectionnée */
    value: string;
    /** Callback déclenché lors du changement de sélection (convention React standard) */
    onChange?: (value: string) => void;
    /** Callback alternatif compatible Radix UI / Shadcn */
    onValueChange?: (value: string) => void;
    /** Texte affiché dans le bouton de déclenchement lorsqu'aucune option n'est active */
    placeholder?: string;
    /** Texte indicatif dans la zone de recherche */
    searchPlaceholder?: string;
    /** Message affiché lorsqu'aucun résultat ne correspond */
    emptyMessage?: string;
    /** Classes CSS supplémentaires pour le déclencheur */
    className?: string;
    /** Désactive l'interaction avec le composant */
    disabled?: boolean;
}

/**
 * Composant de sélection avec champ de recherche en temps réel et regroupement thématique.
 * Conçu pour fonctionner sans faille sur les pages standards ET à l'intérieur des modales Radix Dialog
 * sans provoquer de conflit de capture de focus ou de blocage d'événements pointeurs.
 *
 * @param {SearchableSelectProps} props
 * @returns {JSX.Element}
 */
export function SearchableSelect({
    id,
    options,
    value,
    onChange,
    onValueChange,
    placeholder = 'Sélectionner une option...',
    searchPlaceholder = 'Rechercher...',
    emptyMessage = 'Aucun élément ne correspond à',
    className,
    disabled = false,
}: SearchableSelectProps) {
    const [isOpen, setIsOpen] = useState(false);
    const [searchQuery, setSearchQuery] = useState('');
    const [placeAbove, setPlaceAbove] = useState(false);
    const containerRef = useRef<HTMLDivElement>(null);
    const searchInputRef = useRef<HTMLInputElement>(null);

    // Retrouve l'option active pour l'afficher dans le déclencheur
    const selectedOption = useMemo(
        () => options.find((opt) => String(opt.value) === String(value)),
        [options, value]
    );

    /**
     * Détermine la direction d'ouverture (au-dessus ou en-dessous) selon l'espace visible disponible.
     */
    const updatePosition = useCallback(() => {
        if (!containerRef.current) return;
        const rect = containerRef.current.getBoundingClientRect();
        const spaceBelow = window.innerHeight - rect.bottom;
        const shouldPlaceAbove = spaceBelow < 280 && rect.top > spaceBelow;
        setPlaceAbove(shouldPlaceAbove);
    }, []);

    // Fermeture automatique lors d'un clic en dehors du composant
    useEffect(() => {
        if (!isOpen) return;

        const handleClickOutside = (event: MouseEvent | TouchEvent) => {
            const target = event.target as Node;
            if (containerRef.current && !containerRef.current.contains(target)) {
                setIsOpen(false);
                setSearchQuery('');
            }
        };

        document.addEventListener('mousedown', handleClickOutside);
        document.addEventListener('touchstart', handleClickOutside);

        return () => {
            document.removeEventListener('mousedown', handleClickOutside);
            document.removeEventListener('touchstart', handleClickOutside);
        };
    }, [isOpen]);

    // Focus automatique dans le champ de recherche à l'ouverture
    useEffect(() => {
        if (isOpen) {
            updatePosition();
            const timer = setTimeout(() => {
                searchInputRef.current?.focus();
            }, 60);
            return () => clearTimeout(timer);
        } else {
            setSearchQuery('');
        }
    }, [isOpen, updatePosition]);

    // Filtrage dynamique des options en fonction de la saisie
    const filteredOptions = useMemo(() => {
        const query = searchQuery.trim().toLowerCase();
        if (!query) return options;

        return options.filter((opt) => {
            const matchLabel = opt.label.toLowerCase().includes(query);
            const matchValue = String(opt.value).toLowerCase().includes(query);
            const matchGroup = opt.group ? opt.group.toLowerCase().includes(query) : false;
            const matchDesc = opt.description ? opt.description.toLowerCase().includes(query) : false;
            return matchLabel || matchValue || matchGroup || matchDesc;
        });
    }, [options, searchQuery]);

    // Regroupement des options filtrées en conservant strictement l'ordre
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
     * Notifie la valeur sélectionnée aux deux callbacks (onChange et onValueChange).
     */
    const triggerChange = (val: string) => {
        onChange?.(val);
        onValueChange?.(val);
    };

    /**
     * Sélectionne une option et referme le menu déroulant.
     */
    const handleSelect = (val: string) => {
        triggerChange(val);
        setIsOpen(false);
        setSearchQuery('');
    };

    /**
     * Réinitialise la sélection actuelle.
     */
    const handleClear = (e: React.MouseEvent) => {
        e.stopPropagation();
        triggerChange('');
    };

    return (
        <div ref={containerRef} className={cn('relative w-full', isOpen ? 'z-50' : 'z-10')}>
            {/* Déclencheur du sélecteur */}
            <button
                id={id}
                type="button"
                disabled={disabled}
                onClick={() => {
                    if (!disabled) {
                        updatePosition();
                        setIsOpen(!isOpen);
                    }
                }}
                className={cn(
                    'border-input focus-visible:border-ring focus-visible:ring-ring/50 flex h-9 w-full min-w-0 items-center justify-between gap-2 rounded-md border bg-transparent px-3 py-2 text-sm shadow-xs transition-colors outline-none focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50 cursor-pointer text-left',
                    isOpen && 'border-ring ring-[3px] ring-ring/50',
                    className
                )}
            >
                <span
                    className={cn('truncate flex-1 min-w-0 text-left', !selectedOption && 'text-muted-foreground')}
                    title={selectedOption ? selectedOption.label : undefined}
                >
                    {selectedOption ? selectedOption.label : placeholder}
                </span>

                <div className="flex items-center gap-1 shrink-0 text-muted-foreground ml-auto">
                    {selectedOption && !disabled && (
                        <span
                            role="button"
                            tabIndex={0}
                            onClick={handleClear}
                            className="p-0.5 rounded-sm hover:text-foreground hover:bg-muted cursor-pointer transition-colors shrink-0"
                            title="Effacer la sélection"
                        >
                            <X className="size-3.5" />
                        </span>
                    )}
                    <ChevronDown
                        className={cn(
                            'size-4 opacity-50 transition-transform duration-200 shrink-0',
                            isOpen && 'rotate-180 opacity-100 text-primary'
                        )}
                    />
                </div>
            </button>

            {/* Menu déroulant positionné de façon absolue directement dans le conteneur */}
            {isOpen && (
                <div
                    className={cn(
                        'absolute left-0 w-full min-w-0 sm:min-w-70 max-w-[calc(100vw-2rem)] z-50 rounded-lg border border-border bg-popover text-popover-foreground shadow-2xl animate-in fade-in-0 zoom-in-95 overflow-hidden flex flex-col max-h-72',
                        placeAbove ? 'bottom-full mb-1' : 'top-full mt-1'
                    )}
                >
                    {/* Zone de recherche */}
                    <div className="p-2 border-b border-border bg-muted/30 shrink-0">
                        <div className="relative flex items-center">
                            <Search className="size-4 absolute left-2.5 text-muted-foreground pointer-events-none" />
                            <input
                                ref={searchInputRef}
                                type="text"
                                value={searchQuery}
                                onChange={(e) => setSearchQuery(e.target.value)}
                                onKeyDown={(e) => {
                                    if (e.key === 'Escape') {
                                        e.stopPropagation();
                                        setIsOpen(false);
                                    } else if (e.key === 'Enter' && filteredOptions.length > 0) {
                                        e.preventDefault();
                                        e.stopPropagation();
                                        handleSelect(filteredOptions[0].value);
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

                    {/* Liste des options découpées par groupes (visible immédiatement sans taper) */}
                    <div className="overflow-y-auto p-1.5 space-y-2 flex-1 overscroll-contain">
                        {groupedOptions.length === 0 ? (
                            <div className="py-6 text-center text-xs text-muted-foreground">
                                {emptyMessage}{' '}
                                {searchQuery ? (
                                    <>
                                        « <span className="font-medium text-foreground">{searchQuery}</span> »
                                    </>
                                ) : (
                                    'votre recherche'
                                )}
                            </div>
                        ) : (
                            groupedOptions.map(({ group, items }) => (
                                <div key={group} className="space-y-0.5">
                                    {/* En-tête de catégorie / groupe (affiché uniquement s'il y a plusieurs groupes ou un intitulé explicite) */}
                                    {(groupedOptions.length > 1 || (group && group !== 'Général')) && (
                                        <div className="px-2 py-1 text-[11px] font-bold text-muted-foreground uppercase tracking-wider bg-muted/50 rounded-sm">
                                            {group}
                                        </div>
                                    )}

                                    {/* Éléments du groupe */}
                                    {items.map((item) => {
                                        const isSelected = String(item.value) === String(value);

                                        return (
                                            <button
                                                key={item.value}
                                                type="button"
                                                onClick={() => handleSelect(item.value)}
                                                className={cn(
                                                    'w-full flex items-center justify-between gap-2 px-2.5 py-2 text-xs rounded-md text-left transition-colors cursor-pointer min-w-0',
                                                    isSelected
                                                        ? 'bg-emerald-50 text-emerald-900 font-semibold dark:bg-emerald-950/60 dark:text-emerald-300'
                                                        : 'hover:bg-accent hover:text-accent-foreground text-foreground'
                                                )}
                                                title={item.label}
                                            >
                                                <div className="flex flex-col min-w-0 flex-1 overflow-hidden">
                                                    <span className="truncate block font-medium">{item.label}</span>
                                                    {item.description && (
                                                        <span
                                                            className="text-[10px] text-muted-foreground truncate block"
                                                            title={item.description}
                                                        >
                                                            {item.description}
                                                        </span>
                                                    )}
                                                </div>

                                                {isSelected && (
                                                    <Check className="size-4 shrink-0 text-emerald-600 dark:text-emerald-400 ml-1" />
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
