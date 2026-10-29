<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Répertoire des Classes - {{ $etablissement->nom ?? 'SYGES' }}</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 8mm 10mm 12mm 10mm;
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
            width: 41%;
            font-size: 6.8pt;
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
            font-size: 11pt;
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
            padding: 6px 10px;
            margin-bottom: 8px;
            display: table;
            width: 100%;
            box-sizing: border-box;
        }
        .banner-title {
            font-size: 10.5pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.8px;
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
        .classes-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
        }
        .classes-table th, .classes-table td {
            border: 1px solid #cbd5e1;
            padding: 4px 5px;
        }
        .classes-table th {
            background-color: #f1f5f9;
            color: #1e293b;
            font-weight: bold;
            font-size: 7pt;
            text-align: center;
            text-transform: uppercase;
        }
        .col-num {
            width: 20px;
            text-align: center;
            font-weight: bold;
        }
        .col-nom {
            font-weight: bold;
            color: #1e3a8a;
            text-align: left;
        }
        .col-cycle {
            font-size: 7pt;
            color: #475569;
        }
        .col-pp {
            font-size: 7pt;
        }
        .col-eff {
            width: 32px;
            text-align: center;
            font-family: monospace;
        }
        .col-total {
            width: 38px;
            text-align: center;
            font-weight: bold;
            font-family: monospace;
            background-color: #f8fafc;
        }
        .col-cap {
            width: 35px;
            text-align: center;
            font-family: monospace;
            color: #64748b;
        }
        .col-taux {
            width: 42px;
            text-align: center;
            font-weight: bold;
            font-family: monospace;
        }
        .taux-sur {
            color: #dc2626;
        }
        .taux-ok {
            color: #15803d;
        }
        .tr-even {
            background-color: #f8fafc;
        }
        .tr-total {
            background-color: #e2e8f0;
            font-weight: bold;
            font-size: 7.5pt;
        }
        .footer-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        .footer-table td {
            vertical-align: top;
            width: 50%;
        }
        .sign-left {
            font-size: 7pt;
            color: #64748b;
        }
        .sign-right {
            text-align: right;
            padding-right: 15px;
        }
        .sign-role {
            font-size: 8.5pt;
            font-weight: bold;
            text-transform: uppercase;
            color: #1e3a8a;
            margin-bottom: 45px;
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
                {{ $etablissement->ministere_tutelle ?? 'MINISTÈRE DES ENSEIGNEMENTS SECONDAIRES' }}<br>
                @if($etablissement->delegation_regionale)
                    {{ mb_strtoupper($etablissement->delegation_regionale, 'UTF-8') }}<br>
                @endif
                @if($etablissement->delegation_departementale)
                    {{ mb_strtoupper($etablissement->delegation_departementale, 'UTF-8') }}<br>
                @endif
                @if($etablissement->arrondissement)
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
                    <img src="{{ $logoSrc }}" alt="Logo" style="max-height: 55px; max-width: 70px; object-fit: contain;">
                @else
                    <div style="font-size: 18pt; font-weight: bold; color: #1e3a8a;">SYGES</div>
                @endif
            </td>
            <td class="header-right">
                <strong>REPUBLIC OF CAMEROON</strong><br>
                <em>Peace – Work – Fatherland</em><br>
                {{ $etablissement->ministere_tutelle_en ?? 'MINISTRY OF SECONDARY EDUCATION' }}<br>
                @if($etablissement->delegation_regionale_en || $etablissement->delegation_regionale)
                    {{ mb_strtoupper($etablissement->delegation_regionale_en ?? $etablissement->delegation_regionale, 'UTF-8') }}<br>
                @endif
                @if($etablissement->delegation_departementale_en || $etablissement->delegation_departementale)
                    {{ mb_strtoupper($etablissement->delegation_departementale_en ?? $etablissement->delegation_departementale, 'UTF-8') }}<br>
                @endif
                @if($etablissement->arrondissement_en || $etablissement->arrondissement)
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

    <!-- Bannière Officielle -->
    <div class="banner-box">
        <div class="banner-title">
            Répertoire Officiel des Classes & Divisions Pédagogiques
        </div>
        <div class="banner-meta">
            Année Scolaire : <strong>{{ $annee->session ?? ($annee->code ?? $annee->nom) }}</strong> &bull; Total : <strong>{{ count($classes) }} classe(s)</strong>
        </div>
    </div>

    <!-- Tableau des Classes -->
    <table class="classes-table">
        <thead>
            <tr>
                <th class="col-num">N°</th>
                <th style="text-align: left;">Classe</th>
                <th>Cycle & Section</th>
                <th style="text-align: left;">Professeur Principal</th>
                <th class="col-eff">Filles</th>
                <th class="col-eff">Garç.</th>
                <th class="col-total">Total</th>
                <th class="col-cap">Cap.</th>
                <th class="col-taux">Taux</th>
            </tr>
        </thead>
        <tbody>
            @php
                $totFilles = 0;
                $totGarcons = 0;
                $totEleves = 0;
                $totCapacite = 0;
            @endphp
            @forelse($classes as $index => $cls)
                @php
                    $effFilles = $cls->inscriptions->filter(fn($i) => $i->eleve?->sexe === 'F')->count();
                    $effGarcons = $cls->inscriptions->filter(fn($i) => $i->eleve?->sexe === 'M')->count();
                    $effTotal = $cls->inscriptions->count();
                    $cap = $cls->capacite_max ?? 50;
                    $taux = $cap > 0 ? round(($effTotal / $cap) * 100) : 0;
                    $isSurcharg = $taux > 100;

                    $totFilles += $effFilles;
                    $totGarcons += $effGarcons;
                    $totEleves += $effTotal;
                    $totCapacite += $cap;
                @endphp
                <tr class="{{ $index % 2 === 1 ? 'tr-even' : '' }}">
                    <td class="col-num">{{ $index + 1 }}</td>
                    <td class="col-nom">{{ $cls->nom }}</td>
                    <td class="col-cycle">
                        {{ $cls->niveau?->cycle?->nom ?? 'Général' }}
                        @if($cls->niveau?->cycle?->section)
                            - {{ $cls->niveau->cycle->section->nom }}
                        @endif
                    </td>
                    <td class="col-pp">
                        {{ $cls->professeurPrincipal ? ($cls->professeurPrincipal->nom . ' ' . $cls->professeurPrincipal->prenom) : '---' }}
                    </td>
                    <td class="col-eff" style="color: #7c3aed;">{{ $effFilles }}</td>
                    <td class="col-eff" style="color: #0284c7;">{{ $effGarcons }}</td>
                    <td class="col-total">{{ $effTotal }}</td>
                    <td class="col-cap">{{ $cap }}</td>
                    <td class="col-taux {{ $isSurcharg ? 'taux-sur' : 'taux-ok' }}">
                        {{ $taux }}%
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" style="text-align: center; padding: 15px; color: #64748b; font-style: italic;">
                        Aucune classe répertoriée pour cet établissement.
                    </td>
                </tr>
            @endforelse

            <!-- Ligne de totalisation consolidée -->
            @php
                $tauxGlobal = $totCapacite > 0 ? round(($totEleves / $totCapacite) * 100) : 0;
            @endphp
            <tr class="tr-total">
                <td colspan="4" style="text-align: right; padding-right: 8px;">TOTAL CONSOLIDÉ ÉTABLISSEMENT :</td>
                <td class="col-eff" style="color: #7c3aed;">{{ $totFilles }}</td>
                <td class="col-eff" style="color: #0284c7;">{{ $totGarcons }}</td>
                <td class="col-total" style="background-color: #cbd5e1;">{{ $totEleves }}</td>
                <td class="col-cap">{{ $totCapacite }}</td>
                <td class="col-taux">{{ $tauxGlobal }}%</td>
            </tr>
        </tbody>
    </table>

    <!-- Pied de page & Signature -->
    <table class="footer-table">
        <tr>
            <td class="sign-left">
                Document officiel généré par SYGES &bull; Date d'impression : {{ $dateGeneration }}<br>
                Moyenne d'élèves par salle : <strong>{{ count($classes) > 0 ? round($totEleves / count($classes), 1) : 0 }}</strong> élèves
            </td>
            <td class="sign-right">
                <div style="font-size: 8pt; margin-bottom: 4px;">Fait à {{ $etablissement->ville ?? ($etablissement->institution?->ville ?? 'Fongo-Tongo') }}, le {{ $dateGeneration }}</div>
                <div class="sign-role">{{ $etablissement->titre_chef ?? "Le Chef d'Établissement" }}</div>
                <div style="font-size: 7pt; color: #94a3b8; font-style: italic;">(Signature et Cachet Officiel)</div>
            </td>
        </tr>
    </table>

</body>
</html>
