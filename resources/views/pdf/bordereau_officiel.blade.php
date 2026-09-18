<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Bordereau Officiel des Examens - {{ $classe ? $classe->nom : $etablissement->nom }}</title>
    <style>
        @page {
            size: A4 landscape;
            margin: 8mm 10mm 8mm 10mm;
        }
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 8pt;
            color: #111827;
            line-height: 1.2;
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
            text-align: center;
        }
        .bold { font-weight: bold; }
        .uppercase { text-transform: uppercase; }
        .text-left { text-align: left; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        
        .main-title {
            background-color: #f3f4f6;
            border: 1.5px solid #1f2937;
            padding: 5px;
            text-align: center;
            font-weight: bold;
            font-size: 10pt;
            text-transform: uppercase;
            margin-bottom: 8px;
            border-radius: 4px;
        }
        
        .meta-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8px;
            font-size: 8.5pt;
        }
        .meta-table td {
            padding: 2px 4px;
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 7.5pt;
            margin-bottom: 12px;
        }
        .data-table th, .data-table td {
            border: 1px solid #374151;
            padding: 4px 5px;
        }
        .data-table th {
            background-color: #e5e7eb;
            font-weight: bold;
            text-align: center;
            text-transform: uppercase;
        }
        .data-table tr:nth-child(even) {
            background-color: #f9fafb;
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
            font-size: 8pt;
        }
        .signature-box {
            height: 60px;
        }
    </style>
</head>
<body>
    <!-- En-tête officiel MINESEC / MINEDUB -->
    <table class="header-table">
        <tr>
            <td style="width: 40%;" class="text-left">
                <div class="bold uppercase" style="font-size: 7.5pt;">RÉPUBLIQUE DU CAMEROUN</div>
                <div style="font-size: 6.5pt; font-style: italic;">Paix – Travail – Patrie</div>
                <div class="uppercase" style="font-size: 6.5pt;">MINISTÈRE DES ENSEIGNEMENTS SECONDAIRES</div>
                <div class="uppercase" style="font-size: 6.5pt;">{{ $etablissement->institution?->delegation_regionale ?? 'DÉLÉGATION RÉGIONALE DES ENSEIGNEMENTS SECONDAIRES' }}</div>
                <div class="bold uppercase" style="font-size: 8pt; color: #1e3a8a; margin-top: 3px;">{{ $etablissement->nom }}</div>
                <div style="font-size: 6.5pt;">Code Établissement : <strong>{{ $etablissement->code ?? 'N/A' }}</strong></div>
            </td>
            <td style="width: 20%;" class="text-center">
                @if($etablissement->logo_url)
                    <img src="{{ public_path($etablissement->logo_url) }}" style="max-height: 45px;" alt="Logo">
                @endif
            </td>
            <td style="width: 40%;" class="text-right">
                <div class="bold uppercase" style="font-size: 7.5pt;">REPUBLIC OF CAMEROON</div>
                <div style="font-size: 6.5pt; font-style: italic;">Peace – Work – Fatherland</div>
                <div class="uppercase" style="font-size: 6.5pt;">MINISTRY OF SECONDARY EDUCATION</div>
                <div class="uppercase" style="font-size: 6.5pt;">{{ $etablissement->institution?->delegation_regionale_en ?? 'REGIONAL DELEGATION OF SECONDARY EDUCATION' }}</div>
                <div style="font-size: 7pt; margin-top: 4px;">Année Scolaire : <strong>{{ $annee ? $annee->nom : '2026-2027' }}</strong></div>
                <div style="font-size: 6.5pt; color: #4b5563;">Date d'édition : {{ $dateGeneration }}</div>
            </td>
        </tr>
    </table>

    <!-- Titre du document -->
    <div class="main-title">
        BORDEREAU OFFICIEL D'INSCRIPTION AUX EXAMENS OFFICIELS & LISTE D'EFFECTIF ALPHABÉTIQUE
    </div>

    <!-- Méta-informations -->
    <table class="meta-table">
        <tr>
            <td style="width: 35%;">Classe : <strong>{{ $classe ? $classe->nom : 'Toutes les classes' }}</strong></td>
            <td style="width: 35%;">Série / Spécialité : <strong>{{ $classe && $classe->serie ? $classe->serie : 'Enseignement Général' }}</strong></td>
            <td style="width: 30%; text-align: right;">Effectif total listé : <strong>{{ count($eleves) }} candidat(s)</strong></td>
        </tr>
    </table>

    <!-- Tableau officiel des élèves -->
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 3%;">N°</th>
                <th style="width: 11%;">Matricule Interne</th>
                <th style="width: 14%;">Matricule Ministère</th>
                <th style="width: 25%;">Nom(s) et Prénom(s)</th>
                <th style="width: 5%;">Sexe</th>
                <th style="width: 10%;">Date Naiss.</th>
                <th style="width: 12%;">Lieu de Naissance</th>
                <th style="width: 8%;">Statut</th>
                <th style="width: 12%;">Émargement Candidat</th>
            </tr>
        </thead>
        <tbody>
            @forelse($eleves as $el)
                <tr>
                    <td class="text-center">{{ $el['rang_alphabetique'] }}</td>
                    <td class="text-center bold">{{ $el['matricule'] }}</td>
                    <td class="text-center bold" style="color: #1e3a8a;">{{ $el['matricule_ministere'] }}</td>
                    <td class="bold">{{ $el['nom_complet'] }}</td>
                    <td class="text-center">{{ $el['sexe'] }}</td>
                    <td class="text-center">{{ $el['date_naissance'] }}</td>
                    <td>{{ $el['lieu_naissance'] }}</td>
                    <td class="text-center">{{ $el['statut_candidat'] }}</td>
                    <td></td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" class="text-center" style="padding: 15px;">Aucun élève trouvé pour ces critères.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <!-- Visas et signatures -->
    <table class="signatures-table">
        <tr>
            <td>
                <div class="bold uppercase">Le Censeur / Dir. Études</div>
                <div class="signature-box"></div>
                <div>(Nom, Date & Signature)</div>
            </td>
            <td>
                <div class="bold uppercase">Le Professeur Principal</div>
                <div class="signature-box"></div>
                <div>(Nom, Date & Signature)</div>
            </td>
            <td>
                <div class="bold uppercase">Le Chef d'Établissement</div>
                <div class="signature-box"></div>
                <div>(Signature & Sceau officiel)</div>
            </td>
        </tr>
    </table>
</body>
</html>
