<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Reçu Recette Diverse - {{ $reglement->reference_recu }}</title>
    <style>
        @page {
            size: A5 landscape;
            margin: 8mm 10mm;
        }
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 9pt;
            color: #1a202c;
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
            width: 35%;
            font-size: 7.5pt;
            text-align: center;
            line-height: 1.2;
        }
        .header-center {
            width: 30%;
            text-align: center;
        }
        .school-name {
            font-size: 10pt;
            font-weight: bold;
            color: #1e3a8a;
            text-transform: uppercase;
            margin-top: 3px;
        }
        .school-contacts {
            font-size: 7pt;
            color: #4b5563;
        }
        .document-title-box {
            background-color: #f1f5f9;
            border: 1.5px solid #0f172a;
            border-radius: 4px;
            padding: 6px 10px;
            margin-bottom: 10px;
        }
        .document-title {
            font-size: 11pt;
            font-weight: bold;
            text-align: center;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #0f172a;
        }
        .rec-meta-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 3px;
            font-size: 8.5pt;
        }
        .rec-number {
            font-size: 11pt;
            font-weight: bold;
            color: #047857;
        }
        .info-grid {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }
        .info-card {
            width: 49%;
            border: 1px solid #cbd5e1;
            border-radius: 4px;
            padding: 6px 8px;
            background-color: #fafafa;
            font-size: 8.5pt;
            vertical-align: top;
        }
        .info-title {
            font-weight: bold;
            text-transform: uppercase;
            font-size: 7.5pt;
            color: #475569;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 3px;
            margin-bottom: 6px;
        }
        .table-details {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
            font-size: 9pt;
        }
        .table-details th {
            background-color: #1e293b;
            color: #ffffff;
            font-weight: bold;
            padding: 6px 8px;
            text-align: left;
            font-size: 8pt;
            text-transform: uppercase;
        }
        .table-details td {
            border: 1px solid #e2e8f0;
            padding: 6px 8px;
        }
        .signatures-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
            font-size: 8pt;
        }
        .signature-box {
            width: 33%;
            vertical-align: top;
            text-align: center;
        }
        .signature-line {
            height: 40px;
            border-bottom: 1px dashed #94a3b8;
            margin: 0 10px;
        }
        .watermark-annule {
            position: absolute;
            top: 35%;
            left: 20%;
            font-size: 55pt;
            color: rgba(220, 38, 38, 0.25);
            font-weight: bold;
            text-transform: uppercase;
            transform: rotate(-25deg);
        }
    </style>
