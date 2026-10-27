<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Babillard - {{ $classe->nom }} - {{ $periode ? $periode->nom : 'Général' }}</title>
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
            margin-bottom: 6px;
        }
        .header-table td {
            vertical-align: top;
        }
        .header-left, .header-right {
            width: 35%;
            font-size: 7pt;
            text-align: center;
            line-height: 1.2;
        }
        .header-center {
            width: 30%;
            text-align: center;
        }
        .school-name {
            font-size: 11pt;
            font-weight: bold;
            color: #1e3a8a;
            text-transform: uppercase;
            margin-top: 2px;
        }
        .banner-box {
            background-color: #1e3a8a;
            color: #ffffff;
            border-radius: 4px;
            padding: 6px 12px;
            margin-bottom: 8px;
            display: table;
            width: 100%;
            box-sizing: border-box;
        }
        .banner-title {
            font-size: 11pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: table-cell;
            vertical-align: middle;
        }
        .banner-meta {
            font-size: 8pt;
            display: table-cell;
            text-align: right;
            vertical-align: middle;
            color: #bfdbfe;
        }
        .stats-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8px;
            background-color: #f8fafc;
            border: 1px solid #cbd5e1;
            border-radius: 4px;
        }
        .stats-table td {
            padding: 4px 6px;
            text-align: center;
            border-right: 1px solid #e2e8f0;
            font-size: 7.5pt;
        }
        .stats-table td:last-child {
            border-right: none;
        }
        .stats-label {
            color: #64748b;
            font-size: 6.5pt;
            text-transform: uppercase;
            font-weight: bold;
        }
        .stats-val {
            font-size: 9.5pt;
            font-weight: bold;
            color: #1e3a8a;
            margin-top: 1px;
        }
        .babillard-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8px;
        }
        .babillard-table th {
            background-color: #0f172a;
            color: #ffffff;
            font-weight: bold;
            font-size: 7.5pt;
            padding: 5px 4px;
            text-align: center;
            border: 1px solid #0f172a;
            text-transform: uppercase;
        }
        .babillard-table td {
            border: 1px solid #cbd5e1;
            padding: 4px 5px;
            font-size: 7.5pt;
        }
        .babillard-table tr:nth-child(even) {
            background-color: #f8fafc;
        }
        .rank-top {
            font-weight: bold;
            color: #1e3a8a;
            background-color: #e0e7ff;
            text-align: center;
        }
        .rank-cell {
            text-align: center;
            font-weight: bold;
        }
        .moyenne-ok {
            font-weight: bold;
            color: #15803d;
            text-align: center;
        }
        .moyenne-fail {
            font-weight: bold;
            color: #b91c1c;
            text-align: center;
        }
        .mention-badge {
            display: inline-block;
            padding: 1px 4px;
            border-radius: 2px;
            font-size: 6.5pt;
            font-weight: bold;
            text-transform: uppercase;
        }
        .sign-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        .sign-table td {
            width: 33.33%;
            text-align: center;
            vertical-align: top;
            font-size: 7.5pt;
        }
        .sign-role {
            font-weight: bold;
            text-transform: uppercase;
            color: #334155;
            margin-bottom: 30px;
        }
    </style>
