<!DOCTYPE html>
<html lang="fr">
<head>
    {{-- Identité visuelle & Favicons officiels SYGES --}}
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}?v=syges3">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}?v=syges3">
    <link rel="shortcut icon" href="{{ asset('favicon.ico') }}?v=syges3">
    <meta charset="UTF-8">
    <title>Journal Synthétique de Caisse - {{ $date_debut }} au {{ $date_fin }}</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 10mm 12mm;
        }
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 8.5pt;
            color: #0f172a;
            line-height: 1.35;
            margin: 0;
            padding: 0;
        }
        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }
        .header-table td {
            vertical-align: top;
        }
        .header-left, .header-right {
            width: 35%;
            font-size: 7.5pt;
            text-align: center;
            line-height: 1.25;
        }
        .header-center {
            width: 30%;
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
            padding: 8px;
            margin: 10px 0 12px 0;
            text-align: center;
        }
        .doc-title {
            font-size: 12pt;
            font-weight: bold;
            text-transform: uppercase;
            color: #0f172a;
        }
        .doc-subtitle {
            font-size: 8pt;
            color: #475569;
            margin-top: 2px;
        }
        .section-title {
            font-size: 9.5pt;
            font-weight: bold;
            color: #1e3a8a;
            text-transform: uppercase;
            border-bottom: 2px solid #1e3a8a;
            padding-bottom: 3px;
            margin: 12px 0 6px 0;
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
        .kpi-row {
            background-color: #ecfdf5;
            font-weight: bold;
            color: #065f46;
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

    <table class="header-table">
        <tr>
            <td class="header-left">
                RÉPUBLIQUE DU CAMEROUN<br>
                <em>Paix - Travail - Patrie</em><br>
                MINISTÈRE DES ENSEIGNEMENTS SECONDAIRES
            </td>
            <td class="header-center">
                <div class="school-name">{{ $etablissement->nom ?? 'SYGES COMPLEXE SCOLAIRE' }}</div>
                <div class="school-sub">Direction Financière & Comptabilité</div>
                <div class="school-sub">Année Scolaire : {{ $annee->code ?? '2026-2027' }}</div>
            </td>
            <td class="header-right">
                REPUBLIC OF CAMEROON<br>
                <em>Peace - Work - Fatherland</em><br>
                MINISTRY OF SECONDARY EDUCATION
            </td>
        </tr>
    </table>

    <div class="doc-banner">
        <div class="doc-title">Journal Synthétique & Récapitulatif de Caisse</div>
        <div class="doc-subtitle">
            Période du <strong>{{ $date_debut }}</strong> au <strong>{{ $date_fin }}</strong>
            • {{ $nombre_total }} transaction(s) au total
            @if(!empty($mode_paiement_filtre))
                • Mode : <strong>{{ strtoupper(str_replace('_', ' ', $mode_paiement_filtre)) }}</strong>
            @endif
        </div>
        <div class="doc-subtitle" style="margin-top: 2px;">
            Périmètre : <strong>{{ $perimetre_label ?? 'Toutes les sections, cycles, niveaux et classes' }}</strong>
        </div>
    </div>

    {{-- 1. Récapitulatif Chronologique par Jour --}}
    <div class="section-title">1. Évolution Journalière des Recettes</div>
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 20%;">Date</th>
                <th style="width: 12%;" class="text-center">Nb Reçus</th>
                <th style="width: 17%;" class="text-right">Espèces</th>
                <th style="width: 17%;" class="text-right">Mobile Money</th>
                <th style="width: 17%;" class="text-right">Banque (Chèque/Vir.)</th>
                <th style="width: 17%;" class="text-right">Total Journalier</th>
            </tr>
        </thead>
        <tbody>
            @forelse($synthese_jours as $j)
                <tr>
                    <td class="font-bold">{{ $j['date'] }}</td>
                    <td class="text-center">{{ $j['nombre'] }}</td>
                    <td class="text-right">{{ number_format($j['especes'], 0, ',', ' ') }} F</td>
                    <td class="text-right">{{ number_format($j['momo'], 0, ',', ' ') }} F</td>
                    <td class="text-right">{{ number_format($j['cheque'] + $j['virement'], 0, ',', ' ') }} F</td>
                    <td class="text-right font-bold" style="color: #047857;">{{ number_format($j['total'], 0, ',', ' ') }} FCFA</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="text-center" style="color: #64748b;">Aucune transaction enregistrée.</td>
                </tr>
            @endforelse
            <tr class="kpi-row">
                <td colspan="5">TOTAL PÉRIODE ENCAISSÉ :</td>
                <td class="text-right">{{ number_format($total_general, 0, ',', ' ') }} FCFA</td>
            </tr>
        </tbody>
    </table>

    {{-- 2. Répartition par Niveau & Classe --}}
    @if(!empty($synthese_niveaux))
        <div class="section-title">2. Répartition par Niveau & Classe</div>
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 35%;">Niveau d'Études</th>
                    <th style="width: 30%;">Classe</th>
                    <th style="width: 15%;" class="text-center">Nb Reçus</th>
                    <th style="width: 20%;" class="text-right">Montant Encaissé</th>
                </tr>
            </thead>
            <tbody>
                @foreach($synthese_niveaux as $sn)
                    <tr>
                        <td class="font-bold">{{ $sn['niveau'] }}</td>
                        <td>{{ $sn['classe'] }}</td>
                        <td class="text-center">{{ $sn['nombre'] }}</td>
                        <td class="text-right font-bold" style="color: #047857;">{{ number_format($sn['total'], 0, ',', ' ') }} FCFA</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    {{-- 3. Répartition par Caisse --}}
    <div class="section-title">3. Répartition par Caisse d'Encaissement</div>
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 25%;">Code Caisse</th>
                <th style="width: 45%;">Nom de la Caisse</th>
                <th style="width: 15%;" class="text-center">Transactions</th>
                <th style="width: 15%;" class="text-right">Montant Cumulé</th>
            </tr>
        </thead>
        <tbody>
            @foreach($synthese_caisses as $sc)
                <tr>
                    <td class="font-bold" style="font-family: monospace;">{{ $sc['code'] }}</td>
                    <td>{{ $sc['caisse'] }}</td>
                    <td class="text-center">{{ $sc['nombre'] }}</td>
                    <td class="text-right font-bold">{{ number_format($sc['total'], 0, ',', ' ') }} FCFA</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    {{-- 4. Ventilation par Nature de Frais --}}
    <div class="section-title">4. Ventilation Analytique par Rubrique</div>
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 55%;">Nature de Frais / Rubrique</th>
                <th style="width: 20%;" class="text-center">Occurrences</th>
                <th style="width: 25%;" class="text-right">Montant Encaissé</th>
            </tr>
        </thead>
        <tbody>
            @foreach($synthese_rubriques as $sr)
                <tr>
                    <td class="font-bold">{{ $sr->libelle }}</td>
                    <td class="text-center">{{ $sr->total_occurrences }}</td>
                    <td class="text-right font-bold">{{ number_format($sr->total_montant, 0, ',', ' ') }} FCFA</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="signatures-table">
        <tr>
            <td>
                <div class="sign-title">L'Intendant / Caissier Central</div>
                <div style="font-size: 7.5pt; color: #64748b;">(Visa et vérification des soldes)</div>
            </td>
            <td>
                <div class="sign-title">Le Chef d'Établissement</div>
                <div style="font-size: 7.5pt; color: #64748b;">(Pour arrêté définitif des comptes)</div>
            </td>
        </tr>
    </table>

</body>
</html>

