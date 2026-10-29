<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Bulletin Séquentiel - {{ $eleve['nom_complet'] }} - {{ $evaluation['nom'] }}</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 6mm 7mm 6mm 7mm;
        }
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 7.2pt;
            color: #1a1a1a;
            line-height: 1.12;
            margin: 0;
            padding: 0;
        }
        .header-table {
            width: 100%;
            border-collapse: collapse;
            text-align: center;
            margin-bottom: 2px;
        }
        .header-table td {
            vertical-align: top;
            padding: 0 3px;
        }
        .country-title {
            font-size: 6.8pt;
            font-weight: bold;
            text-transform: uppercase;
        }
        .motto {
            font-size: 5.6pt;
            font-style: italic;
        }
        .delegation {
            font-size: 5.6pt;
            text-transform: uppercase;
        }
        .school-name {
            font-size: 7.8pt;
            font-weight: bold;
            color: #1e3a8a;
            margin-top: 1px;
            text-transform: uppercase;
            white-space: nowrap;
        }
        .school-contact {
            font-size: 5.4pt;
            color: #475569;
            margin-top: 1px;
        }
        .school-year {
            font-size: 6.5pt;
            font-weight: bold;
            color: #0f172a;
            margin-top: 1px;
            text-transform: uppercase;
        }
        .logo-img {
            max-width: 65px;
            max-height: 65px;
            object-fit: contain;
        }
        .title-banner {
            background-color: #1e3a8a;
            border: 1px solid #172554;
            border-radius: 2.5px;
            text-align: center;
            padding: 2.5px 0;
            margin: 2px 0 3px 0;
        }
        .title-banner h1 {
            font-size: 9.5pt;
            margin: 0;
            color: #ffffff;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: bold;
        }
        .title-banner .sub {
            font-size: 6.8pt;
            color: #ffffff;
            font-weight: bold;
            margin-top: 1px;
        }
        .student-info-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 3px;
            background-color: #f8fafc;
            border: 0.8px solid #94a3b8;
        }
        .student-info-table td {
            padding: 1.5px 4px;
            font-size: 7pt;
        }
        .student-info-label {
            font-weight: bold;
            color: #334155;
            width: 15%;
        }
        .student-info-val {
            color: #0f172a;
            font-weight: 600;
        }
        .photo-cell {
            width: 44px;
            height: 48px;
            max-width: 44px;
            max-height: 48px;
            text-align: center;
            vertical-align: middle;
            border: 0.8px dashed #94a3b8;
            padding: 1px;
            background: #ffffff;
            overflow: hidden;
        }
        .grades-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 3px;
            font-size: 7pt;
        }
        .grades-table th, .grades-table td {
            border: 0.6px solid #64748b;
            padding: 1.8px 3px;
        }
        .grades-table th {
            background-color: #1e3a8a;
            color: #ffffff;
            font-weight: bold;
            text-align: center;
            font-size: 6.4pt;
            text-transform: uppercase;
        }
        .col-n-coef-header {
            background-color: #172554 !important;
            border-left: 1px solid #93c5fd !important;
            border-right: 1px solid #93c5fd !important;
        }
        .col-n-coef {
            background-color: #e0f2fe !important;
            color: #0369a1 !important;
            font-weight: bold !important;
            border-left: 1px solid #93c5fd !important;
            border-right: 1px solid #93c5fd !important;
        }
        .group-header-row {
            background-color: #e2e8f0;
            font-weight: bold;
            color: #0f172a;
            text-transform: uppercase;
            font-size: 6.5pt;
            border-top: 0.8px solid #94a3b8;
            border-bottom: 0.8px solid #94a3b8;
        }
        .group-avg-badge {
            background-color: #1e3a8a;
            color: #ffffff;
            padding: 1px 5px;
            border-radius: 2px;
            font-weight: bold;
            font-size: 6pt;
            margin-left: 8px;
            display: inline-block;
            text-transform: none;
            letter-spacing: 0.2px;
        }
        .total-general-row td {
            border-top: 1.5px solid #1e3a8a !important;
            border-bottom: 1.5px solid #1e3a8a !important;
            padding: 2px 3px !important;
            background-color: #f1f5f9;
        }
        .total-general-label {
            background-color: #1e3a8a !important;
            color: #ffffff !important;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            padding-left: 5px !important;
            font-size: 6.8pt;
        }
        .total-general-points {
            background-color: #bae6fd !important;
            color: #0369a1 !important;
            font-weight: bold !important;
            font-size: 7.5pt !important;
            border-left: 1.2px solid #0284c7 !important;
            border-right: 1.2px solid #0284c7 !important;
        }
        .total-general-moyenne {
            background-color: #1e3a8a !important;
            color: #ffffff !important;
            font-weight: bold;
            font-size: 7.2pt !important;
            text-align: center;
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
            font-size: 6pt;
            padding: 0.8px 2.5px;
            border-radius: 1.5px;
            border: 0.5px solid currentColor;
        }
        .cote-CTBA { color: #065f46; background-color: #d1fae5; }
        .cote-CBA { color: #1e40af; background-color: #dbeafe; }
        .cote-CMA { color: #92400e; background-color: #fef3c7; }
        .cote-CNA { color: #991b1b; background-color: #fee2e2; }

        /* Grilles Footer compactes */
        .footer-grid {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 2px;
        }
        .footer-grid td {
            vertical-align: top;
            padding: 0 1.5px;
        }
        .footer-box {
            border: 0.8px solid #64748b;
            border-radius: 2.5px;
            padding: 2px 3px;
            background: #ffffff;
            min-height: 64px;
        }
        .box-title {
            font-size: 6.2pt;
            font-weight: bold;
            text-transform: uppercase;
            color: #1e3a8a;
            border-bottom: 0.6px solid #cbd5e1;
            padding-bottom: 1px;
            margin-bottom: 1.5px;
            text-align: center;
        }
        .mini-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 6pt;
        }
        .mini-table td, .mini-table th {
            padding: 0.8px 1.5px;
        }

        .visa-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1.5px;
            text-align: center;
            font-size: 6.5pt;
        }
        .visa-table td {
            width: 33.33%;
            vertical-align: top;
            border: 0.8px solid #94a3b8;
            padding: 2.5px;
            height: 44px;
            background: #ffffff;
        }
        .stamp-box {
            font-size: 5.5pt;
            color: #64748b;
            font-style: italic;
            margin-top: 16px;
        }
        .bottom-print-info {
            font-size: 5.2pt;
            color: #64748b;
            text-align: right;
            margin-top: 1px;
        }
    </style>
</head>
<body>

    @php
        $rawBp = $classe['etablissement']['boite_postale'] ?? '---';
        $cleanBp = trim((string) preg_replace('/^(b\.?p\.?|p\.?o\.?\s*box)\s*:?\s*/i', '', trim((string) $rawBp)));
        $displayBp = ($cleanBp !== '' && $cleanBp !== '---') ? $cleanBp : '---';

        $dispEnseignant = $disposition_enseignant ?? request('disposition_enseignant', 'sous_matiere');
    @endphp

    {{-- En-tête Officiel MINESEC Bilingue --}}
    <table class="header-table">
        <tr>
            {{-- Partie Gauche (Francophone) --}}
            <td style="width: 38%; text-align: center;">
                <div class="country-title">RÉPUBLIQUE DU CAMEROUN</div>
                <div class="motto">Paix - Travail - Patrie</div>
                <div class="delegation">MINISTÈRE DES ENSEIGNEMENTS SECONDAIRES</div>
                <div class="delegation">{{ $classe['etablissement']['delegation_regionale'] ?? 'DÉLÉGATION RÉGIONALE DES ENSEIGNEMENTS SECONDAIRES' }}</div>
                <div class="school-name">{{ $classe['etablissement']['nom'] }}</div>
                <div class="school-contact">BP : {{ $displayBp }} | Tél : {{ $classe['etablissement']['telephone'] ?? '---' }}</div>
                <div class="school-year">ANNÉE SCOLAIRE {{ preg_replace('/^\s*Ann[ée]e\s+scolaire\s*[:\-]?\s*/ui', '', $annee_scolaire['nom']) }}</div>
            </td>

            {{-- Partie Centrale (Armoiries / Logo Établissement + Devise) --}}
            <td style="width: 24%; text-align: center; vertical-align: middle;">
                @php
                    $logoPath = !empty($classe['etablissement']['logo_url']) ? public_path(ltrim($classe['etablissement']['logo_url'], '/\\')) : null;
                    if (!$logoPath || !file_exists($logoPath)) {
                        $logoPath = !empty($classe['etablissement']['logo']) ? public_path(ltrim($classe['etablissement']['logo'], '/\\')) : null;
                    }
                    if (!$logoPath || !file_exists($logoPath)) {
                        $logoPath = file_exists(public_path('logo.png')) ? public_path('logo.png') : (file_exists(public_path('logo.jpg')) ? public_path('logo.jpg') : null);
                    }
                    $logoSrc = null;
                    if ($logoPath && file_exists($logoPath)) {
                        $logoExt = strtolower(pathinfo($logoPath, PATHINFO_EXTENSION)) ?: 'png';
                        $logoSrc = 'data:image/' . ($logoExt === 'jpg' ? 'jpeg' : $logoExt) . ';base64,' . base64_encode(file_get_contents($logoPath));
                    }
                @endphp
                @if($logoSrc)
                    <img src="{{ $logoSrc }}" class="logo-img" alt="Logo">
                @else
                    <div style="font-weight: bold; font-size: 11pt; color: #1e3a8a; margin-top: 1px;">
                        {{ $classe['etablissement']['sigle'] ?? 'SYGES' }}
                    </div>
                @endif
                <div style="font-size: 5.8pt; font-style: italic; margin-top: 1px; color: #334155;">
                    {{ $classe['etablissement']['devise'] ?? 'Discipline - Travail - Succès' }}
                </div>
            </td>

            {{-- Partie Droite (Anglophone) --}}
            <td style="width: 38%; text-align: center;">
                <div class="country-title">REPUBLIC OF CAMEROON</div>
                <div class="motto">Peace - Work - Fatherland</div>
                <div class="delegation">MINISTRY OF SECONDARY EDUCATION</div>
                <div class="delegation">{{ $classe['etablissement']['delegation_regionale_en'] ?? 'REGIONAL DELEGATION OF SECONDARY EDUCATION' }}</div>
                <div class="school-name">{{ $classe['etablissement']['nom_en'] ?: $classe['etablissement']['nom'] }}</div>
                <div class="school-contact">PO. Box : {{ $displayBp }} | Phone : {{ $classe['etablissement']['telephone'] ?? '---' }}</div>
                <div class="school-year">SCHOOL YEAR {{ preg_replace('/^\s*Ann[ée]e\s+scolaire\s*[:\-]?\s*/ui', '', $annee_scolaire['nom']) }}</div>
            </td>
        </tr>
    </table>

    {{-- Bannière du Titre du Bulletin Séquentiel --}}
    <div class="title-banner">
        <h1>BULLETIN DE NOTES / REPORT CARD &mdash; {{ $evaluation['nom'] }}</h1>
        <div class="sub">
            CLASSE DE {{ $classe['nom'] }} ({{ $classe['niveau'] }}) &bull; {{ $periode['nom'] }}
        </div>
    </div>

    {{-- Identification de l'Élève --}}
    <table class="student-info-table">
        <tr>
            <td class="student-info-label">Nom et Prénom(s) :</td>
            <td class="student-info-val" style="font-size: 8pt;">{{ $eleve['nom_complet'] }}</td>
            <td class="student-info-label">Classe :</td>
            <td class="student-info-val">{{ $classe['nom'] }} ({{ $classe['niveau'] }})</td>
            <td rowspan="4" class="photo-cell">
                @php
                    $photoPath = !empty($eleve['photo_url']) ? public_path(ltrim($eleve['photo_url'], '/\\')) : null;
                    $photoSrc = null;
                    if ($photoPath && file_exists($photoPath)) {
                        $photoExt = pathinfo($photoPath, PATHINFO_EXTENSION) ?: 'jpeg';
                        $photoSrc = 'data:image/' . $photoExt . ';base64,' . base64_encode(file_get_contents($photoPath));
                    }
                @endphp
                @if($photoSrc)
                    <img src="{{ $photoSrc }}" style="width: 42px; height: 46px; object-fit: cover; display: block; margin: 0 auto;" alt="Photo">
                @else
                    <div style="font-size: 5.5pt; color: #64748b; font-weight: bold; padding: 12px 0;">PHOTO</div>
                @endif
            </td>
        </tr>
        <tr>
            <td class="student-info-label">Matricule :</td>
            <td class="student-info-val">
                {{ $eleve['matricule'] }}
                @php
                    $matNat = !empty($eleve['matricule_national']) ? $eleve['matricule_national'] : (!empty($eleve['matricule_ministere']) ? $eleve['matricule_ministere'] : null);
                @endphp
                @if($matNat)
                    <span style="font-size: 6.4pt; color: #1e293b; margin-left: 6px;">| Mat. Nat. : <strong style="color: #0f172a;">{{ $matNat }}</strong></span>
                @endif
            </td>
            <td class="student-info-label">Effectif :</td>
            <td class="student-info-val">{{ $classe['effectif'] }} élèves</td>
        </tr>
        <tr>
            <td class="student-info-label">Né(e) le :</td>
            <td class="student-info-val">{{ $eleve['date_naissance'] ?? '---' }} à {{ $eleve['lieu_naissance'] ?? '---' }}</td>
            <td class="student-info-label">Prof. Principal :</td>
            <td class="student-info-val">{{ $classe['professeur_principal'] ?? 'Non désigné' }}</td>
        </tr>
        <tr>
            <td class="student-info-label">Sexe :</td>
            <td class="student-info-val">{{ $eleve['genre'] ?? ($eleve['sexe'] ?? '---') }}</td>
            <td class="student-info-label">Redoublant(e) :</td>
            <td class="student-info-val">{{ !empty($eleve['is_redoublant']) ? 'Oui' : 'Non' }}</td>
        </tr>
    </table>

    {{-- Tableau des Notes Séquentielles --}}
    <table class="grades-table">
        <thead>
            <tr>
                <th style="{{ $mode_bulletin === 'avec_competences' ? 'width: 24%;' : 'width: 32%;' }}">Matières & Enseignant</th>
                @if($mode_bulletin === 'avec_competences')
                    <th style="width: 23%;">Compétences Évaluées (APC)</th>
                @endif
                <th style="width: 7%;">Note/20</th>
                <th style="width: 4%;">Coef</th>
                <th style="width: 7%;" class="col-n-coef-header">Total</th>
                <th style="width: 5%;">Rg</th>
                <th style="width: 5%;">Cote</th>
                <th style="width: 8%;">[Min - Max]</th>
                <th style="width: 6%;">Moy. Cl.</th>
                <th style="width: 14%;">Appréciation</th>
            </tr>
        </thead>
        <tbody>
            @php
                $groupes = collect($eleve['lignes_matieres'])->groupBy('groupe_nom')->sortBy(function ($lignes) {
                    return $lignes->first()['groupe_ordre'] ?? 99;
                });
            @endphp

            @foreach($groupes as $nomGroupe => $lignes)
                @php
                    $ptsGroupe = $lignes->sum('points');
                    $coefGroupe = $lignes->sum('coefficient');
                    $moyGroupe = $coefGroupe > 0 ? round($ptsGroupe / $coefGroupe, 2) : 0;
                @endphp
                <tr class="group-header-row">
                    <td colspan="{{ $mode_bulletin === 'avec_competences' ? '10' : '9' }}" style="padding: 1.5px 5px;">
                        <span>{{ $nomGroupe }}</span> &mdash; <span class="group-avg-badge">Moy. Groupe : {{ number_format($moyGroupe, 2) }}/20</span>
                    </td>
                </tr>

                @foreach($lignes as $ligne)
                    <tr class="{{ $ligne['est_eliminatoire'] ? 'elim-warning' : '' }}">
                        <td class="text-left font-bold">
                            @if($dispEnseignant === 'en_ligne')
                                {{ $ligne['matiere_nom'] }}
                                @if(!empty($ligne['enseignant_nom']) && $ligne['enseignant_nom'] !== 'Non assigné')
                                    <span style="font-size: 5.5pt; font-weight: normal; color: #475569;">({{ $ligne['enseignant_nom'] }})</span>
                                @endif
                            @elseif($dispEnseignant === 'masquer')
                                {{ $ligne['matiere_nom'] }}
                            @else
                                {{ $ligne['matiere_nom'] }}
                                <div style="font-size: 5.5pt; font-weight: normal; color: #475569; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 140px;" title="{{ $ligne['enseignant_nom_complet'] ?? $ligne['enseignant_nom'] }}">
                                    {{ $ligne['enseignant_nom'] }}
                                </div>
                            @endif
                        </td>

                        @if($mode_bulletin === 'avec_competences')
                            <td class="text-left" style="font-size: 5.8pt; color: #334155;">
                                {{ \Illuminate\Support\Str::limit($ligne['competences'] ?: 'Non spécifiée', 65, '...') }}
                            </td>
                        @endif

                        <td class="text-center font-bold" style="{{ ($ligne['note'] ?? 0) < 10 && $ligne['note'] !== null ? 'color: #dc2626;' : '' }}">
                            @if($ligne['statut_presence'] === 'absent_justifie')
                                <span style="font-size: 6pt; color: #d97706;">Abs.J</span>
                            @elseif($ligne['statut_presence'] === 'absent_non_justifie')
                                <span style="font-size: 6pt; color: #dc2626;">Abs.NJ</span>
                            @elseif($ligne['statut_presence'] === 'non_evalue')
                                <span style="font-size: 6pt; color: #64748b;">NE</span>
                            @elseif($ligne['note'] !== null)
                                {{ number_format($ligne['note'], 2) }}
                            @else
                                -
                            @endif
                        </td>
                        <td class="text-center">{{ $ligne['coefficient'] }}</td>
                        <td class="text-center font-bold col-n-coef">{{ number_format($ligne['points'], 2) }}</td>
                        <td class="text-center font-bold">{{ $ligne['rang_matiere'] ?? '-' }}</td>
                        <td class="text-center">
                            @if($ligne['cote_apc'])
                                <span class="cote-badge cote-{{ $ligne['cote_apc'] }}">{{ $ligne['cote_apc'] }}</span>
                            @else
                                -
                            @endif
                        </td>
                        <td class="text-center" style="font-size: 5.8pt; color: #475569;">
                            [{{ number_format($ligne['note_min'] ?? 0, 1) }} - {{ number_format($ligne['note_max'] ?? 0, 1) }}]
                        </td>
                        <td class="text-center" style="font-size: 6pt;">
                            {{ number_format($ligne['moyenne_classe'] ?? 0, 2) }}
                        </td>
                        <td class="text-left" style="font-size: 5.8pt;">
                            {{ !empty($ligne['appreciation']) ? $ligne['appreciation'] : \App\Services\BulletinCalculationService::genererAppreciationParDefaut($ligne['note'] ?? null) }}
                        </td>
                    </tr>
                @endforeach
            @endforeach

            {{-- Ligne Totale Récapitulative Séquentielle (Conforme TOTAUX / TOTALS) --}}
            <tr class="total-general-row">
                <td class="text-left font-bold total-general-label" colspan="{{ $mode_bulletin === 'avec_competences' ? '2' : '1' }}">
                    TOTAUX / TOTALS
                </td>
                <td class="text-center font-bold" style="background-color: #e2e8f0;">-</td>
                <td class="text-center font-bold" style="background-color: #e2e8f0;">{{ $eleve['total_coefficients'] }}</td>
                <td class="text-center font-bold total-general-points">{{ number_format($eleve['total_points'], 2) }}</td>
                <td class="text-center font-bold" style="background-color: #e2e8f0; color: #1e3a8a;">{{ $eleve['rang_general'] }}</td>
                <td class="text-center font-bold" style="background-color: #e2e8f0;">
                    <span class="cote-badge cote-{{ $eleve['cote_globale'] }}">{{ $eleve['cote_globale'] }}</span>
                </td>
                <td class="text-center total-general-moyenne" colspan="3">
                    MOY. SÉQ. / SEQ. AV. : <strong>{{ number_format($eleve['moyenne_generale'], 2) }} / 20</strong>
                </td>
            </tr>
        </tbody>
    </table>

    {{-- Panneau Inférieur 1 : 4 Cadres Horizontaux Officiels (Résultats, Profil Classe, Conduite, Rappels Moyennes & Rangs) --}}
    <table class="footer-grid">
        <tr>
            {{-- Cadre 1 : RÉSULTAT SÉQ. DE L'ÉLÈVE --}}
            <td style="width: 25%;">
                <div class="footer-box">
                    <div class="box-title">RÉSULTAT SÉQ. DE L'ÉLÈVE</div>
                    <table class="mini-table">
                        <tr><td>Total des points :</td><td class="text-right font-bold">{{ number_format($eleve['total_points'], 2) }}</td></tr>
                        <tr><td>Total coefficients :</td><td class="text-right font-bold">{{ $eleve['total_coefficients'] }}</td></tr>
                        <tr>
                            <td>Moyenne séq. :</td>
                            <td class="text-right font-bold" style="color: #1e3a8a; font-size: 7.2pt;">
                                {{ number_format($eleve['moyenne_generale'], 2) }} / 20
                            </td>
                        </tr>
                        <tr>
                            <td>Rang séquentiel :</td>
                            <td class="text-right font-bold" style="color: #1e3a8a; font-size: 7.2pt;">
                                @if(($eleve['rang_general'] ?? '') === 'NC')
                                    <span style="color: #dc2626;">NC</span>
                                @else
                                    {{ $eleve['rang_general'] }} / {{ $classe['effectif'] }}
                                @endif
                            </td>
                        </tr>
                    </table>
                </div>
            </td>

            {{-- Cadre 2 : PROFIL SÉQ. DE LA CLASSE --}}
            <td style="width: 25%;">
                <div class="footer-box">
                    <div class="box-title">PROFIL SÉQ. DE LA CLASSE</div>
                    <table class="mini-table">
                        <tr><td>Moyenne premier :</td><td class="text-right font-bold" style="color: #1e3a8a;">{{ number_format($stats_classe['moyenne_max'] ?? 0, 2) }}</td></tr>
                        <tr><td>Moyenne dernier :</td><td class="text-right font-bold" style="color: #dc2626;">{{ number_format($stats_classe['moyenne_min'] ?? 0, 2) }}</td></tr>
                        <tr><td>Nombre de moy. :</td><td class="text-right font-bold">{{ $stats_classe['nombre_moyennes'] ?? 0 }} / {{ $stats_classe['effectif'] ?? 0 }}</td></tr>
                        <tr><td>Taux de réussite :</td><td class="text-right font-bold" style="color: #047857;">{{ number_format($stats_classe['taux_reussite'] ?? 0, 1) }} %</td></tr>
                        <tr><td>Moy. gén. classe :</td><td class="text-right font-bold">{{ number_format($stats_classe['moyenne_classe'] ?? 0, 2) }}</td></tr>
                    </table>
                </div>
            </td>

            {{-- Cadre 3 : CONDUITE SÉQUENTIELLE --}}
            <td style="width: 25%;">
                <div class="footer-box">
                    <div class="box-title">CONDUITE SÉQUENTIELLE</div>
                    <table class="mini-table">
                        <tr><td>Absences NJ :</td><td class="text-right font-bold" style="color: {{ ($eleve['discipline']['absences_non_justifiees'] ?? 0) > 0 ? '#dc2626' : '#1e293b' }};">{{ $eleve['discipline']['absences_non_justifiees'] ?? 0 }} h</td></tr>
                        <tr><td>Absences J :</td><td class="text-right">{{ $eleve['discipline']['absences_justifiees'] ?? 0 }} h</td></tr>
                        <tr><td>Consignes :</td><td class="text-right">{{ ($eleve['discipline']['consignes'] ?? 0) > 0 ? ($eleve['discipline']['consignes'].' h') : 'NON / No' }}</td></tr>
                        <tr><td>Exclusion :</td><td class="text-right font-bold" style="color: {{ ($eleve['discipline']['exclusions'] ?? 0) > 0 ? '#dc2626' : '#1e293b' }};">{{ ($eleve['discipline']['exclusions'] ?? 0) > 0 ? ($eleve['discipline']['exclusions'].' j') : 'NON / No' }}</td></tr>
                        <tr><td>Avert. / Blâme :</td><td class="text-right">{{ ($eleve['discipline']['avertissements'] ?? 0) > 0 ? $eleve['discipline']['avertissements'] : 'NON' }} / {{ ($eleve['discipline']['blames'] ?? 0) > 0 ? $eleve['discipline']['blames'] : 'NON' }}</td></tr>
                    </table>
                </div>
            </td>

            {{-- Cadre 4 : RAPPELS MOYENNES ET RANGS --}}
            <td style="width: 25%;">
                <div class="footer-box">
                    <div class="box-title">RAPPELS MOY. ET RANGS</div>
                    <table class="mini-table" style="text-align: center;">
                        <tr style="font-weight: bold; background: #f1f5f9; color: #1e3a8a;">
                            <td style="text-align: left;">Évaluation</td>
                            <td>Moyenne</td>
                            <td>Rang</td>
                        </tr>
                        @if(!empty($eleve['rappels_evaluations']))
                            @foreach($eleve['rappels_evaluations'] as $re)
                                <tr>
                                    <td style="text-align: left; font-weight: 600;">{{ $re['nom'] }}</td>
                                    <td class="font-bold">{{ $re['moyenne'] !== null ? number_format($re['moyenne'], 2) : '-' }}</td>
                                    <td>{{ $re['rang'] ?? '-' }}</td>
                                </tr>
                            @endforeach
                        @else
                            <tr>
                                <td style="text-align: left; font-weight: 600;">{{ $evaluation['nom'] }}</td>
                                <td class="font-bold">{{ number_format($eleve['moyenne_generale'], 2) }}</td>
                                <td>{{ $eleve['rang_general'] }}</td>
                            </tr>
                        @endif
                    </table>
                </div>
            </td>
        </tr>
    </table>

    {{-- Panneau Inférieur 2 : 3 Blocs (Observations Travail, Décision Conseil & Observations Chef d'Établissement) --}}
    <table class="visa-table">
        <tr>
            {{-- Bloc 1 : OBSERVATIONS DU TRAVAIL & VISA PARENT --}}
            <td style="text-align: left; vertical-align: top;">
                <div style="font-weight: bold; color: #1e3a8a; text-transform: uppercase; font-size: 6.2pt; border-bottom: 0.5px solid #94a3b8; padding-bottom: 1px; margin-bottom: 2px;">
                    OBSERVATIONS DU TRAVAIL
                </div>
                <div style="font-size: 5.8pt; color: #0f172a; line-height: 1.35; margin-bottom: 2px;">
                    <span>Cote APC : <strong class="cote-badge cote-{{ $eleve['cote_globale'] }}">{{ $eleve['cote_globale'] }}</strong></span> &bull;
                    <span>T.H. : <strong>{{ !empty($eleve['tableau_honneur']) ? 'OUI' : 'NON' }}</strong></span><br>
                    <span>Encouragements : <strong>{{ !empty($eleve['encouragements']) ? 'OUI' : 'NON' }}</strong></span> &bull;
                    <span>Félicitations : <strong>{{ !empty($eleve['felicitations']) ? 'OUI' : 'NON' }}</strong></span>
                </div>
                <div style="font-weight: bold; font-size: 5.8pt; color: #1e293b; border-top: 0.5px dashed #cbd5e1; padding-top: 2px; margin-top: 2px;">
                    OBS. DU PARENT / TUTEUR :
                </div>
                <div class="stamp-box" style="margin-top: 2px; text-align: left; color: #94a3b8; font-size: 5.2pt;">
                    Date & signature :
                </div>
            </td>

            {{-- Bloc 2 : DÉCISION DU CONSEIL DE CLASSE & MATIÈRES FAIBLES --}}
            <td style="text-align: left; vertical-align: top;">
                <div style="font-weight: bold; color: #0f766e; text-transform: uppercase; font-size: 6.2pt; border-bottom: 0.5px solid #94a3b8; padding-bottom: 1px; margin-bottom: 2px;">
                    DÉCISION DU CONSEIL DE CLASSE
                </div>
                <div style="font-size: 5.8pt; color: #1e293b; margin-bottom: 2px;">
                    <strong>Un effort s'impose en :</strong>
                    @if(!empty($eleve['matieres_faibles']))
                        <div style="color: #b91c1c; font-weight: bold; margin-top: 1px; line-height: 1.1;">
                            {{ implode(', ', array_slice($eleve['matieres_faibles'], 0, 4)) }}
                        </div>
                    @else
                        <div style="color: #047857; font-weight: bold; margin-top: 1px;">
                            Aucune matière &lt; 10/20 (Félicitations)
                        </div>
                    @endif
                </div>
                <div style="font-size: 5.8pt; color: #1e293b; margin-top: 3px;">
                    <strong>Avis du conseil :</strong>
                    @if(!empty($eleve['discipline']['decision_conseil']))
                        <div style="font-style: italic; font-weight: 600; color: #0f172a; margin-top: 1px;">
                            {{ $eleve['discipline']['decision_conseil'] }}
                        </div>
                    @else
                        <div style="margin-top: 3px;">
                            <div style="border-bottom: 0.8px dotted #94a3b8; height: 10px;"></div>
                            <div style="border-bottom: 0.8px dotted #94a3b8; height: 10px;"></div>
                        </div>
                    @endif
                </div>
            </td>

            {{-- Bloc 3 : OBSERVATIONS DU CHEF D'ÉTABLISSEMENT & SCEAU OFFICIEL --}}
            <td style="text-align: center; vertical-align: top;">
                <div style="font-weight: bold; text-transform: uppercase; color: #1e3a8a; font-size: 6.5pt; border-bottom: 0.5px solid #94a3b8; padding-bottom: 1px; margin-bottom: 2px;">
                    {{ mb_strtoupper($classe['etablissement']['titre_chef'] ?? 'LE CHEF D\'ÉTABLISSEMENT', 'UTF-8') }} / {{ mb_strtoupper($classe['etablissement']['titre_chef_en'] ?? 'THE PRINCIPAL', 'UTF-8') }}
                </div>
                <div class="stamp-box" style="margin-top: 10px; color: #94a3b8; font-size: 5.2pt;">
                    Sceau officiel & signature
                </div>
                <div style="font-size: 5.6pt; color: #1e293b; margin-top: 14px;">
                    Fait à {{ $classe['etablissement']['ville'] ?? ($classe['etablissement']['institution']['ville'] ?? 'Fongo-Tongo') }}, le {{ date('d-m-Y') }}
                </div>
            </td>
        </tr>
    </table>

    <div class="bottom-print-info">
        Imprimé le {{ date('d-m-Y') }} à {{ date('H:i:s') }} &bull; SYGES
    </div>

</body>
</html>
