<!DOCTYPE html>
<html lang="fr">
<head>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}?v=syges3">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}?v=syges3">
    <link rel="shortcut icon" href="{{ asset('favicon.ico') }}?v=syges3">
    <meta charset="UTF-8">
    <title>Bilan Détaillé des Versements - {{ $annee->code ?? '2026-2027' }}</title>
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
        .classe-title {
            background-color: #e2e8f0;
            border: 1px solid #94a3b8;
            padding: 5px 8px;
            font-size: 8.5pt;
            font-weight: bold;
            color: #1e3a8a;
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
            font-size: 8.5pt;
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

    @include('finances.partials.header', ['serviceTitle' => "Direction Financière & Recouvrement des Frais Scolaires"])

    <div class="doc-banner">
        <div class="doc-title">Bilan Détaillé Nominatif des Versements par Élève</div>
        <div class="doc-subtitle">
            Situation arrêtée au <strong>{{ \Carbon\Carbon::now()->format('d-m-Y à H:i') }}</strong>
            • Année Scolaire : <strong>{{ $annee->code ?? '2026-2027' }}</strong>
            • <strong>{{ $total_eleves }}</strong> élève(s) répertorié(s)
        </div>
        <div class="doc-subtitle" style="margin-top: 2px;">
            Périmètre : <strong>{{ $perimetre_label ?? 'Toutes les sections, cycles, niveaux et classes' }}</strong>
        </div>
    </div>

    @forelse($classes as $c)
        @php
            $hasAnnexes = ($c['total_frais_annexes'] ?? 0) > 0;
        @endphp
        <div class="classe-title">
            Classe : {{ $c['classe'] }} — {{ count($c['eleves']) }} élève(s) — Versé : {{ number_format($c['total_verse'], 0, ',', ' ') }} FCFA | Solde restant : {{ number_format($c['total_reste'], 0, ',', ' ') }} FCFA
            @if(($c['total_a_verser'] ?? 0) <= 0 && ($c['total_verse'] ?? 0) <= 0)
                <span style="font-size: 7.5pt; font-weight: normal; color: #b45309; margin-left: 8px;">(Tarifs non configurés)</span>
            @endif
        </div>
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 3%;" class="text-center">#</th>
                    <th style="width: 10%;">Matricule</th>
                    <th style="width: {{ $hasAnnexes ? '21%' : '25%' }};">Nom & Prénoms de l'Élève</th>
                    <th style="width: {{ $hasAnnexes ? '4%' : '5%' }};" class="text-center">Sexe</th>
                    @if($hasAnnexes)
                        <th style="width: 10%;" class="text-right">Scolarité</th>
                        <th style="width: 12%;" class="text-right">Frais Annexes</th>
                        <th style="width: 10%;" class="text-right">Total Exigible</th>
                        <th style="width: 8%;" class="text-right">Réduction</th>
                        <th style="width: 9%;" class="text-right">Total Versé</th>
                        <th style="width: 9%;" class="text-right">Solde Dû</th>
                        <th style="width: 4%;" class="text-center">Statut</th>
                    @else
                        <th style="width: 13%;" class="text-right">Montant Exigible</th>
                        <th style="width: 11%;" class="text-right">Réduction</th>
                        <th style="width: 13%;" class="text-right">Total Versé</th>
                        <th style="width: 12%;" class="text-right">Solde Dû</th>
                        <th style="width: 8%;" class="text-center">Statut</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @foreach($c['eleves'] as $idx => $e)
                    <tr>
                        <td class="text-center">{{ $idx + 1 }}</td>
                        <td class="font-bold" style="font-family: monospace;">{{ $e['matricule'] }}</td>
                        <td class="font-bold">{{ $e['nom_complet'] }}</td>
                        <td class="text-center">{{ $e['sexe'] }}</td>
                        @if($hasAnnexes)
                            <td class="text-right">{{ number_format($e['scolarite_base'] ?? $e['a_verser'], 0, ',', ' ') }} F</td>
                            <td class="text-right" style="color: {{ ($e['frais_annexes'] ?? 0) > 0 ? '#b45309' : '#64748b' }};">
                                {{ number_format($e['frais_annexes'] ?? 0, 0, ',', ' ') }} F
                                @if(!empty($e['details_annexes']))
                                    <div style="font-size: 6pt; color: #92400e; font-weight: normal; line-height: 1.15;">
                                        {{ $e['details_annexes'] }}
                                    </div>
                                @endif
                            </td>
                        @endif
                        <td class="text-right {{ $hasAnnexes ? 'font-bold' : '' }}">{{ number_format($e['a_verser'], 0, ',', ' ') }} F</td>
                        <td class="text-right" style="color: #64748b;">{{ number_format($e['reduction'], 0, ',', ' ') }} F</td>
                        <td class="text-right font-bold" style="color: #047857;">{{ number_format($e['verse'], 0, ',', ' ') }} F</td>
                        <td class="text-right font-bold" style="color: {{ ($e['a_verser'] <= 0 && $e['verse'] <= 0) ? '#64748b' : ($e['reste'] > 0 ? '#dc2626' : '#047857') }};">
                            {{ number_format($e['reste'], 0, ',', ' ') }} F
                        </td>
                        <td class="text-center font-bold" style="font-size: {{ $hasAnnexes ? '6.5pt' : '7pt' }};">
                            @if($e['a_verser'] <= 0 && $e['verse'] <= 0 && $e['reduction'] <= 0)
                                <span style="color: #64748b;">NON CONFIGURÉ</span>
                            @elseif($e['reste'] <= 0)
                                <span style="color: #047857;">SOLDÉ</span>
                            @elseif($e['verse'] > 0)
                                <span style="color: #b45309;">PARTIEL</span>
                            @else
                                <span style="color: #dc2626;">IMPAYÉ</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @empty
        <div style="text-align: center; padding: 25px; color: #64748b;">
            Aucun compte élève trouvé pour le filtre sélectionné.
        </div>
    @endforelse

    <table class="total-box">
        <tr>
            <td style="width: 14%; text-align: center;">EFFECTIF : {{ $total_eleves }}</td>
            <td style="width: 32%; text-align: right;">
                CUMUL EXIGIBLE : {{ number_format($total_a_verser, 0, ',', ' ') }} FCFA
                @if(($total_frais_annexes ?? 0) > 0)
                    <div style="font-size: 6.5pt; font-weight: normal; color: #92400e;">
                        (Scolarité : {{ number_format($total_scolarite ?? 0, 0, ',', ' ') }} F + Annexes : {{ number_format($total_frais_annexes, 0, ',', ' ') }} F)
                    </div>
                @endif
            </td>
            <td style="width: 27%; text-align: right; color: #047857;">CUMUL VERSÉ : {{ number_format($total_verse, 0, ',', ' ') }} FCFA</td>
            <td style="width: 27%; text-align: right; color: {{ $total_reste > 0 ? '#dc2626' : '#047857' }};">
                TOTAL RESTE DÛ : {{ number_format($total_reste, 0, ',', ' ') }} FCFA
            </td>
        </tr>
    </table>

    <div class="in-words">
        Total des versements enregistrés pour ce groupe :
        <strong style="text-transform: uppercase; color: #047857;">{{ $total_verse_lettres ?? number_format($total_verse, 0, ',', ' ') . ' Francs CFA' }}</strong>.
    </div>

    <table class="signatures-table">
        <tr>
            <td>
                <div class="sign-title">L'Intendant / Responsable Financier</div>
                <div style="font-size: 7.5pt; color: #64748b;">(Visa et vérification des soldes)</div>
            </td>
            <td>
                <div class="sign-title">Le Chef d'Établissement / Direction</div>
                <div style="font-size: 7.5pt; color: #64748b;">(Pour approbation)</div>
            </td>
        </tr>
    </table>

</body>
</html>

