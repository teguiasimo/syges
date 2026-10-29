<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Procès-Verbal du Conseil de Classe - {{ $classe->nom }} - {{ $periode ? $periode->nom : 'Bilan Annuel' }}</title>
    <style>
        @page {
            size: A4 landscape;
            margin: 7mm 8mm 7mm 8mm;
        }
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 7.5pt;
            color: #111827;
            line-height: 1.15;
            margin: 0;
            padding: 0;
        }
        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 5px;
        }
        .header-table td {
            vertical-align: top;
        }
        .bold { font-weight: bold; }
        .uppercase { text-transform: uppercase; }
        .text-left { text-align: left; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        
        .main-title {
            background-color: #1e3a8a;
            color: white;
            padding: 4px;
            text-align: center;
            font-weight: bold;
            font-size: 9.5pt;
            text-transform: uppercase;
            margin-bottom: 6px;
            border-radius: 3px;
        }
        
        .stats-grid {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 6px;
            font-size: 7.5pt;
        }
        .stats-grid td {
            border: 1px solid #9ca3af;
            padding: 3px 6px;
            background-color: #f9fafb;
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 7pt;
            margin-bottom: 8px;
        }
        .data-table th, .data-table td {
            border: 1px solid #4b5563;
            padding: 3px 4px;
        }
        .data-table th {
            background-color: #e5e7eb;
            font-weight: bold;
            text-align: center;
            text-transform: uppercase;
        }
        .data-table tr:nth-child(even) {
            background-color: #f9fafb;
        }
        
        .mention-badge {
            font-weight: bold;
            color: #065f46;
        }
        .blocked-badge {
            color: #991b1b;
            font-style: italic;
        }
        .promu { color: #065f46; font-weight: bold; }
        .redouble { color: #9a3412; font-weight: bold; }
        .exclu { color: #991b1b; font-weight: bold; }
        
        .signatures-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
        }
        .signatures-table td {
            width: 33.33%;
            text-align: center;
            vertical-align: top;
            font-size: 7.5pt;
        }
        .signature-box {
            height: 45px;
        }
    </style>
</head>
<body>
    <!-- En-tête officiel -->
    <table class="header-table">
        <tr>
            <td style="width: 35%;" class="text-left">
                <div class="bold uppercase" style="font-size: 7pt;">RÉPUBLIQUE DU CAMEROUN</div>
                <div style="font-size: 6pt; font-style: italic;">Paix – Travail – Patrie</div>
                <div class="uppercase" style="font-size: 6pt;">MINISTÈRE DES ENSEIGNEMENTS SECONDAIRES</div>
                <div class="bold uppercase" style="font-size: 7.5pt; color: #1e3a8a;">{{ $classe->niveau->cycle->section->etablissement->nom }}</div>
            </td>
            <td style="width: 30%;" class="text-center">
                @php
                    $etab = $classe->niveau->cycle->section->etablissement ?? null;
                    $logoSrc = ($etab && method_exists($etab, 'getEffectiveLogoBase64'))
                        ? $etab->getEffectiveLogoBase64()
                        : (file_exists(public_path('logo.png')) ? 'data:image/png;base64,' . base64_encode(file_get_contents(public_path('logo.png'))) : null);
                @endphp
                @if($logoSrc)
                    <img src="{{ $logoSrc }}" alt="Logo" style="max-height: 42px; max-width: 65px; object-fit: contain; margin-bottom: 2px;"><br>
                @endif
                <div class="bold uppercase" style="font-size: 8.5pt;">PROCES-VERBAL DE DELIBERATION</div>
                <div style="font-size: 7.5pt;">Session : <strong>{{ $periode ? $periode->nom : 'Bilan Annuel Définitif' }}</strong></div>
                <div style="font-size: 7pt;">Année Scolaire : <strong>{{ $annee->session ?? ($annee->code ?? $annee->nom) }}</strong></div>
            </td>
            <td style="width: 35%;" class="text-right">
                <div class="bold uppercase" style="font-size: 7pt;">REPUBLIC OF CAMEROON</div>
                <div style="font-size: 6pt; font-style: italic;">Peace – Work – Fatherland</div>
                <div style="font-size: 7pt;">Classe : <strong>{{ $classe->nom }}</strong></div>
                <div style="font-size: 6.5pt;">Prof. Principal : <strong>{{ $classe->professeurPrincipal ? $classe->professeurPrincipal->nom_complet : 'Non assigné' }}</strong></div>
                <div style="font-size: 6pt; color: #6b7280;">Édité le : {{ $dateGeneration }}</div>
            </td>
        </tr>
    </table>

    <!-- Synthèse statistique de classe -->
    @php
        $stats = $deliberation['statistiques_classe'] ?? [];
        $eleves = $deliberation['eleves'] ?? [];
        $totalEleves = count($eleves);
        $totalAdmis = collect($eleves)->filter(fn($e) => ($e['moyenne_obtenue'] ?? 0) >= 10.0)->count();
        $tauxReussite = $totalEleves > 0 ? round(($totalAdmis / $totalEleves) * 100, 1) : 0.0;
        $moyenneClasse = $stats['moyenne_classe'] ?? ($totalEleves > 0 ? round(collect($eleves)->avg('moyenne_obtenue'), 2) : 0.0);
        $plusForte = $stats['moyenne_max'] ?? (collect($eleves)->max('moyenne_obtenue') ?? 0.0);
        $plusFaible = $stats['moyenne_min'] ?? (collect($eleves)->min('moyenne_obtenue') ?? 0.0);
    @endphp
    <table class="stats-grid">
        <tr>
            <td>Effectif délibéré : <strong>{{ $totalEleves }}</strong></td>
            <td>Nombre Admis (≥10/20) : <strong>{{ $totalAdmis }}</strong></td>
            <td>Taux de réussite : <strong>{{ $tauxReussite }}%</strong></td>
            <td>Moyenne Classe : <strong>{{ number_format($moyenneClasse, 2) }}/20</strong></td>
            <td>Forte Moyenne : <strong>{{ number_format($plusForte, 2) }}/20</strong></td>
            <td>Faible Moyenne : <strong>{{ number_format($plusFaible, 2) }}/20</strong></td>
        </tr>
    </table>

    <!-- Tableau nominatif de délibération -->
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 3%;">Rang</th>
                <th style="width: 8%;">Matricule</th>
                <th style="width: 18%;">Nom(s) et Prénom(s)</th>
                <th style="width: 3%;">Sexe</th>
                <th style="width: 6%;">Moyenne</th>
                <th style="width: 5%;">Abs. NJ</th>
                <th style="width: 12%;">Mention / Distinction</th>
                <th style="width: 15%;">Décision du Conseil</th>
                <th style="width: 30%;">Appréciation Générale du Conseil</th>
            </tr>
        </thead>
        <tbody>
            @forelse($eleves as $el)
                <tr>
                    <td class="text-center bold">{{ $el['rang'] ?? '-' }}</td>
                    <td class="text-center">{{ $el['matricule'] }}</td>
                    <td class="bold">{{ $el['nom_complet'] }}</td>
                    <td class="text-center">{{ $el['sexe'] }}</td>
                    <td class="text-center bold" style="font-size: 8pt;">
                        {{ number_format($el['moyenne_obtenue'], 2) }}
                    </td>
                    <td class="text-center {{ $el['absences_non_justifiees'] >= 15 ? 'blocked-badge bold' : '' }}">
                        {{ $el['absences_non_justifiees'] }}h
                    </td>
                    <td>
                        @if($el['mention_bloquee'])
                            <span class="blocked-badge">Refusée ({{ $el['motif_blocage_mention'] === 'absences_excessives' ? '≥15h abs.' : 'Note élim.' }})</span>
                        @elseif($el['mention_obtenue'] === 'felicitations')
                            <span class="mention-badge">Félicitations</span>
                        @elseif($el['mention_obtenue'] === 'encouragements')
                            <span class="mention-badge">Encouragements</span>
                        @elseif($el['mention_obtenue'] === 'tableau_honneur')
                            <span class="mention-badge">Tableau d'Honneur</span>
                        @else
                            <span style="color: #6b7280;">-</span>
                        @endif
                    </td>
                    <td>
                        @php $statut = $el['statut_retenu'] ?? $el['statut_propose']; @endphp
                        <span class="{{ $statut }}">
                            {{ $el['decision_conseil'] ?? ucfirst($statut) }}
                        </span>
                    </td>
                    <td>{{ $el['appreciation_globale'] }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" class="text-center" style="padding: 12px;">Aucune donnée de délibération disponible.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <!-- Signatures -->
    <table class="signatures-table">
        <tr>
            <td>
                <div class="bold uppercase">Le Secrétaire de Séance</div>
                <div class="signature-box"></div>
                <div>(Nom & Signature)</div>
            </td>
            <td>
                <div class="bold uppercase">Le Professeur Principal</div>
                <div class="signature-box"></div>
                <div>(Nom & Signature)</div>
            </td>
            <td>
                @php
                    $etab = $classe->niveau?->cycle?->section?->etablissement ?? $classe->etablissement;
                    $titreChef = $etab?->titre_chef ?? "Le Chef d'Établissement";
                @endphp
                <div class="bold uppercase">{{ $titreChef }}</div>
                <div class="signature-box"></div>
                <div>(Signature & Sceau de l'Établissement)</div>
            </td>
        </tr>
    </table>
</body>
</html>
