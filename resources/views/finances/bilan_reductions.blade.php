<!DOCTYPE html>
<html lang="fr">
<head>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}?v=syges3">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}?v=syges3">
    <link rel="shortcut icon" href="{{ asset('favicon.ico') }}?v=syges3">
    <meta charset="UTF-8">
    <title>Bilan des Réductions & Bourses Scolaires - {{ $annee->code ?? '2026-2027' }}</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 10mm 12mm;
        }
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 8.5pt;
            color: #0f172a;
            line-height: 1.3;
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
            margin-bottom: 10px;
        }
        .data-table th, .data-table td {
            border: 1px solid #cbd5e1;
            padding: 5px 7px;
            font-size: 8pt;
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
            margin-top: 8px;
            margin-bottom: 12px;
        }
        .total-box td {
            padding: 8px 10px;
            border: 1.5px solid #047857;
            background-color: #ecfdf5;
            font-size: 9pt;
            font-weight: bold;
        }
        .in-words {
            background-color: #f1f5f9;
            padding: 6px 10px;
            border-left: 3px solid #047857;
            font-size: 8pt;
            margin-bottom: 18px;
        }
        .signatures-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 25px;
        }
        .signatures-table td {
            width: 50%;
            text-align: center;
            vertical-align: top;
            font-size: 8pt;
        }
        .sign-title {
            font-weight: bold;
            margin-bottom: 40px;
            text-transform: uppercase;
        }
    </style>
</head>
<body>

    @include('finances.partials.header', ['serviceTitle' => "Direction Financière & Commission des Bourses"])

    <div class="doc-banner">
        <div class="doc-title">Bilan des Bourses & Réductions Accordées</div>
        <div class="doc-subtitle">
            Situation arrêtée au <strong>{{ \Carbon\Carbon::now()->format('d-m-Y à H:i') }}</strong>
            • Année Scolaire : <strong>{{ $annee->code ?? '2026-2027' }}</strong>
            • <strong>{{ $total_beneficiaires }}</strong> élève(s) bénéficiaire(s)
        </div>
        <div class="doc-subtitle" style="margin-top: 2px;">
            Périmètre : <strong>{{ $perimetre_label ?? 'Toutes les sections, cycles, niveaux et classes' }}</strong>
        </div>
    </div>

    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 4%;" class="text-center">#</th>
                <th style="width: 13%;">Matricule</th>
                <th style="width: 28%;">Nom & Prénoms de l'Élève</th>
                <th style="width: 10%;">Classe</th>
                <th style="width: 15%;">Catégorie</th>
                <th style="width: 14%;">Motif / Justification</th>
                <th style="width: 16%;" class="text-right">Montant Réduction</th>
            </tr>
        </thead>
        <tbody>
            @forelse($beneficiaires as $idx => $b)
                <tr>
                    <td class="text-center">{{ $idx + 1 }}</td>
                    <td class="font-bold" style="font-family: monospace;">{{ $b['matricule'] }}</td>
                    <td class="font-bold">{{ $b['nom_complet'] }}</td>
                    <td>{{ $b['classe'] }}</td>
                    <td>
                        <span style="font-weight: bold; text-transform: uppercase; color: {{ $b['type'] === 'bourse' ? '#1d4ed8' : '#7c3aed' }};">
                            {{ $b['type'] }}
                        </span>
                    </td>
                    <td style="color: #475569; font-size: 7.5pt;">{{ $b['motif'] ?: 'Non précisé' }}</td>
                    <td class="text-right font-bold" style="color: #047857;">
                        {{ number_format($b['montant'], 0, ',', ' ') }} FCFA
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="text-center" style="padding: 20px; color: #64748b;">
                        Aucune réduction ou bourse accordée pour les filtres sélectionnés.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <table class="total-box">
        <tr>
            <td style="width: 40%;">TOTAL BÉNÉFICIAIRES : {{ $total_beneficiaires }}</td>
            <td style="width: 60%; text-align: right; color: #047857; font-size: 10pt;">
                CUMUL TOTAL DES AVANTAGES ACCORDÉS : {{ number_format($total_montant, 0, ',', ' ') }} FCFA
            </td>
        </tr>
    </table>

    <div class="in-words">
        Arrêté le présent bilan des bourses et réductions à la somme totale de :
        <strong style="text-transform: uppercase; color: #047857;">{{ $total_lettres ?? number_format($total_montant, 0, ',', ' ') . ' Francs CFA' }}</strong>.
    </div>

    <table class="signatures-table">
        <tr>
            <td>
                <div class="sign-title">La Commission des Bourses / Intendance</div>
                <div style="font-size: 7.5pt; color: #64748b;">(Visa et vérification des dossiers)</div>
            </td>
            <td>
                <div class="sign-title">Le Chef d'Établissement / Le Fondateur</div>
                <div style="font-size: 7.5pt; color: #64748b;">(Pour validation et approbation)</div>
            </td>
        </tr>
    </table>

</body>
</html>

