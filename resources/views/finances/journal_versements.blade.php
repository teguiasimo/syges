<!DOCTYPE html>
<html lang="fr">
<head>
    {{-- Identité visuelle & Favicons officiels SYGES --}}
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}?v=syges3">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}?v=syges3">
    <link rel="shortcut icon" href="{{ asset('favicon.ico') }}?v=syges3">
    <meta charset="UTF-8">
    <title>Journal des Versements - {{ $date_debut }} au {{ $date_fin }}</title>
    <style>
        @page {
            size: A4 landscape;
            margin: 8mm 10mm;
        }
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 8pt;
            color: #0f172a;
            line-height: 1.25;
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
            margin-bottom: 8px;
        }
        .data-table th, .data-table td {
            border: 1px solid #cbd5e1;
            padding: 4px 5px;
            font-size: 7.5pt;
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
        .total-box {
            width: 100%;
            border-collapse: collapse;
            margin-top: 6px;
            margin-bottom: 12px;
        }
        .total-box td {
            padding: 6px 8px;
            border: 1px solid #cbd5e1;
            font-size: 8pt;
        }
        .in-words {
            background-color: #f1f5f9;
            padding: 6px 10px;
            border-left: 3px solid #047857;
            font-size: 8pt;
            margin-bottom: 14px;
        }
        .signatures-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        .signatures-table td {
            width: 50%;
            text-align: center;
            vertical-align: top;
            font-size: 8pt;
        }
        .sign-title {
            font-weight: bold;
            margin-bottom: 35px;
            text-transform: uppercase;
        }
    </style>
</head>
<body>

    @include('finances.partials.header', ['serviceTitle' => "Service de l'Intendance & Comptabilité Scolaire"])

    <div class="doc-banner">
        <div class="doc-title">Journal Chronologique des Versements & Encaissements</div>
        <div class="doc-subtitle">
            Période du <strong>{{ $date_debut }}</strong> au <strong>{{ $date_fin }}</strong>
            • <strong>{{ $nombre_versements }}</strong> versement(s) enregistré(s)
            @if(!empty($mode_paiement_filtre))
                • Mode : <strong>{{ strtoupper(str_replace('_', ' ', $mode_paiement_filtre)) }}</strong>
            @endif
        </div>
        <div class="doc-subtitle" style="margin-top: 2px;">
            Périmètre : <strong>{{ $perimetre_label ?? 'Toutes les sections, cycles, niveaux et classes' }}</strong>
        </div>
    </div>

    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 3%;" class="text-center">#</th>
                <th style="width: 10%;">Date & Heure</th>
                <th style="width: 12%;">N° Reçu</th>
                <th style="width: 8%;">Matricule</th>
                <th style="width: 16%;">Nom & Prénoms Élève</th>
                <th style="width: 7%;">Classe</th>
                <th style="width: 11%;">Payeur</th>
                <th style="width: 8%;">Mode</th>
                <th style="width: 13%;">Rubriques / Tranches</th>
                <th style="width: 12%;" class="text-right">Montant</th>
            </tr>
        </thead>
        <tbody>
            @forelse($versements as $idx => $v)
                <tr>
                    <td class="text-center">{{ $idx + 1 }}</td>
                    <td>{{ \Carbon\Carbon::parse($v->date_versement)->format('d-m-Y H:i') }}</td>
                    <td class="font-bold" style="font-family: monospace;">{{ $v->reference_recu }}</td>
                    <td>{{ $v->compteFinancier?->eleve?->matricule ?? 'N/A' }}</td>
                    <td class="font-bold">{{ $v->compteFinancier?->eleve?->nom }} {{ $v->compteFinancier?->eleve?->prenom }}</td>
                    <td>{{ $v->inscription?->classe?->nom ?? 'N/A' }}</td>
                    <td>{{ $v->payeur_nom ?? 'Élève / Parent' }}</td>
                    <td style="text-transform: uppercase;">{{ str_replace('_', ' ', $v->mode_paiement) }}</td>
                    <td style="font-size: 7pt; color: #475569;">
                        @foreach($v->ventilations as $vent)
                            {{ $vent->libelle }}@if(!$loop->last), @endif
                        @endforeach
                    </td>
                    <td class="text-right font-bold" style="color: #047857;">
                        {{ number_format($v->montant_total, 0, ',', ' ') }} FCFA
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="10" class="text-center" style="color: #64748b; padding: 15px;">
                        Aucun versement enregistré sur la période sélectionnée.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    {{-- Synthèse des Règlements --}}
    <table class="total-box">
        <tr style="background-color: #f8fafc; font-weight: bold;">
            <td style="width: 25%;">Espèces : <span style="color: #0f172a;">{{ number_format($totaux_par_mode['especes'] ?? 0, 0, ',', ' ') }} FCFA</span></td>
            <td style="width: 25%;">Mobile Money : <span style="color: #0f172a;">{{ number_format($totaux_par_mode['mobile_money_manuel'] ?? 0, 0, ',', ' ') }} FCFA</span></td>
            <td style="width: 25%;">Chèque / Virement : <span style="color: #0f172a;">{{ number_format(($totaux_par_mode['cheque'] ?? 0) + ($totaux_par_mode['virement'] ?? 0), 0, ',', ' ') }} FCFA</span></td>
            <td style="width: 25%; text-align: right; background-color: #ecfdf5; color: #065f46; font-size: 9pt;">
                TOTAL : {{ number_format($total_general, 0, ',', ' ') }} FCFA
            </td>
        </tr>
    </table>

    <div class="in-words">
        Arrêté le présent journal des versements à la somme totale de :
        <strong style="text-transform: uppercase; color: #047857;">{{ $total_lettres ?? number_format($total_general, 0, ',', ' ') . ' Francs CFA' }}</strong>.
    </div>

    <table class="signatures-table">
        <tr>
            <td>
                <div class="sign-title">Le Caissier / L'Intendant</div>
                <div style="font-size: 7.5pt; color: #64748b;">(Visa et arrêté des écritures)</div>
            </td>
            <td>
                <div class="sign-title">Le Chef d'Établissement / La Direction</div>
                <div style="font-size: 7.5pt; color: #64748b;">(Pour contrôle et approbation)</div>
            </td>
        </tr>
    </table>

</body>
</html>

