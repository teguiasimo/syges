{{--
    Vue Blade : Ticket de Caisse Thermique 80mm (format pharmacie / POS).
    Conçue pour les imprimantes thermiques à rouleau 80mm afin d'économiser le papier A4.
    Variables attendues : $versement, $compte, $eleve, $inscription, $etablissement, $qrCodeBase64.
--}}
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Ticket {{ $versement->reference_recu }}</title>
    <style>
        @page {
            margin: 3mm 4mm;
        }
        * {
            box-sizing: border-box;
        }
        body {
            font-family: 'DejaVu Sans', 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 7.5pt;
            color: #000000;
            line-height: 1.3;
            margin: 0;
            padding: 0;
            width: 100%;
        }
        .center { text-align: center; }
        .right { text-align: right; }
        .bold { font-weight: bold; }
        .upper { text-transform: uppercase; }
        .mono { font-family: 'DejaVu Sans Mono', monospace; }
        .sep {
            border-top: 1px dashed #334155;
            margin: 5px 0;
        }
        .sep-solid {
            border-top: 1.5px solid #000000;
            margin: 5px 0;
        }
        .school-name {
            font-size: 8.5pt;
            font-weight: bold;
            text-transform: uppercase;
            line-height: 1.2;
        }
        .school-meta {
            font-size: 6.5pt;
            color: #1e293b;
        }
        .ticket-title {
            font-size: 8pt;
            font-weight: bold;
            border: 1px solid #000000;
            padding: 2px 4px;
            margin: 4px 0;
            text-align: center;
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }
        .annule-box {
            border: 1.5px solid #991b1b;
            background-color: #fef2f2;
            color: #991b1b;
            text-align: center;
            font-weight: bold;
            font-size: 7.5pt;
            padding: 3px;
            margin: 4px 0;
        }
        table.kv {
            width: 100%;
            border-collapse: collapse;
        }
        table.kv td {
            padding: 1.5px 0;
            vertical-align: top;
            font-size: 7pt;
        }
        table.kv td.lbl {
            color: #334155;
            width: 36%;
        }
        table.kv td.val {
            text-align: right;
            font-weight: bold;
            width: 64%;
            word-break: break-word;
        }
        table.items {
            width: 100%;
            border-collapse: collapse;
            margin: 3px 0;
        }
        table.items th {
            font-size: 6.8pt;
            text-transform: uppercase;
            border-bottom: 1px solid #000000;
            padding: 2px 0;
            text-align: left;
        }
        table.items td {
            font-size: 7pt;
            padding: 2.5px 0;
            border-bottom: 1px dotted #cbd5e1;
            vertical-align: top;
        }
        .total-panel {
            border: 1px solid #000000;
            background-color: #f8fafc;
            padding: 4px 5px;
            margin: 4px 0;
        }
        .total-row {
            width: 100%;
            border-collapse: collapse;
        }
        .total-row td {
            font-size: 8.5pt;
            font-weight: bold;
        }
        .qr-wrap {
            margin-top: 6px;
            text-align: center;
        }
        .qr-wrap img {
            width: 62px;
            height: 62px;
        }
        .footer-note {
            font-size: 6.2pt;
            color: #334155;
            text-align: center;
            margin-top: 4px;
            line-height: 1.25;
        }
    </style>
