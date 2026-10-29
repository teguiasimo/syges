import React from 'react';
import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
    SheetDescription,
} from '@/components/ui/sheet';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    User,
    Phone,
    Mail,
    MapPin,
    Calendar,
    GraduationCap,
    School,
    ShieldAlert,
    Clock,
    FileText,
} from 'lucide-react';
import { formatDate } from '@/lib/utils';
import type { Inscription } from '@/types/eleve';

/**
 * Propriétés du composant EleveDetailDrawer.
 */
interface EleveDetailDrawerProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    inscription: Inscription | null;
    onEdit?: (eleve: any) => void;
}

/**
 * Tiroir latéral (Sheet) présentant la Fiche Scolaire d'Identification Complète
 * d'un élève (style carte officielle Gesco / SAGEES).
 *
 * @param {EleveDetailDrawerProps} props
 * @returns {JSX.Element}
 */
export function EleveDetailDrawer({
    open,
    onOpenChange,
    inscription,
    onEdit,
}: EleveDetailDrawerProps) {
    if (!inscription) return null;
    const eleve = inscription.eleve;
    const classe = inscription.classe;

    // Calcul de l'âge
    const age = eleve.date_naissance
        ? Math.floor((new Date().getTime() - new Date(eleve.date_naissance).getTime()) / (365.25 * 24 * 60 * 60 * 1000))
        : null;

    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            {/* Contenu du tiroir latéral optimisé pour le responsive mobile et tablette */}
            <SheetContent className="w-full sm:max-w-xl overflow-y-auto p-4 sm:p-6 space-y-6">
                <SheetHeader className="pb-4 border-b border-border text-left">
                    <div className="flex flex-col sm:flex-row items-center sm:items-start gap-4 text-center sm:text-left">
                        {/* Photo de profil ou avatar */}
                        <div className="size-20 rounded-2xl border-2 border-primary/20 bg-muted/40 overflow-hidden flex items-center justify-center shrink-0 shadow-sm">
                            {eleve.photo_url ? (
                                <img src={eleve.photo_url} alt={eleve.nom_complet} className="size-full object-cover" />
                            ) : (
                                <User className="size-10 text-muted-foreground/50" />
                            )}
                        </div>

                        {/* Identité principale */}
                        <div className="min-w-0 flex-1">
                            <div className="flex items-center justify-center sm:justify-start gap-2 flex-wrap">
                                <Badge variant="outline" className="font-mono text-xs font-bold bg-primary/5 text-primary border-primary/20">
                                    {eleve.matricule}
                                </Badge>
                                {eleve.matricule_national && (
                                    <Badge variant="outline" className="font-mono text-[10px] text-muted-foreground">
                                        Nat: {eleve.matricule_national}
                                    </Badge>
                                )}
                                <Badge
                                    className={`text-[10px] font-semibold uppercase ${
                                        inscription.statut === 'validee'
                                            ? 'bg-emerald-500 text-white'
                                            : inscription.statut === 'en_attente'
                                            ? 'bg-amber-500 text-white'
                                            : inscription.statut === 'suspendue'
                                            ? 'bg-red-500 text-white'
                                            : 'bg-zinc-500 text-white'
                                    }`}
                                >
                                    {inscription.statut}
                                </Badge>
                            </div>

                            <SheetTitle className="text-xl font-bold mt-1 text-foreground">
                                {eleve.nom_complet}
                            </SheetTitle>

                            <SheetDescription className="text-xs text-muted-foreground mt-0.5">
                                {eleve.sexe === 'M' ? 'Masculin (Garçon)' : 'Féminin (Fille)'}
                                {age !== null ? ` • ${age} ans` : ''}
                                {eleve.date_naissance ? ` • Né(e) le ${formatDate(eleve.date_naissance)}` : ''}
                                {eleve.lieu_naissance ? ` à ${eleve.lieu_naissance}` : ''}
                            </SheetDescription>
                        </div>
                    </div>
                </SheetHeader>

                {/* 1. Affectation Académique Actuelle */}
                <div className="rounded-2xl border border-border bg-card p-4 space-y-3">
                    <div className="flex items-center justify-between">
                        <span className="text-xs font-bold uppercase tracking-wider text-muted-foreground flex items-center gap-1.5">
                            <School className="size-4 text-primary" />
                            Scolarité en cours
                        </span>
                        <Badge variant="secondary" className="text-xs">
                            {inscription.is_redoublant ? 'Redoublant' : 'Nouveau / Promu'}
                        </Badge>
                    </div>

                    <div className="grid grid-cols-1 min-[420px]:grid-cols-2 gap-3 text-xs pt-1">
                        <div>
                            <span className="text-muted-foreground block text-[11px]">Classe :</span>
                            <span className="font-bold text-foreground text-sm">{classe?.nom ?? 'Non assignée'}</span>
                        </div>
                        <div>
                            <span className="text-muted-foreground block text-[11px]">Niveau & Cycle :</span>
                            <span className="font-semibold text-foreground">
                                {classe?.niveau?.nom ?? 'N/A'} ({classe?.niveau?.cycle?.nom ?? ''})
                            </span>
                        </div>
                        <div>
                            <span className="text-muted-foreground block text-[11px]">Établissement / Campus :</span>
                            <span className="font-semibold text-foreground">
                                {inscription.etablissement?.nom ?? classe?.niveau?.cycle?.section?.etablissement?.nom ?? 'Campus Principal'}
                            </span>
                        </div>
                        <div>
                            <span className="text-muted-foreground block text-[11px]">Date d'inscription :</span>
                            <span className="font-semibold text-foreground">
                                {formatDate(inscription.date_inscription)}
                            </span>
                        </div>
                    </div>

                    {inscription.etablissement_provenance && (
                        <div className="pt-2 border-t border-border/60 text-xs text-muted-foreground">
                            <span>Établissement d'origine : </span>
                            <span className="font-semibold text-foreground">{inscription.etablissement_provenance}</span>
                        </div>
                    )}
                </div>

                {/* 2. Parents, Filiation & Coordonnées */}
                <div className="rounded-2xl border border-border bg-card p-4 space-y-3">
                    <span className="text-xs font-bold uppercase tracking-wider text-muted-foreground flex items-center gap-1.5">
                        <Phone className="size-4 text-emerald-600" />
                        Contacts Parents & Tuteurs
                    </span>

                    <div className="space-y-2 text-xs pt-1">
                        <div className="flex flex-col sm:flex-row sm:items-center justify-between p-2 rounded-xl bg-muted/40 gap-1">
                            <span className="text-muted-foreground">Téléphone Principal (SMS / Portail) :</span>
                            <a
                                href={`tel:${eleve.telephone_parent}`}
                                className="font-bold font-mono text-primary hover:underline flex items-center gap-1"
                            >
                                <Phone className="size-3.5" />
                                {eleve.telephone_parent || 'Non renseigné'}
                            </a>
                        </div>

                        {eleve.email_parent && (
                            <div className="flex flex-col sm:flex-row sm:items-center justify-between p-2 rounded-xl bg-muted/40 gap-1">
                                <span className="text-muted-foreground">Email du tuteur :</span>
                                <a href={`mailto:${eleve.email_parent}`} className="font-semibold hover:underline flex items-center gap-1">
                                    <Mail className="size-3.5" />
                                    {eleve.email_parent}
                                </a>
                            </div>
                        )}

                        <div className="grid grid-cols-1 min-[420px]:grid-cols-2 gap-2 pt-1">
                            {eleve.nom_pere && (
                                <div>
                                    <span className="text-muted-foreground block text-[11px]">Père :</span>
                                    <span className="font-semibold text-foreground">{eleve.nom_pere}</span>
                                </div>
                            )}
                            {eleve.nom_mere && (
                                <div>
                                    <span className="text-muted-foreground block text-[11px]">Mère :</span>
                                    <span className="font-semibold text-foreground">{eleve.nom_mere}</span>
                                </div>
                            )}
                            {eleve.nom_tuteur && (
                                <div>
                                    <span className="text-muted-foreground block text-[11px]">Tuteur Légal :</span>
                                    <span className="font-semibold text-foreground">{eleve.nom_tuteur}</span>
                                </div>
                            )}
                        </div>

                        {eleve.adresse && (
                            <div className="pt-2 border-t border-border/60 flex items-start gap-1.5 text-muted-foreground">
                                <MapPin className="size-3.5 mt-0.5 shrink-0" />
                                <span>{eleve.adresse}</span>
                            </div>
                        )}
                    </div>
                </div>

                {/* Boutons d'actions responsive */}
                <div className="flex flex-col-reverse sm:flex-row gap-2 pt-2">
                    {onEdit && (
                        <Button
                            type="button"
                            variant="secondary"
                            className="w-full sm:flex-1 gap-2"
                            onClick={() => {
                                onOpenChange(false);
                                onEdit(eleve);
                            }}
                        >
                            Modifier les informations
                        </Button>
                    )}
                    <Button type="button" variant="outline" onClick={() => onOpenChange(false)} className="w-full sm:w-auto">
                        Fermer
                    </Button>
                </div>
            </SheetContent>
        </Sheet>
    );
}
