import { router } from '@inertiajs/react';
import { Image as ImageIcon, Loader2, Upload } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import type { LoginSlideRecord } from '@/types';

/**
 * Taille maximale autorisée côté client pour la photographie d'un slide (10 Mo).
 */
const MAX_SLIDE_IMAGE_BYTES = 10 * 1024 * 1024;

/**
 * Style obligatoire des toasts de succès SYGES (position haut-droite, couleur verte).
 */
const SUCCESS_TOAST_STYLE = {
    backgroundColor: '#10b981',
    color: '#ffffff',
    border: '1px solid #059669',
} as const;

/**
 * Propriétés de la modale d'ajout ou d'édition d'un slide du carrousel de connexion.
 */
interface LoginSlideModalProps {
    /** Indique si la boîte de dialogue est ouverte */
    open: boolean;
    /** Callback d'ouverture/fermeture contrôlée */
    onOpenChange: (open: boolean) => void;
    /** Diapositive existante à éditer, ou `null` pour une création */
    slide: LoginSlideRecord | null;
}

/**
 * Formate un nom de fichier en titre lisible par défaut lors de l'importation d'une nouvelle photo.
 *
 * @param {string} filename Nom brut du fichier sélectionné
 * @returns {string} Titre nettoyé et mis en forme
 */
function buildTitleFromFilename(filename: string): string {
    const baseName = filename.replace(/\.[^/.]+$/, '');
    const normalized = baseName.replace(/[_-]+/g, ' ').replace(/\s+/g, ' ').trim();
    if (!normalized) {
        return 'Vie scolaire & Campus';
    }

    return normalized.charAt(0).toUpperCase() + normalized.slice(1, 120);
}

/**
 * Modale d'ajout et d'édition d'un slide personnalisé du carrousel `/login`.
 *
 * Prend en charge le chargement multipart jusqu'à 10 Mo (JPEG, PNG, WebP) avec aperçu instantané,
 * la saisie du titre et du message affichés en superposition, ainsi que le statut d'activation.
 *
 * @param {LoginSlideModalProps} props Propriétés du composant modal
 * @returns {JSX.Element} Dialogue de configuration d'une diapositive
 */
