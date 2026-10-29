<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Fiche de Progression - {{ $attribution->classe->nom }} - {{ $attribution->matiere->nom }}</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 10mm 12mm 12mm 12mm;
        }
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 8.5pt;
            color: #1e293b;
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
        .school-sub {
            font-size: 7pt;
            color: #64748b;
        }
        .title-banner {
            background-color: #f8fafc;
            border: 1.5px solid #1e3a8a;
            border-radius: 4px;
            padding: 7px 10px;
            margin: 8px 0 10px 0;
            text-align: center;
        }
        .title-main {
            font-size: 11.5pt;
            font-weight: bold;
            color: #1e3a8a;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .title-sub {
            font-size: 8pt;
            color: #475569;
            margin-top: 2px;
        }
        .meta-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
            background-color: #f1f5f9;
            border-radius: 4px;
            border: 1px solid #cbd5e1;
        }
        .meta-table td {
            padding: 5px 8px;
            font-size: 8.5pt;
        }
        .meta-label {
            font-weight: bold;
            color: #334155;
            width: 18%;
        }
        .meta-val {
            color: #0f172a;
            width: 32%;
        }
        .progression-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
        }
        .progression-table th {
            background-color: #1e3a8a;
            color: #ffffff;
            font-weight: bold;
            font-size: 7.5pt;
            padding: 6px 4px;
            text-align: center;
            border: 1px solid #1e3a8a;
            text-transform: uppercase;
        }
        .progression-table td {
            border: 1px solid #cbd5e1;
            padding: 5px 4px;
            font-size: 7.5pt;
            vertical-align: top;
        }
        .progression-table tr:nth-child(even) {
            background-color: #f8fafc;
        }
        .badge {
            display: inline-block;
            padding: 2px 5px;
            border-radius: 3px;
            font-size: 6.5pt;
            font-weight: bold;
            text-transform: uppercase;
            text-align: center;
        }
        .badge-fait {
            background-color: #dcfce7;
            color: #15803d;
            border: 0.5px solid #86efac;
        }
        .badge-en-cours {
            background-color: #fef9c3;
            color: #854d0e;
            border: 0.5px solid #fde047;
        }
        .badge-reporte {
            background-color: #fee2e2;
            color: #b91c1c;
            border: 0.5px solid #fca5a5;
        }
        .visa-ok {
            color: #15803d;
            font-weight: bold;
            font-size: 7pt;
        }
        .visa-none {
            color: #94a3b8;
            font-style: italic;
            font-size: 7pt;
        }
        .summary-box {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
            border: 1px solid #94a3b8;
        }
        .summary-box td {
            padding: 6px 8px;
            font-size: 8pt;
            text-align: center;
            background-color: #f8fafc;
        }
        .summary-title {
            font-weight: bold;
            color: #475569;
            font-size: 7pt;
            text-transform: uppercase;
        }
        .summary-val {
            font-size: 11pt;
            font-weight: bold;
            color: #1e3a8a;
            margin-top: 2px;
        }
        .signatures-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        .signatures-table td {
            width: 33.33%;
            text-align: center;
            vertical-align: top;
            padding: 5px;
        }
        .sign-title {
            font-weight: bold;
            font-size: 8pt;
            color: #334155;
            text-transform: uppercase;
            margin-bottom: 45px;
        }
        .sign-name {
            font-size: 7.5pt;
            color: #64748b;
            border-top: 1px dotted #94a3b8;
            padding-top: 4px;
            display: inline-block;
            width: 80%;
        }
        .footer-note {
            margin-top: 15px;
            font-size: 6.5pt;
            color: #94a3b8;
            text-align: center;
            border-top: 0.5px solid #e2e8f0;
            padding-top: 4px;
        }
    </style>
