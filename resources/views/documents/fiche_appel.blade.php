<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Fiche d'Appel - {{ $classe->nom }}</title>
    <style>
        @page {
            size: A4 landscape;
            margin: 6mm 8mm;
        }
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 7.5pt;
            color: #0f172a;
            line-height: 1.15;
            margin: 0;
            padding: 0;
        }
        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 4px;
        }
        .header-table td {
            vertical-align: top;
        }
        .header-left, .header-right {
            width: 36%;
            font-size: 6.5pt;
            text-align: center;
            line-height: 1.15;
        }
        .header-center {
            width: 28%;
            text-align: center;
        }
        .school-name {
            font-size: 11pt;
            font-weight: bold;
            color: #1e3a8a;
            text-transform: uppercase;
            margin-top: 2px;
        }
        .school-sub {
            font-size: 6.5pt;
            color: #64748b;
        }
        .banner-box {
            background-color: #1e3a8a;
            color: #ffffff;
            border-radius: 4px;
            padding: 4px 8px;
            margin-bottom: 6px;
            display: table;
            width: 100%;
            box-sizing: border-box;
        }
        .banner-title {
            font-size: 9.5pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: table-cell;
            vertical-align: middle;
        }
        .banner-meta {
            font-size: 7.5pt;
            display: table-cell;
            text-align: right;
            vertical-align: middle;
            color: #bfdbfe;
        }
        .info-bar {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 6px;
            background-color: #f8fafc;
            border: 1px solid #cbd5e1;
        }
        .info-bar td {
            padding: 3px 6px;
            font-size: 7pt;
            border-right: 1px solid #e2e8f0;
        }
        .info-bar td:last-child {
            border-right: none;
        }
        .info-label {
            color: #64748b;
            text-transform: uppercase;
            font-weight: bold;
            font-size: 6pt;
        }
        .info-val {
            font-weight: bold;
            color: #0f172a;
        }
        .appel-table {
            width: 100%;
            border-collapse: collapse;
        }
        .appel-table th, .appel-table td {
            border: 1px solid #94a3b8;
            padding: 2.5px 3px;
        }
        .appel-table th {
            background-color: #f1f5f9;
            color: #0f172a;
            font-weight: bold;
            font-size: 6.5pt;
            text-align: center;
        }
        .th-day {
            background-color: #e2e8f0;
            color: #1e3a8a;
            font-size: 7pt;
        }
        .th-sub {
            font-size: 5.5pt;
            font-weight: normal;
            color: #475569;
        }
        .col-num {
            width: 18px;
            text-align: center;
            font-weight: bold;
            font-size: 7pt;
        }
        .col-mat {
            width: 65px;
            font-family: monospace;
            font-size: 6.5pt;
            text-align: center;
        }
        .col-nom {
            text-align: left;
            font-size: 7pt;
            white-space: nowrap;
            overflow: hidden;
        }
        .col-sexe {
            width: 15px;
            text-align: center;
            font-weight: bold;
        }
        .col-check {
            width: 16px;
            text-align: center;
        }
        .col-total {
            width: 32px;
            text-align: center;
            font-weight: bold;
        }
        .col-obs {
            width: 75px;
        }
        .tr-even {
            background-color: #f8fafc;
        }
        .footer-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
        }
        .footer-table td {
            vertical-align: top;
            width: 33.33%;
            font-size: 7pt;
            text-align: center;
        }
        .sign-title {
            font-weight: bold;
            text-transform: uppercase;
            color: #1e3a8a;
            margin-bottom: 25px;
            font-size: 7.5pt;
        }
        .sign-dots {
            color: #94a3b8;
            font-style: italic;
            font-size: 6.5pt;
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
                MINISTÈRE DES ENSEIGNEMENTS SECONDAIRES<br>
                {{ $etablissement->delegation_regionale ?? 'DÉLÉGATION RÉGIONALE DU CENTRE' }}<br>
                {{ $etablissement->delegation_departementale ?? 'DÉLÉGATION DÉPARTEMENTALE DU MFOUNDI' }}
            </td>
            <td class="header-center">
                @if($etablissement && $etablissement->logo_url)
                    <img src="{{ public_path($etablissement->logo_url) }}" alt="Logo" style="max-height: 38px; max-width: 50px;">
                @else
                    <div style="font-size: 16pt; font-weight: bold; color: #1e3a8a;">SYGES</div>
                @endif
                <div class="school-name">{{ $etablissement->nom ?? 'ÉTABLISSEMENT SCOLAIRE' }}</div>
                <div class="school-sub">Devise : {{ $etablissement->devise ?? 'Discipline - Travail - Succès' }}</div>
            </td>
            <td class="header-right">
                <strong>REPUBLIC OF CAMEROON</strong><br>
                <em>Peace – Work – Fatherland</em><br>
                MINISTRY OF SECONDARY EDUCATION<br>
                REGIONAL DELEGATION OF CENTRE<br>
                DIVISIONAL DELEGATION OF MFOUNDI
            </td>
        </tr>
    </table>

    <!-- Bannière Titre Officielle -->
    <div class="banner-box">
        <div class="banner-title">
            Fiche d'Appel & Contrôle Quotidien des Présences
        </div>
        <div class="banner-meta">
            Classe : <strong>{{ $classe->nom }}</strong> &bull; Année Scolaire : <strong>{{ $annee->nom ?? $annee->code }}</strong>
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

    <!-- Tableau d'Appel Matriculaire -->
    <table class="appel-table">
        <thead>
            <tr>
                <th rowspan="2" class="col-num">N°</th>
                <th rowspan="2" class="col-mat">Matricule</th>
                <th rowspan="2" class="col-nom">Noms et Prénoms de l'Élève</th>
                <th rowspan="2" class="col-sexe">S</th>
                <th colspan="2" class="th-day">LUNDI</th>
                <th colspan="2" class="th-day">MARDI</th>
                <th colspan="2" class="th-day">MERCREDI</th>
                <th colspan="2" class="th-day">JEUDI</th>
                <th colspan="2" class="th-day">VENDREDI</th>
                <th rowspan="2" class="col-total">Total Abs.</th>
                <th rowspan="2" class="col-obs">Observations</th>
            </tr>
            <tr>
                <th class="th-sub col-check">M</th>
                <th class="th-sub col-check">S</th>
                <th class="th-sub col-check">M</th>
                <th class="th-sub col-check">S</th>
                <th class="th-sub col-check">M</th>
                <th class="th-sub col-check">S</th>
                <th class="th-sub col-check">M</th>
                <th class="th-sub col-check">S</th>
                <th class="th-sub col-check">M</th>
                <th class="th-sub col-check">S</th>
            </tr>
        </thead>
        <tbody>
            @forelse($inscriptions as $index => $insc)
                <tr class="{{ $index % 2 === 1 ? 'tr-even' : '' }}">
                    <td class="col-num">{{ $index + 1 }}</td>
                    <td class="col-mat">{{ $insc->eleve->matricule }}</td>
                    <td class="col-nom">
                        <strong>{{ mb_strtoupper($insc->eleve->nom) }}</strong> {{ $insc->eleve->prenom }}
                    </td>
                    <td class="col-sexe">{{ $insc->eleve->sexe }}</td>
                    <!-- Cases de pointage vierges Lundi à Vendredi (Matin / Soir) -->
                    <td class="col-check"></td>
                    <td class="col-check"></td>
                    <td class="col-check"></td>
                    <td class="col-check"></td>
                    <td class="col-check"></td>
                    <td class="col-check"></td>
                    <td class="col-check"></td>
                    <td class="col-check"></td>
                    <td class="col-check"></td>
                    <td class="col-check"></td>
                    <td class="col-total"></td>
                    <td class="col-obs"></td>
                </tr>
            @empty
                <tr>
                    <td colspan="16" style="text-align: center; padding: 15px; color: #64748b; font-style: italic;">
                        Aucun élève inscrit dans cette classe pour la période sélectionnée.
                    </td>
                </tr>
            @endforelse

            <!-- Ligne de pointage des totaux journaliers -->
            <tr style="background-color: #f1f5f9; font-weight: bold;">
                <td colspan="4" style="text-align: right; padding-right: 6px; font-size: 6.5pt;">TOTAL ABSENTS :</td>
                <td class="col-check"></td>
                <td class="col-check"></td>
                <td class="col-check"></td>
                <td class="col-check"></td>
                <td class="col-check"></td>
                <td class="col-check"></td>
                <td class="col-check"></td>
                <td class="col-check"></td>
                <td class="col-check"></td>
                <td class="col-check"></td>
                <td class="col-total"></td>
                <td class="col-obs"></td>
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