</head>
<body>

    <!-- En-tête -->
    <table class="header-table">
        <tr>
            <td class="header-left">
                <strong>RÉPUBLIQUE DU CAMEROUN</strong> &bull; <em>Paix – Travail – Patrie</em><br>
                {{ $etablissement->ministere_tutelle ?? 'MINISTÈRE DES ENSEIGNEMENTS SECONDAIRES' }}<br>
                @if($etablissement->delegation_regionale)
                    {{ mb_strtoupper($etablissement->delegation_regionale, 'UTF-8') }}
                @endif
                @if($etablissement->delegation_departementale)
                    &bull; {{ mb_strtoupper($etablissement->delegation_departementale, 'UTF-8') }}
                @endif
            </td>
            <td class="header-center">
                <div class="school-name">{{ $etablissement->nom ?? 'ÉTABLISSEMENT SCOLAIRE' }}</div>
            </td>
            <td class="header-right">
                <strong>REPUBLIC OF CAMEROON</strong> &bull; <em>Peace – Work – Fatherland</em><br>
                {{ $etablissement->ministere_tutelle_en ?? 'MINISTRY OF SECONDARY EDUCATION' }}<br>
                @if($etablissement->delegation_regionale_en || $etablissement->delegation_regionale)
                    {{ mb_strtoupper($etablissement->delegation_regionale_en ?? $etablissement->delegation_regionale, 'UTF-8') }}
                @endif
                @if($etablissement->delegation_departementale_en || $etablissement->delegation_departementale)
                    &bull; {{ mb_strtoupper($etablissement->delegation_departementale_en ?? $etablissement->delegation_departementale, 'UTF-8') }}
                @endif
            </td>
        </tr>
    </table>

    <!-- Bannière du Babillard -->
    <div class="banner-box">
        <div class="banner-title">
            Babillard Officiel des Résultats &bull; Classe : {{ $classe->nom }}
        </div>
        <div class="banner-meta">
            Période : <strong>{{ $periode ? $periode->nom : 'Synthèse Globale' }}</strong> &bull; Édition : {{ $dateGeneration }}
        </div>
    </div>

    <!-- Synthèse Statistique de la Classe -->
    <table class="stats-table">
        <tr>
            <td>
                <div class="stats-label">Effectif Classé</div>
                <div class="stats-val">{{ $stats['nb_eleves'] }} élèves</div>
            </td>
            <td>
                <div class="stats-label">Élèves Admis (&ge; 10/20)</div>
                <div class="stats-val" style="color: #15803d;">{{ $stats['nb_admis'] }}</div>
            </td>
            <td>
                <div class="stats-label">Taux de Réussite</div>
                <div class="stats-val" style="color: {{ $stats['taux_reussite'] >= 50 ? '#15803d' : '#b91c1c' }};">
                    {{ $stats['taux_reussite'] }} %
                </div>
            </td>
            <td>
                <div class="stats-label">Moyenne Générale Classe</div>
                <div class="stats-val">{{ number_format($stats['moyenne_classe'], 2, ',', ' ') }} / 20</div>
            </td>
            <td>
                <div class="stats-label">Plus Forte Moyenne</div>
                <div class="stats-val" style="color: #15803d;">{{ number_format($stats['max_moyenne'], 2, ',', ' ') }} / 20</div>
            </td>
            <td>
                <div class="stats-label">Plus Faible Moyenne</div>
                <div class="stats-val" style="color: #b91c1c;">{{ number_format($stats['min_moyenne'], 2, ',', ' ') }} / 20</div>
            </td>
        </tr>
    </table>

    <!-- Tableau d'affichage public des élèves -->
    <table class="babillard-table">
        <thead>
            <tr>
                <th style="width: 5%;">Rang</th>
                <th style="width: 12%;">Matricule</th>
                <th style="width: 33%;">Nom et Prénoms de l'Élève</th>
                <th style="width: 5%;">Sexe</th>
                <th style="width: 10%;">Total Pts</th>
                <th style="width: 10%;">Moyenne /20</th>
                <th style="width: 12%;">Mention</th>
                <th style="width: 13%;">Décision</th>
            </tr>
        </thead>
        <tbody>
            @forelse($resultats as $res)
                <tr>
                    <td class="{{ $res['rang'] <= 3 ? 'rank-top' : 'rank-cell' }}">
                        {{ $res['rang'] }}<sup>{{ $res['rang'] === 1 ? 'er' : 'e' }}</sup>
                    </td>
                    <td style="font-family: monospace; font-size: 7pt; text-align: center;">
                        {{ $res['matricule'] }}
                    </td>
                    <td>
                        <strong>{{ $res['nom'] }}</strong> {{ $res['prenom'] }}
                    </td>
                    <td style="text-align: center;">{{ $res['sexe'] }}</td>
                    <td style="text-align: right; padding-right: 8px;">
                        {{ number_format($res['total_points'], 1, ',', ' ') }}
                    </td>
                    <td class="{{ $res['moyenne'] >= 10 ? 'moyenne-ok' : 'moyenne-fail' }}">
                        {{ number_format($res['moyenne'], 2, ',', ' ') }}
                    </td>
                    <td style="text-align: center;">
                        {{ $res['mention'] }}
                    </td>
                    <td style="text-align: center; font-weight: bold; color: {{ $res['moyenne'] >= 10 ? '#15803d' : '#b91c1c' }};">
                        @if($res['moyenne'] >= 12)
                            Tableau d'Honneur
                        @elseif($res['moyenne'] >= 10)
                            Admis(e)
                        @else
                            Échoué(e)
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" style="text-align: center; padding: 20px; color: #64748b;">
                        Aucune note disponible pour constituer le babillard de cette classe.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <!-- Signatures Officielles -->
    <table class="sign-table">
        <tr>
            <td>
                <div class="sign-role">Le Professeur Principal</div>
                <div>Visa & Observations</div>
            </td>
            <td>
                <div class="sign-role">Le Censeur / Directeur des Études</div>
                <div>Vérification & Conformité</div>
            </td>
            <td>
                <div class="sign-role">Le Chef d'Établissement</div>
                <div>Approbation & Cachet Officiel</div>
            </td>
        </tr>
    </table>

</body>
</html>
