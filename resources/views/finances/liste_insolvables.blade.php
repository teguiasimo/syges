<!DOCTYPE html>
<html lang="fr">
<head>
    {{-- Identité visuelle & Favicons officiels SYGES --}}
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}?v=syges3">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}?v=syges3">
    <link rel="shortcut icon" href="{{ asset('favicon.ico') }}?v=syges3">
    <meta charset="UTF-8">
    <title>État des Élèves Insolvables - {{ $date_reference }}</title>
    <style>
        @page {
            size: A4 landscape;
            margin: 8mm 10mm;
        }
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 8pt;
            color: #0f172a;
            line-height: 1.25;
            margin: 0;
            padding: 0;
        }
        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8px;
        }
        .header-table td {
            vertical-align: top;
        }
        .header-left, .header-right {
            width: 32%;
            font-size: 7pt;
            text-align: center;
            line-height: 1.2;
        }
        .header-center {
            width: 36%;
            text-align: center;
        }
        .school-name {
            font-size: 11pt;
            font-weight: bold;
            color: #1e3a8a;
            text-transform: uppercase;
        }
        .school-sub {
            font-size: 7.5pt;
            color: #475569;
        }
        .doc-banner {
            border: 1.5px solid #b91c1c;
            background-color: #fef2f2;
            border-radius: 4px;
            padding: 6px;
            margin: 6px 0 10px 0;
            text-align: center;
        }
        .doc-title {
            font-size: 11pt;
            font-weight: bold;
            text-transform: uppercase;
            color: #991b1b;
        }
        .doc-subtitle {
            font-size: 7.5pt;
            color: #475569;
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }
        .data-table th, .data-table td {
            border: 1px solid #cbd5e1;
            padding: 4px 6px;
            font-size: 7.5pt;
        }
        .data-table th {
            background-color: #f1f5f9;
            font-weight: bold;
            color: #1e293b;
            text-align: left;
        }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .font-bold { font-weight: bold; }
        .total-box {
            width: 100%;
            border-collapse: collapse;
            margin-top: 6px;
            margin-bottom: 15px;
        }
        .total-box td {
            padding: 6px 8px;
            border: 1px solid #cbd5e1;
            font-size: 8pt;
        }
        .signatures-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        .signatures-table td {
            width: 50%;
            text-align: center;
            vertical-align: top;
            font-size: 8pt;
        }
        .sign-title {
            font-weight: bold;
            margin-bottom: 35px;
            text-transform: uppercase;
        }
    </style>
</head>
<body>

    @include('finances.partials.header', ['serviceTitle' => "Recouvrement & Suivi de la Solvabilité"])

    <div class="doc-banner">
        <div class="doc-title">État Nominatif des Élèves Insolvables (Échéances Échues)</div>
        <div class="doc-subtitle">
            @if(!empty($date_debut))
                Période du <strong>{{ $date_debut }}</strong> au <strong>{{ $date_fin ?? $date_reference }}</strong> (échéances arrêtées au {{ $date_reference }})
            @else
                Situation arrêtée au <strong>{{ $date_reference }}</strong>
            @endif
            • <strong>{{ $nombre_insolvables }}</strong> élève(s) en situation d'impayé
        </div>
        <div class="doc-subtitle" style="margin-top: 2px;">
            Périmètre : <strong>{{ $perimetre_label ?? 'Toutes les sections, cycles, niveaux et classes' }}</strong>
        </div>
    </div>

    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 3%;" class="text-center">#</th>
                <th style="width: 9%;">Matricule</th>
                <th style="width: 20%;">Nom & Prénoms de l'Élève</th>
                <th style="width: 4%;" class="text-center">Sexe</th>
                <th style="width: 8%;">Classe</th>
                <th style="width: 14%;">Contact Parent / Tuteur</th>
                <th style="width: 10%;" class="text-right">Net Exigible</th>
                <th style="width: 10%;" class="text-right">Total Réglé</th>
                <th style="width: 10%;" class="text-right">Versé Période</th>
                <th style="width: 12%;" class="text-right" style="background-color: #fee2e2; color: #991b1b;">Retard Échu</th>
            </tr>
        </thead>
        <tbody>
            @forelse($eleves as $idx => $e)
                <tr>
                    <td class="text-center">{{ $idx + 1 }}</td>
                    <td class="font-bold" style="font-family: monospace;">{{ $e['matricule'] }}</td>
                    <td class="font-bold">
                        {{ $e['nom_complet'] }}
                        @if(!empty($e['date_naissance']))
                            <div style="font-size: 6.5pt; color: #64748b; font-weight: normal;">
                                Né(e) le {{ $e['date_naissance'] }} {{ $e['lieu_naissance'] ? 'à '.$e['lieu_naissance'] : '' }}
                            </div>
                        @endif
                    </td>
                    <td class="text-center">
                        {{ $e['sexe'] ?? '-' }}
                        <div style="font-size: 6.5pt; color: {{ ($e['redoublant'] ?? '') === 'R' ? '#dc2626' : '#64748b' }}; font-weight: bold;">
                            {{ $e['redoublant'] ?? 'NR' }}
                        </div>
                    </td>
                    <td>{{ $e['classe'] }}</td>
                    <td>
                        {{ $e['contact_parent'] }}
                        @if(!empty($e['parent_tuteur']) && $e['parent_tuteur'] !== 'N/A')
                            <div style="font-size: 6.5pt; color: #64748b;">
                                ({{ $e['parent_tuteur'] }})
                            </div>
                        @endif
                    </td>
                    <td class="text-right">{{ number_format($e['montant_net_exigible'], 0, ',', ' ') }} F</td>
                    <td class="text-right" style="color: #047857;">{{ number_format($e['total_paye'], 0, ',', ' ') }} F</td>
                    <td class="text-right" style="color: #0369a1;">{{ number_format($e['verse_periode'] ?? $e['total_paye'], 0, ',', ' ') }} F</td>
                    <td class="text-right font-bold" style="color: #dc2626; background-color: #fff1f2;">
                        {{ number_format($e['montant_echu_en_retard'], 0, ',', ' ') }} FCFA
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="10" class="text-center" style="color: #166534; padding: 15px; font-weight: bold;">
                        Félicitations ! Aucun élève insolvable n'est à déplorer pour ce filtre à la date sélectionnée.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    {{-- Bilan Global --}}
    <table class="total-box">
        <tr style="background-color: #fef2f2; font-weight: bold; border: 1.5px solid #dc2626;">
            <td style="width: 30%;">Nombre d'Élèves Insolvables : <span style="color: #991b1b;">{{ $nombre_insolvables }}</span></td>
            <td style="width: 35%;">Cumul Dû Restant sur l'Année : <span style="color: #0f172a;">{{ number_format($total_solde_restant, 0, ',', ' ') }} FCFA</span></td>
            <td style="width: 35%; text-align: right; color: #b91c1c; font-size: 9pt;">
                TOTAL DES ARRIÉRÉS ÉCHUS : {{ number_format($total_dette_echue, 0, ',', ' ') }} FCFA
            </td>
        </tr>
    </table>

    <table class="signatures-table">
        <tr>
            <td>
                <div class="sign-title">L'Intendant / Responsable Financier</div>
                <div style="font-size: 7.5pt; color: #64748b;">(Pour transmission et relance)</div>
            </td>
            <td>
                <div class="sign-title">Le Chef d'Établissement</div>
                <div style="font-size: 7.5pt; color: #64748b;">(Pour mesures administratives)</div>
            </td>
        </tr>
    </table>

</body>
</html>

