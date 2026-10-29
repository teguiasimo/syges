<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Liste des Élèves - {{ $classe->nom }}</title>
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
            margin-bottom: 6px;
            display: table;
            width: 100%;
            box-sizing: border-box;
        }
        .banner-title {
            font-size: 11pt;
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
            font-size: 7pt;
        }
        .stats-table td:last-child {
            border-right: none;
        }
        .stats-label {
            color: #64748b;
            font-size: 6pt;
            text-transform: uppercase;
            font-weight: bold;
        }
        .stats-val {
            font-size: 9pt;
            font-weight: bold;
            color: #1e3a8a;
            margin-top: 1px;
        }
        .eleves-table {
            width: 100%;
            border-collapse: collapse;
        }
        .eleves-table th, .eleves-table td {
            border: 1px solid #cbd5e1;
            padding: 3.5px 5px;
        }
        .eleves-table th {
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
            font-size: 7.5pt;
        }
        .col-mat {
            width: 75px;
            font-family: monospace;
            font-size: 7pt;
            text-align: center;
            font-weight: bold;
            color: #1e3a8a;
        }
        .col-nom {
            text-align: left;
            font-size: 7.5pt;
        }
        .col-sexe {
            width: 18px;
            text-align: center;
            font-weight: bold;
        }
        .col-naiss {
            width: 110px;
            font-size: 6.8pt;
            text-align: left;
        }
        .col-statut {
            width: 55px;
            text-align: center;
            font-size: 6.8pt;
            font-weight: 600;
        }
        .col-tuteur {
            width: 120px;
            font-size: 6.8pt;
        }
        .tr-even {
            background-color: #f8fafc;
        }
        .badge-redoub {
            color: #b91c1c;
            font-weight: bold;
        }
        .badge-nouveau {
            color: #15803d;
        }
        .footer-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        .footer-table td {
            vertical-align: top;
        }
        .sign-left {
            width: 45%;
            font-size: 7pt;
            color: #64748b;
        }
        .sign-right {
            width: 55%;
            text-align: right;
            padding-right: 15px;
        }
        .sign-date {
            font-size: 8pt;
            margin-bottom: 4px;
        }
        .sign-role {
            font-size: 8.5pt;
            font-weight: bold;
            text-transform: uppercase;
            color: #1e3a8a;
            margin-bottom: 45px;
        }
        .sign-stamp {
            font-size: 7pt;
            color: #94a3b8;
            font-style: italic;
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
            Liste {{ $type === 'provisoire' ? 'Provisoire' : 'Définitive' }} des Élèves
        </div>
        <div class="banner-meta">
            Classe : <strong>{{ $classe->nom }}</strong> &bull; Année Scolaire : <strong>{{ $annee->session ?? ($annee->code ?? $annee->nom) }}</strong>
        </div>
    </div>

    <!-- Synthèse statistique des effectifs -->
    <table class="stats-table">
        <tr>
            <td>
                <div class="stats-label">Effectif Total</div>
                <div class="stats-val">{{ $stats['total'] }} élève(s)</div>
            </td>
            <td>
                <div class="stats-label">Filles</div>
                <div class="stats-val" style="color: #7c3aed;">{{ $stats['filles'] }} ({{ $stats['taux_filles'] }}%)</div>
            </td>
            <td>
                <div class="stats-label">Garçons</div>
                <div class="stats-val" style="color: #0284c7;">{{ $stats['garcons'] }}</div>
            </td>
            <td>
                <div class="stats-label">Nouveaux</div>
                <div class="stats-val" style="color: #15803d;">{{ $stats['nouveaux'] }}</div>
            </td>
            <td>
                <div class="stats-label">Redoublants</div>
                <div class="stats-val" style="color: #b91c1c;">{{ $stats['redoublants'] }}</div>
            </td>
            <td>
                <div class="stats-label">Professeur Principal</div>
                <div class="stats-val" style="font-size: 7.5pt; color: #334155;">
                    {{ $classe->professeurPrincipal ? ($classe->professeurPrincipal->nom . ' ' . $classe->professeurPrincipal->prenom) : 'Non assigné' }}
                </div>
            </td>
        </tr>
    </table>

    <!-- Tableau de la Liste des Élèves -->
    <table class="eleves-table">
        <thead>
            <tr>
                <th class="col-num">N°</th>
                <th class="col-mat">Matricule</th>
                <th class="col-nom">Noms et Prénoms</th>
                <th class="col-sexe">S</th>
                <th class="col-naiss">Date & Lieu de Naissance</th>
                <th class="col-statut">Statut</th>
                <th class="col-tuteur">Parent / Tuteur (Contact)</th>
            </tr>
        </thead>
        <tbody>
            @forelse($inscriptions as $index => $insc)
                <tr class="{{ $index % 2 === 1 ? 'tr-even' : '' }}">
                    <td class="col-num">{{ $index + 1 }}</td>
                    <td class="col-mat">{{ $insc->eleve->matricule }}</td>
                    <td class="col-nom">
                        <strong>{{ mb_strtoupper($insc->eleve->nom) }}</strong> {{ $insc->eleve->prenom }}
                    </td>
                    <td class="col-sexe" style="{{ $insc->eleve->sexe === 'F' ? 'color: #7c3aed;' : 'color: #0284c7;' }}">
                        {{ $insc->eleve->sexe }}
                    </td>
                    <td class="col-naiss">
                        {{ $insc->eleve->date_naissance ? \Carbon\Carbon::parse($insc->eleve->date_naissance)->format('d-m-Y') : '---' }}
                        @if($insc->eleve->lieu_naissance)
                            <br><span style="color: #64748b;">à {{ $insc->eleve->lieu_naissance }}</span>
                        @endif
                    </td>
                    <td class="col-statut">
                        @if($insc->is_redoublant)
                            <span class="badge-redoub">Redoublant</span>
                        @else
                            <span class="badge-nouveau">Nouveau</span>
                        @endif
                    </td>
                    <td class="col-tuteur">
                        {{ $insc->eleve->nom_tuteur ?: ($insc->eleve->nom_pere ?: ($insc->eleve->nom_mere ?: '---')) }}
                        @if($insc->eleve->contact_urgence || $insc->eleve->telephone)
                            <br><span style="color: #475569; font-family: monospace;">{{ $insc->eleve->contact_urgence ?: $insc->eleve->telephone }}</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" style="text-align: center; padding: 18px; color: #64748b; font-style: italic;">
                        Aucun élève enregistré dans cette classe pour le statut sélectionné.
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
                Toute rature ou surcharge annule la validité du présent document.
            </td>
            <td class="sign-right">
                <div class="sign-date">Fait à {{ $etablissement->ville ?? ($etablissement->institution?->ville ?? 'Fongo-Tongo') }}, le {{ $dateGeneration }}</div>
                <div class="sign-role">{{ $etablissement->titre_chef ?? "Le Chef d'Établissement" }}</div>
                <div class="sign-stamp">(Signature et Cachet Officiel)</div>
            </td>
        </tr>
    </table>

</body>
</html>
