import React, { useState } from 'react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { BarChart3, PieChart } from 'lucide-react';
import { cn } from '@/lib/utils';
import type { ClasseDensiteDetail, CycleRepartitionDetail } from '@/types/statistique-deliberation';

/**
 * Palette de couleurs officielles harmonisée avec la plateforme ministérielle (MINESEC / Matr. National).
 * Respecte la charte visuelle SYGES et le bannissement strict du pourpre (Purple Ban).
 */
const MINISTERIAL_CYCLE_COLORS = [
    '#0d9488', // Deuxième Cycle - Francophone - Général (Teal / Sarcelle)
    '#212121', // Deuxième Cycle - Francophone - Technique (Noir / Anthracite)
    '#f59e0b', // Premier Cycle - Francophone - Général (Jaune d'or / Ambre)
    '#00acc1', // Premier Cycle - Francophone - Technique (Turquoise / Cyan)
    '#1e3a8a', // Premier Cycle - Anglophone - General (Bleu marine)
    '#059669', // Second Cycle - Anglophone - General (Émeraude)
    '#ea580c', // Technique / Commercial (Orange)
    '#db2777', // Autres (Rose framboise)
];

interface BarChartClassesProps {
    classes: ClasseDensiteDetail[];
}

/**
 * Calcule l'échelle maximale dynamique de l'axe Y et les paliers de graduation.
 * Prend en compte à la fois la plus haute capacité configurée et le plus grand effectif inscrit.
 *
 * @param {ClasseDensiteDetail[]} classes - Liste des classes avec effectifs et capacités.
 * @returns {{ yAxisMax: number, ySteps: number[], maxCapacite: number, maxEffectif: number }}
 */
function computeYAxis(classes: ClasseDensiteDetail[]) {
    const maxEffectif = Math.max(0, ...classes.map((c) => c.effectif || 0));
    const maxCapacite = Math.max(0, ...classes.map((c) => c.capacite || 0));
    const peak = Math.max(maxEffectif, maxCapacite, 20);

    // Détermination d'un pas de graduation équilibré (10, 20, 25 ou 50)
    let step = 10;
    if (peak > 50) step = 20;
    if (peak > 100) step = 25;
    if (peak > 200) step = 50;

    const yAxisMax = Math.ceil(peak / step) * step;
    const ySteps: number[] = [];
    for (let v = 0; v <= yAxisMax; v += step) {
        ySteps.push(v);
    }

    return { yAxisMax, ySteps, maxCapacite, maxEffectif };
}

/**
 * Composant de graphique en barres (Histogramme) pour la répartition des élèves par classe.
 * Rendu vectoriel SVG natif optimisé pour une pleine largeur panoramique, responsive et interactif.
 * Calcule dynamiquement l'échelle maximale en fonction de la capacité configurée (ex: 80) et des effectifs réels.
 *
 * @param {BarChartClassesProps} props - Liste des classes avec effectifs et capacités.
 * @returns {JSX.Element}
 */
