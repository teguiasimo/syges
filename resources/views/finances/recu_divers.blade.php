<!DOCTYPE html>
<html lang="fr">
<head>
    {{-- Identité visuelle & Favicons officiels SYGES --}}
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}?v=syges3">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}?v=syges3">
    <link rel="shortcut icon" href="{{ asset('favicon.ico') }}?v=syges3">
    <meta charset="UTF-8">
    <title>Reçu Recette Diverse - {{ $reglement->reference_recu }}</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 6mm 10mm;
        }
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 8.5pt;
            color: #1a202c;
            line-height: 1.25;
            margin: 0;
            padding: 0;
        }
        body.is-compact {
            font-size: 7.8pt;
            line-height: 1.2;
        }
        .receipt-copy {
            page-break-inside: avoid;
        }
        .copy-strip {
            text-align: right;
            margin-bottom: 2px;
        }
        .copy-badge {
            display: inline-block;
            padding: 1.5px 7px;
            font-size: 6.5pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            border-radius: 3px;
        }
        .copy-badge-parent {
            background-color: #ecfdf5;
            color: #065f46;
            border: 1px solid #6ee7b7;
        }
        .copy-badge-souche {
            background-color: #eff6ff;
            color: #1e3a8a;
            border: 1px solid #93c5fd;
        }
        .cut-separator {
            margin: 5mm 0;
            border-top: 1.5px dashed #64748b;
            text-align: center;
            height: 4px;
        }
        .cut-separator span {
            background-color: #ffffff;
            color: #475569;
            font-size: 6.5pt;
            font-weight: 600;
            padding: 0 8px;
            position: relative;
            top: -7px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 6px;
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
            padding: 5px 10px;
            margin-bottom: 8px;
        }
        .is-compact .document-title-box {
            padding: 4px 8px;
            margin-bottom: 5px;
        }
        .document-title {
            font-size: 10.5pt;
            font-weight: bold;
            text-align: center;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #0f172a;
        }
        .is-compact .document-title {
            font-size: 9.5pt;
        }
        .rec-meta-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 2px;
            font-size: 8.5pt;
        }
        .is-compact .rec-meta-table {
            font-size: 7.6pt;
        }
        .rec-meta-table td {
            vertical-align: middle;
            white-space: nowrap;
        }
        .rec-number {
            font-size: 9.5pt;
            font-weight: bold;
            color: #047857;
            white-space: nowrap;
        }
        .is-compact .rec-number {
            font-size: 8.5pt;
        }
        .info-grid {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8px;
        }
        .is-compact .info-grid {
            margin-bottom: 5px;
        }
        .info-card {
            width: 49%;
            border: 1px solid #cbd5e1;
            border-radius: 4px;
            padding: 5px 8px;
            background-color: #fafafa;
            font-size: 8pt;
            vertical-align: top;
        }
        .is-compact .info-card {
            padding: 4px 6px;
            font-size: 7.3pt;
        }
        .info-title {
            font-weight: bold;
            text-transform: uppercase;
            font-size: 7.2pt;
            color: #475569;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 2px;
            margin-bottom: 4px;
        }
        .is-compact .info-title {
            font-size: 6.7pt;
            margin-bottom: 2px;
        }
        .table-details {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8px;
            font-size: 8.5pt;
        }
        .is-compact .table-details {
            margin-bottom: 5px;
            font-size: 7.6pt;
        }
        .table-details th {
            background-color: #1e293b;
            color: #ffffff;
            font-weight: bold;
            padding: 5px 8px;
            text-align: left;
            font-size: 7.5pt;
            text-transform: uppercase;
        }
        .is-compact .table-details th {
            padding: 3px 6px;
            font-size: 7pt;
        }
        .table-details td {
            border: 1px solid #e2e8f0;
            padding: 5px 8px;
        }
        .is-compact .table-details td {
            padding: 3.5px 6px;
        }
        .signatures-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            font-size: 7.8pt;
        }
        .is-compact .signatures-table {
            margin-top: 6px;
            font-size: 7pt;
        }
        .signature-box {
            width: 33%;
            vertical-align: top;
            text-align: center;
        }
        .signature-line {
            height: 36px;
            border-bottom: 1px dashed #94a3b8;
            margin: 0 10px;
        }
        .is-compact .signature-line {
            height: 22px;
        }
        .qr-img {
            width: 72px;
            height: 72px;
            display: block;
            margin: 0 auto;
        }
        .is-compact .qr-img {
            width: 50px;
            height: 50px;
        }
        .watermark-annule {
            position: fixed;
            top: 35%;
            left: 15%;
            font-size: 55pt;
            color: rgba(220, 38, 38, 0.25);
            font-weight: bold;
            text-transform: uppercase;
            transform: rotate(-25deg);
            z-index: 9999;
        }
    </style>
