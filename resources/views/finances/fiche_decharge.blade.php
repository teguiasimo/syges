<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Fiche de Décharge - {{ $depense->numero_piece }}</title>
    <style>
        @page {
            size: A5 landscape;
            margin: 8mm 12mm 10mm 12mm;
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
            margin-bottom: 8px;
        }
        .header-table td {
            vertical-align: top;
        }
        .header-left, .header-right {
            width: 38%;
            font-size: 6.5pt;
            text-align: center;
            line-height: 1.2;
        }
        .header-center {
            width: 24%;
            text-align: center;
        }
        .school-name {
            font-size: 10pt;
            font-weight: bold;
            color: #1e3a8a;
            text-transform: uppercase;
        }
        .school-sub {
            font-size: 6.5pt;
            color: #475569;
        }
        .banner {
            border: 1.5px solid #1e3a8a;
            border-radius: 4px;
            padding: 6px;
            text-align: center;
            background-color: #f8fafc;
            margin: 6px 0 10px 0;
        }
        .banner-title {
            font-size: 11pt;
            font-weight: 800;
            color: #1e3a8a;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .banner-sub {
            font-size: 8pt;
            color: #475569;
            margin-top: 1px;
            font-weight: bold;
        }
        .table-info {
            width: 100%;
            border-collapse: collapse;
            font-size: 8pt;
            margin-bottom: 10px;
        }
        .table-info td {
            padding: 4px 8px;
            border: 1px solid #cbd5e1;
        }
        .table-info td.label {
            width: 28%;
            background-color: #f1f5f9;
            font-weight: bold;
            color: #1e293b;
        }
        .amount-box {
            background-color: #ecfdf5;
            border: 1.5px solid #10b981;
            border-radius: 4px;
            padding: 8px 12px;
            margin-bottom: 12px;
        }
        .amount-value {
            font-size: 13pt;
            font-weight: 900;
            color: #065f46;
            font-family: monospace;
        }
        .signatures-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 12px;
        }
        .signatures-table td {
            width: 33.33%;
            text-align: center;
            vertical-align: top;
            font-size: 7.5pt;
        }
        .sig-title {
            font-weight: bold;
            text-decoration: underline;
            margin-bottom: 35px;
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
                @php
                    $logoSrc = $etablissement->getEffectiveLogoBase64();
                @endphp
                @if($logoSrc)
                    <img src="{{ $logoSrc }}" alt="Logo" style="max-height: 48px; max-width: 80px; object-fit: contain; margin-bottom: 2px;"><br>
                @endif
                <div class="school-name">{{ $etablissement->nom }}</div>
                <div class="school-sub">{{ $etablissement->telephone_1 ?? '' }}</div>
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
        <div class="banner-title">BON DE DÉCAISSEMENT & FICHE DE DÉCHARGE</div>
        <div class="banner-sub">PIÈCE DE CAISSE N° : {{ $depense->numero_piece }}</div>
    </div>

    <!-- Montant décaissé -->
    <div class="amount-box">
        <table style="width: 100%;">
            <tr>
                <td style="width: 65%;">
                    <div style="font-size: 7.5pt; text-transform: uppercase; color: #065f46; font-weight: bold;">
                        Montant Décaissé :
                    </div>
                    <div class="amount-value">
                        {{ number_format($depense->montant, 0, ',', ' ') }} FCFA
                    </div>
                </td>
                <td style="width: 35%; text-align: right; font-size: 8pt; color: #065f46;">
                    Date : <strong>{{ $depense->date_depense->format('d/m/Y') }}</strong><br>
                    Mode : <strong>{{ strtoupper($depense->mode_reglement) }}</strong>
                </td>
            </tr>
        </table>
    </div>

    <!-- Informations de décaissement -->
    <table class="table-info">
        <tr>
            <td class="label">Bénéficiaire :</td>
            <td style="font-size: 9pt; font-weight: bold; color: #1e3a8a;">
                {{ $depense->beneficiaire }}
            </td>
        </tr>
        <tr>
            <td class="label">Caisse Débitée :</td>
            <td><strong>{{ $depense->caisse->nom ?? 'Caisse Principale' }}</strong></td>
        </tr>
        <tr>
            <td class="label">Imputation Budgétaire :</td>
            <td><strong>{{ $depense->categorieDepense->nom ?? 'Charges d’exploitation' }}</strong> (Code: {{ $depense->categorieDepense->code ?? '-' }})</td>
        </tr>
        <tr>
            <td class="label">Motif Circonstancié :</td>
            <td>{{ $depense->motif }}</td>
        </tr>
        @if($depense->reference_paiement)
        <tr>
            <td class="label">Réf. Règlement / Chèque :</td>
            <td style="font-family: monospace;">{{ $depense->reference_paiement }}</td>
        </tr>
        @endif
    </table>

    <!-- Signatures tripartites -->
    <table class="signatures-table">
        <tr>
            <td>
                <div class="sig-title">Le Bénéficiaire</div>
                <em>(Pour Acquit des fonds)</em><br><br><br>
                <strong>{{ $depense->beneficiaire }}</strong>
            </td>
            <td>
                <div class="sig-title">Le Caissier / Comptable</div>
                <em>(Vu et Payé)</em><br><br><br>
                <strong>{{ $depense->auteur->name ?? 'La Caisse' }}</strong>
            </td>
            <td>
                <div class="sig-title">L'Ordonnateur</div>
                <em>(Bon pour Décaissement)</em><br><br><br>
                <strong>{{ $etablissement->nom_chef_etablissement ?? 'Le Chef d’Établissement' }}</strong>
            </td>
        </tr>
    </table>

</body>
</html>
