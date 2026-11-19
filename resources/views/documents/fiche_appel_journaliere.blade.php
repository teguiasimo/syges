<!DOCTYPE html>
<html lang="fr">
<head>
    {{-- Identité visuelle & Favicons officiels SYGES --}}
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}?v=syges3">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}?v=syges3">
    <link rel="shortcut icon" href="{{ asset('favicon.ico') }}?v=syges3">
    <meta charset="UTF-8">
    <title>Fiche d'Appel Journalière - {{ $classe->nom }}</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 7mm 8mm 10mm 8mm;
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
            vertical-align: middle;
        }
        .header-left, .header-right {
            width: 41%;
            font-size: 6.5pt;
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
            font-size: 10.5pt;
            font-weight: bold;
            color: #1e3a8a;
            text-transform: uppercase;
            margin-top: 2px;
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
            padding: 4px 10px;
            margin-bottom: 5px;
            display: table;
            width: 100%;
            box-sizing: border-box;
        }
        .banner-title {
            font-size: 9.5pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.4px;
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
            font-size: 6.8pt;
            border-right: 1px solid #e2e8f0;
            vertical-align: top;
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
            border: 0.7px solid #94a3b8;
            padding: 4px 3px;
        }
        .appel-table th {
            background-color: #e2e8f0;
            color: #1e3a8a;
            font-weight: bold;
            font-size: 7pt;
            text-align: center;
            text-transform: uppercase;
        }
        .col-num {
            width: 5%;
            text-align: center;
            font-weight: bold;
            font-size: 7pt;
        }
        .col-nom {
            width: 39%;
            text-align: left;
            font-size: 7.5pt;
            padding-left: 6px !important;
            padding-right: 4px !important;
            white-space: nowrap;
            overflow: hidden;
        }
        .col-jour {
            width: 8.4%;
            text-align: center;
        }
        .col-obs {
            width: 14%;
            text-align: left;
            font-size: 6.8pt;
        }
        .tr-even {
            background-color: #f8fafc;
        }
        .row-total {
            background-color: #f1f5f9;
            font-weight: bold;
            font-size: 7pt;
        }
        .footer-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        .footer-table td {
            vertical-align: top;
            width: 50%;
            font-size: 7pt;
            text-align: center;
        }
        .sign-title {
            font-weight: bold;
            text-transform: uppercase;
            color: #1e3a8a;
            margin-bottom: 24px;
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
    {{-- Filigrane institutionnel ou campus --}}
    @include('partials.watermark', ['etablissement' => $etablissement ?? null])

    <!-- En-tête bilingue officiel républicain adapté automatiquement au ministère de tutelle de l'établissement -->
    <table class="header-table">
        <tr>
            <td class="header-left">
                <strong>RÉPUBLIQUE DU CAMEROUN</strong><br>
                <em>Paix – Travail – Patrie</em><br>
                {{ $etablissement?->ministere_tutelle ?? 'MINISTÈRE DE L\'ÉDUCATION DE BASE' }}<br>
                @if($etablissement?->delegation_regionale)
                    {{ mb_strtoupper($etablissement->delegation_regionale, 'UTF-8') }}<br>
                @endif
                @if($etablissement?->delegation_departementale)
                    {{ mb_strtoupper($etablissement->delegation_departementale, 'UTF-8') }}<br>
                @endif
                @if($etablissement?->arrondissement)
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
                    <img src="{{ $logoSrc }}" alt="Logo" style="max-height: 52px; max-width: 68px; object-fit: contain;">
                @else
                    <div style="font-size: 16pt; font-weight: bold; color: #1e3a8a;">SYGES</div>
                @endif
            </td>
            <td class="header-right">
                <strong>REPUBLIC OF CAMEROON</strong><br>
                <em>Peace – Work – Fatherland</em><br>
                {{ $etablissement?->ministere_tutelle_en ?? 'MINISTRY OF BASIC EDUCATION' }}<br>
                @if($etablissement?->delegation_regionale_en || $etablissement?->delegation_regionale)
                    {{ mb_strtoupper($etablissement->delegation_regionale_en ?? $etablissement->delegation_regionale, 'UTF-8') }}<br>
                @endif
                @if($etablissement?->delegation_departementale_en || $etablissement?->delegation_departementale)
                    {{ mb_strtoupper($etablissement->delegation_departementale_en ?? $etablissement->delegation_departementale, 'UTF-8') }}<br>
                @endif
                @if($etablissement?->arrondissement_en || $etablissement?->arrondissement)
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
            Fiche d'Appel Journalière (Lundi – Vendredi)
        </div>
        <div class="banner-meta">
            Classe : <strong>{{ $classe->nom }}</strong> &bull; Année Scolaire : <strong>{{ $annee->session ?? ($annee->code ?? $annee->nom) }}</strong>
        </div>
    </div>

    <!-- Méta-informations de la classe et de la semaine -->
    <table class="info-bar">
        <tr>
            <td style="width: 17%;">
                <span class="info-label">Classe :</span><br>
                <span class="info-val">{{ $classe->nom }}</span>
            </td>
            <td style="width: 26%;">
                <span class="info-label">{{ $etablissement->titre_responsable_classe ?? 'Responsable de classe' }} :</span><br>
                <span class="info-val">{{ $classe->professeurPrincipal ? ($classe->professeurPrincipal->nom . ' ' . $classe->professeurPrincipal->prenom) : 'Non assigné' }}</span>
            </td>
            <td style="width: 14%;">
                <span class="info-label">Effectif Inscrit :</span><br>
                <span class="info-val">{{ count($inscriptions) }} élève(s)</span>
            </td>
            <td style="width: 25%;">
                <span class="info-label">Semaine d'Appel :</span><br>
                <span class="info-val">Du ............... au ...............</span>
            </td>
            <td style="width: 18%;">
                <span class="info-label">Légende :</span><br>
                <span style="font-size: 6pt; color: #475569;">P = Présent | A = Absent<br>R = Retard | J = Justifié</span>
            </td>
        </tr>
    </table>

    <!-- Tableau d'Appel Journalier (1 case par jour du Lundi au Vendredi + Observations) -->
    <table class="appel-table">
        <thead>
            <tr>
                <th class="col-num">N°</th>
                <th class="col-nom" style="text-align: left;">Noms et Prénoms de l'Élève</th>
                <th class="col-jour">Lundi</th>
                <th class="col-jour">Mardi</th>
                <th class="col-jour">Mercredi</th>
                <th class="col-jour">Jeudi</th>
                <th class="col-jour">Vendredi</th>
                <th class="col-obs">Observations</th>
            </tr>
        </thead>
        <tbody>
            @forelse($inscriptions as $index => $insc)
                <tr class="{{ $index % 2 === 1 ? 'tr-even' : '' }}">
                    <td class="col-num">{{ $index + 1 }}</td>
                    <td class="col-nom">
                        <strong>{{ mb_strtoupper($insc->eleve->nom) }}</strong> {{ $insc->eleve->prenom }}
                    </td>
                    <td class="col-jour"></td>
                    <td class="col-jour"></td>
                    <td class="col-jour"></td>
                    <td class="col-jour"></td>
                    <td class="col-jour"></td>
                    <td class="col-obs"></td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" style="text-align: center; padding: 16px; color: #64748b; font-style: italic;">
                        Aucun élève inscrit dans cette classe pour la période sélectionnée.
                    </td>
                </tr>
            @endforelse

            <!-- Totaux journaliers pour le suivi quotidien de la classe -->
            <tr class="row-total">
                <td colspan="2" style="text-align: right; padding-right: 8px;">TOTAL PRÉSENTS :</td>
                <td class="col-jour"></td>
                <td class="col-jour"></td>
                <td class="col-jour"></td>
                <td class="col-jour"></td>
                <td class="col-jour"></td>
                <td class="col-obs"></td>
            </tr>
            <tr class="row-total">
                <td colspan="2" style="text-align: right; padding-right: 8px;">TOTAL ABSENTS :</td>
                <td class="col-jour"></td>
                <td class="col-jour"></td>
                <td class="col-jour"></td>
                <td class="col-jour"></td>
                <td class="col-jour"></td>
                <td class="col-obs"></td>
            </tr>
        </tbody>
    </table>

    <!-- Signatures neutres (adaptées à tout ordre d'enseignement) -->
    <table class="footer-table">
        <tr>
            <td>
                <div class="sign-title">Le / La Responsable de Classe</div>
                <div class="sign-dots">(Émargement &amp; suivi hebdomadaire)</div>
            </td>
            <td>
                <div class="sign-title">La Direction</div>
                <div class="sign-dots">(Visa &amp; contrôle du registre)</div>
            </td>
        </tr>
    </table>
</body>
</html>
