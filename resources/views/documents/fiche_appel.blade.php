<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Fiche d'Appel - {{ $classe->nom }}</title>
    <style>
        @page {
            size: A4 landscape;
            margin: 5mm 6mm;
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
            margin-bottom: 3px;
        }
        .header-table td {
            vertical-align: middle;
        }
        .header-left, .header-right {
            width: 41%;
            font-size: 6.8pt;
            text-align: center;
            line-height: 1.22;
            white-space: nowrap;
        }
        .header-center {
            width: 18%;
            text-align: center;
            vertical-align: middle;
        }
        .school-name {
            font-size: 11pt;
            font-weight: bold;
            color: #1e3a8a;
            text-transform: uppercase;
            margin-top: 1px;
            white-space: nowrap;
        }
        .school-sub {
            font-size: 7pt;
            color: #64748b;
        }
        .banner-box {
            background-color: #1e3a8a;
            color: #ffffff;
            border-radius: 4px;
            padding: 3px 8px;
            margin-bottom: 4px;
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
            font-size: 7pt;
            display: table-cell;
            text-align: right;
            vertical-align: middle;
            color: #bfdbfe;
        }
        .info-bar {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 4px;
            background-color: #f8fafc;
            border: 1px solid #cbd5e1;
        }
        .info-bar td {
            padding: 2px 5px;
            font-size: 6.5pt;
            border-right: 1px solid #e2e8f0;
        }
        .info-bar td:last-child {
            border-right: none;
        }
        .info-label {
            color: #64748b;
            text-transform: uppercase;
            font-weight: bold;
            font-size: 5.5pt;
        }
        .info-val {
            font-weight: bold;
            color: #0f172a;
        }
        .appel-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }
        .appel-table th, .appel-table td {
            border: 0.5px solid #94a3b8;
            padding: 2px 1px;
        }
        .appel-table th {
            background-color: #f1f5f9;
            color: #0f172a;
            font-weight: bold;
            font-size: 6pt;
            text-align: center;
        }
        .th-day {
            background-color: #e2e8f0;
            color: #1e3a8a;
            font-size: 6.5pt;
            font-weight: bold;
            width: 14%;
            border-right: 1.5px solid #475569 !important;
        }
        .th-hour {
            font-size: 5.5pt;
            font-weight: bold;
            color: #334155;
            width: 1.75%;
            text-align: center;
            padding: 1px 0 !important;
        }
        .th-hour-end {
            border-right: 1.5px solid #475569 !important;
        }
        .col-num {
            width: 3%;
            text-align: center;
            font-weight: bold;
            font-size: 6.5pt;
        }
        .col-nom {
            width: 27%;
            text-align: left;
            font-size: 7.2pt;
            padding-left: 5px !important;
            padding-right: 3px !important;
            line-height: 1.15;
            white-space: nowrap;
            overflow: hidden;
        }
        .col-h {
            width: 1.75%;
            text-align: center;
            padding: 0 !important;
        }
        .col-h-end {
            border-right: 1.5px solid #475569 !important;
        }
        .tr-even {
            background-color: #f8fafc;
        }
        .footer-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 6px;
        }
        .footer-table td {
            vertical-align: top;
            width: 33.33%;
            font-size: 6.5pt;
            text-align: center;
        }
        .sign-title {
            font-weight: bold;
            text-transform: uppercase;
            color: #1e3a8a;
            margin-bottom: 20px;
            font-size: 7pt;
        }
        .sign-dots {
            color: #94a3b8;
            font-style: italic;
            font-size: 6pt;
        }
    </style>
