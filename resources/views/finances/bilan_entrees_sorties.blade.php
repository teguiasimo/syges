<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Bilan Financier Synthétique - {{ $annee->code }}</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 12mm 15mm 15mm 15mm;
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
            margin-bottom: 12px;
        }
        .header-table td {
            vertical-align: top;
        }
        .header-left, .header-right {
            width: 38%;
            font-size: 7pt;
            text-align: center;
            line-height: 1.25;
        }
        .header-center {
            width: 24%;
            text-align: center;
        }
        .school-name {
            font-size: 11pt;
            font-weight: bold;
            color: #1e3a8a;
            text-transform: uppercase;
            margin-top: 4px;
        }
        .school-sub {
            font-size: 7pt;
            color: #475569;
        }
        .banner {
            border: 1.5px solid #1e3a8a;
            border-radius: 4px;
            padding: 8px;
            text-align: center;
            background-color: #f8fafc;
            margin: 10px 0 15px 0;
        }
        .banner-title {
            font-size: 12.5pt;
            font-weight: 800;
            color: #1e3a8a;
            text-transform: uppercase;
        }
        .banner-sub {
            font-size: 8pt;
            color: #475569;
            margin-top: 2px;
            font-weight: 600;
        }
        .table-data {
            width: 100%;
            border-collapse: collapse;
            font-size: 8pt;
            margin-bottom: 15px;
        }
        .table-data th {
            background-color: #1e3a8a;
            color: #ffffff;
            font-weight: bold;
            padding: 6px 8px;
            border: 1px solid #0f172a;
            text-align: left;
            text-transform: uppercase;
            font-size: 7.5pt;
        }
        .table-data td {
            padding: 5px 8px;
            border: 1px solid #cbd5e1;
        }
        .table-data tr:nth-child(even) {
            background-color: #f8fafc;
        }
        .section-header {
            background-color: #e2e8f0;
            font-weight: bold;
            text-transform: uppercase;
            color: #1e293b;
        }
        .total-row {
            background-color: #f1f5f9;
            font-weight: bold;
            font-size: 8.5pt;
        }
        .solde-card {
            border: 2px solid {{ $soldeNet >= 0 ? '#10b981' : '#ef4444' }};
            background-color: {{ $soldeNet >= 0 ? '#ecfdf5' : '#fef2f2' }};
            border-radius: 4px;
            padding: 10px 14px;
            margin: 15px 0 25px 0;
        }
        .solde-label {
            font-size: 9pt;
            font-weight: bold;
            color: {{ $soldeNet >= 0 ? '#065f46' : '#991b1b' }};
            text-transform: uppercase;
        }
        .solde-value {
            font-size: 15pt;
            font-weight: 900;
            font-family: monospace;
            color: {{ $soldeNet >= 0 ? '#047857' : '#b91c1c' }};
        }
        .signatures-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 25px;
            page-break-inside: avoid;
        }
        .signatures-table td {
            width: 50%;
            text-align: center;
            vertical-align: top;
            font-size: 8pt;
        }
        .sig-title {
            font-weight: bold;
            text-decoration: underline;
            margin-bottom: 45px;
            text-transform: uppercase;
        }
    </style>
