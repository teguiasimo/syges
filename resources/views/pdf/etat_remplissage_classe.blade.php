<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>État de Remplissage des Notes & Babillard - {{ $classe->nom }} - {{ $titrePeriode }}</title>
    <style>
        @page {
            size: A4 landscape;
            margin: 5mm 7mm 5mm 7mm;
        }
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 7pt;
            color: #0f172a;
            line-height: 1.15;
            margin: 0;
            padding: 0;
        }
        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 2px;
        }
        .header-table td {
            vertical-align: top;
        }
        .header-left, .header-right {
            width: 40%;
            font-size: 6.5pt;
            text-align: center;
            line-height: 1.25;
        }
        .header-center {
            width: 20%;
            text-align: center;
            vertical-align: middle;
        }
        .school-header-box {
            text-align: center;
            margin-top: 2px;
            margin-bottom: 5px;
            width: 100%;
        }
        .school-name {
            font-size: 10.5pt;
            font-weight: bold;
            color: #1e3a8a;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            white-space: nowrap;
        }
        .school-motto {
            font-size: 6pt;
            font-style: italic;
            color: #475569;
            margin-top: 1px;
        }
        .banner-box {
            background-color: #1e3a8a;
            color: #ffffff;
            border-radius: 3px;
            padding: 4px 8px;
            margin-bottom: 5px;
            display: table;
            width: 100%;
            box-sizing: border-box;
        }
        .banner-title {
            font-size: 9pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: table-cell;
            vertical-align: middle;
        }
        .banner-meta {
            font-size: 7.2pt;
            display: table-cell;
            text-align: right;
            vertical-align: middle;
            color: #bfdbfe;
        }
        .stats-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 5px;
            background-color: #f8fafc;
            border: 1px solid #cbd5e1;
            border-radius: 3px;
        }
        .stats-table td {
            padding: 3px 4px;
            text-align: center;
            border-right: 1px solid #e2e8f0;
            font-size: 6.8pt;
        }
        .stats-table td:last-child {
            border-right: none;
        }
        .stats-label {
            color: #64748b;
            font-size: 5.6pt;
            text-transform: uppercase;
            font-weight: bold;
        }
        .stats-val {
            font-size: 8.5pt;
            font-weight: bold;
            color: #1e3a8a;
            margin-top: 1px;
        }
        .matrix-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 5px;
            font-size: 6.8pt;
            table-layout: fixed;
        }
        .matrix-table th {
            background-color: #0f172a;
            color: #ffffff;
            font-weight: bold;
            font-size: 6.5pt;
            padding: 3px 1px;
            text-align: center;
            border: 1px solid #334155;
            text-transform: uppercase;
            overflow: hidden;
        }
        .matrix-table th.sub-th {
            background-color: #e2e8f0;
            color: #0f172a;
            font-size: 6.2pt;
            font-weight: bold;
            border: 1px solid #94a3b8;
            padding: 2.5px 1px;
        }
        .matrix-table td {
            border: 1px solid #cbd5e1;
            padding: 2.5px 2px;
            font-size: 6.8pt;
            overflow: hidden;
        }
        .matrix-table tr:nth-child(even) {
            background-color: #f8fafc;
        }
        .cell-num {
            text-align: center;
            font-weight: bold;
            color: #64748b;
        }
        .cell-mat {
            text-align: center;
            font-family: monospace;
            font-size: 6pt;
            white-space: nowrap;
        }
        .cell-nom {
            font-weight: 600;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            padding-left: 3px;
        }
        .cell-sexe {
            text-align: center;
        }
        .cell-note {
            text-align: center;
            font-family: monospace;
            font-size: 6.8pt;
        }
        .note-ok {
            color: #0f172a;
            font-weight: bold;
        }
        .note-fail {
            color: #dc2626;
            font-weight: bold;
        }
        .note-abs {
            color: #d97706;
            font-weight: bold;
            font-size: 5.8pt;
        }
        .note-missing {
            color: #94a3b8;
            font-weight: bold;
        }
        .cell-total {
            text-align: right;
            font-weight: bold;
            padding-right: 3px;
        }
        .cell-moy {
            text-align: center;
            font-weight: bold;
        }
        .cell-rang {
            text-align: center;
            font-weight: bold;
        }
        .cell-mention {
            text-align: center;
            font-size: 5.8pt;
            font-weight: bold;
            white-space: nowrap;
        }
        .rank-first {
            background-color: #fef3c7;
            color: #92400e;
        }
        .row-subject-avg td {
            background-color: #e2e8f0;
            font-weight: bold;
            border-top: 1.5px solid #0f172a;
        }
        .row-subject-rate td {
            background-color: #f1f5f9;
            font-size: 6pt;
            border-bottom: 1.5px solid #0f172a;
        }
        .notice-box {
            font-size: 5.8pt;
            color: #475569;
            font-style: italic;
            border-left: 3px solid #1e3a8a;
            padding-left: 6px;
            margin-top: 3px;
            margin-bottom: 6px;
        }
        .sign-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 4px;
        }
        .sign-table td {
            width: 33.33%;
            text-align: center;
            vertical-align: top;
            font-size: 6.8pt;
        }
        .sign-role {
            font-weight: bold;
            text-transform: uppercase;
            color: #1e3a8a;
            margin-bottom: 22px;
        }
    </style>