</head>
<body>

    <!-- En-tête bilingue officiel républicain -->
    <table class="header-table">
        <tr>
            <td class="header-left">
                <strong>RÉPUBLIQUE DU CAMEROUN</strong><br>
                <em>Paix – Travail – Patrie</em><br>
                {{ $etablissement->ministere_tutelle ?? 'MINISTÈRE DES ENSEIGNEMENTS SECONDAIRES' }}<br>
                @if($etablissement->delegation_regionale)
                    {{ mb_strtoupper($etablissement->delegation_regionale, 'UTF-8') }}<br>
                @endif
                @if($etablissement->delegation_departementale)
                    {{ mb_strtoupper($etablissement->delegation_departementale, 'UTF-8') }}<br>
                @endif
                @if($etablissement->arrondissement)
                    {{ \Illuminate\Support\Str::startsWith(mb_strtoupper($etablissement->arrondissement, 'UTF-8'), 'ARRONDISSEMENT') ? mb_strtoupper($etablissement->arrondissement, 'UTF-8') : 'ARRONDISSEMENT DE ' . mb_strtoupper($etablissement->arrondissement, 'UTF-8') }}
                @endif
            </td>
            <td class="header-center">
                @php
                    $logoSrc = ($etablissement && method_exists($etablissement, 'getEffectiveLogoBase64'))
                        ? $etablissement->getEffectiveLogoBase64()
                        : (file_exists(public_path('logo.png')) ? 'data:image/png;base64,' . base64_encode(file_get_contents(public_path('logo.png'))) : null);
                @endphp
                @if($logoSrc)
                    <img src="{{ $logoSrc }}" alt="Logo" style="max-height: 55px; max-width: 70px; object-fit: contain;">
                @else
                    <div style="font-size: 18pt; font-weight: bold; color: #1e3a8a;">SYGES</div>
                @endif
            </td>
            <td class="header-right">
                <strong>REPUBLIC OF CAMEROON</strong><br>
                <em>Peace – Work – Fatherland</em><br>
                {{ $etablissement->ministere_tutelle_en ?? 'MINISTRY OF SECONDARY EDUCATION' }}<br>
                @if($etablissement->delegation_regionale_en || $etablissement->delegation_regionale)
                    {{ mb_strtoupper($etablissement->delegation_regionale_en ?? $etablissement->delegation_regionale, 'UTF-8') }}<br>
                @endif
                @if($etablissement->delegation_departementale_en || $etablissement->delegation_departementale)
                    {{ mb_strtoupper($etablissement->delegation_departementale_en ?? $etablissement->delegation_departementale, 'UTF-8') }}<br>
                @endif
                @if($etablissement->arrondissement_en || $etablissement->arrondissement)
                    @php
                        $arrEn = $etablissement->arrondissement_en ?? $etablissement->arrondissement;
                        $arrEnUpper = mb_strtoupper($arrEn, 'UTF-8');
                    @endphp
                    {{ \Illuminate\Support\Str::contains($arrEnUpper, 'SUB-DIVISION') ? $arrEnUpper : $arrEnUpper . ' SUB-DIVISION' }}
                @endif
            </td>
        </tr>
    </table>

    <div style="text-align: center; margin-top: 1px; margin-bottom: 6px;">
        <div class="school-name">{{ $etablissement->nom ?? 'ÉTABLISSEMENT SCOLAIRE' }}</div>
        <div class="school-sub">Devise : {{ $etablissement->devise ?? 'Discipline - Travail - Succès' }}</div>
    </div>

    <!-- Bannière Titre Officielle -->
    <div class="banner-box">
        <div class="banner-title">
            Fiche d'Appel & Contrôle Quotidien des Présences
        </div>
        <div class="banner-meta">
            Classe : <strong>{{ $classe->nom }}</strong> &bull; Année Scolaire : <strong>{{ $annee->session ?? ($annee->code ?? $annee->nom) }}</strong>
        </div>
    </div>

    <!-- Méta-informations de classe et période -->
    <table class="info-bar">
        <tr>
            <td>
                <span class="info-label">Classe :</span><br>
                <span class="info-val">{{ $classe->nom }}</span>
            </td>
            <td>
                <span class="info-label">Professeur Principal :</span><br>
                <span class="info-val">{{ $classe->professeurPrincipal ? ($classe->professeurPrincipal->nom . ' ' . $classe->professeurPrincipal->prenom) : 'Non assigné' }}</span>
            </td>
            <td>
                <span class="info-label">Effectif Inscrit :</span><br>
                <span class="info-val">{{ count($inscriptions) }} élève(s)</span>
            </td>
            <td>
                <span class="info-label">Période d'Appel :</span><br>
                <span class="info-val">{{ $mois }} &bull; Semaine du : .................. au ..................</span>
            </td>
            <td>
                <span class="info-label">Légende :</span><br>
                <span style="font-size: 6pt; color: #475569;">P = Présent | A = Absent | R = Retard | J = Justifié</span>
            </td>
        </tr>
    </table>

    <!-- Tableau d'Appel (5 jours x 8 heures = 40 colonnes horaires) -->
    <table class="appel-table">
        <thead>
            <tr>
                <th rowspan="2" class="col-num">N°</th>
                <th rowspan="2" class="col-nom">Noms et Prénoms de l'Élève</th>
                @foreach(['LUNDI', 'MARDI', 'MERCREDI', 'JEUDI', 'VENDREDI'] as $jour)
                    <th colspan="8" class="th-day">{{ $jour }}</th>
                @endforeach
            </tr>
            <tr>
                @for($j = 0; $j < 5; $j++)
                    @for($h = 1; $h <= 8; $h++)
                        <th class="th-hour {{ $h === 8 ? 'th-hour-end' : '' }}">{{ $h }}</th>
                    @endfor
                @endfor
            </tr>
        </thead>
        <tbody>
            @forelse($inscriptions as $index => $insc)
                <tr class="{{ $index % 2 === 1 ? 'tr-even' : '' }}">
                    <td class="col-num">{{ $index + 1 }}</td>
                    <td class="col-nom">
                        <strong>{{ mb_strtoupper($insc->eleve->nom) }}</strong> {{ $insc->eleve->prenom }}
                    </td>
                    <!-- 40 cases de pointage horaire (8 heures x 5 jours) -->
                    @for($j = 0; $j < 5; $j++)
                        @for($h = 1; $h <= 8; $h++)
                            <td class="col-h {{ $h === 8 ? 'col-h-end' : '' }}"></td>
                        @endfor
                    @endfor
                </tr>
            @empty
                <tr>
                    <td colspan="42" style="text-align: center; padding: 15px; color: #64748b; font-style: italic;">
                        Aucun élève inscrit dans cette classe pour la période sélectionnée.
                    </td>
                </tr>
            @endforelse

            <!-- Ligne de pointage des totaux journaliers par heure -->
            <tr style="background-color: #f1f5f9; font-weight: bold;">
                <td colspan="2" style="text-align: right; padding-right: 6px; font-size: 6.5pt;">TOTAL ABSENTS :</td>
                @for($j = 0; $j < 5; $j++)
                    @for($h = 1; $h <= 8; $h++)
                        <td class="col-h {{ $h === 8 ? 'col-h-end' : '' }}"></td>
                    @endfor
                @endfor
            </tr>
        </tbody>
    </table>

    <!-- Visas et Signatures officielles -->
    <table class="footer-table">
        <tr>
            <td>
                <div class="sign-title">Le Délégué de Classe</div>
                <div class="sign-dots">(Émargement hebdomadaire)</div>
            </td>
            <td>
                <div class="sign-title">Le Professeur Principal</div>
                <div class="sign-dots">(Visa & Contrôle des carnets)</div>
            </td>
            <td>
                <div class="sign-title">Le Surveillant Général</div>
                <div class="sign-dots">(Enregistrement au registre central)</div>
            </td>
        </tr>
    </table>

</body>
</html>
