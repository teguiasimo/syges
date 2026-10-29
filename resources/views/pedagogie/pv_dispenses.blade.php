<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Procès-Verbal Officiel des Dispenses & Dérogations</title>
    <style>
        @page {
            size: A4 landscape;
            margin: 10mm 12mm 12mm 12mm;
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
        .pv-banner {
            border: 1.5px solid #1e3a8a;
            border-radius: 4px;
            padding: 8px 12px;
            text-align: center;
            background-color: #f8fafc;
            margin: 10px 0 15px 0;
        }
        .pv-title {
            font-size: 13pt;
            font-weight: 800;
            color: #1e3a8a;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .pv-sub {
            font-size: 8.5pt;
            color: #475569;
            margin-top: 2px;
            font-weight: 600;
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 8pt;
            margin-bottom: 20px;
        }
        .data-table th {
            background-color: #1e3a8a;
            color: #ffffff;
            font-weight: bold;
            padding: 5px 6px;
            border: 1px solid #0f172a;
            text-align: left;
            text-transform: uppercase;
            font-size: 7.5pt;
        }
        .data-table td {
            padding: 5px 6px;
            border: 1px solid #cbd5e1;
            vertical-align: middle;
        }
        .data-table tr:nth-child(even) {
            background-color: #f8fafc;
        }
        .badge {
            display: inline-block;
            padding: 2px 5px;
            font-size: 7pt;
            font-weight: bold;
            border-radius: 3px;
        }
        .badge-medical {
            background-color: #e0f2fe;
            color: #0369a1;
            border: 1px solid #bae6fd;
        }
        .badge-success {
            background-color: #dcfce7;
            color: #15803d;
            border: 1px solid #bbf7d0;
        }
        .signatures-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
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
            margin-bottom: 50px;
            text-transform: uppercase;
            font-size: 8pt;
        }
        .sig-name {
            font-weight: bold;
            color: #1e3a8a;
        }
    </style>
</head>
<body>

    <!-- En-tête officiel bilingue camerounais -->
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
    <div class="pv-banner">
        <div class="pv-title">PROCÈS-VERBAL OFFICIEL DES DISPENSES & DÉROGATIONS</div>
        <div class="pv-sub">
            Année Scolaire : {{ $annee->session ?? ($annee->code ?? $annee->nom) }} 
            @if(isset($classe)) | Classe : {{ $classe->nom }} @endif
            | Date d'édition : {{ $date_generation }}
        </div>
    </div>

    <!-- Registre officiel -->
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 25px; text-align: center;">N°</th>
                <th style="width: 80px;">Matricule</th>
                <th style="width: 160px;">Nom & Prénoms de l'Élève</th>
                <th style="width: 65px;">Classe</th>
                <th style="width: 100px;">Matière Dispensée</th>
                <th style="width: 85px;">Type</th>
                <th>Motif Circonstancié</th>
                <th style="width: 100px;">Réf. Certificat</th>
                <th style="width: 110px;">Médecin / Hôpital</th>
                <th style="width: 85px;">Période</th>
                <th style="width: 55px; text-align: center;">Coef.</th>
            </tr>
        </thead>
        <tbody>
            @forelse($dispenses as $index => $dispense)
                <tr>
                    <td style="text-align: center; font-weight: bold;">{{ $index + 1 }}</td>
                    <td style="font-family: monospace; font-weight: bold;">{{ $dispense->eleve->matricule }}</td>
                    <td style="font-weight: 600;">{{ $dispense->eleve->nom }} {{ $dispense->eleve->prenom }}</td>
                    <td>{{ $dispense->inscription->classe->nom ?? '-' }}</td>
                    <td style="font-weight: bold; color: #1e3a8a;">{{ $dispense->matiere->nom }} ({{ $dispense->matiere->code }})</td>
                    <td>
                        <span class="badge badge-medical">
                            {{ ucfirst($dispense->type_dispense) }}
                        </span>
                    </td>
                    <td>{{ $dispense->motif }}</td>
                    <td style="font-family: monospace;">{{ $dispense->reference_certificat ?: '-' }}</td>
                    <td>{{ $dispense->medecin_ou_autorite ?: '-' }}</td>
                    <td>{{ $dispense->periode ? $dispense->periode->nom : 'Toute l\'année' }}</td>
                    <td style="text-align: center;">
                        <span class="badge badge-success">
                            {{ $dispense->neutraliser_coefficient ? '0 (DISP)' : 'Normal' }}
                        </span>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="11" style="text-align: center; padding: 25px; color: #64748b;">
                        Aucune dispense officielle enregistrée pour cette sélection.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <!-- Tableau des signatures officielles -->
    <table class="signatures-table">
        <tr>
            <td>
                <div class="sig-title">Le Chef de Département EPS</div>
                <div class="sig-name">[ Signature & Cachet ]</div>
            </td>
            <td>
                <div class="sig-title">L'Infirmier / Médecin Scolaire</div>
                <div class="sig-name">[ Visa Médical ]</div>
            </td>
            <td>
                <div class="sig-title">Le Chef d'Établissement (Proviseur / Principal)</div>
                <div class="sig-name">{{ $etablissement->nom_chef_etablissement ?? 'La Direction' }}</div>
            </td>
        </tr>
    </table>

</body>
</html>
