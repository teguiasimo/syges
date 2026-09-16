import React, { useState } from 'react';
import { router } from '@inertiajs/react';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
    DialogFooter,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { toast } from 'sonner';
import { UserCheck, Search, CheckCircle2 } from 'lucide-react';
import type { EtablissementRef, AnneeScolaireRef, ClasseRef } from '@/types/eleve';

/**
 * Propriétés du composant ReinscriptionModal.
 */
interface ReinscriptionModalProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    etablissements: EtablissementRef[];
    anneesScolaires: AnneeScolaireRef[];
    selectedAnneeId: number;
}

/**
 * Modal de réinscription d'un ancien élève de la Base Globale pour la nouvelle année scolaire.
 *
 * @param {ReinscriptionModalProps} props
 * @returns {JSX.Element}
 */
export function ReinscriptionModal({
    open,
    onOpenChange,
    etablissements,
    anneesScolaires,
    selectedAnneeId,
}: ReinscriptionModalProps) {
    const [loading, setLoading] = useState(false);
    const [searchQuery, setSearchQuery] = useState('');
    const [searching, setSearching] = useState(false);
    const [searchResults, setSearchResults] = useState<any[]>([]);
    const [selectedEleve, setSelectedEleve] = useState<any | null>(null);

    const [targetClasseId, setTargetClasseId] = useState('');
    const [isRedoublant, setIsRedoublant] = useState('0');
    const [statut, setStatut] = useState('validee');
    const [observations, setObservations] = useState('');

    // Liste des classes
    const availableClasses: ClasseRef[] = React.useMemo(() => {
        const list: ClasseRef[] = [];
        etablissements.forEach((etab) => {
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
    }, [etablissements]);

    // Recherche d'anciens élèves via fetch JSON rapide
    const handleSearch = async () => {
        if (!searchQuery.trim()) return;
        setSearching(true);
        try {
            const res = await fetch(`/api/search-anciens-eleves?q=${encodeURIComponent(searchQuery)}&annee_id=${selectedAnneeId}`);
            if (res.ok) {
                const data = await res.json();
                setSearchResults(data);
            }
        } catch {
            // Si pas d'endpoint dédié, recherche directe ou fallback
        } finally {
            setSearching(false);
        }
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!selectedEleve) {
            toast.error('Veuillez sélectionner un élève à réinscrire.');
            return;
        }

        if (!targetClasseId) {
            toast.error('Veuillez choisir la classe de réinscription.');
            return;
        }

        setLoading(true);

        router.post('/administration/eleves/reinscrire', {
            eleve_id: selectedEleve.id,
            classe_id: targetClasseId,
            annee_scolaire_id: selectedAnneeId,
            is_redoublant: isRedoublant === '1',
            statut,
            observations,
        }, {
            onSuccess: () => {
                setLoading(false);
                toast.success(`L'élève ${selectedEleve.nom_complet} a été réinscrit avec succès !`);
                onOpenChange(false);
                setSelectedEleve(null);
                setSearchQuery('');
                setSearchResults([]);
                setTargetClasseId('');
            },
            onError: () => {
                setLoading(false);
                toast.error('Erreur lors de la réinscription.');
            },
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-w-2xl max-h-[90vh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2 text-xl">
                        <UserCheck className="size-5 text-emerald-600" />
                        Réinscrire un Ancien Élève
                    </DialogTitle>
                    <DialogDescription>
                        Recherchez un élève déjà existant dans la Base Globale pour l'affecter à une classe de l'année scolaire active.
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={handleSubmit} className="space-y-4 pt-2">
                    {/* Étape 1 : Recherche de l'élève */}
                    <div className="space-y-2">
                        <Label>1. Rechercher l'élève (par Nom, Prénom ou Matricule)</Label>
                        <div className="flex gap-2">
                            <Input
                                value={searchQuery}
                                onChange={(e) => setSearchQuery(e.target.value)}
                                onKeyDown={(e) => e.key === 'Enter' && (e.preventDefault(), handleSearch())}
                                placeholder="Tapez un matricule ou un nom..."
                                className="flex-1"
                            />
                            <Button type="button" variant="secondary" onClick={handleSearch} disabled={searching} className="gap-2">
                                <Search className="size-4" />
                                {searching ? 'Recherche...' : 'Chercher'}
                            </Button>
                        </div>
                    </div>

                    {/* Résultats de recherche ou sélection */}
                    {searchResults.length > 0 && (
                        <div className="border border-border rounded-xl max-h-48 overflow-y-auto divide-y divide-border">
                            {searchResults.map((el) => (
                                <div
                                    key={el.id}
                                    onClick={() => setSelectedEleve(el)}
                                    className={`p-3 flex items-center justify-between cursor-pointer transition-colors ${
                                        selectedEleve?.id === el.id ? 'bg-primary/10 border-l-4 border-primary' : 'hover:bg-muted/40'
                                    }`}
                                >
                                    <div>
                                        <p className="text-sm font-bold text-foreground">{el.nom_complet}</p>
                                        <p className="text-xs text-muted-foreground font-mono">
                                            {el.matricule} • Né(e) le {el.date_naissance ? new Date(el.date_naissance).toLocaleDateString('fr-FR') : 'N/A'} • {el.telephone_parent}
                                        </p>
                                    </div>
                                    {selectedEleve?.id === el.id && (
                                        <CheckCircle2 className="size-5 text-primary" />
                                    )}
                                </div>
                            ))}
                        </div>
                    )}

                    {selectedEleve && (
                        <div className="p-3 bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800 rounded-xl flex items-center justify-between">
                            <div>
                                <span className="text-xs font-semibold text-emerald-800 dark:text-emerald-300 uppercase tracking-wider">
                                    Élève sélectionné :
                                </span>
                                <p className="text-sm font-bold text-foreground">{selectedEleve.nom_complet}</p>
                                <p className="text-xs text-muted-foreground font-mono">Matricule : {selectedEleve.matricule}</p>
                            </div>
                            <Button type="button" variant="ghost" size="sm" onClick={() => setSelectedEleve(null)}>
                                Changer
                            </Button>
                        </div>
                    )}

                    {/* Étape 2 : Choix de la classe et options */}
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2 border-t border-border">
                        <div className="sm:col-span-2">
                            <Label htmlFor="target_classe">2. Classe de Réinscription *</Label>
                            <select
                                id="target_classe"
                                value={targetClasseId}
                                onChange={(e) => setTargetClasseId(e.target.value)}
                                required
                                className="w-full mt-1 h-9 rounded-md border border-input bg-background px-3 text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                            >
                                <option value="">-- Sélectionnez la nouvelle classe --</option>
                                {availableClasses.map((cls) => (
                                    <option key={cls.id} value={cls.id}>
                                        {cls.nom} ({cls.niveau?.nom ?? 'Niveau'} — {cls.niveau?.cycle?.section?.etablissement?.nom ?? ''})
                                    </option>
                                ))}
                            </select>
                        </div>

                        <div>
                            <Label htmlFor="reinsc_statut">Statut de l'inscription</Label>
                            <select
                                id="reinsc_statut"
                                value={statut}
                                onChange={(e) => setStatut(e.target.value)}
                                className="w-full mt-1 h-9 rounded-md border border-input bg-background px-3 text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                            >
                                <option value="validee">Validée (Officielle)</option>
                                <option value="en_attente">En attente (Dossier à valider)</option>
                            </select>
                        </div>

                        <div>
                            <Label htmlFor="reinsc_redoublant">Régime</Label>
                            <select
                                id="reinsc_redoublant"
                                value={isRedoublant}
                                onChange={(e) => setIsRedoublant(e.target.value)}
                                className="w-full mt-1 h-9 rounded-md border border-input bg-background px-3 text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                            >
                                <option value="0">Promu (Nouveau niveau)</option>
                                <option value="1">Redoublant (Répète la classe)</option>
                            </select>
                        </div>

                        <div className="sm:col-span-2">
                            <Label htmlFor="reinsc_obs">Observations</Label>
                            <Input
                                id="reinsc_obs"
                                value={observations}
                                onChange={(e) => setObservations(e.target.value)}
                                placeholder="Remarques particulières sur la réinscription..."
                                className="mt-1"
                            />
                        </div>
                    </div>

                    <DialogFooter className="pt-4 border-t border-border">
                        <Button type="button" variant="ghost" onClick={() => onOpenChange(false)}>
                            Annuler
                        </Button>
                        <Button type="submit" disabled={loading || !selectedEleve || !targetClasseId}>
                            {loading ? 'Réinscription...' : 'Valider la Réinscription'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
