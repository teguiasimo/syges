import {
    Briefcase,
    Building2,
    Calendar,
    CheckCircle2,
    Clock,
    FileText,
    GraduationCap,
    KeyRound,
    Mail,
    MapPin,
    Phone,
    Shield,
    User,
    UserX,
    X,
} from 'lucide-react';
import React from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { formatDate, getAvatarStyle } from '@/lib/utils';
import type { Personnel } from '@/types/personnel';

/**
 * Propriétés attendues par la vue détaillée d'un membre du personnel.
 */
interface PersonnelDetailDrawerProps {
    /** Fiche du personnel à afficher */
    personnel: Personnel | null;
    /** Indique si le dialogue est ouvert */
    isOpen: boolean;
    /** Callback de fermeture */
    onClose: () => void;
    /** Callback optionnel pour déclencher la modification */
    onEdit?: (personnel: Personnel) => void;
}

/**
 * Composant d'affichage détaillé de la fiche 360° d'un membre du personnel.
 */
export function PersonnelDetailDrawer({
    personnel,
    isOpen,
    onClose,
    onEdit,
}: PersonnelDetailDrawerProps) {
    if (!personnel) return null;

    // Détermination de la couleur du badge de statut
    const getStatusVariant = (statut: string) => {
        switch (statut) {
            case 'Actif':
                return 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/50 dark:text-emerald-400 dark:border-emerald-800';
            case 'En congé':
                return 'bg-blue-50 text-blue-700 border-blue-200 dark:bg-blue-950/50 dark:text-blue-400 dark:border-blue-800';
            case 'Suspendu':
                return 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-950/50 dark:text-amber-400 dark:border-amber-800';
            default:
                return 'bg-zinc-100 text-zinc-700 border-zinc-200 dark:bg-zinc-800 dark:text-zinc-300 dark:border-zinc-700';
        }
    };

    return (
        <Dialog open={isOpen} onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="max-w-3xl max-h-[90vh] overflow-y-auto p-6 sm:p-8">
                <DialogHeader className="border-b pb-4">
                    <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                        <div className="flex items-center gap-3">
                            <div className={`size-12 rounded-xl font-bold text-lg flex items-center justify-center border shrink-0 ${getAvatarStyle(personnel.nom + ' ' + (personnel.prenom || ''))}`}>
                                {personnel.nom.slice(0, 1)}
                                {personnel.prenom ? personnel.prenom.slice(0, 1) : ''}
                            </div>
                            <div>
                                <DialogTitle className="text-xl font-bold text-foreground">
                                    {personnel.nom} {personnel.prenom}
                                </DialogTitle>
                                <div className="flex items-center gap-2 mt-1">
                                    <span className="font-mono text-xs font-semibold px-2 py-0.5 rounded bg-muted text-muted-foreground border">
                                        {personnel.matricule}
                                    </span>
                                    <span className={`inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium border ${getStatusVariant(personnel.statut)}`}>
                                        {personnel.statut}
                                    </span>
                                </div>
                            </div>
                        </div>

                        {personnel.user && (
                            <div className="flex items-center gap-2 text-xs font-medium px-3 py-1.5 rounded-lg bg-purple-50 dark:bg-purple-950/40 text-purple-700 dark:text-purple-300 border border-purple-200 dark:border-purple-800">
                                <KeyRound className="size-4 text-purple-600 dark:text-purple-400" />
                                <span>Compte SYGES lié ({personnel.user.login})</span>
                            </div>
                        )}
                    </div>
                </DialogHeader>

                <div className="space-y-6 pt-2">
                    {/* CARTE 1 : AFFECTATION & CONTRAT */}
                    <div className="p-4 rounded-xl bg-card border shadow-xs space-y-3">
                        <div className="flex items-center gap-2 text-sm font-semibold text-foreground border-b pb-2">
                            <Building2 className="size-4 text-blue-600 dark:text-blue-400" />
                            <span>Affectation Administrative & Contrat</span>
                        </div>

                        <div className="grid grid-cols-2 sm:grid-cols-3 gap-3 text-xs">
                            <div>
                                <span className="text-muted-foreground block">Établissement</span>
                                <strong className="text-foreground text-sm font-medium">
                                    {personnel.etablissement ? `${personnel.etablissement.nom} (${personnel.etablissement.code})` : 'Non renseigné'}
                                </strong>
                            </div>

                            <div>
                                <span className="text-muted-foreground block">Fonction Principale</span>
                                <strong className="text-foreground text-sm font-medium">
                                    {personnel.fonction_principale}
                                </strong>
                            </div>

                            <div>
                                <span className="text-muted-foreground block">Type de Contrat</span>
                                <strong className="text-foreground text-sm font-medium">
                                    {personnel.type_contrat}
                                </strong>
                            </div>

                            <div>
                                <span className="text-muted-foreground block">Date d'Embauche</span>
                                <strong className="text-foreground font-medium">
                                    {personnel.date_embauche ? formatDate(personnel.date_embauche) : '-'}
                                </strong>
                            </div>

                            <div>
                                <span className="text-muted-foreground block">Numéro CNPS</span>
                                <strong className="text-foreground font-medium">
                                    {personnel.numero_cnps || 'Non renseigné'}
                                </strong>
                            </div>

                            <div>
                                <span className="text-muted-foreground block">Diplôme le plus élevé</span>
                                <strong className="text-foreground font-medium">
                                    {personnel.diplome_plus_eleve || 'Non renseigné'}
                                </strong>
                            </div>
                        </div>
                    </div>

                    {/* CARTE 2 : VOLET PÉDAGOGIQUE (ENSEIGNANT) */}
                    {personnel.is_enseignant && (
                        <div className="p-4 rounded-xl bg-indigo-50/40 dark:bg-indigo-950/20 border border-indigo-100 dark:border-indigo-900/40 space-y-3">
                            <div className="flex items-center gap-2 text-sm font-semibold text-foreground border-b pb-2">
                                <GraduationCap className="size-4 text-indigo-600 dark:text-indigo-400" />
                                <span>Profil Pédagogique & Charge d'Enseignement</span>
                            </div>

                            <div className="grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs">
                                <div>
                                    <span className="text-muted-foreground block">Département</span>
                                    <strong className="text-foreground text-sm font-medium">
                                        {personnel.departement ? personnel.departement.nom : 'Aucun'}
                                    </strong>
                                </div>

                                <div>
                                    <span className="text-muted-foreground block">Discipline / Spécialité</span>
                                    <strong className="text-foreground text-sm font-medium">
                                        {personnel.specialite || 'Généraliste'}
                                    </strong>
                                </div>

                                <div>
                                    <span className="text-muted-foreground block">Quota Hebdomadaire</span>
                                    <strong className="text-indigo-700 dark:text-indigo-300 text-sm font-semibold">
                                        {personnel.volume_horaire_hebdo} heures / sem.
                                    </strong>
                                </div>

                                <div>
                                    <span className="text-muted-foreground block">Taux Vacation</span>
                                    <strong className="text-foreground text-sm font-medium">
                                        {personnel.taux_horaire_vacation ? `${Number(personnel.taux_horaire_vacation).toLocaleString()} FCFA/h` : 'Non applicable'}
                                    </strong>
                                </div>
                            </div>
                        </div>
                    )}

                    {/* CARTE 3 : COORDONNÉES ET CONTACTS */}
                    <div className="p-4 rounded-xl bg-card border shadow-xs space-y-3">
                        <div className="flex items-center gap-2 text-sm font-semibold text-foreground border-b pb-2">
                            <Phone className="size-4 text-amber-600 dark:text-amber-400" />
                            <span>Coordonnées & Informations Personnelles</span>
                        </div>

                        <div className="grid grid-cols-2 sm:grid-cols-3 gap-3 text-xs">
                            <div>
                                <span className="text-muted-foreground block">Téléphone Principal</span>
                                <a href={`tel:${personnel.telephone}`} className="text-indigo-600 dark:text-indigo-400 hover:underline font-medium text-sm">
                                    {personnel.telephone}
                                </a>
                            </div>

                            <div>
                                <span className="text-muted-foreground block">Téléphone Secondaire</span>
                                <span className="text-foreground font-medium">
                                    {personnel.telephone_secondaire || '-'}
                                </span>
                            </div>

                            <div>
                                <span className="text-muted-foreground block">Email</span>
                                {personnel.email ? (
                                    <a href={`mailto:${personnel.email}`} className="text-indigo-600 dark:text-indigo-400 hover:underline font-medium">
                                        {personnel.email}
                                    </a>
                                ) : (
                                    <span className="text-muted-foreground">-</span>
                                )}
                            </div>

                            <div>
                                <span className="text-muted-foreground block">Genre</span>
                                <span className="text-foreground font-medium">
                                    {personnel.sexe === 'M' ? 'Masculin' : 'Féminin'}
                                </span>
                            </div>

                            <div>
                                <span className="text-muted-foreground block">Date & Lieu de Naissance</span>
                                <span className="text-foreground font-medium">
                                    {personnel.date_naissance ? formatDate(personnel.date_naissance) : '-'}
                                    {personnel.lieu_naissance ? ` à ${personnel.lieu_naissance}` : ''}
                                </span>
                            </div>

                            <div>
                                <span className="text-muted-foreground block">Adresse Géographique</span>
                                <span className="text-foreground font-medium">
                                    {personnel.adresse || '-'}
                                </span>
                            </div>
                        </div>
                    </div>

                    {/* CARTE 4 : COMPTE UTILISATEUR APPLICATIF */}
                    <div className="p-4 rounded-xl bg-card border shadow-xs space-y-3">
                        <div className="flex items-center gap-2 text-sm font-semibold text-foreground border-b pb-2">
                            <KeyRound className="size-4 text-purple-600 dark:text-purple-400" />
                            <span>Accès Applicatif SYGES</span>
                        </div>

                        {personnel.user ? (
                            <div className="grid grid-cols-2 sm:grid-cols-3 gap-3 text-xs">
                                <div>
                                    <span className="text-muted-foreground block">Identifiant (Login)</span>
                                    <strong className="text-foreground font-mono text-sm">
                                        {personnel.user.login}
                                    </strong>
                                </div>

                                <div>
                                    <span className="text-muted-foreground block">Email Système</span>
                                    <span className="text-foreground font-medium">
                                        {personnel.user.email}
                                    </span>
                                </div>

                                <div>
                                    <span className="text-muted-foreground block">Statut du Compte</span>
                                    <span className={`inline-flex items-center gap-1 font-semibold ${personnel.user.is_active ? 'text-emerald-600' : 'text-rose-600'}`}>
                                        {personnel.user.is_active ? (
                                            <>
                                                <CheckCircle2 className="size-3.5" /> Actif
                                            </>
                                        ) : (
                                            <>
                                                <UserX className="size-3.5" /> Suspendu
                                            </>
                                        )}
                                    </span>
                                </div>
                            </div>
                        ) : (
                            <div className="text-xs text-muted-foreground py-2 flex items-center gap-2">
                                <UserX className="size-4" />
                                <span>Aucun compte utilisateur SYGES n'est actuellement rattaché à ce personnel. Vous pouvez en associer un en modifiant sa fiche.</span>
                            </div>
                        )}
                    </div>

                    {/* OBSERVATIONS ÉVENTUELLES */}
                    {personnel.observations && (
                        <div className="p-3 rounded-lg bg-muted/40 border text-xs space-y-1">
                            <span className="font-semibold text-foreground block">Observations & Notes RH :</span>
                            <p className="text-muted-foreground whitespace-pre-line">{personnel.observations}</p>
                        </div>
                    )}
                </div>

                <DialogFooter className="gap-2 border-t pt-4">
                    <Button
                        type="button"
                        variant="outline"
                        onClick={onClose}
                        className="cursor-pointer"
                    >
                        Fermer
                    </Button>
                    {onEdit && (
                        <Button
                            type="button"
                            onClick={() => {
                                onClose();
                                onEdit(personnel);
                            }}
                            className="bg-indigo-600 hover:bg-indigo-700 text-white cursor-pointer font-semibold"
                        >
                            Modifier la fiche
                        </Button>
                    )}
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
