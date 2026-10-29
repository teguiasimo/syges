<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Bulletin Annuel - {{ $eleve['nom_complet'] }} - {{ $annee_scolaire['nom'] }}</title>
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
        }
        .box-title {
            font-size: 6.4pt;
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
            font-size: 6.2pt;
        }
        .mini-table td, .mini-table th {
            padding: 0.8px 1.5px;
        }
        .discipline-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 5.8pt;
        }
        .discipline-table th, .discipline-table td {
            border: 0.5px solid #cbd5e1;
            padding: 0.8px 1.5px;
            text-align: center;
        }
        .discipline-table th {
            background-color: #f1f5f9;
            color: #1e3a8a;
            font-weight: bold;
        }
        .discipline-table td.col-label {
            text-align: left;
            font-weight: 600;
            color: #334155;
        }

        .decision-box {
            border: 1.2px solid #0f766e;
            background-color: #f0fdfa;
            border-radius: 2.5px;
            padding: 2.5px;
            text-align: center;
        }
        .decision-badge {
            font-size: 7.8pt;
            font-weight: bold;
            color: #0f766e;
            padding: 2px 4px;
            background: #ccfbf1;
            border: 0.8px solid #5eead4;
            display: block;
            border-radius: 2px;
            text-transform: uppercase;
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
            margin-top: 18px;
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

    {{-- Bannière du Titre du Bulletin Annuel --}}
    <div class="title-banner">
        <h1>BULLETIN ANNUEL DE NOTES / ANNUAL REPORT CARD</h1>
        <div class="sub">
            CLASSE DE {{ $classe['nom'] }} ({{ $classe['niveau'] }}) &bull; BILAN DE FIN D'ANNÉE
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

    {{-- Tableau des Notes Annuelles avec Abréviations standardisées --}}
    <table class="grades-table">
        <thead>
            <tr>
                <th style="{{ $mode_bulletin === 'avec_competences' ? 'width: 22%;' : 'width: 29%;' }}">Matières & Enseignant</th>
                @if($mode_bulletin === 'avec_competences')
                    <th style="width: 19%;">Compétences APC</th>
                @endif
                @foreach($periodes as $p)
                    <th style="width: 5%;">{{ $p['code'] }}</th>
                @endforeach
                <th style="width: 6%;">Moy.</th>
                <th style="width: 4%;">Coef</th>
                <th style="width: 6%;" class="col-n-coef-header">Total</th>
                <th style="width: 4.5%;">Rg</th>
                <th style="width: 5%;">Cote</th>
                <th style="width: 7%;">[Min - Max]</th>
                <th style="width: 5.5%;">Moy. Cl.</th>
                <th style="width: 13%;">Appréciation</th>
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
                    <td colspan="{{ ($mode_bulletin === 'avec_competences' ? 9 : 8) + count($periodes) }}" style="padding: 1.5px 5px;">
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
                                <div style="font-size: 5.5pt; font-weight: normal; color: #475569; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 135px;" title="{{ $ligne['enseignant_nom_complet'] ?? $ligne['enseignant_nom'] }}">
                                    {{ $ligne['enseignant_nom'] }}
                                </div>
                            @endif
                        </td>

                        @if($mode_bulletin === 'avec_competences')
                            <td class="text-left" style="font-size: 5.8pt; color: #334155;">
                                {{ \Illuminate\Support\Str::limit($ligne['competences'] ?: 'Acquisition standard', 60, '...') }}
                            </td>
                        @endif

                        @foreach($periodes as $p)
                            @php $noteTrim = $ligne['notes_trimestres'][$p['id']] ?? null; @endphp
                            <td class="text-center" style="{{ ($noteTrim ?? 0) < 10 && $noteTrim !== null ? 'color: #dc2626;' : '' }}">
                                {{ $noteTrim !== null ? number_format($noteTrim, 2) : '-' }}
                            </td>
                        @endforeach

                        <td class="text-center font-bold" style="{{ ($ligne['moyenne'] ?? 0) < 10 && $ligne['moyenne'] !== null ? 'color: #dc2626;' : '' }}">
                            {{ $ligne['moyenne'] !== null ? number_format($ligne['moyenne'], 2) : '-' }}
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
                            {{ !empty($ligne['appreciation']) ? $ligne['appreciation'] : \App\Services\BulletinCalculationService::genererAppreciationParDefaut($ligne['moyenne'] ?? null) }}
                        </td>
                    </tr>
                @endforeach
            @endforeach

            {{-- Ligne Totale Récapitulative Annuelle --}}
            <tr class="total-general-row">
                <td class="text-left font-bold total-general-label" colspan="{{ ($mode_bulletin === 'avec_competences' ? 2 : 1) + count($periodes) }}">
                    TOTAL GÉNÉRAL ANNUEL
                </td>
                <td class="text-center font-bold" style="background-color: #e2e8f0;">-</td>
                <td class="text-center font-bold" style="background-color: #e2e8f0;">{{ $eleve['total_coefficients'] }}</td>
                <td class="text-center font-bold total-general-points">{{ number_format($eleve['total_points'], 2) }}</td>
                <td class="text-center font-bold" style="background-color: #e2e8f0; color: #1e3a8a;">{{ $eleve['rang_general'] }}</td>
                <td class="text-center font-bold" style="background-color: #e2e8f0;">
                    <span class="cote-badge cote-{{ $eleve['cote_globale'] }}">{{ $eleve['cote_globale'] }}</span>
                </td>
                <td class="text-center total-general-moyenne" colspan="3">
                    MOYENNE ANNUELLE (M.G.A.) : <strong>{{ number_format($eleve['moyenne_generale'], 2) }} / 20</strong>
                </td>
            </tr>
        </tbody>
    </table>

    {{-- Panneau Inférieur 1 : Discipline (5 colonnes), Travail de l'élève & Profil de la classe --}}
    <table class="footer-grid">
        <tr>
            {{-- 1. Cadre Discipline (format officiel 5 colonnes : Discipline | Trim. 1 | Trim. 2 | Trim. 3 | Total) --}}
            <td style="width: 38%;">
                <div class="footer-box">
                    <div class="box-title">DISCIPLINE</div>
                    @php
                        $matrice = $eleve['discipline_matrice']['par_periode'] ?? [];
                        $totDisc = $eleve['discipline_matrice']['total'] ?? $eleve['discipline'];
                    @endphp
                    <table class="discipline-table">
                        <thead>
                            <tr>
                                <th style="text-align: left; width: 36%;">Discipline</th>
                                @foreach($periodes as $p)
                                    <th style="width: 16%;">{{ $p['code'] }}</th>
                                @endforeach
                                <th style="width: 16%;">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td class="col-label">Abs. non J. (h)</td>
                                @foreach($periodes as $p)
                                    <td>{{ $matrice[$p['id']]['absences_non_justifiees'] ?? 0 }}</td>
                                @endforeach
                                <td class="font-bold" style="color: #dc2626;">{{ $totDisc['absences_non_justifiees'] ?? 0 }}</td>
                            </tr>
                            <tr>
                                <td class="col-label">Abs. just. (h)</td>
                                @foreach($periodes as $p)
                                    <td>{{ $matrice[$p['id']]['absences_justifiees'] ?? 0 }}</td>
                                @endforeach
                                <td class="font-bold">{{ $totDisc['absences_justifiees'] ?? 0 }}</td>
                            </tr>
                            <tr>
                                <td class="col-label">Retards (h)</td>
                                @foreach($periodes as $p)
                                    <td>{{ $matrice[$p['id']]['retards'] ?? 0 }}</td>
                                @endforeach
                                <td class="font-bold">{{ $totDisc['retards'] ?? 0 }}</td>
                            </tr>
                            <tr>
                                <td class="col-label">Consignes (h)</td>
                                @foreach($periodes as $p)
                                    <td>{{ $matrice[$p['id']]['consignes'] ?? 0 }}</td>
                                @endforeach
                                <td class="font-bold">{{ $totDisc['consignes'] ?? 0 }}</td>
                            </tr>
                            <tr>
                                <td class="col-label">Avert.</td>
                                @foreach($periodes as $p)
                                    <td>{{ $matrice[$p['id']]['avertissements'] ?? 0 }}</td>
                                @endforeach
                                <td class="font-bold">{{ $totDisc['avertissements'] ?? 0 }}</td>
                            </tr>
                            <tr>
                                <td class="col-label">Blâme</td>
                                @foreach($periodes as $p)
                                    <td>{{ $matrice[$p['id']]['blames'] ?? 0 }}</td>
                                @endforeach
                                <td class="font-bold">{{ $totDisc['blames'] ?? 0 }}</td>
                            </tr>
                            <tr>
                                <td class="col-label">Excl. (j)</td>
                                @foreach($periodes as $p)
                                    <td>{{ $matrice[$p['id']]['exclusions'] ?? 0 }}</td>
                                @endforeach
                                <td class="font-bold" style="color: #dc2626;">{{ $totDisc['exclusions'] ?? 0 }}</td>
                            </tr>
                            <tr>
                                <td class="col-label">CD</td>
                                @foreach($periodes as $p)
                                    <td>{{ (!empty($matrice[$p['id']]['exclusions']) && $matrice[$p['id']]['exclusions'] >= 3) ? 'Oui' : '-' }}</td>
                                @endforeach
                                <td class="font-bold">{{ (!empty($totDisc['exclusions']) && $totDisc['exclusions'] >= 3) ? 'Oui' : '-' }}</td>
                            </tr>
                        </tbody>
                    </table>
                    <div style="font-size: 5.2pt; color: #475569; margin-top: 1px;">
                        &bull; Moy. Annuelle = SOMME(Moy. Trim.) / {{ count($periodes) ?: 3 }}
                    </div>
                </div>
            </td>

            {{-- 2. Cadre Travail de l'élève (Rappels des moyennes trimestrielles + MGA + Rang) --}}
            <td style="width: 32%;">
                <div class="footer-box">
                    <div class="box-title">TRAVAIL DE L'ÉLÈVE</div>
                    <table class="mini-table">
                        @foreach($periodes as $p)
                            @php
                                $elTrimData = $eleve['moyennes_trimestres'][$p['id']] ?? null;
                            @endphp
                            <tr>
                                <td style="color: #475569;">{{ $p['nom'] }} :</td>
                                <td class="text-right font-bold">
                                    {{ $elTrimData && $elTrimData['moyenne'] !== null ? number_format($elTrimData['moyenne'], 2) : '-' }} / 20
                                </td>
                                <td style="padding-left: 4px; color: #64748b; font-size: 5.8pt;">
                                    (Rg: {{ $elTrimData['rang'] ?? '-' }})
                                </td>
                            </tr>
                        @endforeach
                    </table>
                    <div style="border-top: 0.8px solid #cbd5e1; margin-top: 2px; padding-top: 2px;">
                        <table class="mini-table">
                            <tr>
                                <td style="font-size: 6.8pt; font-weight: bold; color: #1e3a8a;">Moyenne annuelle :</td>
                                <td class="text-right font-bold" style="font-size: 8pt; color: #1e3a8a;">
                                    {{ number_format($eleve['moyenne_generale'], 2) }} / 20
                                </td>
                            </tr>
                            <tr>
                                <td style="font-size: 6.8pt; font-weight: bold; color: #1e3a8a;">Rang annuel :</td>
                                <td class="text-right font-bold" style="font-size: 8pt; color: #1e3a8a;">
                                    @if(($eleve['rang_general'] ?? '') === 'NC')
                                        <span style="color: #dc2626;">NC</span>
                                    @else
                                        {{ $eleve['rang_general'] }} / {{ $classe['effectif'] }}
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <td style="color: #475569;">Cote Globale APC :</td>
                                <td class="text-right font-bold">
                                    <span class="cote-badge cote-{{ $eleve['cote_globale'] }}">{{ $eleve['cote_globale'] }}</span>
                                </td>
                            </tr>
                            <tr>
                                <td style="color: #475569;">Distinction :</td>
                                <td class="text-right font-bold" style="color: {{ ($eleve['rang_general'] ?? '') === 'NC' ? '#64748b' : '#047857' }}; font-size: 5.8pt;">
                                    {{ $eleve['mention'] ?? 'Passable' }}
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
            </td>

            {{-- 3. Cadre Profil de la classe (avec Écart-type et Taux de réussite) --}}
            <td style="width: 30%;">
                <div class="footer-box">
                    <div class="box-title">PROFIL DE LA CLASSE</div>
                    <table class="mini-table">
                        <tr>
                            <td style="color: #475569;">Moy. premier :</td>
                            <td class="text-right font-bold" style="color: #1e3a8a;">{{ number_format($stats_classe['moyenne_max'] ?? 0, 2) }}</td>
                        </tr>
                        <tr>
                            <td style="color: #475569;">Moy. gen. classe :</td>
                            <td class="text-right font-bold">{{ number_format($stats_classe['moyenne_classe'] ?? 0, 2) }}</td>
                        </tr>
                        <tr>
                            <td style="color: #475569;">Moy. dernier :</td>
                            <td class="text-right font-bold" style="color: #dc2626;">{{ number_format($stats_classe['moyenne_min'] ?? 0, 2) }}</td>
                        </tr>
                        <tr>
                            <td style="color: #475569;">Taux R. :</td>
                            <td class="text-right font-bold" style="color: #047857;">{{ number_format($stats_classe['taux_reussite'] ?? 0, 1) }} %</td>
                        </tr>
                        <tr>
                            <td style="color: #475569;">Nbr Moy. :</td>
                            <td class="text-right font-bold">{{ $stats_classe['nombre_moyennes'] ?? 0 }} / {{ $stats_classe['effectif'] ?? 0 }}</td>
                        </tr>
                        <tr>
                            <td style="color: #475569;">Ecart-type :</td>
                            <td class="text-right font-bold">{{ number_format($stats_classe['ecart_type'] ?? 0, 2) }}</td>
                        </tr>
                    </table>
                </div>
            </td>
        </tr>
    </table>

    {{-- Panneau Inférieur 2 : Décision du conseil, Appréciations du travail & Visas Officiels --}}
    <table class="visa-table">
        <tr>
            {{-- Bloc 1 : Décision du conseil de classe --}}
            <td style="text-align: center; vertical-align: top;">
                <div style="font-weight: bold; color: #0f766e; text-transform: uppercase; font-size: 6.5pt; border-bottom: 0.5px solid #94a3b8; padding-bottom: 1px; margin-bottom: 2px;">
                    DÉCISION DU CONSEIL DE CLASSE
                </div>
                @if(!empty($eleve['discipline']['decision_conseil']))
                    <div class="decision-badge" style="margin: 3px auto;">
                        {{ $eleve['discipline']['decision_conseil'] }}
                    </div>
                @elseif(!empty($eleve['decision_finale']))
                    <div class="decision-badge" style="margin: 3px auto;">
                        {{ $eleve['decision_finale'] }}
                    </div>
                @else
                    <div style="margin-top: 4px;">
                        <div style="border-bottom: 0.8px dotted #94a3b8; height: 10px;"></div>
                        <div style="border-bottom: 0.8px dotted #94a3b8; height: 10px;"></div>
                    </div>
                @endif
                <div style="font-size: 5.2pt; color: #475569; margin-top: 2px;">
                    Admis(e) si MGA &ge; 10 &bull; Redoublement si 8.5 &le; MGA &lt; 10
                </div>
            </td>

            {{-- Bloc 2 : Appréciations du travail & Visa Professeur Principal --}}
            <td style="text-align: center; vertical-align: top;">
                <div style="font-weight: bold; color: #1e3a8a; text-transform: uppercase; font-size: 6.5pt; border-bottom: 0.5px solid #94a3b8; padding-bottom: 1px; margin-bottom: 2px;">
                    APPRÉCIATIONS DU TRAVAIL
                </div>
                <div style="font-size: 5.8pt; color: #334155; margin-bottom: 2px;">
                    Cote APC : <strong class="cote-badge cote-{{ $eleve['cote_globale'] }}">{{ $eleve['cote_globale'] }}</strong> &bull; T.H. : <strong>{{ !empty($eleve['tableau_honneur']) ? 'Oui' : '-' }}</strong>
                </div>
                <div style="font-weight: bold; font-size: 6.2pt; color: #0f172a; margin-top: 1px;">
                    Visa Professeur Principal
                </div>
                <div class="stamp-box" style="margin-top: 8px; color: #94a3b8; font-size: 5.2pt;">Signature et avis</div>
            </td>

            {{-- Bloc 3 : Visa du Chef d'Établissement --}}
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
