<!DOCTYPE html>
<html lang="fr">
<head>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}?v=syges3">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}?v=syges3">
    <link rel="shortcut icon" href="{{ asset('favicon.ico') }}?v=syges3">
    <meta charset="UTF-8">
    <title>Journal Résumé des Versements - {{ $date_debut }} au {{ $date_fin }}</title>
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
            padding: 5px 8px;
            font-size: 8pt;
        }
        .data-table th {
            background-color: #f1f5f9;
            font-weight: bold;
            color: #1e293b;
            text-align: left;
        }
        .section-row {
            background-color: #e2e8f0;
            font-weight: bold;
            color: #1e3a8a;
        }
        .niveau-row {
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

    @include('finances.partials.header', ['serviceTitle' => "Service de l'Intendance & Comptabilité Scolaire"])

    <div class="doc-banner">
        <div class="doc-title">Journal Résumé des Versements par Période</div>
        <div class="doc-subtitle">
            Période du <strong>{{ $date_debut }}</strong> au <strong>{{ $date_fin }}</strong>
            • <strong>{{ $nombre_versements ?? 0 }}</strong> versement(s) totalisé(s)
        </div>
        <div class="doc-subtitle" style="margin-top: 2px;">
            Périmètre : <strong>{{ $perimetre_label ?? 'Toutes les sections, cycles, niveaux et classes' }}</strong>
        </div>
    </div>

    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 45%;">Structure Pédagogique / Classe</th>
                <th style="width: 25%;" class="text-center">Nombre de Reçus</th>
                <th style="width: 30%;" class="text-right">Montant Encaissé (FCFA)</th>
            </tr>
        </thead>
        <tbody>
            @forelse($sections as $sec)
                <tr class="section-row">
                    <td colspan="3">
                        Section : {{ $sec['nom'] }}
                    </td>
                </tr>
                @foreach($sec['niveaux'] as $niv)
                    <tr class="niveau-row">
                        <td colspan="3" style="padding-left: 14px;">
                            <strong>Niveau : {{ $niv['nom'] }}</strong>
                        </td>
                    </tr>
                    @foreach($niv['classes'] as $cls)
                        <tr>
                            <td style="padding-left: 28px;">
                                {{ $cls['nom'] }}
                            </td>
                            <td class="text-center font-bold" style="color: #475569;">
                                {{ $cls['nombre_recus'] ?? 0 }}
                            </td>
                            <td class="text-right font-bold" style="color: #047857;">
                                {{ number_format($cls['total_verse'], 0, ',', ' ') }} FCFA
                            </td>
                        </tr>
                    @endforeach
                    <tr style="background-color: #f1f5f9; font-weight: bold; font-size: 7.5pt;">
                        <td style="padding-left: 14px;">Sous-total Niveau {{ $niv['nom'] }}</td>
                        <td class="text-center">{{ array_sum(array_column($niv['classes'], 'nombre_recus')) }}</td>
                        <td class="text-right" style="color: #1e3a8a;">{{ number_format($niv['total_verse'], 0, ',', ' ') }} FCFA</td>
                    </tr>
                @endforeach
            @empty
                <tr>
                    <td colspan="3" class="text-center" style="color: #64748b; padding: 20px;">
                        Aucun encaissement sur la période demandée.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <table class="total-box">
        <tr>
            <td style="width: 50%;">TOTAL GÉNÉRAL DE LA PÉRIODE ({{ $nombre_versements ?? 0 }} reçus) :</td>
            <td style="width: 50%; text-align: right; color: #047857; font-size: 11pt;">
                {{ number_format($total_general_verse ?? 0, 0, ',', ' ') }} FCFA
            </td>
        </tr>
    </table>

    <div class="in-words">
        Arrêté le présent journal résumé des encaissements à la somme de :
        <strong style="text-transform: uppercase; color: #047857;">{{ $total_general_lettres ?? number_format($total_general_verse ?? 0, 0, ',', ' ') . ' Francs CFA' }}</strong>.
    </div>

    <table class="signatures-table">
        <tr>
            <td>
                <div class="sign-title">L'Intendant / Caissier</div>
                <div style="font-size: 7.5pt; color: #64748b;">(Visa et arrêté des comptes)</div>
            </td>
            <td>
                <div class="sign-title">Le Chef d'Établissement / La Direction</div>
                <div style="font-size: 7.5pt; color: #64748b;">(Pour vérification et contrôle)</div>
            </td>
        </tr>
    </table>

</body>
</html>
