<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Bulletin Séquentiel - {{ $eleve['nom_complet'] }} - {{ $evaluation['nom'] }}</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 7mm 8mm 7mm 8mm;
        }
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 8pt;
            color: #1a1a1a;
            line-height: 1.15;
            margin: 0;
            padding: 0;
        }
        .header-table {
            width: 100%;
            border-collapse: collapse;
            text-align: center;
            margin-bottom: 3px;
        }
        .header-table td {
            vertical-align: top;
            padding: 0 4px;
        }
        .country-title {
            font-size: 7pt;
            font-weight: bold;
            text-transform: uppercase;
        }
        .motto {
            font-size: 6pt;
            font-style: italic;
        }
        .delegation {
            font-size: 6pt;
            text-transform: uppercase;
        }
        .school-name {
            font-size: 9pt;
            font-weight: bold;
            color: #1e3a8a;
            margin-top: 1px;
        }
        .logo-img {
            max-width: 50px;
            max-height: 50px;
            object-fit: contain;
        }
        .title-banner {
            background-color: #f1f5f9;
            border: 1.5px solid #1e3a8a;
            border-radius: 3px;
            text-align: center;
            padding: 2.5px 0;
            margin: 2px 0 4px 0;
        }
        .title-banner h1 {
            font-size: 10pt;
            margin: 0;
            color: #1e3a8a;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .title-banner .sub {
            font-size: 7.5pt;
            color: #475569;
            font-weight: bold;
            margin-top: 1px;
        }
        .student-info-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 4px;
            background-color: #fafafa;
            border: 1px solid #cbd5e1;
        }
        .student-info-table td {
            padding: 2px 5px;
            font-size: 7.5pt;
        }
        .student-info-label {
            font-weight: bold;
            color: #334155;
            width: 16%;
        }
        .student-info-val {
            color: #0f172a;
            font-weight: 600;
        }
        .photo-cell {
            width: 45px;
            text-align: center;
            border: 1px dashed #cbd5e1;
            padding: 2px;
            background: #fff;
        }
        .grades-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 4px;
            font-size: 7.5pt;
        }
        .grades-table th, .grades-table td {
            border: 0.8px solid #64748b;
            padding: 2px 3.5px;
        }
        .grades-table th {
            background-color: #1e3a8a;
            color: #ffffff;
            font-weight: bold;
            text-align: center;
            font-size: 6.8pt;
            text-transform: uppercase;
        }
        .group-header-row {
            background-color: #e2e8f0;
            font-weight: bold;
            color: #0f172a;
            text-transform: uppercase;
            font-size: 7pt;
        }
        .group-subtotal-row {
            background-color: #f8fafc;
            font-weight: bold;
            font-size: 6.8pt;
            border-top: 1px solid #475569;
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .text-left { text-align: left; }
        .font-bold { font-weight: bold; }
        .elim-warning {
            color: #dc2626;
            font-weight: bold;
            background-color: #fef2f2;
        }
        .cote-badge {
            font-weight: bold;
            font-size: 6.5pt;
            padding: 1px 3px;
            border-radius: 2px;
        }
        .cote-CTBA { color: #065f46; }
        .cote-CBA { color: #1e40af; }
        .cote-CMA { color: #92400e; }
        .cote-CNA { color: #991b1b; }
        .footer-grid {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 3px;
        }
        .footer-grid td {
            vertical-align: top;
            padding: 0 2.5px;
        }
        .footer-box {
            border: 1px solid #64748b;
            border-radius: 3px;
            padding: 3px 4px;
            min-height: 72px;
        }
        .box-title {
            font-size: 7pt;
            font-weight: bold;
            text-transform: uppercase;
            color: #1e3a8a;
            border-bottom: 1px solid #cbd5e1;
            padding-bottom: 1.5px;
            margin-bottom: 2px;
            text-align: center;
        }
        .mini-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 7pt;
        }
        .mini-table td {
            padding: 1px 2px;
        }
        .visa-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 3px;
            text-align: center;
            font-size: 7.2pt;
        }
        .visa-table td {
            width: 33.33%;
            vertical-align: top;
            border: 1px solid #94a3b8;
            padding: 4px;
            height: 52px;
        }
        .stamp-box {
            font-size: 6pt;
            color: #64748b;
            font-style: italic;
            margin-top: 24px;
        }
    </style>
</head>
<body>

    {{-- En-tête Officiel MINESEC Bilingue --}}
    <table class="header-table">
        <tr>
            <td style="width: 36%;">
                <div class="country-title">RÉPUBLIQUE DU CAMEROUN</div>
                <div class="motto">Paix - Travail - Patrie</div>
                <div class="delegation">MINISTÈRE DES ENSEIGNEMENTS SECONDAIRES</div>
                {{-- Tutelle Administrative Régionale et Départementale (FR) --}}
                <div class="delegation">{{ $classe['etablissement']['delegation_regionale'] ?? 'DÉLÉGATION RÉGIONALE DES ENSEIGNEMENTS SECONDAIRES' }}</div>
                <div class="delegation">{{ $classe['etablissement']['delegation_departementale'] ?? 'DÉLÉGATION DÉPARTEMENTALE' }}</div>
                <div class="school-name">{{ $classe['etablissement']['nom'] }}</div>
            </td>
            <td style="width: 28%; text-align: center;">
                @if(!empty($classe['etablissement']['logo_url']) && file_exists(public_path($classe['etablissement']['logo_url'])))
                    <img src="{{ public_path($classe['etablissement']['logo_url']) }}" class="logo-img" alt="Logo">
                @elseif(!empty($classe['etablissement']['logo']) && file_exists(public_path($classe['etablissement']['logo'])))
                    <img src="{{ public_path($classe['etablissement']['logo']) }}" class="logo-img" alt="Logo">
                @else
                    <div style="font-weight: bold; font-size: 13pt; color: #1e3a8a; margin-top: 4px;">
                        {{ $classe['etablissement']['sigle'] ?? 'SYGES' }}
                    </div>
                @endif
                <div style="font-size: 6.5pt; font-style: italic; margin-top: 1px;">
                    {{ $classe['etablissement']['devise'] ?? 'Discipline - Travail - Succès' }}
                </div>
                <div style="font-size: 6pt; color: #64748b;">
                    BP : {{ $classe['etablissement']['ville'] ?? $classe['etablissement']['boite_postale'] ?? '---' }} | Tél : {{ $classe['etablissement']['telephone'] ?? '---' }}
                </div>
            </td>
            <td style="width: 36%;">
                <div class="country-title">REPUBLIC OF CAMEROON</div>
                <div class="motto">Peace - Work - Fatherland</div>
                <div class="delegation">MINISTRY OF SECONDARY EDUCATION</div>
                {{-- Tutelle Administrative Régionale et Départementale (EN) --}}
                <div class="delegation">{{ $classe['etablissement']['delegation_regionale_en'] ?? 'REGIONAL DELEGATION OF SECONDARY EDUCATION' }}</div>
                <div class="delegation">{{ $classe['etablissement']['delegation_departementale_en'] ?? 'DIVISIONAL DELEGATION' }}</div>
                <div class="school-name">{{ $classe['etablissement']['nom_en'] ?: $classe['etablissement']['nom'] }}</div>
            </td>
        </tr>
    </table>

    {{-- Bannière du Titre du Bulletin --}}
    <div class="title-banner">
        <h1>BULLETIN D'ÉVALUATION SÉQUENTIELLE</h1>
        <div class="sub">
            {{ $evaluation['nom'] }} ({{ $evaluation['code'] }}) &bull; {{ $periode['nom'] }} &bull; ANNÉE SCOLAIRE {{ $annee_scolaire['nom'] }}
        </div>
    </div>

    {{-- Identification de l'Élève --}}
    <table class="student-info-table">
        <tr>
            <td class="student-info-label">Nom et Prénom(s) :</td>
            <td class="student-info-val" style="font-size: 8.5pt;">{{ $eleve['nom_complet'] }}</td>
            <td class="student-info-label">Classe :</td>
            <td class="student-info-val">{{ $classe['nom'] }} ({{ $classe['niveau'] }})</td>
            <td rowspan="3" class="photo-cell">
                @if(!empty($eleve['photo_url']))
                    <img src="{{ public_path($eleve['photo_url']) }}" style="max-width: 40px; max-height: 40px;" alt="Photo">
                @else
                    <span style="font-size: 6pt; color: #94a3b8;">PHOTO</span>
                @endif
            </td>
        </tr>
        <tr>
            <td class="student-info-label">Matricule :</td>
            <td class="student-info-val">{{ $eleve['matricule'] }} @if($eleve['matricule_ministere']) / MIN: {{ $eleve['matricule_ministere'] }} @endif</td>
            <td class="student-info-label">Effectif :</td>
            <td class="student-info-val">{{ $classe['effectif'] }} élèves</td>
        </tr>
        <tr>
            <td class="student-info-label">Né(e) le :</td>
            <td class="student-info-val">{{ $eleve['date_naissance'] ?? '---' }} à {{ $eleve['lieu_naissance'] ?? '---' }} ({{ $eleve['genre'] }})</td>
            <td class="student-info-label">Prof. Principal :</td>
            <td class="student-info-val">{{ $classe['professeur_principal'] ?? 'Non désigné' }} ({{ $eleve['is_redoublant'] ? 'Redoublant(e)' : 'Non Redoublant(e)' }})</td>
        </tr>
    </table>

    {{-- Tableau des Notes Séquentielles --}}
    <table class="grades-table">
        <thead>
            <tr>
                <th style="{{ $mode_bulletin === 'avec_competences' ? 'width: 24%;' : 'width: 32%;' }}">Matières & Enseignant</th>
                @if($mode_bulletin === 'avec_competences')
                    <th style="width: 24%;">Compétences Évaluées (APC)</th>
                @endif
                <th style="width: 7%;">Note/20</th>
                <th style="width: 4%;">Coef</th>
                <th style="width: 7%;">N &times; Coef</th>
                <th style="width: 6%;">Rang</th>
                <th style="width: 5%;">Cote</th>
                <th style="width: 8%;">[Min - Max]</th>
                <th style="width: 6%;">Moy. Cl.</th>
                <th style="width: 14%;">Appréciation</th>
            </tr>
        </thead>
        <tbody>
            @php
                $groupes = collect($eleve['lignes_matieres'])->groupBy('groupe_nom');
                $cumulPoints = 0;
                $cumulCoefs = 0;
            @endphp

            @foreach($groupes as $nomGroupe => $lignes)
                @php
                    $ptsGroupe = $lignes->sum('points');
                    $coefGroupe = $lignes->sum('coefficient');
                    $moyGroupe = $coefGroupe > 0 ? round($ptsGroupe / $coefGroupe, 2) : 0;
                    $cumulPoints += $ptsGroupe;
                    $cumulCoefs += $coefGroupe;
                @endphp
                <tr class="group-header-row">
                    <td colspan="{{ $mode_bulletin === 'avec_competences' ? '10' : '9' }}" style="padding-left: 6px;">
                        {{ $nomGroupe }} &mdash; <span style="font-size: 6.5pt; font-weight: normal;">Moyenne Groupe : {{ number_format($moyGroupe, 2) }}/20</span>
                    </td>
                </tr>

                @foreach($lignes as $ligne)
                    <tr class="{{ $ligne['est_eliminatoire'] ? 'elim-warning' : '' }}">
                        <td class="text-left font-bold">
                            {{ $ligne['matiere_nom'] }}
                            <div style="font-size: 6pt; font-weight: normal; color: #475569;">
                                {{ $ligne['enseignant_nom'] }}
                            </div>
                        </td>

                        @if($mode_bulletin === 'avec_competences')
                            <td class="text-left" style="font-size: 6.5pt; color: #334155;">
                                {{ \Illuminate\Support\Str::limit($ligne['competences'] ?: 'Non spécifiée', 70, '...') }}
                            </td>
                        @endif

                        <td class="text-center font-bold" style="{{ ($ligne['note'] ?? 0) < 10 && $ligne['note'] !== null ? 'color: #dc2626;' : '' }}">
                            @if($ligne['statut_presence'] === 'absent_justifie')
                                <span style="font-size: 6.5pt; color: #d97706;">Abs.J</span>
                            @elseif($ligne['statut_presence'] === 'absent_non_justifie')
                                <span style="font-size: 6.5pt; color: #dc2626;">Abs.NJ</span>
                            @elseif($ligne['statut_presence'] === 'non_evalue')
                                <span style="font-size: 6.5pt; color: #64748b;">NE</span>
                            @elseif($ligne['note'] !== null)
                                {{ number_format($ligne['note'], 2) }}
                            @else
                                -
                            @endif
                        </td>
                        <td class="text-center">{{ $ligne['coefficient'] }}</td>
                        <td class="text-center font-bold">{{ number_format($ligne['points'], 2) }}</td>
                        <td class="text-center font-bold">{{ $ligne['rang_matiere'] ?? '-' }}</td>
                        <td class="text-center">
                            @if($ligne['cote_apc'])
                                <span class="cote-badge cote-{{ $ligne['cote_apc'] }}">{{ $ligne['cote_apc'] }}</span>
                            @else
                                -
                            @endif
                        </td>
                        <td class="text-center" style="font-size: 6.5pt; color: #475569;">
                            [{{ number_format($ligne['note_min'] ?? 0, 1) }} - {{ number_format($ligne['note_max'] ?? 0, 1) }}]
                        </td>
                        <td class="text-center" style="font-size: 6.5pt;">
                            {{ number_format($ligne['moyenne_classe'] ?? 0, 2) }}
                        </td>
                        <td class="text-left" style="font-size: 6.5pt;">
                            {{ $ligne['appreciation'] }}
                        </td>
                    </tr>
                @endforeach
            @endforeach

            {{-- Ligne Totale Récapitulative --}}
            <tr style="background-color: #f1f5f9; font-weight: bold; border-top: 1.5px solid #1e3a8a;">
                <td class="text-left font-bold" colspan="{{ $mode_bulletin === 'avec_competences' ? '2' : '1' }}">
                    TOTAL GÉNÉRAL SÉQUENTIEL
                </td>
                <td class="text-center font-bold">-</td>
                <td class="text-center font-bold">{{ $eleve['total_coefficients'] }}</td>
                <td class="text-center font-bold" style="color: #1e3a8a;">{{ number_format($eleve['total_points'], 2) }}</td>
                <td class="text-center font-bold" style="color: #1e3a8a;">{{ $eleve['rang_general'] }}</td>
                <td class="text-center font-bold cote-{{ $eleve['cote_globale'] }}">{{ $eleve['cote_globale'] }}</td>
                <td class="text-center" colspan="3" style="font-size: 7pt; color: #1e3a8a;">
                    MOYENNE SÉQUENCE : <strong>{{ number_format($eleve['moyenne_generale'], 2) }} / 20</strong>
                </td>
            </tr>
        </tbody>
    </table>

    {{-- Panneau Inférieur : Travail de l'élève & Profil de la classe --}}
    <table class="footer-grid">
        <tr>
            {{-- Synthèse Travail de l'élève --}}
            <td style="width: 50%;">
                <div class="footer-box">
                    <div class="box-title">RÉSULTATS DE L'ÉLÈVE (BILAN SÉQUENCE)</div>
                    <table class="mini-table">
                        <tr>
                            <td>Total des points :</td>
                            <td class="text-right font-bold">{{ number_format($eleve['total_points'], 2) }}</td>
                            <td style="padding-left: 10px;">Total Coefficients :</td>
                            <td class="text-right font-bold">{{ $eleve['total_coefficients'] }}</td>
                        </tr>
                        <tr>
                            <td>Moyenne Séquentielle :</td>
                            <td class="text-right font-bold" style="font-size: 8.5pt; color: #1e3a8a;">
                                {{ number_format($eleve['moyenne_generale'], 2) }} / 20
                            </td>
                            <td style="padding-left: 10px;">Rang Séquentiel :</td>
                            <td class="text-right font-bold" style="font-size: 8.5pt; color: #1e3a8a;">
                                {{ $eleve['rang_general'] }} / {{ $classe['effectif'] }}
                            </td>
                        </tr>
                        <tr>
                            <td>Cote Globale APC :</td>
                            <td class="text-right font-bold cote-{{ $eleve['cote_globale'] }}">
                                {{ $eleve['cote_globale'] }}
                            </td>
                            <td style="padding-left: 10px;">Distinction :</td>
                            <td class="text-right font-bold" style="color: #047857;">
                                {{ $eleve['mention'] ?? 'Aucune' }}
                            </td>
                        </tr>
                        @if($eleve['a_note_eliminatoire'])
                            <tr>
                                <td colspan="4" class="elim-warning text-center" style="font-size: 6.5pt; padding: 2px;">
                                    &bull; Distinction honorifique non accordée (Note éliminatoire présente)
                                </td>
                            </tr>
                        @endif
                    </table>
                </div>
            </td>

            {{-- Profil de la Classe --}}
            <td style="width: 50%;">
                <div class="footer-box">
                    <div class="box-title">PROFIL DE LA CLASSE (STATISTIQUES SÉQUENCE)</div>
                    <table class="mini-table">
                        <tr>
                            <td>Moyenne Générale Classe :</td>
                            <td class="text-right font-bold">{{ number_format($stats_classe['moyenne_classe'], 2) }} / 20</td>
                            <td style="padding-left: 10px;">Élèves ayant la moyenne :</td>
                            <td class="text-right font-bold">{{ $stats_classe['nombre_moyennes'] }} / {{ $stats_classe['effectif'] }}</td>
                        </tr>
                        <tr>
                            <td>Note minimale de classe :</td>
                            <td class="text-right font-bold" style="color: #dc2626;">{{ number_format($stats_classe['moyenne_min'], 2) }}</td>
                            <td style="padding-left: 10px;">Taux de réussite :</td>
                            <td class="text-right font-bold" style="color: #047857;">{{ number_format($stats_classe['taux_reussite'], 1) }} %</td>
                        </tr>
                        <tr>
                            <td>Note maximale de classe :</td>
                            <td class="text-right font-bold" style="color: #1e3a8a;">{{ number_format($stats_classe['moyenne_max'], 2) }}</td>
                            <td style="padding-left: 10px;">Écart (Max - Min) :</td>
                            <td class="text-right font-bold">{{ number_format($stats_classe['moyenne_max'] - $stats_classe['moyenne_min'], 2) }}</td>
                        </tr>
                    </table>
                </div>
            </td>
        </tr>
    </table>

    {{-- Visas et Signatures Officielles --}}
    <table class="visa-table">
        <tr>
            <td>
                <strong>Le Parent / Tuteur</strong>
                <div class="stamp-box">Date et signature</div>
            </td>
            <td>
                <strong>Le Professeur Principal</strong>
                <div class="stamp-box">Observations et visa</div>
            </td>
            <td>
                <strong>Le Chef d'Établissement</strong>
                <div class="stamp-box">Sceau officiel & signature</div>
            </td>
        </tr>
    </table>

</body>
</html>
