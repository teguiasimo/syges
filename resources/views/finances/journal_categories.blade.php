<!DOCTYPE html>
<html lang="fr">
<head>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}?v=syges3">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}?v=syges3">
    <link rel="shortcut icon" href="{{ asset('favicon.ico') }}?v=syges3">
    <meta charset="UTF-8">
    <title>Journal des Versements par Catégories - {{ $date_debut }} au {{ $date_fin }}</title>
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
        .category-header {
            background-color: #e2e8f0;
            border: 1px solid #94a3b8;
            padding: 5px 8px;
            font-size: 8.5pt;
            font-weight: bold;
            color: #1e293b;
            margin-top: 10px;
            margin-bottom: 2px;
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 6px;
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
        .total-box {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
            margin-bottom: 12px;
        }
        .total-box td {
            padding: 8px 10px;
            border: 1.5px solid #047857;
            background-color: #ecfdf5;
            font-size: 9pt;
            font-weight: bold;
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
        <div class="doc-title">Journal des Versements Ventilé par Catégories de Frais</div>
        <div class="doc-subtitle">
            Période du <strong>{{ $date_debut }}</strong> au <strong>{{ $date_fin }}</strong>
            • <strong>{{ $nombre_versements }}</strong> ligne(s) de ventilation
        </div>
        <div class="doc-subtitle" style="margin-top: 2px;">
            Périmètre : <strong>{{ $perimetre_label ?? 'Toutes les sections, cycles, niveaux et classes' }}</strong>
        </div>
    </div>

    @forelse($categories as $cat)
        <div class="category-header">
            Catégorie : {{ $cat['categorie'] ?? $cat['nom'] ?? 'Générale' }} — Sous-total : {{ number_format($cat['total_categorie'] ?? $cat['total_montant'] ?? 0, 0, ',', ' ') }} FCFA ({{ count($cat['lignes']) }} ligne(s))
        </div>
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 4%;" class="text-center">#</th>
                    <th style="width: 11%;">Date</th>
                    <th style="width: 14%;">N° Reçu</th>
                    <th style="width: 9%;">Matricule</th>
                    <th style="width: 22%;">Nom & Prénoms de l'Élève</th>
                    <th style="width: 8%;">Classe</th>
                    <th style="width: 18%;">Intitulé du Frais</th>
                    <th style="width: 14%;" class="text-right">Montant Encaissé</th>
                </tr>
            </thead>
            <tbody>
                @foreach($cat['lignes'] as $idx => $l)
                    <tr>
                        <td class="text-center">{{ $idx + 1 }}</td>
                        <td>{{ $l['date_heure'] ?? $l['date'] ?? 'N/A' }}</td>
                        <td class="font-bold" style="font-family: monospace;">{{ $l['reference_recu'] }}</td>
                        <td>{{ $l['matricule'] }}</td>
                        <td class="font-bold">{{ $l['eleve_nom'] ?? $l['eleve'] ?? 'N/A' }}</td>
                        <td>{{ $l['classe'] }}</td>
                        <td style="color: #475569;">{{ $l['rubrique_libelle'] ?? $l['libelle_frais'] ?? 'Frais' }}</td>
                        <td class="text-right font-bold" style="color: #047857;">
                            {{ number_format($l['montant'], 0, ',', ' ') }} FCFA
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @empty
        <div style="text-align: center; padding: 25px; color: #64748b;">
            Aucune opération enregistrée sur cette période pour les catégories sélectionnées.
        </div>
    @endforelse

    <table class="total-box">
        <tr>
            <td style="width: 50%;">TOTAL GÉNÉRAL CONSOLIDÉ PAR CATÉGORIES :</td>
            <td style="width: 50%; text-align: right; color: #047857; font-size: 11pt;">
                {{ number_format($total_general, 0, ',', ' ') }} FCFA
            </td>
        </tr>
    </table>

    <div class="in-words">
        Arrêté le présent journal par catégories à la somme totale de :
        <strong style="text-transform: uppercase; color: #047857;">{{ $total_lettres ?? number_format($total_general, 0, ',', ' ') . ' Francs CFA' }}</strong>.
    </div>

    <table class="signatures-table">
        <tr>
            <td>
                <div class="sign-title">Le Caissier / L'Intendant</div>
                <div style="font-size: 7.5pt; color: #64748b;">(Visa et vérification des catégories)</div>
            </td>
            <td>
                <div class="sign-title">Le Chef d'Établissement / Direction</div>
                <div style="font-size: 7.5pt; color: #64748b;">(Pour approbation)</div>
            </td>
        </tr>
    </table>

</body>
</html>

