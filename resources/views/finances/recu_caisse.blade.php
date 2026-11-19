<!DOCTYPE html>
<html lang="fr">
<head>
    {{-- Identité visuelle & Favicons officiels SYGES --}}
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}?v=syges3">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}?v=syges3">
    <link rel="shortcut icon" href="{{ asset('favicon.ico') }}?v=syges3">
    <meta charset="UTF-8">
    <title>Reçu de Caisse - {{ $versement->reference_recu }}</title>
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
            font-size: 7.6pt;
            line-height: 1.18;
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
            margin: 4mm 0;
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
            padding: 3.5px 8px;
            margin-bottom: 4px;
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
            margin-top: 1px;
        }
        .rec-meta-table td {
            vertical-align: middle;
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
            margin-bottom: 7px;
        }
        .is-compact .info-grid {
            margin-bottom: 4px;
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
            padding: 3.5px 6px;
            font-size: 7.2pt;
            line-height: 1.2;
        }
        .info-title {
            font-weight: bold;
            text-transform: uppercase;
            font-size: 7.2pt;
            color: #475569;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 2px;
            margin-bottom: 3px;
        }
        .is-compact .info-title {
            font-size: 6.6pt;
            padding-bottom: 1.5px;
            margin-bottom: 2px;
        }
        .ventilation-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 6px;
            font-size: 8pt;
        }
        .is-compact .ventilation-table {
            margin-bottom: 4px;
            font-size: 7.2pt;
        }
        .ventilation-table th {
            background-color: #1e293b;
            color: #ffffff;
            font-weight: bold;
            padding: 4px 6px;
            text-align: left;
            font-size: 7.5pt;
            text-transform: uppercase;
        }
        .is-compact .ventilation-table th {
            padding: 2.5px 5px;
            font-size: 6.8pt;
        }
        .ventilation-table td {
            border-bottom: 1px solid #e2e8f0;
            padding: 4px 6px;
        }
        .is-compact .ventilation-table td {
            padding: 2px 5px;
        }
        .balance-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 5px;
            font-size: 8pt;
        }
        .is-compact .balance-table {
            margin-top: 3px;
            font-size: 7.2pt;
        }
        .balance-table td {
            padding: 3px 6px;
            border: 1px solid #cbd5e1;
        }
        .is-compact .balance-table td {
            padding: 2px 5px;
        }
        .balance-label {
            background-color: #f8fafc;
            font-weight: bold;
            color: #334155;
            width: 25%;
        }
        .balance-val {
            font-weight: bold;
            text-align: right;
            width: 25%;
        }
        .signatures-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
            font-size: 7.5pt;
        }
        .is-compact .signatures-table {
            margin-top: 4px;
            font-size: 6.8pt;
        }
        .signatures-table td {
            vertical-align: top;
            width: 33.33%;
            text-align: center;
        }
        .qr-box {
            text-align: left;
        }
        .qr-img {
            width: 70px;
            height: 70px;
        }
        .is-compact .qr-img {
            width: 48px;
            height: 48px;
        }
        .security-note {
            font-size: 6.5pt;
            color: #64748b;
            margin-top: 2px;
        }
        .is-compact .security-note {
            font-size: 5.8pt;
            margin-top: 1px;
        }
        .watermark-cancelled {
            position: fixed;
            top: 32%;
            left: 10%;
            width: 80%;
            text-align: center;
            font-size: 50pt;
            font-weight: 900;
            color: rgba(220, 38, 38, 0.22);
            border: 4px solid rgba(220, 38, 38, 0.25);
            padding: 10px 0;
            transform: rotate(-22deg);
            z-index: 9999;
            text-transform: uppercase;
            letter-spacing: 5px;
        }
        .banner-cancelled {
            background-color: #fee2e2;
            border: 1.5px solid #dc2626;
            color: #991b1b;
            padding: 4px 10px;
            text-align: center;
            font-size: 8pt;
            font-weight: bold;
            margin-bottom: 5px;
            border-radius: 4px;
        }
    </style>
