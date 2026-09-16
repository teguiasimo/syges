import { ChevronDown, ChevronsUpDown, X } from 'lucide-react';
import * as React from 'react';
import { Badge } from '@/components/ui/badge';
import {
    DropdownMenu,
    DropdownMenuCheckboxItem,
    DropdownMenuContent,
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { cn } from '@/lib/utils';

/**
 * Option individuelle pour le composant MultiSelect.
 */
export interface MultiSelectOption {
    /** Valeur unique de l'option (identifiant technique) */
    value: string;
    /** Libellé convivial affiché à l'écran */
    label: string;
    /** Description ou sous-titre optionnel */
    description?: string;
    /** Indique si l'option est désactivée / non sélectionnable */
    disabled?: boolean;
}

/**
 * Propriétés du composant MultiSelect conforme au système de composants shadcn/ui.
 */
interface MultiSelectProps {
    /** Liste des options disponibles pour la sélection */
    options: MultiSelectOption[];
    /** Tableau des valeurs actuellement sélectionnées */
    selected: string[];
    /** Callback de notification de mise à jour des valeurs sélectionnées */
    onChange: (selected: string[]) => void;
    /** Texte indicatif lorsque rien n'est sélectionné */
    placeholder?: string;
    /** Classes CSS additionnelles pour le déclencheur */
    className?: string;
    /** Indique si le sélecteur complet est désactivé */
    disabled?: boolean;
    /** Limite du nombre de badges affichés avant repli en compteur (défaut: 3) */
    maxDisplayBadges?: number;
}

/**
 * Composant de sélection multiple (Select Multiple) propulsé par shadcn/ui et Radix UI.
 *
 * Caractéristiques :
 * - Déclencheur reprenant le style exact des `Select` et `Input` de shadcn/ui.
 * - Rendu en badges des éléments cochés, avec possibilité de retirer individuellement chaque élément.
 * - Menu déroulant avec cases à cocher sans fermeture intempestive lors de la sélection multiple.
 * - Raccourcis pour tout sélectionner ou tout effacer.
 */
export function MultiSelect({
    options,
    selected,
    onChange,
    placeholder = 'Sélectionner une ou plusieurs options...',
    className,
    disabled = false,
    maxDisplayBadges = 3,
}: MultiSelectProps) {
    const [open, setOpen] = React.useState(false);

    /**
     * Bascule l'état d'inclusion d'une option.
     */
    const toggleOption = (value: string) => {
        if (selected.includes(value)) {
            onChange(selected.filter((v) => v !== value));
        } else {
            onChange([...selected, value]);
        }
    };

    /**
     * Retire une valeur spécifique de la sélection.
     */
    const handleRemoveValue = (e: React.MouseEvent, value: string) => {
        e.stopPropagation();
        onChange(selected.filter((v) => v !== value));
    };

    /**
     * Sélectionne toutes les options non désactivées.
     */
    const handleSelectAll = (e: React.MouseEvent) => {
        e.preventDefault();
        const selectableValues = options.filter((opt) => !opt.disabled).map((opt) => opt.value);
        onChange(Array.from(new Set([...selected, ...selectableValues])));
    };

    /**
     * Efface l'ensemble de la sélection.
     */
    const handleClearAll = (e: React.MouseEvent) => {
        e.preventDefault();
        // Conserve uniquement les options désactivées qui étaient déjà cochées (ex: super-admin protégé)
        const preservedDisabled = options
            .filter((opt) => opt.disabled && selected.includes(opt.value))
            .map((opt) => opt.value);
        onChange(preservedDisabled);
    };

    // Filtre les objets d'options correspondant aux valeurs sélectionnées
    const selectedOptions = options.filter((opt) => selected.includes(opt.value));
    const displayedBadges = selectedOptions.slice(0, maxDisplayBadges);
    const hiddenCount = selectedOptions.length - displayedBadges.length;

    return (
        <DropdownMenu open={open} onOpenChange={setOpen}>
            <DropdownMenuTrigger asChild disabled={disabled}>
                <button
                    type="button"
                    className={cn(
                        'border-input focus-visible:border-ring focus-visible:ring-ring/50 flex min-h-9 w-full items-center justify-between gap-2 rounded-md border bg-card px-3 py-1.5 text-xs sm:text-sm shadow-xs transition-colors outline-none focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50 cursor-pointer text-left',
                        className
                    )}
                >
                    {/* Zone de contenu des éléments sélectionnés */}
                    <div className="flex flex-wrap items-center gap-1.5 min-w-0 flex-1 py-0.5">
                        {selectedOptions.length === 0 ? (
                            <span className="text-muted-foreground font-normal truncate">
                                {placeholder}
                            </span>
                        ) : (
                            <>
                                {displayedBadges.map((opt) => (
                                    <Badge
                                        key={opt.value}
                                        variant="secondary"
                                        className="text-[11px] font-normal px-1.5 py-0.5 h-6 flex items-center gap-1 bg-emerald-50 text-emerald-800 border border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-200 dark:border-emerald-800"
                                    >
                                        <span className="truncate max-w-30">{opt.label}</span>
                                        {!opt.disabled && (
                                            <span
                                                role="button"
                                                tabIndex={0}
                                                onClick={(e) => handleRemoveValue(e, opt.value)}
                                                className="hover:text-destructive hover:bg-emerald-100 dark:hover:bg-emerald-900 rounded p-0.5 transition-colors cursor-pointer"
                                                title={`Retirer ${opt.label}`}
                                            >
                                                <X className="size-3" />
                                            </span>
                                        )}
                                    </Badge>
                                ))}

                                {hiddenCount > 0 && (
                                    <Badge
                                        variant="outline"
                                        className="text-[11px] font-medium px-1.5 py-0.5 h-6 bg-muted text-muted-foreground"
                                    >
                                        +{hiddenCount} autre{hiddenCount > 1 ? 's' : ''}
                                    </Badge>
                                )}
                            </>
                        )}
                    </div>

                    {/* Icônes d'action à droite */}
                    <div className="flex items-center gap-1 shrink-0 text-muted-foreground">
                        {selectedOptions.length > 0 && !disabled && (
                            <span
                                role="button"
                                tabIndex={0}
                                onClick={(e) => {
                                    e.stopPropagation();
                                    handleClearAll(e);
                                }}
                                className="hover:text-foreground p-0.5 rounded cursor-pointer"
                                title="Tout désélectionner"
                            >
                                <X className="size-3.5" />
                            </span>
                        )}
                        <ChevronDown className="size-4 opacity-60" />
                    </div>
                </button>
            </DropdownMenuTrigger>

            <DropdownMenuContent
                align="start"
                className="w-[calc(100vw-2rem)] sm:w-(--radix-dropdown-menu-trigger-width) max-h-64 overflow-y-auto p-1"
            >
                {/* En-tête avec raccourcis */}
                <div className="flex items-center justify-between px-2 py-1.5 text-xs text-muted-foreground border-b border-border/60">
                    <span className="font-semibold text-foreground">
                        {selected.length} sélectionné{selected.length > 1 ? 's' : ''}
                    </span>
                    <div className="flex items-center gap-2">
                        <button
                            type="button"
                            onClick={handleSelectAll}
                            className="text-[11px] text-primary hover:underline cursor-pointer"
                        >
                            Tout cocher
                        </button>
                        <span>•</span>
                        <button
                            type="button"
                            onClick={handleClearAll}
                            className="text-[11px] text-muted-foreground hover:text-foreground cursor-pointer"
                        >
                            Effacer
                        </button>
                    </div>
                </div>

                {/* Liste des options avec cases à cocher */}
                <DropdownMenuGroup className="py-1">
                    {options.map((option) => {
                        const isChecked = selected.includes(option.value);

                        return (
                            <DropdownMenuCheckboxItem
                                key={option.value}
                                checked={isChecked}
                                disabled={option.disabled}
                                onSelect={(e) => {
                                    // Empêche la fermeture intempestive du menu lors du clic pour permettre des sélections multiples
                                    e.preventDefault();
                                    if (!option.disabled) {
                                        toggleOption(option.value);
                                    }
                                }}
                                className="cursor-pointer flex items-center justify-between text-xs py-2"
                            >
                                <div className="flex flex-col min-w-0 flex-1">
                                    <span className="font-medium text-foreground truncate">
                                        {option.label}
                                    </span>
                                    {option.description && (
                                        <span className="text-[10px] text-muted-foreground truncate">
                                            {option.description}
                                        </span>
                                    )}
                                </div>
                            </DropdownMenuCheckboxItem>
                        );
                    })}
                </DropdownMenuGroup>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