</head>
<body>

    <!-- En-tête officiel camerounais -->
    <table class="header-table">
        <tr>
            <td class="header-left">
                <strong>RÉPUBLIQUE DU CAMEROUN</strong><br>
                <em>Paix - Travail - Patrie</em><br>
                MINISTÈRE DES ENSEIGNEMENTS SECONDAIRES
            </td>
            <td class="header-center">
                <div class="school-name">{{ $etablissement->nom }}</div>
                <div class="school-sub">{{ $etablissement->devise ?? 'Discipline - Travail - Succès' }}</div>
                <div class="school-sub">{{ $etablissement->adresse ?? '' }} - Tél: {{ $etablissement->telephone_1 ?? '' }}</div>
            </td>
            <td class="header-right">
                <strong>REPUBLIC OF CAMEROON</strong><br>
                <em>Peace - Work - Fatherland</em><br>
                MINISTRY OF SECONDARY EDUCATION
            </td>
        </tr>
    </table>

    <!-- Bannière -->
    <div class="banner">
        <div class="banner-title">COMPTE D'EXPLOITATION & BILAN FINANCIER SYNTHÉTIQUE</div>
        <div class="banner-sub">
            ANNÉE SCOLAIRE : {{ $annee->session ?? ($annee->code ?? $annee->nom) }} | ÉDITÉ LE : {{ $date_generation }}
        </div>
    </div>

    <!-- Tableau Entrées & Sorties -->
    <table class="table-data">
        <thead>
            <tr>
                <th>Poste Comptable & Imputation</th>
                <th style="width: 120px; text-align: right;">Montant (FCFA)</th>
            </tr>
        </thead>
        <tbody>
            <!-- SECTION RECETTES -->
            <tr class="section-header">
                <td colspan="2">I. RECETTES DE TRÉSORERIE (ENTRÉES)</td>
            </tr>
            <tr>
                <td style="padding-left: 20px;">Frais de Scolarité & Droits d'Inscription encaissés</td>
                <td style="text-align: right; font-family: monospace;">{{ number_format($versements, 0, ',', ' ') }}</td>
            </tr>
            <tr>
                <td style="padding-left: 20px;">Règlements Divers (Uniforme, Cantine, Dossiers, Ventes annexes)</td>
                <td style="text-align: right; font-family: monospace;">{{ number_format($divers, 0, ',', ' ') }}</td>
            </tr>
            <tr class="total-row" style="color: #0369a1;">
                <td style="text-align: right;">SOUS-TOTAL RECETTES (A) :</td>
                <td style="text-align: right; font-family: monospace; font-weight: 800;">{{ number_format($totalRecettes, 0, ',', ' ') }} FCFA</td>
            </tr>

            <!-- SECTION DÉPENSES -->
            <tr class="section-header">
                <td colspan="2">II. DÉPENSES D'EXPLOITATION & CHARGES (SORTIES)</td>
            </tr>
            @forelse($lignesCategories as $cat)
                <tr>
                    <td style="padding-left: 20px;">{{ $cat['nom'] }} ({{ $cat['code'] }})</td>
                    <td style="text-align: right; font-family: monospace;">{{ number_format($cat['montant'], 0, ',', ' ') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="2" style="text-align: center; color: #64748b; font-style: italic;">
                        Aucune dépense enregistrée sur cet exercice.
                    </td>
                </tr>
            @endforelse
            <tr class="total-row" style="color: #b91c1c;">
                <td style="text-align: right;">SOUS-TOTAL DÉPENSES (B) :</td>
                <td style="text-align: right; font-family: monospace; font-weight: 800;">{{ number_format($totalDepenses, 0, ',', ' ') }} FCFA</td>
            </tr>
        </tbody>
    </table>

    <!-- Solde Net d'Exploitation -->
    <div class="solde-card">
        <table style="width: 100%;">
            <tr>
                <td>
                    <div class="solde-label">
                        RÉSULTAT NET D'EXPLOITATION (A - B) : {{ $soldeNet >= 0 ? 'EXCÉDENT BUDGÉTAIRE' : 'DÉFICIT BUDGÉTAIRE' }}
                    </div>
                    <div style="font-size: 7.5pt; color: #475569; margin-top: 2px;">
                        Différence entre les encaissements totaux et l'ensemble des décaissements validés.
                    </div>
                </td>
                <td style="text-align: right;">
                    <div class="solde-value">
                        {{ number_format($soldeNet, 0, ',', ' ') }} FCFA
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <!-- Signatures -->
    <table class="signatures-table">
        <tr>
            <td>
                <div class="sig-title">L'Économe / Intendant</div>
                [ Signature & Cachet ]
            </td>
            <td>
                <div class="sig-title">Le Chef d'Établissement (Proviseur / Principal)</div>
                <strong>{{ $etablissement->nom_chef_etablissement ?? 'La Direction' }}</strong>
            </td>
        </tr>
    </table>

</body>
</html>
