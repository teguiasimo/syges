<!DOCTYPE html>
<html lang="fr">
<head>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}?v=syges3">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}?v=syges3">
    <link rel="shortcut icon" href="{{ asset('favicon.ico') }}?v=syges3">
    <meta charset="UTF-8">
    <title>Bilan Résumé des Recouvrements - {{ $annee->code ?? '2026-2027' }}</title>
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
            border: 1.5px solid #0f172a;
            background-color: #f8fafc;
            border-radius: 4px;
            padding: 6px;
            margin: 6px 0 10px 0;
            text-align: center;
        }
        .doc-title {
            font-size: 11pt;
            font-weight: bold;
            text-transform: uppercase;
            color: #0f172a;
        }
        .doc-subtitle {
            font-size: 7.5pt;
            color: #475569;
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8px;
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
        .section-header {
            background-color: #e2e8f0;
            font-weight: bold;
            color: #1e3a8a;
        }
        .niveau-header {
            background-color: #f8fafc;
            font-weight: bold;
            color: #334155;
        }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .font-bold { font-weight: bold; }
        .total-box {
            width: 100%;
            border-collapse: collapse;
            margin-top: 6px;
            margin-bottom: 12px;
        }
        .total-box td {
            padding: 8px 10px;
            border: 1.5px solid #047857;
            background-color: #ecfdf5;
            font-size: 8.5pt;
            font-weight: bold;
        }
        .in-words {
            background-color: #f1f5f9;
            padding: 6px 10px;
            border-left: 3px solid #047857;
            font-size: 8pt;
            margin-bottom: 14px;
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

    @include('finances.partials.header', ['serviceTitle' => "Direction Financière & Recouvrement des Frais Scolaires"])

    <div class="doc-banner">
        <div class="doc-title">Bilan Résumé des Recouvrements & Taux de Performance</div>
        <div class="doc-subtitle">
            Situation arrêtée au <strong>{{ \Carbon\Carbon::now()->format('d-m-Y à H:i') }}</strong>
            • Année Scolaire : <strong>{{ $annee->code ?? '2026-2027' }}</strong>
        </div>
        <div class="doc-subtitle" style="margin-top: 2px;">
            Périmètre : <strong>{{ $perimetre_label ?? 'Toutes les sections, cycles, niveaux et classes' }}</strong>
        </div>
    </div>

    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 24%;">Classe / Niveau</th>
                <th style="width: 8%;" class="text-center">Effectif</th>
                <th style="width: 14%;" class="text-right">Montant Exigible</th>
                <th style="width: 12%;" class="text-right">Réductions</th>
                <th style="width: 14%;" class="text-right">Montant Recouvré</th>
                <th style="width: 14%;" class="text-right">Reste à Recouvrer</th>
                <th style="width: 14%;" class="text-center">Taux Recouvrement</th>
            </tr>
        </thead>
        <tbody>
            @forelse($sections as $sec)
                <tr class="section-header">
                    <td colspan="7">Section : {{ $sec['nom'] }}</td>
                </tr>
                @foreach($sec['niveaux'] as $niv)
                    <tr class="niveau-header">
                        <td colspan="7" style="padding-left: 12px;">Niveau : {{ $niv['nom'] }}</td>
                    </tr>
                    @foreach($niv['classes'] as $cls)
                        <tr>
                            <td class="font-bold" style="padding-left: 24px;">{{ $cls['nom'] }}</td>
                            <td class="text-center">{{ $cls['effectif'] }}</td>
                            <td class="text-right">{{ number_format($cls['a_verser'], 0, ',', ' ') }} F</td>
                            <td class="text-right" style="color: #64748b;">{{ number_format($cls['reduction'], 0, ',', ' ') }} F</td>
                            <td class="text-right font-bold" style="color: #047857;">{{ number_format($cls['verse'], 0, ',', ' ') }} F</td>
                            <td class="text-right font-bold" style="color: {{ ($cls['a_verser'] <= 0 && $cls['verse'] <= 0) ? '#64748b' : ($cls['reste'] > 0 ? '#dc2626' : '#047857') }};">
                                {{ number_format($cls['reste'], 0, ',', ' ') }} F
                            </td>
                            <td class="text-center font-bold">
                                @if($cls['a_verser'] <= 0 && $cls['verse'] <= 0)
                                    <span style="display: inline-block; padding: 2px 5px; border-radius: 3px; background-color: #f1f5f9; color: #64748b; font-size: 6.5pt;">
                                        NON CONFIGURÉ
                                    </span>
                                @else
                                    <span style="display: inline-block; padding: 2px 6px; border-radius: 3px; background-color: {{ $cls['taux_recouvrement'] >= 80 ? '#ecfdf5; color: #047857;' : ($cls['taux_recouvrement'] >= 50 ? '#fefce8; color: #a16207;' : '#fef2f2; color: #dc2626;') }}">
                                        {{ number_format($cls['taux_recouvrement'], 1, ',', ' ') }} %
                                    </span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    <tr style="background-color: #f8fafc; font-weight: bold; font-size: 7.5pt;">
                        <td style="padding-left: 12px;">Sous-total Niveau {{ $niv['nom'] }}</td>
                        <td class="text-center">{{ $niv['effectif'] }}</td>
                        <td class="text-right">{{ number_format($niv['a_verser'], 0, ',', ' ') }} F</td>
                        <td class="text-right">{{ number_format($niv['reduction'], 0, ',', ' ') }} F</td>
                        <td class="text-right" style="color: #047857;">{{ number_format($niv['verse'], 0, ',', ' ') }} F</td>
                        <td class="text-right" style="color: {{ $niv['reste'] > 0 ? '#dc2626' : '#047857' }};">{{ number_format($niv['reste'], 0, ',', ' ') }} F</td>
                        <td class="text-center font-bold" style="color: #1e3a8a;">{{ number_format($niv['taux_recouvrement'], 1, ',', ' ') }} %</td>
                    </tr>
                @endforeach
            @empty
                <tr>
                    <td colspan="7" class="text-center" style="padding: 20px; color: #64748b;">
                        Aucune classe trouvée pour les critères spécifiés.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <table class="total-box">
        <tr>
            <td style="width: 15%; text-align: center;">EFFECTIF : {{ $total_effectif }}</td>
            <td style="width: 25%; text-align: right;">TOTAL EXIGIBLE : {{ number_format($total_a_verser, 0, ',', ' ') }} FCFA</td>
            <td style="width: 30%; text-align: right; color: #047857;">TOTAL RECOUVRÉ : {{ number_format($total_verse, 0, ',', ' ') }} FCFA</td>
            <td style="width: 30%; text-align: right; color: {{ $total_reste > 0 ? '#dc2626' : '#047857' }};">
                RESTE DÛ : {{ number_format($total_reste, 0, ',', ' ') }} FCFA ({{ number_format($taux_recouvrement_global ?? $taux_global ?? 0, 1, ',', ' ') }} %)
            </td>
        </tr>
    </table>

    <div class="in-words">
        Montant total recouvré sur ce périmètre :
        <strong style="text-transform: uppercase; color: #047857;">{{ $total_verse_lettres ?? number_format($total_verse, 0, ',', ' ') . ' Francs CFA' }}</strong>
        (Taux de recouvrement moyen : <strong>{{ number_format($taux_recouvrement_global ?? $taux_global ?? 0, 2, ',', ' ') }} %</strong>).
    </div>

    <table class="signatures-table">
        <tr>
            <td>
                <div class="sign-title">L'Intendant Général / DAF</div>
                <div style="font-size: 7.5pt; color: #64748b;">(Visa et vérification des recouvrements)</div>
            </td>
            <td>
                <div class="sign-title">Le Chef d'Établissement / Le Fondateur</div>
                <div style="font-size: 7.5pt; color: #64748b;">(Pour validation et arbitrage)</div>
            </td>
        </tr>
    </table>

</body>
</html>
