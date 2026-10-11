<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Liste du Personnel - {{ $etablissement->nom ?? 'SYGES' }}</title>
    <style>
        @page {
            size: A4 landscape;
            margin: 6mm 8mm 8mm 8mm;
        }
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 7.5pt;
            color: #0f172a;
            line-height: 1.2;
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
            padding: 5px 10px;
            margin-bottom: 6px;
            display: table;
            width: 100%;
            box-sizing: border-box;
        }
        .banner-title {
            font-size: 10pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.8px;
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
        .stats-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 6px;
            background-color: #f8fafc;
            border: 1px solid #cbd5e1;
            border-radius: 4px;
        }
        .stats-table td {
            padding: 3px 6px;
            text-align: center;
            border-right: 1px solid #e2e8f0;
            font-size: 6.8pt;
        }
        .stats-table td:last-child {
            border-right: none;
        }
        .stats-label {
            color: #64748b;
            font-size: 5.8pt;
            text-transform: uppercase;
            font-weight: bold;
        }
        .stats-val {
            font-size: 8.5pt;
            font-weight: bold;
            color: #1e3a8a;
            margin-top: 1px;
        }
        .personnel-table {
            width: 100%;
            border-collapse: collapse;
        }
        .personnel-table th, .personnel-table td {
            border: 1px solid #cbd5e1;
            padding: 3.5px 4px;
        }
        .personnel-table th {
            background-color: #f1f5f9;
            color: #1e293b;
            font-weight: bold;
            font-size: 6.8pt;
            text-align: center;
            text-transform: uppercase;
        }
        .col-num {
            width: 18px;
            text-align: center;
            font-weight: bold;
        }
        .col-mat {
            width: 85px;
            font-family: monospace;
            font-size: 6.8pt;
            font-weight: bold;
            color: #1e3a8a;
            text-align: center;
        }
        .col-nom {
            text-align: left;
            font-size: 7.2pt;
        }
        .col-sexe {
            width: 16px;
            text-align: center;
            font-weight: bold;
        }
        .col-contrat {
            width: 68px;
            font-size: 6.5pt;
            text-align: center;
        }
        .col-fonction {
            width: 105px;
            font-size: 6.8pt;
            text-align: left;
        }
        .col-dept {
            width: 95px;
            font-size: 6.8pt;
            text-align: left;
        }
        .col-charge {
            width: 40px;
            text-align: center;
            font-family: monospace;
            font-size: 6.8pt;
        }
        .col-contact {
            width: 125px;
            font-size: 6.5pt;
            font-family: monospace;
        }
        .tr-even {
            background-color: #f8fafc;
        }
        .badge-perm {
            color: #15803d;
            font-weight: 600;
        }
        .badge-vac {
            color: #b45309;
            font-weight: 600;
        }
        .footer-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        .footer-table td {
            vertical-align: top;
            width: 50%;
        }
        .sign-left {
            font-size: 6.8pt;
            color: #64748b;
        }
        .sign-right {
            text-align: right;
            padding-right: 20px;
        }
        .sign-role {
            font-size: 8pt;
            font-weight: bold;
            text-transform: uppercase;
            color: #1e3a8a;
            margin-bottom: 35px;
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
            Répertoire Officiel du Personnel & Corps Enseignant
        </div>
        <div class="banner-meta">
            Année Scolaire : <strong>{{ $annee->nom ?? $annee->code }}</strong> &bull; Total Actif : <strong>{{ count($personnels) }} agent(s)</strong>
        </div>
    </div>

    <!-- Synthèse statistique des effectifs RH -->
    <table class="stats-table">
        <tr>
            <td>
                <div class="stats-label">Effectif Total</div>
                <div class="stats-val">{{ $stats['total'] }} agent(s)</div>
            </td>
            <td>
                <div class="stats-label">Hommes</div>
                <div class="stats-val" style="color: #0284c7;">{{ $stats['hommes'] }}</div>
            </td>
            <td>
                <div class="stats-label">Femmes</div>
                <div class="stats-val" style="color: #7c3aed;">{{ $stats['femmes'] }}</div>
            </td>
            <td>
                <div class="stats-label">Enseignants</div>
                <div class="stats-val" style="color: #15803d;">{{ $stats['enseignants'] }}</div>
            </td>
            <td>
                <div class="stats-label">Personnel Administratif / Appui</div>
                <div class="stats-val" style="color: #b45309;">{{ $stats['administratifs'] }}</div>
            </td>
        </tr>
    </table>

    <!-- Tableau du Personnel -->
    <table class="personnel-table">
        <thead>
            <tr>
                <th class="col-num">N°</th>
                <th class="col-mat">Matricule RH</th>
                <th class="col-nom">Noms et Prénoms</th>
                <th class="col-sexe">S</th>
                <th class="col-contrat">Contrat / Statut</th>
                <th class="col-fonction">Fonction Principale</th>
                <th class="col-dept">Département / Discipline</th>
                <th class="col-charge">Charge</th>
                <th class="col-contact">Contact (Tél. / Email)</th>
            </tr>
        </thead>
        <tbody>
            @forelse($personnels as $index => $pers)
                <tr class="{{ $index % 2 === 1 ? 'tr-even' : '' }}">
                    <td class="col-num">{{ $index + 1 }}</td>
                    <td class="col-mat">{{ $pers->matricule }}</td>
                    <td class="col-nom">
                        <strong>{{ mb_strtoupper($pers->nom) }}</strong> {{ $pers->prenom }}
                    </td>
                    <td class="col-sexe" style="{{ $pers->sexe === 'F' ? 'color: #7c3aed;' : 'color: #0284c7;' }}">
                        {{ $pers->sexe }}
                    </td>
                    <td class="col-contrat">
                        @if(in_array(strtolower($pers->type_contrat ?? ''), ['vacataire', 'temporaire']))
                            <span class="badge-vac">{{ ucfirst($pers->type_contrat ?? 'Vacataire') }}</span>
                        @else
                            <span class="badge-perm">{{ ucfirst($pers->type_contrat ?? 'Permanent') }}</span>
                        @endif
                    </td>
                    <td class="col-fonction">
                        {{ $pers->fonction_principale ?: ($pers->is_enseignant ? 'Professeur' : 'Personnel Administratif') }}
                    </td>
                    <td class="col-dept">
                        {{ $pers->departement?->nom ?: ($pers->specialite ?: '---') }}
                    </td>
                    <td class="col-charge">
                        {{ $pers->volume_horaire_hebdo ? ($pers->volume_horaire_hebdo . 'h') : '---' }}
                    </td>
                    <td class="col-contact">
                        {{ $pers->telephone ?: '---' }}
                        @if($pers->email)
                            <br><span style="color: #64748b; font-size: 5.8pt;">{{ $pers->email }}</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" style="text-align: center; padding: 15px; color: #64748b; font-style: italic;">
                        Aucun membre du personnel enregistré pour cet établissement.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <!-- Pied de page & Signature -->
    <table class="footer-table">
        <tr>
            <td class="sign-left">
                Document officiel certifié par SYGES &bull; Édité le {{ $dateGeneration }}<br>
                Registre du personnel conforme au barème national des établissements secondaires.
            </td>
            <td class="sign-right">
                <div style="font-size: 7.5pt; margin-bottom: 3px;">Fait à {{ $etablissement->ville ?? 'Yaoundé' }}, le {{ $dateGeneration }}</div>
                <div class="sign-role">Le Chef d'Établissement</div>
                <div style="font-size: 6.5pt; color: #94a3b8; font-style: italic;">(Signature et Cachet Officiel)</div>
            </td>
        </tr>
    </table>

</body>
</html>