</head>
@php
    // Double exemplaire (Parent / Souche École) sur 1 page A4 Portrait jusqu'à 4 lignes ventilées ; au-delà, 1 exemplaire pleine page.
    $doubleExemplaire = $versement->ventilations->count() <= 4;
    $exemplaires = $doubleExemplaire
        ? [
            ['badge' => 'Exemplaire Parent / Élève', 'badgeClass' => 'copy-badge-parent'],
            ['badge' => 'Souche Administration / Caisse', 'badgeClass' => 'copy-badge-souche'],
        ]
        : [
            ['badge' => 'Exemplaire Officiel — Format A4', 'badgeClass' => 'copy-badge-parent'],
        ];
@endphp
<body class="{{ $doubleExemplaire ? 'is-compact' : '' }}">
    @if($versement->statut === 'annule')
        <div class="watermark-cancelled">REÇU ANNULÉ</div>
        <div class="banner-cancelled">
            ⚠️ CE REÇU A ÉTÉ ANNULÉ LE {{ $versement->annule_le ? \Carbon\Carbon::parse($versement->annule_le)->format('d-m-Y H:i') : '' }}
            @if($versement->motif_annulation)
                — MOTIF : {{ $versement->motif_annulation }}
            @endif
        </div>
    @endif

    @foreach($exemplaires as $exemplaire)
        <div class="receipt-copy">
            <div class="copy-strip">
                <span class="copy-badge {{ $exemplaire['badgeClass'] }}">{{ $exemplaire['badge'] }}</span>
            </div>

            {{-- En-tête officiel bilingue --}}
            @include('finances.partials.header', [
                'annee' => $compte->anneeScolaire,
                'serviceTitle' => 'Service de Caisse & Recouvrement',
                'compact' => $doubleExemplaire,
                'skipWatermark' => $loop->index > 0,
            ])

            {{-- Bandeau Titre et Métadonnées du reçu (Disposition optimisée garantissant le N° de reçu sur une ligne unitaire) --}}
            <div class="document-title-box">
                <table class="rec-meta-table">
                    <tr>
                        <td style="width: 44%;">
                            <span class="document-title">REÇU DE CAISSE OFFICIEL</span><br>
                            <span style="font-size: 6.5pt; color: #64748b; white-space: nowrap;">OFFICIAL CASH RECEIPT — ANNÉE SCOLAIRE {{ $compte->anneeScolaire->code ?? '2026-2027' }}</span>
                        </td>
                        <td style="width: 34%; text-align: right; white-space: nowrap;">
                            <span style="color: #334155;">N° REÇU : </span><span class="rec-number">{{ $versement->reference_recu }}</span>
                        </td>
                        <td style="width: 22%; text-align: right; white-space: nowrap;">
                            <span style="color: #334155;">Date : </span><strong>{{ \Carbon\Carbon::parse($versement->date_versement)->format('d/m/Y à H:i') }}</strong>
                        </td>
                    </tr>
                </table>
            </div>

            {{-- Informations Élève et Payeur --}}
            <table class="info-grid">
                <tr>
                    <td class="info-card">
                        <div class="info-title">IDENTITÉ DE L'ÉLÈVE / STUDENT DETAILS</div>
                        <strong>Nom & Prénom :</strong> {{ $eleve->nom }} {{ $eleve->prenom ?? '' }}<br>
                        <strong>Matricule :</strong> {{ $eleve->matricule }} | <strong>Classe :</strong> {{ $inscription->classe->nom ?? 'N/A' }}<br>
                        <strong>Série/Filière :</strong> {{ $inscription->classe->serie ?? 'Générale' }} | <strong>Statut :</strong> {{ ucfirst($inscription->statut) }}
                    </td>
                    <td style="width: 2%;"></td>
                    <td class="info-card">
                        <div class="info-title">INFORMATIONS DU PAIEMENT / PAYMENT DETAILS</div>
                        <strong>Versé par :</strong> {{ $versement->payeur_nom ?? ($eleve->nom_parent ?? 'Parent / Tuteur') }}<br>
                        <strong>Téléphone :</strong> {{ $versement->payeur_telephone ?? ($eleve->telephone_parent ?? 'N/A') }}<br>
                        <strong>Mode de règlement :</strong>
                        @switch($versement->mode_paiement)
                            @case('especes') <strong>ESPÈCES</strong> @break
                            @case('cheque') <strong>CHÈQUE</strong> (Réf: {{ $versement->reference_paiement ?? 'N/A' }}) @break
                            @case('virement') <strong>VIREMENT BANCAIRE</strong> (Réf: {{ $versement->reference_paiement ?? 'N/A' }}) @break
                            @case('mobile_money_manuel') <strong>MOBILE MONEY (MANUEL)</strong> (TxID: {{ $versement->reference_paiement ?? 'N/A' }}) @break
                        @endswitch
                    </td>
                </tr>
            </table>

            {{-- Tableau de ventilation des frais encaissés --}}
            <table class="ventilation-table">
                <thead>
                    <tr>
                        <th style="width: 5%;">#</th>
                        <th style="width: 70%;">Rubrique de Frais / Tranche d'affectation</th>
                        <th style="width: 25%; text-align: right;">Montant Encaissé (FCFA)</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($versement->ventilations as $index => $ventilation)
                        <tr>
                            <td>{{ $index + 1 }}</td>
                            <td><strong>{{ $ventilation->libelle }}</strong></td>
                            <td style="text-align: right; font-weight: bold;">{{ number_format($ventilation->montant, 0, ',', ' ') }} FCFA</td>
                        </tr>
                    @empty
                        <tr>
                            <td>1</td>
                            <td>Versement général sur scolarité</td>
                            <td style="text-align: right; font-weight: bold;">{{ number_format($versement->montant_total, 0, ',', ' ') }} FCFA</td>
                        </tr>
                    @endforelse
                    <tr style="background-color: #f1f5f9; font-weight: bold;">
                        <td colspan="2" style="text-align: right; text-transform: uppercase;">TOTAL VERSÉ SUR CE REÇU :</td>
                        <td style="text-align: right; color: #047857;">{{ number_format($versement->montant_total, 0, ',', ' ') }} FCFA</td>
                    </tr>
                </tbody>
            </table>

            {{-- Situation Financière Mise à Jour du Compte Élève --}}
            <table class="balance-table">
                <tr>
                    <td class="balance-label">Total Exigible Net :</td>
                    <td class="balance-val">
                        {{ number_format($compte->montant_net_exigible, 0, ',', ' ') }} FCFA
                        @if(!empty($compte->arrieres_anterieurs) && $compte->arrieres_anterieurs > 0)
                            <span style="font-size: 6.5pt; color: #b45309; font-weight: normal;">(dont {{ number_format($compte->arrieres_anterieurs, 0, ',', ' ') }} FCFA d'arriérés)</span>
                        @endif
                    </td>
                    <td class="balance-label">Cumul Total Payé :</td>
                    <td class="balance-val" style="color: #047857;">{{ number_format($compte->total_paye, 0, ',', ' ') }} FCFA</td>
                </tr>
                <tr>
                    <td class="balance-label">Ce Versement :</td>
                    <td class="balance-val">{{ number_format($versement->montant_total, 0, ',', ' ') }} FCFA</td>
                    <td class="balance-label" style="background-color: #fef2f2; color: #991b1b;">SOLDE RESTANT DÛ :</td>
                    <td class="balance-val" style="background-color: #fef2f2; color: #b91c1c;">
                        {{ number_format($compte->solde_restant, 0, ',', ' ') }} FCFA
                    </td>
                </tr>
            </table>

            {{-- Bloc Signatures et QR Code de sécurité --}}
            <table class="signatures-table">
                <tr>
                    <td class="qr-box">
                        @if(!empty($qrCodeBase64))
                            <img src="{{ $qrCodeBase64 }}" class="qr-img" alt="QR Sécurité"><br>
                        @endif
                        <div class="security-note">
                            Document officiel infalsifiable SYGES — Vérifiable au service financier.
                        </div>
                    </td>
                    <td>
                        <strong>Le Payeur / Tuteur</strong><br>{!! $doubleExemplaire ? '<br>' : '<br><br>' !!}
                        <em>(Signature)</em>
                    </td>
                    <td>
                        <strong>Le Caissier / L'Économe</strong><br>
                        <span>{{ $versement->recu_par ?? 'Service Comptable' }}</span><br>{!! $doubleExemplaire ? '' : '<br>' !!}
                        <em>(Cachet & Signature)</em>
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
