<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Reçu de Caisse - {{ $versement->reference_recu }}</title>
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
            margin-bottom: 8px;
        }
        .info-card {
            width: 49%;
            border: 1px solid #cbd5e1;
            border-radius: 4px;
            padding: 6px 8px;
            background-color: #fafafa;
            font-size: 8pt;
            vertical-align: top;
        }
        .info-title {
            font-weight: bold;
            text-transform: uppercase;
            font-size: 7.5pt;
            color: #475569;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 3px;
            margin-bottom: 4px;
        }
        .ventilation-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8px;
            font-size: 8pt;
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
        .ventilation-table td {
            border-bottom: 1px solid #e2e8f0;
            padding: 4px 6px;
        }
        .balance-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 6px;
            font-size: 8pt;
        }
        .balance-table td {
            padding: 3px 6px;
            border: 1px solid #cbd5e1;
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
            margin-top: 10px;
            font-size: 7.5pt;
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
            width: 75px;
            height: 75px;
        }
        .security-note {
            font-size: 6.5pt;
            color: #64748b;
            margin-top: 2px;
        }
    </style>
</head>
<body>
    {{-- En-tête officiel MINESEC / Établissement --}}
    <table class="header-table">
        <tr>
            <td class="header-left">
                <strong>RÉPUBLIQUE DU CAMEROUN</strong><br>
                <em>Paix – Travail – Patrie</em><br>
                MINISTÈRE DES ENSEIGNEMENTS SECONDAIRES<br>
                DÉLÉGATION RÉGIONALE DU CENTRE<br>
                DÉLÉGATION DÉPARTEMENTALE DU MFOUNDI
            </td>
            <td class="header-center">
                @if(!empty($etablissement->logo) && file_exists(public_path($etablissement->logo)))
                    <img src="{{ public_path($etablissement->logo) }}" style="max-height: 45px;" alt="Logo"><br>
                @endif
                <div class="school-name">{{ $etablissement->nom ?? 'SYGES INSTITUTION' }}</div>
                <div class="school-contacts">
                    {{ $etablissement->telephone_1 ?? '' }} | {{ $etablissement->email ?? '' }}<br>
                    {{ $etablissement->adresse ?? '' }}
                </div>
            </td>
            <td class="header-right">
                <strong>REPUBLIC OF CAMEROON</strong><br>
                <em>Peace – Work – Fatherland</em><br>
                MINISTRY OF SECONDARY EDUCATION<br>
                REGIONAL DELEGATION OF CENTER<br>
                MFOUNDI DIVISIONAL DELEGATION
            </td>
        </tr>
    </table>

    {{-- Bandeau Titre et Métadonnées du reçu --}}
    <div class="document-title-box">
        <table class="rec-meta-table">
            <tr>
                <td style="width: 50%;">
                    <span class="document-title">REÇU DE CAISSE OFFICIEL</span><br>
                    <span style="font-size: 7.5pt; color: #64748b;">OFFICIAL CASH RECEIPT — ANNÉE SCOLAIRE {{ $compte->anneeScolaire->code ?? '2026-2027' }}</span>
                </td>
                <td style="width: 25%; text-align: right;">
                    N° REÇU : <span class="rec-number">{{ $versement->reference_recu }}</span>
                </td>
                <td style="width: 25%; text-align: right;">
                    Date : <strong>{{ \Carbon\Carbon::parse($versement->date_versement)->format('d/m/Y à H:i') }}</strong>
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
                <td style="text-align: right; color: #047857; font-size: 9pt;">{{ number_format($versement->montant_total, 0, ',', ' ') }} FCFA</td>
            </tr>
        </tbody>
    </table>

    {{-- Situation Financière Mise à Jour du Compte Élève --}}
    <table class="balance-table">
        <tr>
            <td class="balance-label">Total Exigible Net :</td>
            <td class="balance-val">{{ number_format($compte->montant_net_exigible, 0, ',', ' ') }} FCFA</td>
            <td class="balance-label">Cumul Total Payé :</td>
            <td class="balance-val" style="color: #047857;">{{ number_format($compte->total_paye, 0, ',', ' ') }} FCFA</td>
        </tr>
        <tr>
            <td class="balance-label">Ce Versement :</td>
            <td class="balance-val">{{ number_format($versement->montant_total, 0, ',', ' ') }} FCFA</td>
            <td class="balance-label" style="background-color: #fef2f2; color: #991b1b;">SOLDE RESTANT DÛ :</td>
            <td class="balance-val" style="background-color: #fef2f2; color: #b91c1c; font-size: 9pt;">
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
                    Document officiel infalsifiable SYGES.<br>
                    Vérifiable au service financier.
                </div>
            </td>
            <td>
                <strong>Le Payeur / Tuteur</strong><br><br><br>
                <em>(Signature)</em>
            </td>
            <td>
                <strong>Le Caissier / L'Économe</strong><br>
                <span>{{ $versement->recu_par ?? 'Service Comptable' }}</span><br><br>
                <em>(Cachet & Signature)</em>
            </td>
        </tr>
    </table>
</body>
</html>