export function LoginSlideModal({
    open,
    onOpenChange,
    slide,
}: LoginSlideModalProps) {
    const isEditing = slide !== null;
    const fileInputRef = useRef<HTMLInputElement | null>(null);

    const [titre, setTitre] = useState('');
    const [message, setMessage] = useState('');
    const [isActive, setIsActive] = useState(true);
    const [imageFile, setImageFile] = useState<File | null>(null);
    const [objectPreviewUrl, setObjectPreviewUrl] = useState<string | null>(null);
    const [clientFileError, setClientFileError] = useState<string | null>(null);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [processing, setProcessing] = useState(false);

    // Synchronise les champs dès l'ouverture de la modale ou le changement de slide ciblé
    useEffect(() => {
        if (!open) {
            return;
        }

        setTitre(slide?.titre ?? '');
        setMessage(slide?.message ?? '');
        setIsActive(slide ? slide.is_active : true);
        setImageFile(null);
        setClientFileError(null);
        setErrors({});
        setProcessing(false);

        setObjectPreviewUrl((prev) => {
            if (prev) {
                URL.revokeObjectURL(prev);
            }
            return null;
        });

        if (fileInputRef.current) {
            fileInputRef.current.value = '';
        }
    }, [open, slide]);

    // Nettoie les URL temporaires créées par URL.createObjectURL lors du démontage
    useEffect(() => {
        return () => {
            if (objectPreviewUrl) {
                URL.revokeObjectURL(objectPreviewUrl);
            }
        };
    }, [objectPreviewUrl]);

    const currentPreviewSrc = objectPreviewUrl || slide?.image_path || null;

    /**
     * Valide et prépare l'aperçu local du fichier photo sélectionné par l'administrateur.
     *
     * @param {React.ChangeEvent<HTMLInputElement>} event Événement de changement du champ fichier
     */
    const handleFileChange = (event: React.ChangeEvent<HTMLInputElement>) => {
        const selected = event.target.files?.[0] ?? null;
        setClientFileError(null);

        if (!selected) {
            return;
        }

        if (selected.size > MAX_SLIDE_IMAGE_BYTES) {
            setClientFileError(
                'La photo dépasse la limite autorisée de 10 Mo. Veuillez choisir un fichier plus léger.',
            );
            event.target.value = '';
            return;
        }

        if (objectPreviewUrl) {
            URL.revokeObjectURL(objectPreviewUrl);
        }

        const nextUrl = URL.createObjectURL(selected);
        setImageFile(selected);
        setObjectPreviewUrl(nextUrl);

        // Pré-remplit un titre propre lors d'un ajout si aucun titre n'a encore été saisi
        if (!isEditing && titre.trim() === '') {
            setTitre(buildTitleFromFilename(selected.name));
        }
    };

    /**
     * Envoie la création ou la mise à jour du slide en multipart/form-data vers le backend Laravel.
     *
     * @param {React.FormEvent<HTMLFormElement>} event Événement de soumission du formulaire
     */
    const handleSubmit = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        setErrors({});

        if (!isEditing && !imageFile) {
            setClientFileError('Veuillez sélectionner une photo pour créer ce slide.');
            return;
        }

        const endpoint = isEditing
            ? `/administration/institution/login-appearance/slides/${slide.id}`
            : '/administration/institution/login-appearance/slides';

        const payload: {
            titre: string;
            message: string;
            is_active: number;
            image_file?: File;
        } = {
            titre: titre.trim(),
            message: message.trim(),
            is_active: isActive ? 1 : 0,
        };

        if (imageFile) {
            payload.image_file = imageFile;
        }

        setProcessing(true);

        router.post(endpoint, payload, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                toast.success(
                    isEditing
                        ? 'Slide mis à jour avec succès.'
                        : 'Slide ajouté au carrousel de connexion avec succès.',
                    {
                        position: 'top-right',
                        style: SUCCESS_TOAST_STYLE,
                    },
                );
                onOpenChange(false);
            },
            onError: (serverErrors) => {
                setErrors(serverErrors);
                toast.error(
                    'Impossible d’enregistrer le slide. Vérifiez les champs signalés.',
                    {
                        position: 'top-right',
                    },
                );
            },
            onFinish: () => {
                setProcessing(false);
            },
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-[560px] max-h-[92vh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>
                        {isEditing ? 'Modifier le slide du carrousel' : 'Ajouter un slide personnalisé'}
                    </DialogTitle>
                    <DialogDescription>
                        Importez une photo de l&apos;établissement et définissez le message affiché sur l&apos;écran de connexion.
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={handleSubmit} className="space-y-5 mt-2">
                    {/* Zone d'aperçu et d'importation de la photographie */}
                    <div className="space-y-2">
                        <Label htmlFor="slide-image-file">
                            Photo du slide {!isEditing && <span className="text-destructive">*</span>}
                        </Label>

                        <div
                            onClick={() => fileInputRef.current?.click()}
                            role="button"
                            tabIndex={0}
                            onKeyDown={(e) => {
                                if (e.key === 'Enter' || e.key === ' ') {
                                    e.preventDefault();
                                    fileInputRef.current?.click();
                                }
                            }}
                            className="group relative overflow-hidden rounded-xl border-2 border-dashed border-muted-foreground/30 hover:border-primary/60 transition-colors bg-muted/30 cursor-pointer"
                        >
                            {currentPreviewSrc ? (
                                <div className="relative aspect-video w-full overflow-hidden bg-slate-950">
                                    <img
                                        src={currentPreviewSrc}
                                        alt={titre || 'Aperçu du slide'}
                                        className="h-full w-full object-cover transition-transform duration-300 group-hover:scale-[1.02]"
                                    />
                                    <div className="absolute inset-0 bg-gradient-to-t from-slate-950/80 via-slate-950/20 to-transparent flex items-end justify-between p-3">
                                        <div className="text-left text-white pr-3 min-w-0">
                                            <p className="text-xs font-semibold truncate">
                                                {titre.trim() || 'Titre du slide'}
                                            </p>
                                            <p className="text-[11px] text-slate-300 truncate">
                                                {message.trim() || 'Message d’accompagnement'}
                                            </p>
                                        </div>
                                        <span className="inline-flex items-center gap-1.5 rounded-md bg-white/15 backdrop-blur-sm border border-white/20 px-2.5 py-1 text-xs font-medium text-white shrink-0">
                                            <Upload className="size-3.5" />
                                            Changer
                                        </span>
                                    </div>
                                </div>
                            ) : (
                                <div className="flex flex-col items-center justify-center py-9 px-4 text-center">
                                    <div className="rounded-full bg-primary/10 p-3 text-primary mb-2.5">
                                        <ImageIcon className="size-6" />
                                    </div>
                                    <p className="text-sm font-medium text-foreground">
                                        Cliquez pour importer une photo
                                    </p>
                                    <p className="text-xs text-muted-foreground mt-1">
                                        JPG, PNG ou WebP jusqu&apos;à 10 Mo • Recadrée et optimisée automatiquement
                                    </p>
                                </div>
                            )}

                            <input
                                ref={fileInputRef}
                                id="slide-image-file"
                                type="file"
                                accept="image/jpeg,image/png,image/webp"
                                onChange={handleFileChange}
                                className="hidden"
                            />
                        </div>

                        {(clientFileError || errors.image_file) && (
                            <p className="text-xs font-medium text-destructive">
                                {clientFileError || errors.image_file}
                            </p>
                        )}
                    </div>

                    {/* Titre du slide */}
                    <div className="space-y-2">
                        <div className="flex items-center justify-between">
                            <Label htmlFor="slide-titre">
                                Titre affiché <span className="text-destructive">*</span>
                            </Label>
                            <span className="text-[11px] text-muted-foreground tabular-nums">
                                {titre.length}/120
                            </span>
                        </div>
                        <Input
                            id="slide-titre"
                            value={titre}
                            onChange={(e) => setTitre(e.target.value)}
                            maxLength={120}
                            placeholder="Ex. : Cour d'honneur & Bâtiment Principal"
                            required
                        />
                        {errors.titre && (
                            <p className="text-xs font-medium text-destructive">{errors.titre}</p>
                        )}
                    </div>

                    {/* Message descriptif optionnel */}
                    <div className="space-y-2">
                        <div className="flex items-center justify-between">
                            <Label htmlFor="slide-message">Message d&apos;accompagnement</Label>
                            <span className="text-[11px] text-muted-foreground tabular-nums">
                                {message.length}/255
                            </span>
                        </div>
                        <Textarea
                            id="slide-message"
                            value={message}
                            onChange={(e) => setMessage(e.target.value)}
                            maxLength={255}
                            rows={3}
                            placeholder="Ex. : Encadrement de proximité, discipline et réussite scolaire au quotidien."
                        />
                        {errors.message && (
                            <p className="text-xs font-medium text-destructive">{errors.message}</p>
                        )}
                    </div>

                    {/* Activation immédiate */}
                    <div className="flex items-start gap-3 rounded-lg border bg-muted/30 p-3">
                        <Checkbox
                            id="slide-is-active"
                            checked={isActive}
                            onCheckedChange={(checked) => setIsActive(checked === true)}
                            className="mt-0.5"
                        />
                        <div className="grid gap-1 leading-none">
                            <Label
                                htmlFor="slide-is-active"
                                className="text-sm font-medium cursor-pointer"
                            >
                                Slide actif dans le carrousel
                            </Label>
                            <p className="text-xs text-muted-foreground">
                                Décochez pour conserver cette photo en brouillon sans l&apos;afficher sur la page de connexion.
                            </p>
                        </div>
                    </div>

                    <DialogFooter className="gap-2 pt-2">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => onOpenChange(false)}
                            disabled={processing}
                        >
                            Annuler
                        </Button>
                        <Button type="submit" disabled={processing} className="gap-2">
                            {processing && <Loader2 className="size-4 animate-spin" />}
                            <span>
                                {isEditing ? 'Enregistrer les modifications' : 'Ajouter au carrousel'}
                            </span>
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
