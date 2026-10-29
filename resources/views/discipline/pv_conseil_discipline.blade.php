<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Procès-Verbal du Conseil de Discipline - {{ $conseil->numero_session }}</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 12mm 15mm 15mm 15mm;
        }
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 8.5pt;
            color: #0f172a;
            line-height: 1.35;
            margin: 0;
            padding: 0;
        }
        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
        }
        .header-table td {
            vertical-align: top;
        }
        .header-left, .header-right {
            width: 38%;
            font-size: 7pt;
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
            margin-top: 4px;
        }
        .school-sub {
            font-size: 7pt;
            color: #475569;
        }
        .banner {
            border: 1.5px solid #1e3a8a;
            border-radius: 4px;
            padding: 8px;
            text-align: center;
            background-color: #f8fafc;
            margin: 10px 0 15px 0;
        }
        .banner-title {
            font-size: 12.5pt;
            font-weight: 800;
            color: #1e3a8a;
            text-transform: uppercase;
        }
        .banner-sub {
            font-size: 8pt;
            color: #475569;
            margin-top: 2px;
            font-weight: 600;
        }
        .meta-box {
            background-color: #f1f5f9;
            border-left: 3px solid #1e3a8a;
            padding: 8px 10px;
            font-size: 8pt;
            margin-bottom: 15px;
        }
        .meta-box td {
            padding: 2px 6px;
        }
        .table-data {
            width: 100%;
            border-collapse: collapse;
            font-size: 8pt;
            margin-bottom: 20px;
        }
        .table-data th {
            background-color: #1e3a8a;
            color: #ffffff;
            font-weight: bold;
            padding: 5px 6px;
            border: 1px solid #0f172a;
            text-align: left;
            text-transform: uppercase;
            font-size: 7.5pt;
        }
        .table-data td {
            padding: 5px 6px;
            border: 1px solid #cbd5e1;
            vertical-align: top;
        }
        .table-data tr:nth-child(even) {
            background-color: #f8fafc;
        }
        .badge {
            display: inline-block;
            padding: 2px 4px;
            font-size: 7pt;
            font-weight: bold;
            border-radius: 3px;
        }
        .badge-danger {
            background-color: #fee2e2;
            color: #b91c1c;
            border: 1px solid #fca5a5;
        }
        .badge-warning {
            background-color: #fef3c7;
            color: #b45309;
            border: 1px solid #fde68a;
        }
        .badge-success {
            background-color: #dcfce7;
            color: #15803d;
            border: 1px solid #bbf7d0;
        }
        .signatures-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            page-break-inside: avoid;
        }
        .signatures-table td {
            width: 33.33%;
            text-align: center;
            vertical-align: top;
            font-size: 8pt;
        }
        .sig-title {
            font-weight: bold;
            text-decoration: underline;
            margin-bottom: 45px;
            text-transform: uppercase;
        }
    </style>