export function BarChartClasses({ classes }: BarChartClassesProps) {
    const [hoveredIdx, setHoveredIdx] = useState<number | null>(null);

    if (!classes || classes.length === 0) {
        return (
            <div className="flex h-56 items-center justify-center text-sm text-muted-foreground italic">
                Aucune classe enregistrée pour cet établissement.
            </div>
        );
    }

    // Calcul dynamique de l'échelle de l'axe Y basée sur les capacités configurées et effectifs
    const { yAxisMax, ySteps, maxCapacite } = computeYAxis(classes);

    // Dimensions SVG adaptatives pour exploiter toute la largeur de l'écran
    const totalBars = classes.length;
    const svgWidth = Math.max(960, totalBars * 48 + 120);
    const svgHeight = 275;
    const chartTopPadding = 25;
    const chartBottomPadding = 70;
    const usableHeight = svgHeight - chartTopPadding - chartBottomPadding;

    const xStart = 50;
    const xEnd = svgWidth - 30;
    const usableWidth = xEnd - xStart;

    return (
        <div className="relative w-full overflow-x-auto">
            <div className="min-w-175">
                {/* Légende du graphique enrichie avec la capacité d'accueil */}
                <div className="mb-2 flex flex-wrap items-center justify-center gap-4 text-xs font-medium text-muted-foreground">
                    <div className="flex items-center gap-1.5">
                        <span className="inline-block size-3 rounded-xs bg-[#1b5e20]" />
                        <span>Effectif des élèves inscrits</span>
                    </div>
                    <div className="flex items-center gap-1.5">
                        <span className="inline-flex items-center justify-center size-3 rounded-xs border border-dashed border-slate-500 bg-muted/40" />
                        <span>Capacité configurée ({maxCapacite > 0 ? `${maxCapacite} max` : '80 max'})</span>
                    </div>
                </div>

                <div className="relative">
                    <svg viewBox={`0 0 ${svgWidth} ${svgHeight}`} className="w-full h-72 overflow-visible">
                        {/* Lignes de repère horizontales de l'axe Y */}
                        {ySteps.map((stepVal, idx) => {
                            const yPos = chartTopPadding + usableHeight - (stepVal / yAxisMax) * usableHeight;
                            return (
                                <g key={`grid-${idx}`}>
                                    <line
                                        x1={xStart}
                                        y1={yPos}
                                        x2={xEnd}
                                        y2={yPos}
                                        stroke="currentColor"
                                        strokeOpacity="0.12"
                                        strokeDasharray="3 3"
                                    />
                                    <text
                                        x={xStart - 8}
                                        y={yPos + 3.5}
                                        textAnchor="end"
                                        fontSize="9.5"
                                        fill="currentColor"
                                        className="fill-muted-foreground font-mono"
                                    >
                                        {Math.round(stepVal)}
                                    </text>
                                </g>
                            );
                        })}

                        {/* Axe horizontal X */}
                        <line
                            x1={xStart}
                            y1={chartTopPadding + usableHeight}
                            x2={xEnd}
                            y2={chartTopPadding + usableHeight}
                            stroke="currentColor"
                            strokeOpacity="0.3"
                            strokeWidth="1.2"
                        />

                        {/* Barres verticales des classes */}
                        {classes.map((cls, idx) => {
                            const slotWidth = usableWidth / totalBars;
                            const barWidth = Math.min(36, Math.max(16, slotWidth * 0.45));
                            const xCenter = xStart + idx * slotWidth + slotWidth / 2;
                            const xLeft = xCenter - barWidth / 2;

                            const barHeight = (cls.effectif / yAxisMax) * usableHeight;
                            const yTop = chartTopPadding + usableHeight - barHeight;
                            const isHovered = hoveredIdx === idx;

                            // Hauteur et repère de la capacité maximale de la classe
                            const capHeight = ((cls.capacite || 0) / yAxisMax) * usableHeight;
                            const yCapTop = chartTopPadding + usableHeight - capHeight;

                            return (
                                <g
                                    key={cls.id}
                                    className="cursor-pointer transition-all duration-150"
                                    onMouseEnter={() => setHoveredIdx(idx)}
                                    onMouseLeave={() => setHoveredIdx(null)}
                                >
                                    {/* Fond représentant la capacité d'accueil configurée */}
                                    {cls.capacite > 0 && (
                                        <rect
                                            x={xLeft}
                                            y={yCapTop}
                                            width={barWidth}
                                            height={Math.max(capHeight, 2)}
                                            rx={2.5}
                                            fill="currentColor"
                                            className="fill-muted/20"
                                        />
                                    )}

                                    {/* Trait pointillé de seuil de la capacité de la classe */}
                                    {cls.capacite > 0 && (
                                        <line
                                            x1={xLeft - 3}
                                            y1={yCapTop}
                                            x2={xLeft + barWidth + 3}
                                            y2={yCapTop}
                                            stroke={cls.is_surchargee ? '#ef4444' : '#64748b'}
                                            strokeWidth="1.5"
                                            strokeDasharray="2 2"
                                            strokeOpacity="0.8"
                                        />
                                    )}

                                    {/* Barre de données d'effectif réel */}
                                    <rect
                                        x={xLeft}
                                        y={yTop}
                                        width={barWidth}
                                        height={Math.max(barHeight, 2)}
                                        rx={2.5}
                                        fill={isHovered ? (cls.is_surchargee ? '#ef4444' : '#2e7d32') : (cls.is_surchargee ? '#dc2626' : '#1b5e20')}
                                        className="transition-colors duration-200"
                                    />

                                    {/* Valeur chiffrée au-dessus de la barre */}
                                    {cls.effectif > 0 && (
                                        <text
                                            x={xCenter}
                                            y={yTop - 5}
                                            textAnchor="middle"
                                            fontSize="9.5"
                                            fontWeight="600"
                                            fill="currentColor"
                                            className="fill-foreground font-mono"
                                        >
                                            {cls.effectif}
                                        </text>
                                    )}

                                    {/* Libellé de classe incliné à 42° sur l'axe X (style officiel MINESEC) */}
                                    <text
                                        x={xCenter}
                                        y={chartTopPadding + usableHeight + 14}
                                        transform={`rotate(-42, ${xCenter}, ${chartTopPadding + usableHeight + 14})`}
                                        textAnchor="end"
                                        fontSize="9.5"
                                        fill="currentColor"
                                        className={isHovered ? 'fill-foreground font-bold' : 'fill-muted-foreground'}
                                    >
                                        {cls.nom}
                                    </text>
                                </g>
                            );
                        })}
                    </svg>

                    {/* Infobulle interactive flottante lors du survol */}
                    {hoveredIdx !== null && classes[hoveredIdx] && (
                        <div
                            className="pointer-events-none absolute top-3 left-1/2 -translate-x-1/2 z-20 rounded-md border bg-popover px-3 py-1.5 text-xs shadow-md text-popover-foreground transition-all duration-150 flex items-center gap-3 animate-in fade-in"
                        >
                            <span className="font-bold text-primary">{classes[hoveredIdx].nom}</span>
                            <span className="font-mono">
                                <strong>{classes[hoveredIdx].effectif}</strong> élèves inscrits / {classes[hoveredIdx].capacite} places configurées
                            </span>
                            <Badge
                                variant={classes[hoveredIdx].is_surchargee ? 'destructive' : 'secondary'}
                                className="text-[10px] py-0 px-1.5"
                            >
                                {classes[hoveredIdx].taux_occupation}% d'occupation
                            </Badge>
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
}

interface PieChartCyclesProps {
    repartition: CycleRepartitionDetail[];
    totalEleves: number;
}

/**
 * Composant de diagramme circulaire / Donut chart pour la répartition des élèves par cycle.
 * Rendu vectoriel SVG avec segments angulaires précis et légende détaillée avec pourcentages.
 *
 * @param {PieChartCyclesProps} props - Données démographiques par cycle/section.
 * @returns {JSX.Element}
 */
export function DonutChartCycles({ repartition, totalEleves }: PieChartCyclesProps) {
    const [hoveredIdx, setHoveredIdx] = useState<number | null>(null);

    if (!repartition || repartition.length === 0 || totalEleves === 0) {
        return (
            <div className="flex h-56 items-center justify-center text-sm text-muted-foreground italic">
                Aucune donnée de cycle disponible.
            </div>
        );
    }

    // Rayon, épaisseur et circonférence agrandis pour un diagramme circulaire imposant et central
    const center = 130;
    const radius = 98;
    const strokeWidth = 44;
    const circumference = 2 * Math.PI * radius; // ≈ 615.752

    let cumulativePercentage = 0;
    const slices = repartition.map((item, idx) => {
        const percentage = totalEleves > 0 ? (item.total / totalEleves) * 100 : 0;
        const dashLength = (percentage / 100) * circumference;
        const strokeDashoffset = - (cumulativePercentage / 100) * circumference;
        cumulativePercentage += percentage;

        const color = MINISTERIAL_CYCLE_COLORS[idx % MINISTERIAL_CYCLE_COLORS.length];

        return {
            ...item,
            percentage,
            dashLength,
            strokeDashoffset,
            color,
        };
    });

    return (
        <div className="flex flex-col items-center gap-6 w-full py-2 min-w-0">
            {/* 1. Grand Diagramme Circulaire Donut (Centré en haut, adaptatif sur très petits écrans 320px) */}
            <div className="relative size-52 sm:size-72 max-w-full shrink-0">
                <svg viewBox="0 0 260 260" className="size-full overflow-visible">
                    {/* Anneau de fond discret (track) */}
                    <circle
                        cx={center}
                        cy={center}
                        r={radius}
                        fill="none"
                        stroke="currentColor"
                        strokeOpacity="0.08"
                        strokeWidth={strokeWidth}
                    />

                    {/* Segments circulaires du Donut Chart */}
                    {slices.map((slice, idx) => {
                        const isHovered = hoveredIdx === idx;
                        const gap = slices.length > 1 ? 3.5 : 0;
                        const dashArray = `${Math.max(0, slice.dashLength - gap)} ${circumference}`;

                        return (
                            <circle
                                key={idx}
                                cx={center}
                                cy={center}
                                r={radius}
                                fill="none"
                                stroke={slice.color}
                                strokeWidth={isHovered ? strokeWidth + 6 : strokeWidth}
                                strokeDasharray={dashArray}
                                strokeDashoffset={slice.strokeDashoffset}
                                transform={`rotate(-90 ${center} ${center})`}
                                className="cursor-pointer transition-all duration-200"
                                style={{
                                    filter: isHovered ? 'brightness(1.15) drop-shadow(0 4px 8px rgba(0,0,0,0.2))' : 'none',
                                }}
                                onMouseEnter={() => setHoveredIdx(idx)}
                                onMouseLeave={() => setHoveredIdx(null)}
                            />
                        );
                    })}
                </svg>

                {/* Donnée chiffrée centrale imposante */}
                <div className="pointer-events-none absolute inset-0 flex flex-col items-center justify-center text-center">
                    <span className="text-3xl sm:text-4xl font-extrabold font-mono text-foreground leading-none">
                        {hoveredIdx !== null ? slices[hoveredIdx].total : totalEleves}
                    </span>
                    <span className="text-xs font-semibold text-muted-foreground mt-1.5 uppercase tracking-widest">
                        {hoveredIdx !== null ? 'Élèves' : 'Total'}
                    </span>
                </div>
            </div>

            {/* 2. Intitulés et légende détaillés situés EN BAS du diagramme circulaire */}
            <div className="w-full pt-4 border-t border-border/60">
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 w-full">
                    {slices.map((slice, idx) => (
                        <div
                            key={idx}
                            className={`flex items-center justify-between gap-3 p-2.5 rounded-lg border transition-all cursor-pointer ${
                                hoveredIdx === idx
                                    ? 'bg-muted border-primary/40 shadow-xs ring-1 ring-primary/20'
                                    : 'bg-card/70 border-border/70 hover:bg-muted/50 hover:border-border'
                            }`}
                            onMouseEnter={() => setHoveredIdx(idx)}
                            onMouseLeave={() => setHoveredIdx(null)}
                        >
                            <div className="flex items-center gap-2.5 min-w-0">
                                <span
                                    className="size-4 shrink-0 rounded-xs shadow-xs"
                                    style={{ backgroundColor: slice.color }}
                                />
                                <span className="truncate text-foreground font-semibold text-xs sm:text-sm" title={slice.nom}>
                                    {slice.nom}
                                </span>
                            </div>
                            <div className="flex items-center gap-2 shrink-0 font-mono text-right">
                                <span className="font-bold text-foreground text-xs sm:text-sm">{slice.total}</span>
                                <span className="text-muted-foreground text-xs">({slice.percentage.toFixed(1)}%)</span>
                            </div>
                        </div>
                    ))}
                </div>
            </div>
        </div>
    );
}

/**
 * Carte autonome de Répartition des élèves par classe (Pleine largeur).
 *
 * @param {BarChartClassesProps} props - Liste des classes avec effectifs et capacités.
 * @returns {JSX.Element}
 */
export function CarteRepartitionClasses({ classes }: BarChartClassesProps) {
    return (
        <Card className="w-full">
            <CardHeader className="pb-3">
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                    <div>
                        <CardTitle className="text-base flex items-center gap-2">
                            <BarChart3 className="size-5 text-emerald-600" />
                            Répartition des élèves par classe
                        </CardTitle>
                        <CardDescription>
                            Effectif présent par salle de classe et proportion de scolarisation (Plateforme Ministérielle)
                        </CardDescription>
                    </div>
                    <Badge variant="outline" className="font-mono text-xs w-fit">
                        {classes.length} classe(s)
                    </Badge>
                </div>
            </CardHeader>
            <CardContent className="pt-0">
                <BarChartClasses classes={classes} />
            </CardContent>
        </Card>
    );
}

export interface CarteRepartitionCyclesProps extends PieChartCyclesProps {
    className?: string;
}

/**
 * Carte autonome de Répartition des élèves par cycle (Diagramme Donut).
 *
 * @param {CarteRepartitionCyclesProps} props - Données démographiques par cycle/section et style.
 * @returns {JSX.Element}
 */
export function CarteRepartitionCycles({ repartition, totalEleves, className }: CarteRepartitionCyclesProps) {
    return (
        <Card className={cn('flex flex-col h-full min-w-0', className)}>
            <CardHeader className="pb-3">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <CardTitle className="text-base flex items-center gap-2">
                            <PieChart className="size-4 text-teal-600 shrink-0" />
                            Répartition des élèves par cycle
                        </CardTitle>
                        <CardDescription>
                            Ventilation par sous-système (Général / Technique)
                        </CardDescription>
                    </div>
                    <Badge variant="outline" className="font-mono text-xs shrink-0">
                        {repartition.length} cycle(s)
                    </Badge>
                </div>
            </CardHeader>
            <CardContent className="flex-1 pt-0">
                <DonutChartCycles
                    repartition={repartition}
                    totalEleves={totalEleves}
                />
            </CardContent>
        </Card>
    );
}

