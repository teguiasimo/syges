<!DOCTYPE html>
<html lang="fr">
<head>
    {{-- Identité visuelle & Favicons officiels SYGES --}}
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}?v=syges3">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}?v=syges3">
    <link rel="shortcut icon" href="{{ asset('favicon.ico') }}?v=syges3">
    <meta charset="UTF-8">
    <title>État des Recettes Diverses - {{ $date_debut }} au {{ $date_fin }}</title>
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
        .summary-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }
        .summary-table td {
            border: 1px solid #cbd5e1;
            padding: 5px 8px;
            text-align: center;
            background-color: #f8fafc;
        }
        .summary-label {
            font-size: 6.5pt;
            text-transform: uppercase;
            color: #64748b;
            font-weight: bold;
        }
        .summary-value {
            font-size: 9pt;
            font-weight: bold;
            color: #0f172a;
            margin-top: 2px;
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }
        .data-table th, .data-table td {
            border: 1px solid #cbd5e1;
            padding: 4px 6px;
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
        .cat-table {
            width: 50%;
            border-collapse: collapse;
            margin-top: 8px;
            margin-bottom: 15px;
        }
        .cat-table th, .cat-table td {
            border: 1px solid #cbd5e1;
            padding: 3px 6px;
            font-size: 7pt;
        }
        .cat-table th {
            background-color: #e2e8f0;
            font-weight: bold;
        }
        .signatures-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        .signatures-table td {
            width: 33.33%;
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

    {{-- En-tête officiel --}}
    @include('finances.partials.header', ['serviceTitle' => "Service de l'Intendance & Gestion Financière"])

    {{-- Bannière de titre --}}
    <div class="doc-banner">
        <div class="doc-title">État des Recettes Diverses & Encaissements Libres</div>
        <div class="doc-subtitle">
            Période du <strong>{{ $date_debut }}</strong> au <strong>{{ $date_fin }}</strong>
            • <strong>{{ $nombre_operations }}</strong> opération(s)
            • Caisse : <strong>{{ $caisse_nom }}</strong>
            • Mode : <strong>{{ strtoupper(str_replace('_', ' ', $mode_paiement_filtre)) }}</strong>
            • Catégorie : <strong>{{ $categorie_filtre }}</strong>
        </div>
    </div>

    {{-- Synthèse des totaux par mode de règlement --}}
    <table class="summary-table">
        <tr>
            <td>
                <div class="summary-label">Total Général Perçu</div>
                <div class="summary-value" style="color: #059669;">{{ number_format($total_general, 0, ',', ' ') }} FCFA</div>
            </td>
            <td>
                <div class="summary-label">Espèces</div>
                <div class="summary-value">{{ number_format($total_especes, 0, ',', ' ') }} FCFA</div>
            </td>
            <td>
                <div class="summary-label">Mobile Money</div>
                <div class="summary-value">{{ number_format($total_mobile_money, 0, ',', ' ') }} FCFA</div>
            </td>
            <td>
                <div class="summary-label">Virements</div>
                <div class="summary-value">{{ number_format($total_virements, 0, ',', ' ') }} FCFA</div>
            </td>
            <td>
                <div class="summary-label">Chèques</div>
                <div class="summary-value">{{ number_format($total_cheques, 0, ',', ' ') }} FCFA</div>
            </td>
        </tr>
    </table>

    {{-- Tableau chronologique détaillé --}}
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 3%;" class="text-center">#</th>
                <th style="width: 10%;">Date & Heure</th>
                <th style="width: 12%;">N° Reçu</th>
                <th style="width: 16%;">Payeur & Téléphone</th>
                <th style="width: 14%;">Catégorie</th>
                <th style="width: 25%;">Commentaire / Motif</th>
                <th style="width: 8%;">Mode</th>
                <th style="width: 12%;" class="text-right">Montant</th>
            </tr>
        </thead>
        <tbody>
            @forelse($recettes as $idx => $r)
                <tr>
                    <td class="text-center">{{ $idx + 1 }}</td>
                    <td>{{ \Carbon\Carbon::parse($r->date_reglement)->format('d-m-Y H:i') }}</td>
                    <td class="font-bold" style="font-family: monospace;">{{ $r->reference_recu }}</td>
                    <td>
                        <span class="font-bold">{{ $r->payeur_nom }}</span>
                        @if($r->payeur_telephone)
                            <div style="font-size: 6.5pt; color: #64748b;">Tél: {{ $r->payeur_telephone }}</div>
                        @endif
                    </td>
                    <td>
                        <span style="font-weight: 600; color: #1e3a8a;">{{ $r->categorie }}</span>
                    </td>
                    <td>{{ $r->libelle }}</td>
                    <td style="text-transform: uppercase;">{{ str_replace('_', ' ', $r->mode_paiement) }}</td>
                    <td class="text-right font-bold" style="color: #059669;">
                        {{ number_format($r->montant, 0, ',', ' ') }} FCFA
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="text-center" style="padding: 15px; color: #64748b;">
                        Aucun encaissement libre ou recette diverse trouvé(e) pour les critères sélectionnés.
                    </td>
                </tr>
            @endforelse
        </tbody>
        @if($recettes->count() > 0)
            <tfoot>
                <tr style="background-color: #f1f5f9; font-weight: bold;">
                    <td colspan="7" class="text-right" style="padding: 6px;">TOTAL GÉNÉRAL DE LA PÉRIODE :</td>
                    <td class="text-right" style="color: #059669; font-size: 8.5pt;">
                        {{ number_format($total_general, 0, ',', ' ') }} FCFA
                    </td>
                </tr>
            </tfoot>
        @endif
    </table>

    {{-- Ventilation par catégorie --}}
    @if(count($par_categorie) > 0)
        <table class="cat-table">
            <thead>
                <tr>
                    <th>Catégorie de recette</th>
                    <th class="text-center" style="width: 25%;">Nombre</th>
                    <th class="text-right" style="width: 35%;">Montant global</th>
                </tr>
            </thead>
            <tbody>
                @foreach($par_categorie as $cat)
                    <tr>
                        <td>{{ $cat['categorie'] }}</td>
                        <td class="text-center">{{ $cat['nombre'] }}</td>
                        <td class="text-right font-bold">{{ number_format($cat['montant'], 0, ',', ' ') }} FCFA</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    {{-- Signatures officielles --}}
    <table class="signatures-table">
        <tr>
            <td>
                <div class="sign-title">Le Caissier / Agent Encaisseur</div>
                <div><em>(Nom, signature & date)</em></div>
            </td>
            <td>
                <div class="sign-title">L'Intendant / Comptable</div>
                <div><em>(Visa & signature)</em></div>
            </td>
            <td>
                <div class="sign-title">Le Chef d'Établissement</div>
                <div><em>(Cachet & signature)</em></div>
            </td>
        </tr>
    </table>

</body>
</html>