</head>
<body>
    @if ($reglement->statut === 'annule')
        <div class="watermark-annule">ANNULÉ / NON VALIDE</div>
    @endif

    <!-- En-tête officiel de l'établissement -->
    <table class="header-table">
        <tr>
            <td class="header-left">
                <strong>RÉPUBLIQUE DU CAMEROUN</strong><br>
                <em>Paix - Travail - Patrie</em><br>
                MINISTÈRE DES ENSEIGNEMENTS SECONDAIRES
            </td>
            <td class="header-center">
                @php
                    $logoSrc = ($etablissement && method_exists($etablissement, 'getEffectiveLogoBase64'))
                        ? $etablissement->getEffectiveLogoBase64()
                        : (file_exists(public_path('logo.png')) ? 'data:image/png;base64,' . base64_encode(file_get_contents(public_path('logo.png'))) : null);
                @endphp
                @if($logoSrc)
                    <img src="{{ $logoSrc }}" style="max-height: 48px; max-width: 80px; object-fit: contain; margin-bottom: 2px;" alt="Logo"><br>
                @endif
                <div class="school-name">{{ $etablissement->nom ?? 'ÉTABLISSEMENT SCOLAIRE' }}</div>
                <div class="school-contacts">
                    {{ $etablissement->adresse ?? '' }}<br>
                    Tél : {{ $etablissement->telephone ?? '-' }} | Email : {{ $etablissement->email ?? '-' }}
                </div>
            </td>
            <td class="header-right">
                <strong>REPUBLIC OF CAMEROON</strong><br>
                <em>Peace - Work - Fatherland</em><br>
                MINISTRY OF SECONDARY EDUCATION
            </td>
        </tr>
    </table>

    <!-- Titre et références du reçu -->
    <div class="document-title-box">
        <div class="document-title">REÇU D'ENCAISSEMENT - RECETTES DIVERSES</div>
        <table class="rec-meta-table">
            <tr>
                <td style="width: 35%;">N° Reçu : <span class="rec-number">{{ $reglement->reference_recu }}</span></td>
                <td style="width: 35%; text-align: center;">Date : <strong>{{ $reglement->date_reglement->format('d/m/Y H:i') }}</strong></td>
                <td style="width: 30%; text-align: right;">Caisse : <strong>{{ $reglement->caisse->nom ?? 'Caisse Principale' }}</strong></td>
            </tr>
        </table>
    </div>

    <!-- Informations du payeur et de l'opération -->
    <table class="info-grid">
        <tr>
            <td class="info-card">
                <div class="info-title">PARTIE VERSANTE / PAYEUR</div>
                <div>Nom / Raison sociale : <strong>{{ $reglement->payeur_nom }}</strong></div>
                @if ($reglement->payeur_telephone)
                    <div>Téléphone : <strong>{{ $reglement->payeur_telephone }}</strong></div>
                @endif
                <div>Catégorie : <strong>{{ strtoupper($reglement->categorie) }}</strong></div>
            </td>
            <td style="width: 2%;"></td>
            <td class="info-card">
                <div class="info-title">MODALITÉS DE PAIEMENT</div>
                <div>Mode de règlement : <strong>{{ strtoupper(str_replace('_', ' ', $reglement->mode_paiement)) }}</strong></div>
                @if ($reglement->reference_paiement)
                    <div>Réf. transaction : <strong>{{ $reglement->reference_paiement }}</strong></div>
                @endif
                <div>Agent encaisseur : <strong>{{ $reglement->caissier ? ($reglement->caissier->nom . ' ' . $reglement->caissier->prenom) : 'Service Caisse' }}</strong></div>
            </td>
        </tr>
    </table>

    <!-- Tableau détaillé de l'encaissement -->
    <table class="table-details">
        <thead>
            <tr>
                <th style="width: 70%;">Désignation / Motif de la prestation</th>
                <th style="width: 30%; text-align: right;">Montant perçu (FCFA)</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>
                    <strong>{{ $reglement->libelle }}</strong>
                    @if ($reglement->observations)
                        <br><small style="color: #64748b;">Obs: {{ $reglement->observations }}</small>
                    @endif
                </td>
                <td style="text-align: right; font-weight: bold; font-size: 10.5pt; color: #047857;">
                    {{ number_format($reglement->montant, 0, ',', ' ') }} FCFA
                </td>
            </tr>
            <tr style="background-color: #f8fafc;">
                <td style="text-align: right; font-weight: bold;">TOTAL ENCAISSÉ :</td>
                <td style="text-align: right; font-weight: bold; font-size: 11pt; color: #1e3a8a;">
                    {{ number_format($reglement->montant, 0, ',', ' ') }} FCFA
                </td>
            </tr>
        </tbody>
    </table>

    <!-- QR Code de sécurité et Signatures -->
    <table class="signatures-table">
        <tr>
            <td style="width: 25%; text-align: center; vertical-align: middle;">
                @if (!empty($qrCodeBase64))
                    <img src="{{ $qrCodeBase64 }}" alt="QR Code d'authenticité" style="width: 75px; height: 75px; display: block; margin: 0 auto;">
                    <div style="font-size: 6pt; color: #64748b; margin-top: 2px;">Authenticité certifiée</div>
                @endif
            </td>
            <td class="signature-box">
                <div style="font-weight: bold; margin-bottom: 2px;">Le Versant / Le Client</div>
                <div class="signature-line"></div>
                <div style="font-size: 7pt; color: #64748b; margin-top: 2px;">Signature & Nom</div>
            </td>
            <td class="signature-box">
                <div style="font-weight: bold; margin-bottom: 2px;">L'Intendant / Caissier</div>
                <div class="signature-line"></div>
                <div style="font-size: 7pt; color: #64748b; margin-top: 2px;">Cachet & Signature</div>
            </td>
        </tr>
    </table>
</body>
</html>