</head>
<body>

    <!-- En-tête officiel camerounais -->
    <table class="header-table">
        <tr>
            <td class="header-left">
                <strong>RÉPUBLIQUE DU CAMEROUN</strong><br>
                <em>Paix - Travail - Patrie</em><br>
                MINISTÈRE DES ENSEIGNEMENTS SECONDAIRES<br>
                {{ $institution->delegation_regionale ?? 'DÉLÉGATION RÉGIONALE DU CENTRE' }}<br>
                {{ $institution->delegation_departementale ?? 'DÉLÉGATION DÉPARTEMENTALE DU MFOUNDI' }}
            </td>
            <td class="header-center">
                @php
                    $logoSrc = ($etablissement && method_exists($etablissement, 'getEffectiveLogoBase64'))
                        ? $etablissement->getEffectiveLogoBase64()
                        : (file_exists(public_path('logo.png')) ? 'data:image/png;base64,' . base64_encode(file_get_contents(public_path('logo.png'))) : null);
                @endphp
                @if($logoSrc)
                    <img src="{{ $logoSrc }}" alt="Logo" style="max-height: 48px; max-width: 65px; object-fit: contain; margin-bottom: 2px;"><br>
                @endif
                <div class="school-name">{{ $etablissement->nom }}</div>
                <div class="school-sub">{{ $etablissement->devise ?? 'Discipline - Travail - Succès' }}</div>
                <div class="school-sub">{{ $etablissement->adresse ?? '' }} - Tél: {{ $etablissement->telephone_1 ?? '' }}</div>
            </td>
            <td class="header-right">
                <strong>REPUBLIC OF CAMEROON</strong><br>
                <em>Peace - Work - Fatherland</em><br>
                MINISTRY OF SECONDARY EDUCATION<br>
                {{ $institution->delegation_regionale_en ?? 'REGIONAL DELEGATION FOR THE CENTRE' }}<br>
                {{ $institution->delegation_departementale_en ?? 'DIVISIONAL DELEGATION FOR MFOUNDI' }}
            </td>
        </tr>
    </table>

    <!-- Bannière de titre -->
    <div class="banner">
        <div class="banner-title">PROCÈS-VERBAL OFFICIEL DU CONSEIL DE DISCIPLINE</div>
        <div class="banner-sub">
            SESSION N° : {{ $conseil->numero_session }} | ANNÉE SCOLAIRE : {{ $conseil->anneeScolaire->session ?? ($conseil->anneeScolaire->code ?? $conseil->anneeScolaire->nom) }}
        </div>
    </div>

    <!-- Synthèse de séance -->
    <div class="meta-box">
        <table style="width: 100%;">
            <tr>
                <td style="width: 25%;"><strong>Date & Heure :</strong></td>
                <td style="width: 35%;">Le {{ $conseil->date_conseil->format('d/m/Y à H:i') }}</td>
                <td style="width: 20%;"><strong>Lieu :</strong></td>
                <td style="width: 20%;">{{ $conseil->lieu }}</td>
            </tr>
            <tr>
                <td><strong>Président de séance :</strong></td>
                <td>{{ $conseil->president_seance }}</td>
                <td><strong>Rapporteur :</strong></td>
                <td>{{ $conseil->rapporteur }}</td>
            </tr>
            @if($conseil->membres_presents)
            <tr>
                <td colspan="4" style="padding-top: 4px;">
                    <strong>Membres du Conseil présents :</strong> {{ $conseil->membres_presents }}
                </td>
            </tr>
            @endif
        </table>
    </div>

    <p style="font-size: 8.5pt; text-align: justify; margin-bottom: 12px;">
        L'an deux mille vingt-six et à la date indiquée ci-dessus, s'est réuni le Conseil de Discipline de l'établissement sus-désigné pour statuer sur les fautes disciplinaires graves commises par les élèves ci-après mentionnés. Après lecture des rapports, audition contradictoire des élèves et de leurs représentants légaux, et délibération à huis clos, les décisions suivantes ont été souverainement arrêtées :
    </p>

    <!-- Tableau des dossiers et décisions -->
    <table class="table-data">
        <thead>
            <tr>
                <th style="width: 20px; text-align: center;">N°</th>
                <th style="width: 80px;">Matricule</th>
                <th style="width: 130px;">Élève & Classe</th>
                <th>Motifs & Faits Reprochés</th>
                <th style="width: 90px; text-align: center;">Présence Parent</th>
                <th style="width: 130px;">Décision & Sanction Prononcée</th>
            </tr>
        </thead>
        <tbody>
            @forelse($conseil->dossiers as $idx => $dossier)
                <tr>
                    <td style="text-align: center; font-weight: bold;">{{ $idx + 1 }}</td>
                    <td style="font-family: monospace; font-weight: bold;">{{ $dossier->eleve->matricule }}</td>
                    <td>
                        <strong>{{ $dossier->eleve->nom }} {{ $dossier->eleve->prenom }}</strong><br>
                        <span style="color: #64748b; font-size: 7.5pt;">Classe : {{ $dossier->inscription->classe->nom ?? '-' }}</span>
                    </td>
                    <td>
                        {{ $dossier->motifs_renvoi }}
                        @if($dossier->plaidoirie_eleve)
                            <div style="font-style: italic; color: #475569; font-size: 7.5pt; margin-top: 3px;">
                                <strong>Plaidoirie :</strong> {{ $dossier->plaidoirie_eleve }}
                            </div>
                        @endif
                    </td>
                    <td style="text-align: center;">
                        {{ $dossier->parent_present ? 'OUI' : 'NON' }}
                    </td>
                    <td>
                        @php
                            $dec = $dossier->decision_finale;
                        @endphp
                        @if($dec === 'exclusion_definitive')
                            <span class="badge badge-danger">EXCLUSION DÉFINITIVE</span>
                        @elseif($dec === 'exclusion_temporaire')
                            <span class="badge badge-danger">EXCLUSION ({{ $dossier->nombre_jours_exclusion }} JOURS)</span>
                        @elseif($dec === 'consigne')
                            <span class="badge badge-warning">CONSIGNE ({{ $dossier->nombre_heures_consigne }} HEURES)</span>
                        @elseif($dec === 'blame')
                            <span class="badge badge-warning">BLÂME DE CONDUITE</span>
                        @elseif($dec === 'avertissement')
                            <span class="badge badge-warning">AVERTISSEMENT</span>
                        @elseif($dec === 'acquittement')
                            <span class="badge badge-success">ACQUITTÉ(E)</span>
                        @else
                            <span style="color: #64748b; font-style: italic;">En délibération</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" style="text-align: center; padding: 20px; color: #64748b;">
                        Aucun dossier d'élève examiné lors de cette session.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    @if($conseil->observations_generales)
        <div style="margin-bottom: 15px; font-size: 8pt; padding: 6px 10px; background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 3px;">
            <strong>Observations & Recommandations générales du Conseil :</strong><br>
            {{ $conseil->observations_generales }}
        </div>
    @endif

    <!-- Signatures officielles -->
    <table class="signatures-table">
        <tr>
            <td>
                <strong>Le Rapporteur</strong><br><br><br><br>
                <strong>{{ $conseil->rapporteur }}</strong>
            </td>
            <td>
                <strong>Le Représentant des Parents (APEE)</strong><br><br><br><br>
                [ Signature & Nom ]
            </td>
            <td>
                <strong>Le Président du Conseil de Discipline</strong><br><br><br><br>
                <strong>{{ $conseil->president_seance }}</strong>
            </td>
        </tr>
    </table>

</body>
</html>