</head>
@php
    $doubleExemplaire = mb_strlen((string) ($reglement->observations ?? '')) <= 180;
    $exemplaires = $doubleExemplaire
        ? [
            ['badge' => 'Exemplaire Versant / Client', 'badgeClass' => 'copy-badge-parent'],
            ['badge' => 'Souche Administration / Caisse', 'badgeClass' => 'copy-badge-souche'],
        ]
        : [
            ['badge' => 'Exemplaire Officiel — Format A4', 'badgeClass' => 'copy-badge-parent'],
        ];
@endphp
<body class="{{ $doubleExemplaire ? 'is-compact' : '' }}">
    @if ($reglement->statut === 'annule')
        <div class="watermark-annule">ANNULÉ / NON VALIDE</div>
    @endif

    @foreach($exemplaires as $exemplaire)
        <div class="receipt-copy">
            <div class="copy-strip">
                <span class="copy-badge {{ $exemplaire['badgeClass'] }}">{{ $exemplaire['badge'] }}</span>
            </div>

            <!-- En-tête officiel bilingue -->
            @include('finances.partials.header', [
                'annee' => $reglement->anneeScolaire ?? null,
                'serviceTitle' => 'Service de Caisse & Recettes Diverses',
                'compact' => $doubleExemplaire,
                'skipWatermark' => $loop->index > 0,
            ])

            <!-- Titre et références du reçu -->
            <div class="document-title-box">
                <div class="document-title">REÇU D'ENCAISSEMENT - RECETTES DIVERSES</div>
                <table class="rec-meta-table">
                    <tr>
                        <td style="width: 38%; white-space: nowrap;">N° Reçu : <span class="rec-number">{{ $reglement->reference_recu }}</span></td>
                        <td style="width: 32%; text-align: center; white-space: nowrap;">Date : <strong>{{ $reglement->date_reglement->format('d/m/Y H:i') }}</strong></td>
                        <td style="width: 30%; text-align: right; white-space: nowrap;">Caisse : <strong>{{ $reglement->caisse->nom ?? 'Caisse Principale' }}</strong></td>
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
                        <td style="text-align: right; font-weight: bold; color: #047857;">
                            {{ number_format($reglement->montant, 0, ',', ' ') }} FCFA
                        </td>
                    </tr>
                    <tr style="background-color: #f8fafc;">
                        <td style="text-align: right; font-weight: bold;">TOTAL ENCAISSÉ :</td>
                        <td style="text-align: right; font-weight: bold; color: #1e3a8a;">
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
                            <img src="{{ $qrCodeBase64 }}" alt="QR Code d'authenticité" class="qr-img">
                            <div style="font-size: 5.8pt; color: #64748b; margin-top: 2px;">Authenticité certifiée</div>
                        @endif
                    </td>
                    <td class="signature-box">
                        <div style="font-weight: bold; margin-bottom: 2px;">Le Versant / Le Client</div>
                        <div class="signature-line"></div>
                        <div style="font-size: 6.5pt; color: #64748b; margin-top: 2px;">Signature & Nom</div>
                    </td>
                    <td class="signature-box">
                        <div style="font-weight: bold; margin-bottom: 2px;">L'Intendant / Caissier</div>
                        <div class="signature-line"></div>
                        <div style="font-size: 6.5pt; color: #64748b; margin-top: 2px;">Cachet & Signature</div>
                    </td>
                </tr>
            </table>
        </div>

        @if(! $loop->last)
            <div class="cut-separator">
                <span>&#9986; Découper suivant les pointillés — Souche École ci-dessous</span>
            </div>
        @endif
    @endforeach
</body>
</html>
