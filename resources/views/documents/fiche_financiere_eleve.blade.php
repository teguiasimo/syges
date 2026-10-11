<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Situation Financière - {{ $eleve->matricule }}</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 10mm 14mm 14mm 14mm;
        }
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 8.5pt;
            color: #0f172a;
            line-height: 1.3;
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
            width: 38%;
            font-size: 7.5pt;
            text-align: center;
            line-height: 1.25;
        }
        .header-center {
            width: 24%;
            text-align: center;
        }
        .school-name {
            font-size: 11pt;
            font-weight: bold;
            color: #1e3a8a;
            text-transform: uppercase;
            margin-top: 3px;
        }
        .title-banner {
            background-color: #047857;
            color: #ffffff;
            border-radius: 4px;
            padding: 7px 12px;
            margin: 8px 0 10px 0;
            display: table;
            width: 100%;
            box-sizing: border-box;
        }
        .title-text {
            font-size: 11pt;
            font-weight: bold;
            text-transform: uppercase;
            display: table-cell;
            vertical-align: middle;
        }
        .title-sub {
            font-size: 8pt;
            display: table-cell;
            text-align: right;
            vertical-align: middle;
            color: #a7f3d0;
        }
        .student-box {
            width: 100%;
            border-collapse: collapse;
            background-color: #f1f5f9;
            border: 1px solid #cbd5e1;
            border-radius: 4px;
            margin-bottom: 10px;
        }
        .student-box td {
            padding: 5px 8px;
            font-size: 8.5pt;
        }
        .meta-label {
            font-weight: bold;
            color: #475569;
            width: 18%;
        }
        .meta-val {
            color: #0f172a;
            width: 32%;
        }
        .kpi-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
            border: 1px solid #94a3b8;
        }
        .kpi-table td {
            padding: 6px 8px;
            text-align: center;
            background-color: #f8fafc;
            border-right: 1px solid #cbd5e1;
        }
        .kpi-table td:last-child {
            border-right: none;
        }
        .kpi-title {
            font-weight: bold;
            font-size: 6.5pt;
            color: #64748b;
            text-transform: uppercase;
        }
        .kpi-val {
            font-size: 10.5pt;
            font-weight: bold;
            margin-top: 2px;
        }
        .solvency-box {
            padding: 6px 10px;
            border-radius: 4px;
            text-align: center;
            font-weight: bold;
            font-size: 9pt;
            margin-bottom: 12px;
            text-transform: uppercase;
        }
        .solvency-ok {
            background-color: #dcfce7;
            color: #15803d;
            border: 1px solid #86efac;
        }
        .solvency-due {
            background-color: #fee2e2;
            color: #b91c1c;
            border: 1px solid #fca5a5;
        }
        .section-title {
            font-size: 9pt;
            font-weight: bold;
            color: #1e3a8a;
            text-transform: uppercase;
            border-bottom: 1.5px solid #1e3a8a;
            padding-bottom: 2px;
            margin: 10px 0 6px 0;
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
        }
        .data-table th {
            background-color: #334155;
            color: #ffffff;
            font-weight: bold;
            font-size: 7.5pt;
            padding: 5px 4px;
            text-align: center;
            border: 1px solid #334155;
            text-transform: uppercase;
        }
        .data-table td {
            border: 1px solid #cbd5e1;
            padding: 4px 6px;
            font-size: 7.5pt;
        }
        .data-table tr:nth-child(even) {
            background-color: #f8fafc;
        }
        .sign-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        .sign-table td {
            width: 50%;
            text-align: center;
            vertical-align: top;
            font-size: 8pt;
        }
        .sign-role {
            font-weight: bold;
            text-transform: uppercase;
            color: #334155;
            margin-bottom: 40px;
        }
    </style>
