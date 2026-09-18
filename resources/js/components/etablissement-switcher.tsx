import React from 'react';
import { router, usePage } from '@inertiajs/react';
import { School, Check, ChevronDown } from 'lucide-react';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';

/**
 * Interface pour la référence d'un établissement scolaire.
 */
interface EtablissementItem {
    id: number;
    nom: string;
    code: string;
    ordre_enseignement?: string;
}

/**
 * Propriétés du composant EtablissementSwitcher.
 */
interface EtablissementSwitcherProps {
    className?: string;
}

/**
 * Sélecteur Maître de Campus (Multi-Établissements SaaS).
 * Permet au Super Admin (Promoteur / Direction Générale) de basculer instantanément
 * d'un établissement à un autre à travers toute l'application, et affiche clairement
 * au personnel scolaire le campus auquel il est assigné.
 *
 * @param {EtablissementSwitcherProps} props Propriétés CSS optionnelles.
 * @returns {JSX.Element | null} Le sélecteur de campus pour la barre d'en-tête.
 */
export function EtablissementSwitcher({ className }: EtablissementSwitcherProps) {
    const { props } = usePage<{
        currentEtablissement?: EtablissementItem | null;
        availableEtablissements?: EtablissementItem[];
    }>();

    const currentEtablissement = props.currentEtablissement;
    const availableEtablissements = props.availableEtablissements || [];

    if (!currentEtablissement) {
        return null;
    }

    // Cas d'un seul établissement disponible (ex: Personnel restreint à son campus)
    if (availableEtablissements.length <= 1) {
        return (
            <div
                className={cn(
                    'hidden sm:flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 text-xs font-semibold border border-slate-200 dark:border-slate-700',
                    className
                )}
                title={`Campus actif : ${currentEtablissement.nom}`}
            >
                <School className="size-3.5 text-primary" />
                <span className="truncate max-w-[160px] md:max-w-[220px]">{currentEtablissement.nom}</span>
            </div>
        );
    }

    /**
     * Déclenche la bascule d'établissement actif pour la session de travail.
     *
     * @param {number} etablissementId Identifiant du nouvel établissement choisi.
     */
    const handleSwitchEtablissement = (etablissementId: number) => {
        if (etablissementId === currentEtablissement.id) return;

        router.post(
            '/switch-etablissement',
            { etablissement_id: etablissementId },
            {
                preserveScroll: true,
                preserveState: false,
            }
        );
    };

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <button
                    type="button"
                    title="Changer de campus"
                    className={cn(
                        'flex items-center gap-2 px-3 py-1.5 rounded-xl bg-primary/10 hover:bg-primary/15 text-primary border border-primary/20 text-xs font-bold transition-all shadow-xs cursor-pointer focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring',
                        className
                    )}
                >
                    <School className="size-4 text-primary shrink-0" />
                    <span className="truncate max-w-[130px] sm:max-w-[180px] md:max-w-[240px]">
                        {currentEtablissement.nom}
                    </span>
                    <Badge variant="outline" className="hidden md:inline-flex text-[9px] px-1.5 py-0 bg-background/60 font-mono">
                        {currentEtablissement.code}
                    </Badge>
                    <ChevronDown className="size-3.5 text-primary/70 shrink-0" />
                </button>
            </DropdownMenuTrigger>

            <DropdownMenuContent align="end" side="bottom" className="w-64 rounded-xl p-1.5 shadow-lg">
                <DropdownMenuLabel className="px-2 py-1.5 text-xs font-semibold text-muted-foreground">
                    Changer de Campus ({availableEtablissements.length})
                </DropdownMenuLabel>
                <DropdownMenuSeparator className="my-1" />

                {availableEtablissements.map((etab) => {
                    const isSelected = etab.id === currentEtablissement.id;
                    return (
                        <DropdownMenuItem
                            key={etab.id}
                            onClick={() => handleSwitchEtablissement(etab.id)}
                            className={cn(
                                'flex items-center justify-between px-2.5 py-2 rounded-lg text-xs font-medium cursor-pointer transition-colors',
                                isSelected
                                    ? 'bg-primary/10 text-primary font-bold'
                                    : 'text-foreground hover:bg-muted'
                            )}
                        >
                            <div className="flex items-center gap-2 min-w-0">
                                <School className={cn('size-4 shrink-0', isSelected ? 'text-primary' : 'text-muted-foreground')} />
                                <div className="min-w-0">
                                    <span className="truncate block font-semibold">{etab.nom}</span>
                                    {etab.ordre_enseignement && (
                                        <span className="text-[10px] text-muted-foreground block truncate">
                                            {etab.ordre_enseignement.replace('_', ' ')}
                                        </span>
                                    )}
                                </div>
                            </div>
                            {isSelected && (
                                <Check className="size-4 text-primary shrink-0 ml-2" />
                            )}
                        </DropdownMenuItem>
                    );
                })}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