</head>
<body>
    @php
        $dateFormatee = $versement->date_versement instanceof \Carbon\Carbon
            ? $versement->date_versement->format('d-m-Y H:i')
            : \Carbon\Carbon::parse($versement->date_versement)->format('d-m-Y H:i');
        $anneeCode = $compte?->anneeScolaire?->code ?? ($compte?->anneeScolaire?->libelle ?? '');
        $modesLabels = [
            'especes' => 'Espèces',
            'cheque' => 'Chèque',
            'virement' => 'Virement bancaire',
            'mobile_money_manuel' => 'Mobile Money',
        ];
        $modeTexte = $modesLabels[$versement->mode_paiement] ?? strtoupper((string) $versement->mode_paiement);
    @endphp

    {{-- En-tête compact de l'établissement --}}
    <div class="center">
        <div class="school-name">{{ $etablissement?->nom ?? 'ÉTABLISSEMENT SCOLAIRE' }}</div>
        @if($etablissement?->adresse || $etablissement?->telephone)
            <div class="school-meta">
                {{ $etablissement?->adresse }}
                @if($etablissement?->adresse && $etablissement?->telephone) • @endif
                @if($etablissement?->telephone) Tél : {{ $etablissement->telephone }} @endif
            </div>
        @endif
        @if($anneeCode)
            <div class="school-meta bold">ANNÉE SCOLAIRE {{ $anneeCode }}</div>
        @endif
    </div>

    <div class="ticket-title">Ticket de Caisse • Scolarité</div>

    @if($versement->statut === 'annule')
        <div class="annule-box">
            TICKET ANNULÉ / AVOIR
            @if($versement->motif_annulation)
                <div style="font-weight: normal; font-size: 6.2pt;">{{ $versement->motif_annulation }}</div>
            @endif
        </div>
    @endif

    {{-- Informations de caisse & traçabilité --}}
    <table class="kv">
        <tr>
            <td class="lbl">N° Ticket :</td>
            <td class="val mono">{{ $versement->reference_recu }}</td>
        </tr>
        <tr>
            <td class="lbl">Date / Heure :</td>
            <td class="val">{{ $dateFormatee }}</td>
        </tr>
        @if($versement->caisse)
            <tr>
                <td class="lbl">Caisse :</td>
                <td class="val">{{ $versement->caisse->nom }}</td>
            </tr>
        @endif
        <tr>
            <td class="lbl">Opérateur :</td>
            <td class="val">{{ $versement->caissier?->nom ?? 'Secrétariat / Caisse' }}</td>
        </tr>
    </table>

    <div class="sep"></div>

    {{-- Identification de l'élève --}}
    <table class="kv">
        <tr>
            <td class="lbl">Élève :</td>
            <td class="val upper">{{ trim(($eleve?->nom ?? '').' '.($eleve?->prenom ?? '')) }}</td>
        </tr>
        <tr>
            <td class="lbl">Matricule :</td>
            <td class="val mono">{{ $eleve?->matricule ?? 'N/A' }}</td>
        </tr>
        <tr>
            <td class="lbl">Classe :</td>
            <td class="val">{{ $inscription?->classe?->nom ?? 'N/A' }}</td>
        </tr>
        @if($versement->payeur_nom)
            <tr>
                <td class="lbl">Payeur :</td>
                <td class="val">{{ $versement->payeur_nom }}</td>
            </tr>
        @endif
    </table>

    <div class="sep"></div>

    {{-- Détail des rubriques ventilées --}}
    <table class="items">
        <thead>
            <tr>
                <th style="width: 65%;">Rubrique / Frais</th>
                <th style="width: 35%; text-align: right;">Montant</th>
            </tr>
        </thead>
        <tbody>
            @forelse($versement->ventilations as $vent)
                <tr>
                    <td>{{ $vent->libelle }}</td>
                    <td class="right bold">{{ number_format((float) $vent->montant, 0, ',', ' ') }} F</td>
                </tr>
            @empty
                <tr>
                    <td>Encaissement scolarité</td>
                    <td class="right bold">{{ number_format((float) $versement->montant_total, 0, ',', ' ') }} F</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    {{-- Total encaissé sur ce ticket --}}
    <div class="total-panel">
        <table class="total-row">
            <tr>
                <td>TOTAL PAYÉ :</td>
                <td class="right">{{ number_format((float) $versement->montant_total, 0, ',', ' ') }} FCFA</td>
            </tr>
        </table>
    </div>

    {{-- Mode de règlement & Situation du compte après versement --}}
    <table class="kv">
        <tr>
            <td class="lbl">Règlement :</td>
            <td class="val">{{ $modeTexte }}</td>
        </tr>
        @if($versement->reference_paiement)
            <tr>
                <td class="lbl">Réf. pièce :</td>
                <td class="val mono">{{ $versement->reference_paiement }}</td>
            </tr>
        @endif
        <tr>
            <td class="lbl">Total versé an :</td>
            <td class="val">{{ number_format((float) ($compte?->total_paye ?? 0), 0, ',', ' ') }} FCFA</td>
        </tr>
        <tr>
            <td class="lbl">Reste à payer :</td>
            <td class="val">
                @if((float) ($compte?->solde_restant ?? 0) <= 0)
                    SOLDÉ (0 FCFA)
                @else
                    {{ number_format((float) $compte->solde_restant, 0, ',', ' ') }} FCFA
                @endif
            </td>
        </tr>
    </table>

    <div class="sep"></div>

    {{-- QR Code d'authenticité et pied de ticket --}}
    @if(!empty($qrCodeBase64))
        <div class="qr-wrap">
            <img src="{{ $qrCodeBase64 }}" alt="QR Vérification">
        </div>
    @endif

    <div class="footer-note">
        Ticket généré électroniquement — Valide sans rature.<br>
        Imprimé le {{ now()->format('d-m-Y H:i') }} • SYGES Finances
    </div>
</body>
</html>