</head>
<body>

    <!-- En-tête officiel bilingue MINESEC -->
    <table class="header-table">
        <tr>
            {{-- Partie Gauche (Francophone) : Délégation Régionale et Départementale sur deux lignes distinctes --}}
            <td class="header-left">
                <strong>RÉPUBLIQUE DU CAMEROUN</strong><br>
                <em>Paix – Travail – Patrie</em><br>
                {{ $etablissement->ministere_tutelle ?? 'MINISTÈRE DES ENSEIGNEMENTS SECONDAIRES' }}<br>
                @if(!empty($etablissement->delegation_regionale))
                    {{ mb_strtoupper($etablissement->delegation_regionale, 'UTF-8') }}<br>
                @endif
                @if(!empty($etablissement->delegation_departementale))
                    {{ mb_strtoupper($etablissement->delegation_departementale, 'UTF-8') }}<br>
                @endif
                @if(!empty($etablissement->arrondissement))
                    {{ \Illuminate\Support\Str::startsWith(mb_strtoupper($etablissement->arrondissement, 'UTF-8'), 'ARRONDISSEMENT') ? mb_strtoupper($etablissement->arrondissement, 'UTF-8') : 'ARRONDISSEMENT DE ' . mb_strtoupper($etablissement->arrondissement, 'UTF-8') }}
                @endif
            </td>

            {{-- Partie Centrale : Logo officiel centré --}}
            <td class="header-center">
                @php
                    $logoSrc = ($etablissement && method_exists($etablissement, 'getEffectiveLogoBase64'))
                        ? $etablissement->getEffectiveLogoBase64()
                        : (file_exists(public_path('logo.png')) ? 'data:image/png;base64,' . base64_encode(file_get_contents(public_path('logo.png'))) : null);
                @endphp
                @if($logoSrc)
                    <img src="{{ $logoSrc }}" alt="Logo" style="max-height: 48px; max-width: 70px; object-fit: contain; margin-bottom: 1px;">
                @else
                    <div style="font-size: 14pt; font-weight: bold; color: #1e3a8a; letter-spacing: 2px;">{{ $etablissement->sigle ?? 'SYGES' }}</div>
                @endif
            </td>

            {{-- Partie Droite (Anglophone) : Regional et Divisional Delegations sur deux lignes distinctes --}}
            <td class="header-right">
                <strong>REPUBLIC OF CAMEROON</strong><br>
                <em>Peace – Work – Fatherland</em><br>
                {{ $etablissement->ministere_tutelle_en ?? 'MINISTRY OF SECONDARY EDUCATION' }}<br>
                @if(!empty($etablissement->delegation_regionale_en) || !empty($etablissement->delegation_regionale))
                    {{ mb_strtoupper($etablissement->delegation_regionale_en ?? $etablissement->delegation_regionale, 'UTF-8') }}<br>
                @endif
                @if(!empty($etablissement->delegation_departementale_en) || !empty($etablissement->delegation_departementale))
                    {{ mb_strtoupper($etablissement->delegation_departementale_en ?? $etablissement->delegation_departementale, 'UTF-8') }}<br>
                @endif
                @if(!empty($etablissement->arrondissement_en) || !empty($etablissement->arrondissement))
                    @php
                        $arrEn = $etablissement->arrondissement_en ?? $etablissement->arrondissement;
                        $arrEnUpper = mb_strtoupper($arrEn, 'UTF-8');
                    @endphp
                    {{ \Illuminate\Support\Str::contains($arrEnUpper, 'SUB-DIVISION') ? $arrEnUpper : $arrEnUpper . ' SUB-DIVISION' }}
                @endif
            </td>
        </tr>
    </table>

    {{-- Nom de l'établissement centré sur une seule ligne (comme sur les autres états officiels) --}}
    <div class="school-header-box">
        <div class="school-name">{{ $etablissement->nom ?? 'ÉTABLISSEMENT SCOLAIRE' }}</div>
        @if(!empty($etablissement->devise))
            <div class="school-motto">Devise : {{ $etablissement->devise }}</div>
        @endif
    </div>

    <!-- Bannière Officielle du Grand Babillard / État de Remplissage -->
    <div class="banner-box">
        <div class="banner-title">
            État de Remplissage des Notes & Babillard de Classe &bull; {{ $classe->nom }}
        </div>
        <div class="banner-meta">
            Session : <strong>{{ $titrePeriode }}</strong> &bull; Année Scolaire : <strong>{{ $sessionAnnee ?? ($anneeScolaire->session ?? ($anneeScolaire->code ?? $anneeScolaire->nom)) }}</strong> &bull; Édition : {{ $dateGeneration }}
        </div>
    </div>

    <!-- Synthèse & Indicateurs de Complétude -->
    <table class="stats-table">
        <tr>
            <td>
                <div class="stats-label">Effectif Classé</div>
                <div class="stats-val">{{ $stats['nb_eleves'] }} élèves</div>
            </td>
            <td>
                <div class="stats-label">Matières Enseignées</div>
                <div class="stats-val">{{ $stats['nb_matieres'] }}</div>
            </td>
            <td>
                <div class="stats-label">Taux de Remplissage</div>
                <div class="stats-val" style="color: {{ $stats['taux_remplissage'] >= 90 ? '#15803d' : ($stats['taux_remplissage'] >= 75 ? '#d97706' : '#dc2626') }};">
                    {{ $stats['taux_remplissage'] }} %
                </div>
            </td>
            <td>
                <div class="stats-label">Notes Saisies / Attendues</div>
                <div class="stats-val">{{ $stats['notes_saisies'] }} / {{ $stats['notes_attendues'] }}</div>
            </td>
            <td>
                <div class="stats-label">Moyenne Générale Classe</div>
                <div class="stats-val">{{ number_format($stats['moyenne_classe'], 2, ',', ' ') }} / 20</div>
            </td>
            <td>
                {{-- Libellé explicite sans entité incompatible DomPDF pour éviter le point d'interrogation (?) --}}
                <div class="stats-label">Taux Réussite (Moyenne >= 10/20)</div>
                <div class="stats-val" style="color: {{ $stats['taux_reussite'] >= 50 ? '#15803d' : '#dc2626' }};">
                    {{ $stats['taux_reussite'] }} % ({{ $stats['nb_admis'] }} admis)
                </div>
            </td>
            <td>
                <div class="stats-label">Fourchette Notes (Min / Max)</div>
                <div class="stats-val">{{ number_format($stats['min_moyenne'], 2, ',', ' ') }} &bull; {{ number_format($stats['max_moyenne'], 2, ',', ' ') }}</div>
            </td>
        </tr>
    </table>

    <!-- Matrice Croisée : Élèves (lignes) x Matières (colonnes) -->
    <table class="matrix-table">
        <thead>
            <tr>
                <th style="width: 2.2%;">N°</th>
                <th style="width: 6.5%;">Matricule</th>
                <th style="width: 19%; text-align: left; padding-left: 3px;">Nom et Prénoms de l'Élève</th>
                <th style="width: 2.3%;">Sexe</th>
                @foreach($attributions as $attr)
                    <th title="{{ $attr->matiere?->nom }}">
                        {{ $attr->matiere?->code ?? substr($attr->matiere?->nom, 0, 4) }}
                    </th>
                @endforeach
                <th style="width: 4.8%;">Total</th>
                <th style="width: 4.8%;">Moy./20</th>
                <th style="width: 3.2%;">Rang</th>
                <th style="width: 5.5%;">Mention</th>
            </tr>
            {{-- Ligne des Coefficients Officiels (fond clair contrasté et texte gras pour lisibilité maximale) --}}
            <tr>
                <th class="sub-th" colspan="4">Coefficients Officiels</th>
                @foreach($attributions as $attr)
                    <th class="sub-th">c:{{ (float) ($attr->coefficient_officiel ?? ($attr->matiere?->coefficient_defaut ?? 1)) }}</th>
                @endforeach
                <th class="sub-th">/{{ $stats['total_coeff_classe'] }}</th>
                <th class="sub-th">/20</th>
                <th class="sub-th">-</th>
                <th class="sub-th">-</th>
            </tr>
        </thead>
        <tbody>
            @forelse($eleves as $el)
                <tr>
                    <td class="cell-num">{{ $el['numero'] }}</td>
                    <td class="cell-mat">{{ $el['matricule'] }}</td>
                    <td class="cell-nom">{{ $el['nom_complet'] }}</td>
                    <td class="cell-sexe">{{ $el['sexe'] }}</td>
                    @foreach($attributions as $attr)
                        @php
                            $noteData = $el['notes'][$attr->id] ?? null;
                        @endphp
                        <td class="cell-note">
                            @if($noteData && $noteData['valeur'] !== null)
                                @if($noteData['presence'] === 'P')
                                    <span class="{{ $noteData['valeur'] >= 10 ? 'note-ok' : 'note-fail' }}">
                                        {{ number_format($noteData['valeur'], 1, ',', '') }}
                                    </span>
                                @else
                                    <span class="note-abs">ABS</span>
                                @endif
                            @else
                                <span class="note-missing">—</span>
                            @endif
                        </td>
                    @endforeach
                    <td class="cell-total">{{ $el['total_points'] > 0 ? number_format($el['total_points'], 1, ',', ' ') : '-' }}</td>
                    <td class="cell-moy">
                        @if($el['moyenne'] !== null)
                            <span class="{{ $el['moyenne'] >= 10 ? 'note-ok' : 'note-fail' }}">
                                {{ number_format($el['moyenne'], 2, ',', ' ') }}
                            </span>
                        @else
                            -
                        @endif
                    </td>
                    <td class="cell-rang {{ ($el['rang'] ?? 99) === 1 ? 'rank-first' : '' }}">
                        {{ $el['rang'] ? $el['rang'] . ($el['rang'] === 1 ? 'er' : 'e') : '-' }}
                    </td>
                    <td class="cell-mention">
                        {{ $el['mention'] ?? '-' }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="{{ 8 + count($attributions) }}" style="text-align: center; padding: 15px; color: #64748b;">
                        Aucun élève inscrit ou aucune donnée disponible pour cette classe.
                    </td>
                </tr>
            @endforelse

            <!-- Ligne de synthèse : Moyennes par discipline -->
            @if(count($eleves) > 0)
                <tr class="row-subject-avg">
                    <td colspan="4" style="text-align: right; padding-right: 6px; text-transform: uppercase;">Moyenne Matière /20</td>
                    @foreach($attributions as $attr)
                        @php
                            $avgMat = $stats['matieres_stats'][$attr->id]['moyenne'] ?? null;
                        @endphp
                        <td class="cell-note" style="font-weight: bold;">
                            {{ $avgMat !== null ? number_format($avgMat, 1, ',', '') : '-' }}
                        </td>
                    @endforeach
                    <td class="cell-total">{{ number_format($stats['moyenne_classe'] * $stats['total_coeff_classe'], 1, ',', ' ') }}</td>
                    <td class="cell-moy">{{ number_format($stats['moyenne_classe'], 2, ',', ' ') }}</td>
                    <td colspan="2" style="text-align: center; font-size: 6pt;">Classe</td>
                </tr>
                <!-- Ligne de synthèse : Taux de saisie par discipline -->
                <tr class="row-subject-rate">
                    <td colspan="4" style="text-align: right; padding-right: 6px; text-transform: uppercase; color: #475569;">Taux Saisie Notes</td>
                    @foreach($attributions as $attr)
                        @php
                            $rateMat = $stats['matieres_stats'][$attr->id]['taux'] ?? 0;
                            $countSaisiesMat = $stats['matieres_stats'][$attr->id]['saisies'] ?? 0;
                        @endphp
                        <td class="cell-note" style="color: {{ $rateMat >= 100 ? '#15803d' : ($rateMat >= 80 ? '#d97706' : '#dc2626') }}; font-weight: bold;" title="{{ $countSaisiesMat }}/{{ $stats['nb_eleves'] }} élèves">
                            {{ $rateMat }}%
                        </td>
                    @endforeach
                    <td colspan="4" style="text-align: center; font-weight: bold; color: {{ $stats['taux_remplissage'] >= 90 ? '#15803d' : '#dc2626' }};">
                        Global : {{ $stats['taux_remplissage'] }}%
                    </td>
                </tr>
            @endif
        </tbody>
    </table>

    <!-- Note d'affichage pour élèves et professeurs -->
    <div class="notice-box">
        <strong>Avis d'affichage public :</strong> Ce tableau récapitulatif est affiché pour permettre à chaque élève de contrôler contradictoirement ses notes avant la génération définitive des bulletins scolaires. Tout élève observant une divergence, une omission ou une anomalie doit immédiatement la signaler au Professeur de la matière ou au Censeur/Directeur des Études dans un délai de 48 heures.
    </div>

    <!-- Signatures Officielles -->
    <table class="sign-table">
        <tr>
            <td>
                <div class="sign-role">Le Professeur Principal</div>
                <div style="font-size: 6pt; color: #64748b;">(Nom, Visa & Date)</div>
            </td>
            <td>
                <div class="sign-role">Le Censeur / Directeur des Études</div>
                <div style="font-size: 6pt; color: #64748b;">(Vérification des saisies & Visa)</div>
            </td>
            <td>
                <div class="sign-role">Le Chef d'Établissement</div>
                <div style="font-size: 6pt; color: #64748b;">(Approbation pour tirage des bulletins)</div>
            </td>
        </tr>
    </table>

</body>
</html>
