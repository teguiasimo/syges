{{--
    Vue Blade : État Nominatif #09 — Suivi des Élèves par Catégorie / Frais & Palier (PDF A4 Portrait/Paysage).
    Permet d'extraire précisément tous les élèves pour une catégorie (ex: Scolarité, Examen) ou un frais
    spécifique avec filtre par condition/palier (Tous, Soldé, Uniquement jusqu'à ce palier, Partiel, Non payé).
--}}
<!DOCTYPE html>
<html lang="fr">
<head>
    {{-- Identité visuelle & Favicons officiels SYGES --}}
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}?v=syges3">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}?v=syges3">
    <link rel="shortcut icon" href="{{ asset('favicon.ico') }}?v=syges3">
    <meta charset="UTF-8">
    <title>Suivi par Frais & Palier - {{ $cible_label }}</title>
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
        .doc-banner {
            border: 1.5px solid #0f172a;
            background-color: #f8fafc;
            border-radius: 4px;
            padding: 6px 8px;
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
            margin-top: 2px;
        }
        .kpi-strip {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }
        .kpi-strip td {
            border: 1px solid #cbd5e1;
            background-color: #f8fafc;
            padding: 5px 8px;
            text-align: center;
            width: 20%;
        }
        .kpi-lbl {
            font-size: 6.8pt;
            text-transform: uppercase;
            color: #475569;
            font-weight: bold;
        }
        .kpi-val {
            font-size: 9.5pt;
            font-weight: bold;
            color: #0f172a;
            margin-top: 2px;
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
            background-color: #1e3a8a;
            color: #ffffff;
            font-weight: bold;
            text-align: left;
        }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .font-bold { font-weight: bold; }
        .badge {
            display: inline-block;
            padding: 1.5px 5px;
            border-radius: 3px;
            font-size: 6.8pt;
            font-weight: bold;
        }
        .badge-ok { background-color: #dcfce7; color: #166534; }
        .badge-part { background-color: #fef9c3; color: #854d0e; }
        .badge-due { background-color: #fee2e2; color: #991b1b; }
        .in-words {
            background-color: #f1f5f9;
            padding: 6px 10px;
            border-left: 3px solid #1e3a8a;
            font-size: 8pt;
            margin-top: 6px;
            margin-bottom: 14px;
        }
        .signatures-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 14px;
        }
        .signatures-table td {
            width: 50%;
            text-align: center;
            vertical-align: top;
            font-size: 8pt;
        }
        .sign-title {
            font-weight: bold;
            margin-bottom: 34px;
            text-transform: uppercase;
        }
    </style>
</head>
<body>
    @include('finances.partials.header', ['serviceTitle' => "Service de l'Intendance & Comptabilité Scolaire"])

    <div class="doc-banner">
        <div class="doc-title">État Nominatif de Suivi — {{ $cible_label }}</div>
        <div class="doc-subtitle">
            Condition : <strong>{{ $mode_label }}</strong>
            • Effectif retenu : <strong>{{ $nombre_eleves }}</strong> élève(s)
            • Taux de recouvrement sur cible : <strong>{{ number_format((float) $taux_recouvrement, 1, ',', ' ') }} %</strong>
        </div>
        <div class="doc-subtitle">
            Périmètre : <strong>{{ $perimetre_label ?? 'Toutes les sections, cycles, niveaux et classes' }}</strong>
        </div>
    </div>

    <table class="kpi-strip">
        <tr>
            <td>
                <div class="kpi-lbl">Effectif Filtré</div>
                <div class="kpi-val">{{ $nombre_eleves }} élève(s)</div>
            </td>
            <td>
                <div class="kpi-lbl">Total Attendu (Cible)</div>
                <div class="kpi-val">{{ number_format((float) $total_attendu, 0, ',', ' ') }} FCFA</div>
            </td>
            <td>
                <div class="kpi-lbl">Total Encaissé (Cible)</div>
                <div class="kpi-val" style="color: #047857;">{{ number_format((float) $total_paye, 0, ',', ' ') }} FCFA</div>
            </td>
            <td>
                <div class="kpi-lbl">Reste à Recouvrer (Cible)</div>
                <div class="kpi-val" style="color: #b91c1c;">{{ number_format((float) $total_restant, 0, ',', ' ') }} FCFA</div>
            </td>
            <td>
                <div class="kpi-lbl">Taux de Recouvrement</div>
                <div class="kpi-val">{{ number_format((float) $taux_recouvrement, 1, ',', ' ') }} %</div>
            </td>
        </tr>
    </table>

    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 3%;" class="text-center">#</th>
                <th style="width: 9%;">Matricule</th>
                <th style="width: 21%;">Nom & Prénoms de l'Élève</th>
                <th style="width: 10%;">Classe</th>
                <th style="width: 17%;">Rubrique(s) / Frais Ciblé(s)</th>
                <th style="width: 10%;" class="text-right">Attendu</th>
                <th style="width: 10%;" class="text-right">Payé</th>
                <th style="width: 10%;" class="text-right">Reste</th>
                <th style="width: 10%;" class="text-center">Statut Cible</th>
            </tr>
        </thead>
        <tbody>
            @forelse($eleves as $idx => $item)
                <tr>
                    <td class="text-center">{{ $idx + 1 }}</td>
                    <td style="font-family: monospace;">{{ $item['matricule'] }}</td>
                    <td class="font-bold">{{ $item['nom_complet'] }}</td>
                    <td>{{ $item['classe'] }}</td>
                    <td>{{ $item['rubriques_label'] }}</td>
                    <td class="text-right">{{ number_format((float) $item['attendu'], 0, ',', ' ') }}</td>
                    <td class="text-right font-bold" style="color: #047857;">{{ number_format((float) $item['paye'], 0, ',', ' ') }}</td>
                    <td class="text-right font-bold" style="color: {{ $item['restant'] > 0 ? '#b91c1c' : '#047857' }};">
                        {{ number_format((float) $item['restant'], 0, ',', ' ') }}
                    </td>
                    <td class="text-center">
                        @if($item['statut_cible'] === 'Soldé')
                            <span class="badge badge-ok">Soldé</span>
                        @elseif($item['statut_cible'] === 'Partiel')
                            <span class="badge badge-part">Partiel</span>
                        @else
                            <span class="badge badge-due">Non payé</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" class="text-center" style="padding: 14px; color: #64748b;">
                        Aucun élève ne correspond à ces critères pour la période / grille sélectionnée.
                    </td>
                </tr>
            @endforelse
            @if(count($eleves) > 0)
                <tr style="background-color: #f1f5f9; font-weight: bold;">
                    <td colspan="5" class="text-right">TOTAUX GÉNÉRAUX ({{ $nombre_eleves }} élève(s)) :</td>
                    <td class="text-right">{{ number_format((float) $total_attendu, 0, ',', ' ') }} FCFA</td>
                    <td class="text-right" style="color: #047857;">{{ number_format((float) $total_paye, 0, ',', ' ') }} FCFA</td>
                    <td class="text-right" style="color: #b91c1c;">{{ number_format((float) $total_restant, 0, ',', ' ') }} FCFA</td>
                    <td class="text-center">{{ number_format((float) $taux_recouvrement, 1, ',', ' ') }} %</td>
                </tr>
            @endif
        </tbody>
    </table>

    @if(!empty($total_paye_lettres))
        <div class="in-words">
            Total encaissé sur la sélection arrêté à la somme de : <strong>{{ $total_paye_lettres }}</strong>.
        </div>
    @endif

    <table class="signatures-table">
        <tr>
            <td>
                <div class="sign-title">L'Intendant / Secrétaire Comptable</div>
                <div>Date & Visa</div>
            </td>
            <td>
                <div class="sign-title">Le Chef d'Établissement</div>
                <div>Vu et Approuvé le {{ now()->format('d-m-Y') }}</div>
            </td>
        </tr>
    </table>
</body>
</html>