</head>
<body>

    <!-- En-tête officiel bilingue -->
    <table class="header-table">
        <tr>
            <td class="header-left">
                <strong>RÉPUBLIQUE DU CAMEROUN</strong><br>
                <em>Paix – Travail – Patrie</em><br>
                MINISTÈRE DES ENSEIGNEMENTS SECONDAIRES<br>
                DÉLÉGATION RÉGIONALE DU CENTRE
            </td>
            <td class="header-center">
                @php
                    $logoSrc = ($etablissement && method_exists($etablissement, 'getEffectiveLogoBase64'))
                        ? $etablissement->getEffectiveLogoBase64()
                        : (file_exists(public_path('logo.png')) ? 'data:image/png;base64,' . base64_encode(file_get_contents(public_path('logo.png'))) : null);
                @endphp
                @if($logoSrc)
                    <img src="{{ $logoSrc }}" alt="Logo" style="max-height: 48px; max-width: 60px; object-fit: contain;">
                @else
                    <div style="font-size: 22pt; font-weight: bold; color: #1e3a8a;">SYGES</div>
                @endif
            </td>
            <td class="header-right">
                <strong>REPUBLIC OF CAMEROON</strong><br>
                <em>Peace – Work – Fatherland</em><br>
                MINISTRY OF SECONDARY EDUCATION<br>
                REGIONAL DELEGATION OF CENTRE
            </td>
        </tr>
    </table>

    <div style="text-align: center; margin-bottom: 6px;">
        <div class="school-name">{{ $etablissement->nom ?? 'ÉTABLISSEMENT SCOLAIRE' }}</div>
        <div class="school-sub">
            BP {{ $etablissement->boite_postale ?? '---' }} &bull; Tél: {{ $etablissement->telephone ?? '---' }} &bull; Email: {{ $etablissement->email ?? '---' }}
        </div>
    </div>

    <!-- Bannière de titre -->
    <div class="title-banner">
        <div class="title-main">Fiche de Progression Pédagogique & Cahier de Texte</div>
        <div class="title-sub">Année Scolaire : <strong>{{ $attribution->anneeScolaire->nom ?? '2026-2027' }}</strong> &bull; Conforme aux normes d'inspection pédagogique</div>
    </div>

    <!-- Métadonnées du cours -->
    <table class="meta-table">
        <tr>
            <td class="meta-label">Classe :</td>
            <td class="meta-val"><strong>{{ $attribution->classe->nom }}</strong> ({{ $attribution->classe->code }})</td>
            <td class="meta-label">Discipline / Matière :</td>
            <td class="meta-val"><strong>{{ $attribution->matiere->nom }}</strong></td>
        </tr>
        <tr>
            <td class="meta-label">Enseignant titulaire :</td>
            <td class="meta-val"><strong>{{ $attribution->personnel->nom }} {{ $attribution->personnel->prenom }}</strong> ({{ $attribution->personnel->matricule }})</td>
            <td class="meta-label">Charge hebdomadaire :</td>
            <td class="meta-val"><strong>{{ number_format($attribution->volume_horaire_reel, 2, ',', ' ') }} h / semaine</strong></td>
        </tr>
    </table>

    <!-- Tableau récapitulatif des métriques -->
    <table class="summary-box">
        <tr>
            <td style="border-right: 1px solid #cbd5e1;">
                <div class="summary-title">Séances Consignées</div>
                <div class="summary-val">{{ count($fiches) }}</div>
            </td>
            <td style="border-right: 1px solid #cbd5e1;">
                <div class="summary-title">Heures Planifiées</div>
                <div class="summary-val">{{ number_format($totalPrevues, 1, ',', ' ') }} h</div>
            </td>
            <td style="border-right: 1px solid #cbd5e1;">
                <div class="summary-title">Heures Réalisées</div>
                <div class="summary-val">{{ number_format($totalEffectuees, 1, ',', ' ') }} h</div>
            </td>
            <td>
                <div class="summary-title">Taux de Réalisation</div>
                <div class="summary-val" style="color: {{ $taux >= 80 ? '#15803d' : ($taux >= 50 ? '#d97706' : '#b91c1c') }};">
                    {{ number_format($taux, 1, ',', ' ') }} %
                </div>
            </td>
        </tr>
    </table>

    <!-- Grille détaillée des séances -->
    <table class="progression-table">
        <thead>
            <tr>
                <th style="width: 7%;">Date</th>
                <th style="width: 5%;">Sem.</th>
                <th style="width: 18%;">Chapitre / Module</th>
                <th style="width: 32%;">Leçon & Activités Réalisées</th>
                <th style="width: 6%;">Prév.</th>
                <th style="width: 6%;">Eff.</th>
                <th style="width: 9%;">Statut</th>
                <th style="width: 17%;">Visa Censeur</th>
            </tr>
        </thead>
        <tbody>
            @forelse($fiches as $fiche)
                <tr>
                    <td style="text-align: center; font-weight: bold;">
                        {{ \Carbon\Carbon::parse($fiche->date_seance)->format('d-m-Y') }}
                    </td>
                    <td style="text-align: center;">
                        {{ $fiche->semaine_numero ? 'S' . $fiche->semaine_numero : '-' }}
                    </td>
                    <td>
                        <strong>{{ $fiche->chapitre }}</strong>
                    </td>
                    <td>
                        <div style="font-weight: bold; color: #0f172a;">{{ $fiche->titre_lecon }}</div>
                        @if($fiche->resume_activite)
                            <div style="font-size: 7pt; color: #475569; margin-top: 2px;">{{ $fiche->resume_activite }}</div>
                        @endif
                        @if($fiche->travail_a_faire)
                            <div style="font-size: 6.5pt; color: #2563eb; margin-top: 2px; font-style: italic;">
                                <strong>Devoirs :</strong> {{ $fiche->travail_a_faire }}
                            </div>
                        @endif
                    </td>
                    <td style="text-align: center;">{{ number_format($fiche->heures_prevues, 1) }}h</td>
                    <td style="text-align: center; font-weight: bold;">{{ number_format($fiche->heures_effectuees, 1) }}h</td>
                    <td style="text-align: center;">
                        @if($fiche->statut === 'fait')
                            <span class="badge badge-fait">Fait</span>
                        @elseif($fiche->statut === 'en_cours')
                            <span class="badge badge-en-cours">En cours</span>
                        @else
                            <span class="badge badge-reporte">Reporté</span>
                        @endif
                    </td>
                    <td>
                        @if($fiche->visa_censeur)
                            <div class="visa-ok">&check; Visé le {{ \Carbon\Carbon::parse($fiche->date_visa)->format('d-m-Y') }}</div>
                            @if($fiche->observations_censeur)
                                <div style="font-size: 6.5pt; color: #334155; font-style: italic;">« {{ $fiche->observations_censeur }} »</div>
                            @endif
                        @else
                            <div class="visa-none">Non visé</div>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" style="text-align: center; padding: 15px; color: #64748b;">
                        Aucune séance consignée dans le cahier de texte pour ce cours.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <!-- Blocs de signature tripartite -->
    <table class="signatures-table">
        <tr>
            <td>
                <div class="sign-title">L'Enseignant</div>
                <div class="sign-name">{{ $attribution->personnel->nom }} {{ $attribution->personnel->prenom }}</div>
            </td>
            <td>
                <div class="sign-title">Le Censeur / Dir. Études</div>
                <div class="sign-name">Visa & Observations</div>
            </td>
            <td>
                <div class="sign-title">L'Inspecteur Pédagogique</div>
                <div class="sign-name">Signature & Cachet Officiel</div>
            </td>
        </tr>
    </table>

    <div class="footer-note">
        Document officiel généré le {{ $dateImpression }} par le Système de Gestion Scolaire SYGES &bull; Toute rature annule la validité administrative
    </div>

</body>
</html>
