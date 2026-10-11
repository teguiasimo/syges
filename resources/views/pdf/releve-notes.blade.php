<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Relevé de Notes - {{ $attribution->classe?->nom }} - {{ $attribution->matiere?->nom }}</title>
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
            margin-bottom: 6px;
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
            padding: 4px;
            text-align: center;
            font-weight: bold;
            font-size: 10pt;
            text-transform: uppercase;
            margin-bottom: 6px;
            border-radius: 3px;
            letter-spacing: 0.5px;
        }

        .meta-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8px;
            font-size: 8.5pt;
            background-color: #fafafa;
            border: 1px solid #d1d5db;
        }
        .meta-table td {
            padding: 3px 6px;
            border: 1px solid #e5e7eb;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 7.5pt;
            margin-bottom: 10px;
        }
        .data-table th, .data-table td {
            border: 1px solid #374151;
            padding: 3px 4px;
        }
        .data-table th {
            background-color: #e5e7eb;
            font-weight: bold;
            text-align: center;
            text-transform: uppercase;
            font-size: 7pt;
        }
        .data-table tr:nth-child(even) {
            background-color: #f9fafb;
        }

        .signatures-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        .signatures-table td {
            width: 33.33%;
            text-align: center;
            vertical-align: top;
            font-size: 8pt;
        }
        .signature-box {
            min-height: 45px;
        }
        .footer-note {
            font-size: 6.5pt;
            color: #6b7280;
            text-align: right;
            margin-top: 4px;
        }
        .badge {
            display: inline-block;
            padding: 1px 4px;
            font-size: 6.5pt;
            font-weight: bold;
            border-radius: 2px;
            border: 1px solid #9ca3af;
        }
    </style>