</head>
<body>

    <!-- En-tête officiel -->
    <table class="header-table">
        <tr>
            <td class="header-left">
                <strong>RÉPUBLIQUE DU CAMEROUN</strong><br>
                <em>Paix – Travail – Patrie</em><br>
                MINISTÈRE DES ENSEIGNEMENTS SECONDAIRES
            </td>
            <td class="header-center">
                <div class="school-name">{{ $etablissement->nom ?? 'ÉTABLISSEMENT SCOLAIRE' }}</div>
                <div style="font-size: 7pt; color: #64748b;">SERVICE DE L'INTENDANCE & DE LA COMPTABILITÉ</div>
            </td>
            <td class="header-right">
                <strong>REPUBLIC OF CAMEROON</strong><br>
                <em>Peace – Work – Fatherland</em><br>
                MINISTRY OF SECONDARY EDUCATION
            </td>
        </tr>
    </table>

    <!-- Bannière Titre -->
    <div class="title-banner">
        <div class="title-text">Situation Financière Individuelle de l'Élève</div>
        <div class="title-sub">Année Scolaire : {{ $annee->nom ?? $annee->code }} &bull; Édition : {{ $dateEdition }}</div>
    </div>

    <!-- Identité Élève -->
    <table class="student-box">
        <tr>
            <td class="meta-label">Nom de l'Élève :</td>
            <td class="meta-val"><strong>{{ $eleve->nom }} {{ $eleve->prenom }}</strong></td>
            <td class="meta-label">Classe :</td>
            <td class="meta-val"><strong>{{ $classe->nom }}</strong> ({{ $classe->code }})</td>
        </tr>
        <tr>
            <td class="meta-label">Matricule Interne :</td>
            <td class="meta-val"><strong>{{ $eleve->matricule }}</strong></td>
            <td class="meta-label">Parent / Tuteur :</td>
            <td class="meta-val">{{ $eleve->nom_tuteur ?: ($eleve->nom_pere ?: '---') }} ({{ $eleve->telephone_parent ?? '---' }})</td>
        </tr>
    </table>

    <!-- KPI Financiers -->
    <table class="kpi-table">
        <tr>
            <td>
                <div class="kpi-title">Frais Exigibles Bruts</div>
                <div class="kpi-val" style="color: #0f172a;">{{ number_format($compte->total_debit ?? 0, 0, ',', ' ') }} FCFA</div>
            </td>
            <td>
                <div class="kpi-title">Bourses / Remises Déduites</div>
                <div class="kpi-val" style="color: #047857;">- {{ number_format($compte->boursesRemises->sum('montant_deduit') ?? 0, 0, ',', ' ') }} FCFA</div>
            </td>
            <td>
                <div class="kpi-title">Total Encaissé</div>
                <div class="kpi-val" style="color: #2563eb;">{{ number_format($compte->total_paye ?? 0, 0, ',', ' ') }} FCFA</div>
            </td>
            <td>
                <div class="kpi-title">Solde Net Restant</div>
                <div class="kpi-val" style="color: {{ ($compte->solde ?? 0) <= 0 ? '#15803d' : '#b91c1c' }};">
                    {{ number_format($compte->solde ?? 0, 0, ',', ' ') }} FCFA
                </div>
            </td>
        </tr>
    </table>

    <!-- Bannière de Solvabilité -->
    @if(($compte->solde ?? 0) <= 0)
        <div class="solvency-box solvency-ok">
            &check; Situation Financière Régularisée &bull; Élève Solvable pour l'année scolaire en cours
        </div>
    @else
        <div class="solvency-box solvency-due">
            &excl; Reste à payer : {{ number_format($compte->solde, 0, ',', ' ') }} FCFA &bull; Règlement attendu auprès de l'intendance
        </div>
    @endif

    <!-- Tableau des Bourses & Réductions -->
    @if($compte && $compte->boursesRemises && count($compte->boursesRemises) > 0)
        <div class="section-title">Bourses & Remises Accordées</div>
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 25%;">Intitulé Bourse / Remise</th>
                    <th style="width: 20%;">Type</th>
                    <th style="width: 20%;">Mode de Calcul</th>
                    <th style="width: 15%;">Valeur</th>
                    <th style="width: 20%;">Montant Déduit</th>
                </tr>
            </thead>
            <tbody>
                @foreach($compte->boursesRemises as $br)
                    <tr>
                        <td><strong>{{ $br->bourseRemise->nom ?? 'Remise' }}</strong></td>
                        <td style="text-align: center;">{{ ucfirst($br->bourseRemise->type ?? 'remise') }}</td>
                        <td style="text-align: center;">{{ $br->bourseRemise->mode_calcul === 'pourcentage' ? 'Pourcentage (%)' : 'Montant Fixe' }}</td>
                        <td style="text-align: right;">{{ $br->bourseRemise->mode_calcul === 'pourcentage' ? $br->bourseRemise->valeur . ' %' : number_format($br->bourseRemise->valeur, 0, ',', ' ') . ' FCFA' }}</td>
                        <td style="text-align: right; font-weight: bold; color: #047857;">- {{ number_format($br->montant_deduit, 0, ',', ' ') }} FCFA</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <!-- Tableau Historique des Versements -->
    <div class="section-title">Historique des Règlements & Encaissements</div>
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 15%;">Date</th>
                <th style="width: 18%;">Reçu N°</th>
                <th style="width: 22%;">Caisse</th>
                <th style="width: 15%;">Mode Règlement</th>
                <th style="width: 15%;">Montant Versé</th>
                <th style="width: 15%;">Statut</th>
            </tr>
        </thead>
        <tbody>
            @forelse($compte->versements ?? [] as $v)
                <tr>
                    <td style="text-align: center;">{{ \Carbon\Carbon::parse($v->date_versement)->format('d-m-Y') }}</td>
                    <td style="text-align: center; font-family: monospace; font-weight: bold;">{{ $v->numero_recu }}</td>
                    <td>{{ $v->caisse->nom ?? 'Caisse Principale' }}</td>
                    <td style="text-align: center;">{{ $v->modePaiement->libelle ?? 'Espèces' }}</td>
                    <td style="text-align: right; font-weight: bold; color: #1e3a8a;">{{ number_format($v->montant, 0, ',', ' ') }} FCFA</td>
                    <td style="text-align: center; color: #15803d; font-weight: bold;">Validé</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" style="text-align: center; padding: 12px; color: #64748b;">
                        Aucun versement enregistré à ce jour pour cette inscription.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <!-- Signatures -->
    <table class="sign-table">
        <tr>
            <td>
                <div class="sign-role">Le Parent d'Élève / Le Payeur</div>
                <div>Pour acquit et prise de connaissance</div>
            </td>
            <td>
                <div class="sign-role">L'Intendant / Caissier Général</div>
                <div>Signature & Cachet de l'Intendance</div>
            </td>
        </tr>
    </table>

</body>
</html>
