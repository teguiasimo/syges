<!DOCTYPE html>
<html lang="fr">
<head>
    {{-- Identité visuelle & Favicons officiels SYGES --}}
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}?v=syges3">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}?v=syges3">
    <link rel="shortcut icon" href="{{ asset('favicon.ico') }}?v=syges3">
    <meta charset="UTF-8">
    <title>Fiche de Décharge - {{ $depense->numero_piece }}</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 6mm 10mm;
        }
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 8.2pt;
            color: #0f172a;
            line-height: 1.25;
            margin: 0;
            padding: 0;
        }
        body.is-compact {
            font-size: 7.5pt;
            line-height: 1.18;
        }
        .decharge-copy {
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
        .copy-badge-benef {
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
            padding: 5px;
            text-align: center;
            background-color: #f8fafc;
            margin: 4px 0 6px 0;
        }
        .is-compact .banner {
            padding: 3.5px 6px;
            margin: 2px 0 4px 0;
        }
        .banner-title {
            font-size: 10.5pt;
            font-weight: 800;
            color: #1e3a8a;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .is-compact .banner-title {
            font-size: 9.5pt;
        }
        .banner-sub {
            font-size: 7.8pt;
            color: #475569;
            margin-top: 1px;
            font-weight: bold;
        }
        .table-info {
            width: 100%;
            border-collapse: collapse;
            font-size: 7.8pt;
            margin-bottom: 7px;
        }
        .is-compact .table-info {
            font-size: 7.2pt;
            margin-bottom: 5px;
        }
        .table-info td {
            padding: 3.5px 7px;
            border: 1px solid #cbd5e1;
        }
        .is-compact .table-info td {
            padding: 2.5px 6px;
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
            padding: 6px 10px;
            margin-bottom: 7px;
        }
        .is-compact .amount-box {
            padding: 4px 8px;
            margin-bottom: 5px;
        }
        .amount-value {
            font-size: 12pt;
            font-weight: 900;
            color: #065f46;
            font-family: monospace;
        }
        .is-compact .amount-value {
            font-size: 10.5pt;
        }
        .signatures-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
        }
        .is-compact .signatures-table {
            margin-top: 5px;
        }
        .signatures-table td {
            width: 33.33%;
            text-align: center;
            vertical-align: top;
            font-size: 7.2pt;
        }
        .sig-title {
            font-weight: bold;
            text-decoration: underline;
            margin-bottom: 24px;
            text-transform: uppercase;
        }
        .is-compact .sig-title {
            margin-bottom: 14px;
        }
    </style>
</head>
@php
    $doubleExemplaire = mb_strlen((string) ($depense->motif ?? '')) <= 220;
    $exemplaires = $doubleExemplaire
        ? [
            ['badge' => 'Exemplaire Bénéficiaire', 'badgeClass' => 'copy-badge-benef'],
            ['badge' => 'Souche Caisse / Comptabilité', 'badgeClass' => 'copy-badge-souche'],
        ]
        : [
            ['badge' => 'Exemplaire Officiel — Format A4', 'badgeClass' => 'copy-badge-benef'],
        ];
@endphp
<body class="{{ $doubleExemplaire ? 'is-compact' : '' }}">

    @foreach($exemplaires as $exemplaire)
        <div class="decharge-copy">
            <div class="copy-strip">
                <span class="copy-badge {{ $exemplaire['badgeClass'] }}">{{ $exemplaire['badge'] }}</span>
            </div>

            <!-- En-tête officiel camerounais -->
            @include('finances.partials.header', [
                'annee' => $depense->anneeScolaire,
                'serviceTitle' => 'Bon de Caisse & Décaissement',
                'compact' => $doubleExemplaire,
                'skipWatermark' => $loop->index > 0,
            ])

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
                            <div style="font-size: 7pt; text-transform: uppercase; color: #065f46; font-weight: bold;">
                                Montant Décaissé :
                            </div>
                            <div class="amount-value">
                                {{ number_format($depense->montant, 0, ',', ' ') }} FCFA
                            </div>
                        </td>
                        <td style="width: 35%; text-align: right; font-size: 7.5pt; color: #065f46;">
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
                    <td style="font-weight: bold; color: #1e3a8a;">
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
                        <em>(Pour Acquit des fonds)</em>{!! $doubleExemplaire ? '<br>' : '<br><br>' !!}
                        <strong>{{ $depense->beneficiaire }}</strong>
                    </td>
                    <td>
                        <div class="sig-title">Le Caissier / Comptable</div>
                        <em>(Vu et Payé)</em>{!! $doubleExemplaire ? '<br>' : '<br><br>' !!}
                        <strong>{{ $depense->auteur->name ?? 'La Caisse' }}</strong>
                    </td>
                    <td>
                        <div class="sig-title">L'Ordonnateur</div>
                        <em>(Bon pour Décaissement)</em>{!! $doubleExemplaire ? '<br>' : '<br><br>' !!}
                        <strong>{{ $etablissement->nom_chef_etablissement ?? 'Le Chef d’Établissement' }}</strong>
                    </td>
                </tr>
            </table>
        </div>

        @if(! $loop->last)
            <div class="cut-separator">
                <span>&#9986; Découper suivant les pointillés — Souche Caisse ci-dessous</span>
            </div>
        @endif
    @endforeach

</body>
</html>