</head>
<body>

    <!-- En-tête officiel bilingue de l'établissement -->
    <table class="header-table">
        <tr>
            <td style="width: 35%;" class="text-center">
                <span class="bold uppercase" style="font-size: 7pt;">RÉPUBLIQUE DU CAMEROUN</span><br>
                <span style="font-size: 6pt;">Paix – Travail – Patrie</span><br>
                <span class="bold uppercase" style="font-size: 6.5pt;">MINISTÈRE DES ENSEIGNEMENTS SECONDAIRES</span><br>
                <span class="bold uppercase" style="font-size: 7pt; color: #1e3a8a;">{{ $etablissement->nom ?? 'SYGES ÉTABLISSEMENT' }}</span>
                @if($etablissement && $etablissement->telephone)
                    <br><span style="font-size: 6pt;">Tél : {{ $etablissement->telephone }}</span>
                @endif
            </td>
            <td style="width: 30%;" class="text-center">
                @if($etablissement && $etablissement->logo_path)
                    <img src="{{ public_path('storage/' . $etablissement->logo_path) }}" alt="Logo" style="max-height: 45px; max-width: 90px;">
                @else
                    <div style="font-size: 14pt; font-weight: bold; color: #1e3a8a; letter-spacing: 2px;">SYGES</div>
                @endif
                <div style="font-size: 7pt; font-weight: bold; margin-top: 2px;">Année Scolaire {{ $anneeScolaire->nom ?? $anneeScolaire->code }}</div>
            </td>
            <td style="width: 35%;" class="text-center">
                <span class="bold uppercase" style="font-size: 7pt;">REPUBLIC OF CAMEROON</span><br>
                <span style="font-size: 6pt;">Peace – Work – Fatherland</span><br>
                <span class="bold uppercase" style="font-size: 6.5pt;">MINISTRY OF SECONDARY EDUCATION</span><br>
                <span class="bold uppercase" style="font-size: 6.5pt;">Délégation Régionale des Enseignements</span><br>
                <span style="font-size: 6pt;">Code Établissement : {{ $etablissement->code ?? 'ETAB' }}</span>
            </td>
        </tr>
    </table>

    <!-- Titre du Document -->
    <div class="main-title">
        RELEVÉ DE NOTES & FICHE OFFICIELLE DE NOTATION
        @if($mode === 'vierge')
            <span style="color: #b45309;">(FICHE DE SAISIE MANUSCRITE VIERGE)</span>
        @else
            <span style="color: #047857;">(RÉSULTATS ENREGISTRÉS)</span>
        @endif
    </div>

    <!-- Bannière des métadonnées du cours -->
    <table class="meta-table">
        <tr>
            <td style="width: 25%;">
                <span class="bold">Classe :</span> {{ $attribution->classe?->nom }}
            </td>
            <td style="width: 35%;">
                <span class="bold">Discipline / Matière :</span> {{ $attribution->matiere?->nom }} ({{ $attribution->matiere?->code }})
            </td>
            <td style="width: 40%;">
                <span class="bold">Enseignant :</span>
                {{ $attribution->personnel ? ($attribution->personnel->nom . ' ' . $attribution->personnel->prenom) : 'Non assigné' }}
            </td>
        </tr>
        <tr>
            <td>
                <span class="bold">Période :</span> {{ $titrePeriode }}
            </td>
            <td>
                <span class="bold">Effectif Classe :</span> {{ $stats['total'] }} élèves
                (Filles : {{ $stats['filles'] }} | Garçons : {{ $stats['garcons'] }})
            </td>
            <td>
                <span class="bold">Volume Horaire Hebdo :</span> {{ $attribution->volume_horaire_reel ?? 0 }} h/semaine
            </td>
        </tr>
    </table>

    <!-- Tableau TanStack / Grille des Élèves et Notes -->
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 3%;">N°</th>
                <th style="width: 10%;">Matricule</th>
                <th style="width: 28%;" class="text-left">Noms et Prénoms de l'Élève</th>
                <th style="width: 4%;">Sexe</th>
                @foreach($evaluations as $eval)
                    <th style="width: {{ count($evaluations) > 2 ? '9%' : '14%' }};">
                        {{ $eval->nom }}<br>
                        <span style="font-weight: normal; font-size: 6pt;">/ 20</span>
                    </th>
                @endforeach
                @if(count($evaluations) > 1)
                    <th style="width: 9%;">
                        Moyenne<br>
                        <span style="font-weight: normal; font-size: 6pt;">/ 20</span>
                    </th>
                @endif
                <th style="width: 14%;">Appréciation / Compétence</th>
                <th style="width: 10%;">Émargement</th>
            </tr>
        </thead>
        <tbody>
            @forelse($eleves as $eleve)
                <tr>
                    <td class="text-center bold">{{ $eleve['numero'] }}</td>
                    <td class="text-center font-mono">{{ $eleve['matricule'] }}</td>
                    <td class="bold uppercase text-left">{{ $eleve['nom_complet'] }}</td>
                    <td class="text-center">{{ $eleve['sexe'] }}</td>
                    @foreach($evaluations as $eval)
                        <td class="text-center bold" style="font-size: 8pt; height: 16px;">
                            @if($mode === 'rempli')
                                {{ $eleve['notes'][$eval->id]['valeur'] ?? '-' }}
                            @else
                                &nbsp;
                            @endif
                        </td>
                    @endforeach
                    @if(count($evaluations) > 1)
                        <td class="text-center bold" style="background-color: #f3f4f6;">
                            @if($mode === 'rempli')
                                {{ $eleve['moyenne'] }}
                            @else
                                &nbsp;
                            @endif
                        </td>
                    @endif
                    <td class="text-left" style="font-size: 6.5pt;">&nbsp;</td>
                    <td>&nbsp;</td>
                </tr>
            @empty
                <tr>
                    <td colspan="{{ 6 + count($evaluations) }}" class="text-center" style="padding: 15px;">
                        Aucun élève actif inscrit dans cette classe pour l'année scolaire sélectionnée.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <!-- Bloc des Signatures officielles -->
    <table class="signatures-table">
        <tr>
            <td>
                <span class="bold">L'Enseignant Titulaire du Cours</span><br>
                <span style="font-size: 6.5pt;">Date et Signature</span>
                <div class="signature-box"></div>
                <span style="font-size: 7pt; font-style: italic;">
                    {{ $attribution->personnel ? ($attribution->personnel->nom . ' ' . $attribution->personnel->prenom) : '' }}
                </span>
            </td>
            <td>
                <span class="bold">Le Professeur Principal</span><br>
                <span style="font-size: 6.5pt;">Visa & Observations</span>
                <div class="signature-box"></div>
            </td>
            <td>
                <span class="bold">Le Censeur / La Direction des Études</span><br>
                <span style="font-size: 6.5pt;">Contrôle et Visa Officiel</span>
                <div class="signature-box"></div>
            </td>
        </tr>
    </table>

    <div class="footer-note">
        SYGES Cloud Platform • Édité le {{ $dateImpression }} • Conforme au Référentiel Pédagogique MINESEC Gesco V1.1
    </div>

</body>
</html>
