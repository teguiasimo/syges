<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Bulletin APC - {{ $eleve['nom_complet'] }} - {{ $periode['nom'] }}</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 8mm 8mm 8mm 8mm;
        }
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 8.5pt;
            color: #1a1a1a;
            line-height: 1.15;
            margin: 0;
            padding: 0;
        }
        .header-table {
            width: 100%;
            border-collapse: collapse;
            text-align: center;
            margin-bottom: 4px;
        }
        .header-table td {
            vertical-align: top;
            padding: 0 4px;
        }
        .country-title {
            font-size: 7.5pt;
            font-weight: bold;
            text-transform: uppercase;
        }
        .motto {
            font-size: 6.5pt;
            font-style: italic;
        }
        .delegation {
            font-size: 6.5pt;
            text-transform: uppercase;
        }
        .school-name {
            font-size: 8pt;
            font-weight: bold;
            color: #1e3a8a;
            margin-top: 2px;
            white-space: nowrap;
        }
        .logo-img {
            max-width: 72px;
            max-height: 72px;
            object-fit: contain;
        }
        .title-banner {
            background-color: #f1f5f9;
            border: 1.5px solid #1e3a8a;
            border-radius: 4px;
            text-align: center;
            padding: 3px 0;
            margin: 3px 0;
        }
        .title-banner h1 {
            font-size: 11pt;
            margin: 0;
            color: #1e3a8a;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .student-info-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 5px;
            background-color: #fafafa;
            border: 1px solid #cbd5e1;
        }
        .student-info-table td {
            padding: 2px 5px;
            font-size: 8pt;
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
            width: 50px;
            text-align: center;
            border: 1px dashed #cbd5e1;
            padding: 2px;
            background: #fff;
        }
        .grades-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 5px;
            font-size: 7.5pt;
        }
        .grades-table th, .grades-table td {
            border: 0.8px solid #64748b;
            padding: 2.5px 3px;
        }
        .grades-table th {
            background-color: #1e3a8a;
            color: #ffffff;
            font-weight: bold;
            text-align: center;
            font-size: 7pt;
            text-transform: uppercase;
        }
        .group-header-row {
            background-color: #e2e8f0;
            font-weight: bold;
            color: #0f172a;
            text-transform: uppercase;
            font-size: 7.5pt;
        }
        .group-subtotal-row {
            background-color: #f8fafc;
            font-weight: bold;
            font-size: 7pt;
            border-top: 1px solid #475569;
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .text-left { text-align: left; }
        .font-bold { font-weight: bold; }
        .cote-badge {
            display: inline-block;
            padding: 1px 3px;
            border-radius: 3px;
            font-weight: bold;
            font-size: 6.5pt;
        }
        .cote-ctba { background: #dcfce7; color: #15803d; }
        .cote-cba { background: #dbeafe; color: #1d4ed8; }
        .cote-cma { background: #fef9c3; color: #a16207; }
        .cote-cna { background: #fee2e2; color: #b91c1c; }
        .elim-warning {
            color: #dc2626;
            font-weight: bold;
            background-color: #fef2f2;
        }
        .footer-grid {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 4px;
        }
        .footer-grid td {
            vertical-align: top;
            padding: 0 3px;
        }
        .footer-box {
            border: 1px solid #64748b;
            border-radius: 3px;
            padding: 3px;
            min-height: 80px;
        }
        .box-title {
            font-size: 7.5pt;
            font-weight: bold;
            text-transform: uppercase;
            color: #1e3a8a;
            border-bottom: 1px solid #cbd5e1;
            padding-bottom: 2px;
            margin-bottom: 3px;
            text-align: center;
        }
        .mini-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 7pt;
        }
        .mini-table td {
            padding: 1.5px 1px;
        }
        .signatures-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 2px;
            text-align: center;
            font-size: 7.5pt;
        }
        .signatures-table td {
            padding: 10px 4px 2px 4px;
            width: 33.33%;
        }
        .signature-title {
            font-weight: bold;
            color: #1e3a8a;
            margin-bottom: 25px;
        }
        .signature-line {
            font-size: 7pt;
            color: #64748b;
            font-style: italic;
        }
    </style>
</head>
<body>
    {{-- En-tête Bilingue Officiel République du Cameroun --}}
    <table class="header-table">
        <tr>
            <td style="width: 38%;">
                <div class="country-title">RÉPUBLIQUE DU CAMEROUN</div>
                <div class="motto">Paix – Travail – Patrie</div>
                <div class="delegation">{{ $classe['etablissement']['delegation_regionale'] ?? 'DÉLÉGATION RÉGIONALE DU CENTRE' }}</div>
                <div class="delegation">{{ $classe['etablissement']['delegation_departementale'] ?? 'DÉLÉGATION DÉPARTEMENTALE DU MFOUNDI' }}</div>
                <div class="school-name">{{ $classe['etablissement']['nom'] }}</div>
                <div style="font-size: 6.5pt; color: #475569;">BP: {{ $classe['etablissement']['boite_postale'] ?? '---' }} | Tél: {{ $classe['etablissement']['telephone'] ?? '---' }}</div>
                <div class="school-year" style="font-size: 6.8pt; font-weight: bold; color: #1e3a8a; margin-top: 1px;">ANNÉE SCOLAIRE : {{ preg_replace('/^\s*Ann[ée]e\s+scolaire\s*[:\-]?\s*/ui', '', $annee_scolaire['nom']) }}</div>
            </td>
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
                    <div style="font-size: 20pt; font-weight: bold; color: #1e3a8a; line-height: 1;">★</div>
                @endif
                @if(!empty($classe['etablissement']['devise']))
                    <div style="font-size: 6pt; font-weight: bold; font-style: italic; color: #1e3a8a; margin-top: 2px;">
                        « {{ $classe['etablissement']['devise'] }} »
                    </div>
                @else
                    <div style="font-size: 6pt; font-weight: bold; color: #64748b; margin-top: 2px;">
                        MINESEC / MINEDUB
                    </div>
                @endif
            </td>
            <td style="width: 38%;">
                <div class="country-title">REPUBLIC OF CAMEROON</div>
                <div class="motto">Peace – Work – Fatherland</div>
                <div class="delegation">REGIONAL DELEGATION OF EDUCATION</div>
                <div class="delegation">DIVISIONAL DELEGATION</div>
                <div class="school-name">{{ $classe['etablissement']['nom_en'] ?: $classe['etablissement']['nom'] }}</div>
                <div style="font-size: 6.5pt; color: #475569;">Email: {{ $classe['etablissement']['email'] ?? '---' }}</div>
                <div class="school-year" style="font-size: 6.8pt; font-weight: bold; color: #1e3a8a; margin-top: 1px;">SCHOOL YEAR: {{ preg_replace('/^\s*Ann[ée]e\s+scolaire\s*[:\-]?\s*/ui', '', $annee_scolaire['nom']) }}</div>
            </td>
        </tr>
    </table>

    {{-- Bannière du Bulletin --}}
    <div class="title-banner">
        <h1>BULLETIN SCOLAIRE DU {{ mb_strtoupper($periode['nom']) }} — ANNÉE {{ $annee_scolaire['nom'] }}</h1>
        <div style="font-size: 7.5pt; font-weight: bold; color: #475569;">(NOUVELLES NORMES OFFICIELLES APC - APPROCHE PAR COMPÉTENCES)</div>
    </div>

    {{-- Identification de l'Élève --}}
    <table class="student-info-table">
        <tr>
            <td class="student-info-label">Nom et Prénom(s) :</td>
            <td class="student-info-val" style="font-size: 8.5pt;">{{ $eleve['nom_complet'] }}</td>
            <td class="student-info-label">Classe :</td>
            <td class="student-info-val">{{ $classe['nom'] }} ({{ $classe['niveau'] }})</td>
            <td rowspan="4" class="photo-cell">
                @php
                    $photoPath = !empty($eleve['photo_url']) ? public_path(ltrim($eleve['photo_url'], '/\\')) : null;
                @endphp
                @if($photoPath && file_exists($photoPath))
                    <img src="{{ $photoPath }}" style="max-width: 44px; max-height: 48px;" alt="Photo">
                @else
                    <div style="font-size: 6pt; color: #94a3b8; padding: 10px 0;">PHOTO</div>
                @endif
            </td>
        </tr>
        <tr>
            <td class="student-info-label">Matricule :</td>
            <td class="student-info-val">
                {{ $eleve['matricule'] }}
                @if(!empty($eleve['matricule_ministere']) || !empty($eleve['matricule_national']))
                    <span style="font-size: 6.8pt; color: #475569; margin-left: 6px;">| Mat. National : <strong style="color: #0f172a;">{{ $eleve['matricule_ministere'] ?? $eleve['matricule_national'] }}</strong></span>
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

    {{-- Tableau des Notes (Mode APC avec Compétences) --}}
    <table class="grades-table">
        <thead>
            <tr>
                <th style="width: 22%;">Matières & Enseignant</th>
                <th style="width: 26%;">Compétences Évaluées (APC)</th>
                @foreach($periode['evaluations'] as $ev)
                    <th style="width: 5%;">{{ $ev['code'] }}</th>
                @endforeach
                <th style="width: 5%;">Moy/20</th>
                <th style="width: 4%;">Coef</th>
                <th style="width: 5%;">Total</th>
                <th style="width: 5%;">Cote</th>
                <th style="width: 8%;">[Min - Max]</th>
                <th style="width: 20%;">Appréciations & Visa</th>
            </tr>
        </thead>
        <tbody>
            @foreach($eleve['groupes_matieres'] as $groupe)
                <tr class="group-header-row">
                    <td colspan="{{ 7 + count($periode['evaluations']) }}">
                        {{ $groupe['groupe_nom'] }}
                    </td>
                </tr>
                @foreach($groupe['matieres'] as $m)
                    <tr>
                        <td class="text-left font-bold">
                            {{ $m['matiere_nom'] }}
                            <div style="font-size: 6pt; font-weight: normal; color: #475569;">Prof: {{ $m['enseignant_nom'] }}</div>
                        </td>
                        <td class="text-left" style="font-size: 6.5pt; line-height: 1.1;">
                            {{ $m['competences'] ?: 'Maîtrise des acquis fondamentaux du programme séquentiel.' }}
                        </td>
                        @foreach($periode['evaluations'] as $ev)
                            <td class="text-center">
                                @php
                                    $nEv = $m['notes_evaluations'][$ev['id']]['note'] ?? null;
                                    $st = $m['notes_evaluations'][$ev['id']]['statut_presence'] ?? 'non_evalue';
                                @endphp
                                @if($st === 'absent_justifie')
                                    <span style="font-size: 6pt; color: #d97706;">AJ</span>
                                @elseif($st === 'absent_non_justifie')
                                    <span style="font-size: 6pt; color: #dc2626;">ANJ</span>
                                @elseif($nEv !== null)
                                    {{ number_format($nEv, 2) }}
                                @else
                                    -
                                @endif
                            </td>
                        @endforeach
                        <td class="text-center font-bold {{ $m['est_eliminatoire'] ? 'elim-warning' : '' }}">
                            {{ $m['moyenne'] !== null ? number_format($m['moyenne'], 2) : '-' }}
                        </td>
                        <td class="text-center">{{ number_format($m['coefficient'], 1) }}</td>
                        <td class="text-center font-bold">{{ number_format($m['points'], 2) }}</td>
                        <td class="text-center">
                            @if($m['cote_apc'])
                                <span class="cote-badge cote-{{ strtolower($m['cote_apc']) }}">{{ $m['cote_apc'] }}</span>
                            @else
                                -
                            @endif
                        </td>
                        <td class="text-center" style="font-size: 6.5pt;">
                            [{{ isset($m['min_classe']) ? number_format($m['min_classe'], 1) : '-' }} - {{ isset($m['max_classe']) ? number_format($m['max_classe'], 1) : '-' }}]
                        </td>
                        <td class="text-left" style="font-size: 6.5pt;">
                            @if($m['est_eliminatoire'])
                                <span style="color: #dc2626; font-weight: bold;">(Note éliminatoire)</span>
                            @endif
                            {{ $m['appreciation'] }}
                        </td>
                    </tr>
                @endforeach
                {{-- Sous-total du Groupe --}}
                <tr class="group-subtotal-row">
                    <td colspan="{{ 2 + count($periode['evaluations']) }}" class="text-right">Sous-total {{ $groupe['groupe_nom'] }} :</td>
                    <td class="text-center font-bold">{{ number_format($groupe['moyenne_groupe'], 2) }}</td>
                    <td class="text-center font-bold">{{ number_format($groupe['total_coef'], 1) }}</td>
                    <td class="text-center font-bold">{{ number_format($groupe['total_points'], 2) }}</td>
                    <td colspan="3" class="text-left font-bold" style="font-size: 6.5pt;">Moyenne Groupe: {{ number_format($groupe['moyenne_groupe'], 2) }}/20</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    {{-- Section Synthèse Bas de page (3 Blocs) --}}
    <table class="footer-grid">
        <tr>
            {{-- Bloc 1 : Discipline --}}
            <td style="width: 32%;">
                <div class="footer-box">
                    <div class="box-title">Discipline & Assiduité</div>
                    <table class="mini-table">
                        <tr>
                            <td>Absences non justifiées :</td>
                            <td class="text-right font-bold">{{ $eleve['discipline']['absences_non_justifiees'] }} h</td>
                        </tr>
                        <tr>
                            <td>Absences justifiées :</td>
                            <td class="text-right font-bold">{{ $eleve['discipline']['absences_justifiees'] }} h</td>
                        </tr>
                        <tr>
                            <td>Retards :</td>
                            <td class="text-right font-bold">{{ $eleve['discipline']['retards'] }}</td>
                        </tr>
                        <tr>
                            <td>Consignes (retenues) :</td>
                            <td class="text-right font-bold">{{ $eleve['discipline']['consignes'] }} h</td>
                        </tr>
                        <tr>
                            <td>Avertissements / Blâmes :</td>
                            <td class="text-right font-bold">{{ $eleve['discipline']['avertissements'] }} / {{ $eleve['discipline']['blames'] }}</td>
                        </tr>
                        <tr>
                            <td>Exclusion temporaire :</td>
                            <td class="text-right font-bold">{{ $eleve['discipline']['exclusions'] }} jour(s)</td>
                        </tr>
                    </table>
                </div>
            </td>

            {{-- Bloc 2 : Travail de l'Élève --}}
            <td style="width: 36%;">
                <div class="footer-box" style="border-color: #1e3a8a; background-color: #f8fafc;">
                    <div class="box-title" style="background: #1e3a8a; color: #fff; margin: -3px -3px 3px -3px; padding: 2px;">Résultats de l'Élève</div>
                    <table class="mini-table">
                        <tr>
                            <td>Total des points obtenus :</td>
                            <td class="text-right font-bold">{{ number_format($eleve['total_points'], 2) }}</td>
                        </tr>
                        <tr>
                            <td>Total des coefficients :</td>
                            <td class="text-right font-bold">{{ number_format($eleve['total_coefficients'], 1) }}</td>
                        </tr>
                        <tr style="font-size: 8.5pt;">
                            <td class="font-bold" style="color: #1e3a8a;">MOYENNE TRIMESTRE :</td>
                            <td class="text-right font-bold" style="color: #1e3a8a;">{{ number_format($eleve['moyenne_generale'], 2) }} / 20</td>
                        </tr>
                        <tr>
                            <td class="font-bold">Rang de l'élève :</td>
                            <td class="text-right font-bold" style="font-size: 8.5pt; color: #0284c7;">
                                @if(($eleve['rang_general'] ?? '') === 'NC')
                                    <span style="color: #dc2626;" title="{{ $eleve['motif_non_classe'] ?? 'Non Classé' }}">NC (Non Classé)</span>
                                @else
                                    {{ $eleve['rang_general'] }} / {{ $classe['effectif'] }}
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <td>Cote globale APC :</td>
                            <td class="text-right font-bold"><span class="cote-badge cote-{{ strtolower($eleve['cote_globale']) }}">{{ $eleve['cote_globale'] }}</span></td>
                        </tr>
                        <tr>
                            <td>Distinction / Mention :</td>
                            <td class="text-right font-bold" style="font-size: 6.5pt; {{ $eleve['a_note_eliminatoire'] ? 'color: #dc2626;' : 'color: #15803d;' }}">
                                {{ $eleve['distinction'] }}
                            </td>
                        </tr>
                    </table>
                </div>
            </td>

            {{-- Bloc 3 : Profil de la Classe --}}
            <td style="width: 32%;">
                <div class="footer-box">
                    <div class="box-title">Profil de la Classe</div>
                    <table class="mini-table">
                        <tr>
                            <td>Moyenne générale classe :</td>
                            <td class="text-right font-bold">{{ number_format($stats_classe['moyenne_generale'], 2) }} / 20</td>
                        </tr>
                        <tr>
                            <td>Note minimale classe :</td>
                            <td class="text-right font-bold">{{ number_format($stats_classe['min_generale'], 2) }} / 20</td>
                        </tr>
                        <tr>
                            <td>Note maximale classe :</td>
                            <td class="text-right font-bold">{{ number_format($stats_classe['max_generale'], 2) }} / 20</td>
                        </tr>
                        <tr>
                            <td>Nombre de moyennes :</td>
                            <td class="text-right font-bold">{{ $stats_classe['nb_moyennes'] }} / {{ $stats_classe['effectif_total'] }}</td>
                        </tr>
                        <tr>
                            <td>Taux de réussite classe :</td>
                            <td class="text-right font-bold" style="color: #15803d;">{{ number_format($stats_classe['taux_reussite'], 1) }} %</td>
                        </tr>
                        <tr>
                            <td>Élèves évalués :</td>
                            <td class="text-right font-bold">{{ $stats_classe['effectif_evalue'] }}</td>
                        </tr>
                    </table>
                </div>
            </td>
        </tr>
    </table>

    {{-- Décision du Conseil de Classe --}}
    <div style="border: 1px solid #64748b; border-radius: 3px; padding: 3px; margin-bottom: 5px; font-size: 7pt;">
        <span class="font-bold" style="color: #1e3a8a;">DÉCISION DU CONSEIL DE CLASSE :</span>
        <span>{{ $eleve['discipline']['decision_conseil'] ?: 'Travail régulier. Poursuivre les efforts au prochain trimestre pour consolider les acquis.' }}</span>
    </div>

    {{-- Visas et Signatures --}}
    <table class="signatures-table">
        <tr>
            <td>
                <div class="signature-title">Visa du Parent / Tuteur</div>
                <div class="signature-line">Date & Signature</div>
            </td>
            <td>
                <div class="signature-title">Le Professeur Principal</div>
                <div class="signature-line">{{ $classe['professeur_principal'] ?? 'Signature' }}</div>
            </td>
            <td>
                <div class="signature-title">Le Chef d'Établissement</div>
                <div class="signature-line">{{ $classe['etablissement']['nom_chef'] ?? 'Signature & Cachet Numérique' }}</div>
            </td>
        </tr>
    </table>
</body>
</html>
