<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Certificat de Scolarité - {{ $eleve->matricule }}</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 10mm 12mm 15mm 12mm;
        }
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 10pt;
            color: #0f172a;
            line-height: 1.5;
            margin: 0;
            padding: 0;
        }
        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8px;
        }
        .header-table td {
            vertical-align: middle;
        }
        .header-left, .header-right {
            width: 41%;
            font-size: 7.2pt;
            text-align: center;
            line-height: 1.25;
            white-space: nowrap;
        }
        .header-center {
            width: 18%;
            text-align: center;
            vertical-align: middle;
        }
        .school-name {
            font-size: 12pt;
            font-weight: bold;
            color: #1e3a8a;
            text-transform: uppercase;
            margin-top: 4px;
            white-space: nowrap;
        }
        .school-sub {
            font-size: 7.5pt;
            color: #64748b;
        }
        .cert-banner {
            border: 2px solid #1e3a8a;
            border-radius: 6px;
            padding: 10px;
            text-align: center;
            background-color: #f8fafc;
            margin: 20px 0 25px 0;
        }
        .cert-title {
            font-size: 16pt;
            font-weight: 900;
            color: #1e3a8a;
            text-transform: uppercase;
            letter-spacing: 1.5px;
        }
        .cert-ref {
            font-size: 9pt;
            color: #475569;
            margin-top: 4px;
            font-family: monospace;
            font-weight: bold;
        }
        .cert-body {
            font-size: 11pt;
            text-align: justify;
            line-height: 1.8;
            margin: 25px 0;
        }
        .highlight {
            font-weight: bold;
            color: #0f172a;
        }
        .cert-footer-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 40px;
        }
        .cert-footer-table td {
            vertical-align: top;
        }
        .qr-box {
            width: 35%;
            text-align: center;
        }
        .qr-caption {
            font-size: 6.5pt;
            color: #64748b;
            margin-top: 4px;
        }
        .sign-box {
            width: 65%;
            text-align: right;
            padding-right: 15px;
        }
        .sign-place {
            font-size: 10pt;
            margin-bottom: 5px;
        }
        .sign-role {
            font-size: 10.5pt;
            font-weight: bold;
            text-transform: uppercase;
            color: #1e3a8a;
            margin-bottom: 60px;
        }
        .sign-stamp {
            font-size: 8pt;
            color: #94a3b8;
            font-style: italic;
        }
        .security-notice {
            margin-top: 50px;
            border-top: 1px dashed #cbd5e1;
            padding-top: 8px;
            font-size: 7pt;
            color: #64748b;
            text-align: center;
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
                    <img src="{{ $logoSrc }}" alt="Logo" style="max-height: 82px; max-width: 95px; object-fit: contain;">
                @else
                    <div style="font-size: 24pt; font-weight: bold; color: #1e3a8a;">SYGES</div>
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

    <div style="text-align: center; margin-bottom: 15px;">
        <div class="school-name">{{ $etablissement->nom ?? 'ÉTABLISSEMENT SCOLAIRE' }}</div>
        <div class="school-sub">
            BP : {{ $etablissement->boite_postale ?? '---' }} &bull; Tél : {{ $etablissement->telephone ?? '---' }} &bull; Email : {{ $etablissement->email ?? '---' }}
        </div>
    </div>

    <!-- Bannière Titre -->
    <div class="cert-banner">
        <div class="cert-title">Certificat de Scolarité</div>
        <div class="cert-ref">N° {{ $numeroCertificat }}</div>
    </div>

    <!-- Corps officiel de l'attestation -->
    <div class="cert-body">
        @php
            $nomChef = $etablissement->nom_chef_etablissement;
            $titreChef = $etablissement->titre_chef ?? "Chef d'Établissement";
            $titreChefCourt = preg_replace('/^(Le|La|L\')\s+/iu', '', trim($titreChef));
            $premiereLettre = mb_substr(ltrim($etablissement->nom ?? ''), 0, 1, 'UTF-8');
            $prep = in_array(mb_strtoupper($premiereLettre, 'UTF-8'), ['A', 'E', 'I', 'O', 'U', 'Y', 'É', 'È', 'Ê', 'Ë', 'À', 'Â']) ? "de l'" : 'du ';
        @endphp

        @if(!empty($nomChef))
            Je soussigné, <strong>{{ $nomChef }}</strong>, {{ $titreChefCourt }} {{ $prep }}<span class="highlight">{{ $etablissement->nom }}</span>, certifie par la présente que :
        @else
            Je soussigné, <strong>{{ $titreChef }}</strong> {{ $prep }}<span class="highlight">{{ $etablissement->nom }}</span>, certifie par la présente que :
        @endif
        <br><br>
        L'élève : <span class="highlight" style="font-size: 12pt; text-transform: uppercase;">{{ $eleve->nom }} {{ $eleve->prenom }}</span><br>
        Matricule : <span class="highlight">{{ $eleve->matricule }}</span>
        @if(!empty($eleve->matricule_national))
            &nbsp;&bull;&nbsp; Matricule national : <span class="highlight">{{ $eleve->matricule_national }}</span>
        @endif
        <br>
        Né(e) le : <span class="highlight">{{ $eleve->date_naissance ? \Carbon\Carbon::parse($eleve->date_naissance)->format('d-m-Y') : '---' }}</span> à <span class="highlight">{{ $eleve->lieu_naissance ?? '---' }}</span><br>
        Sexe : <span class="highlight">{{ $eleve->sexe === 'F' ? 'Féminin' : 'Masculin' }}</span> &bull; Nationalité : <span class="highlight">{{ $eleve->nationalite ?? 'CAMEROUNAISE' }}</span><br>
        Fils / Fille de : <span class="highlight">{{ $eleve->nom_pere ?: '---' }}</span> et de : <span class="highlight">{{ $eleve->nom_mere ?: '---' }}</span><br>
        <br>
        Est régulièrement inscrit(e) et fréquente assidûment les cours au sein de notre établissement dans la classe de : <span class="highlight" style="font-size: 12pt; text-decoration: underline;">{{ $classe->nom }}</span> au titre de l'année scolaire <span class="highlight">{{ $annee->session ?? ($annee->code ?? $annee->nom) }}</span>.
        <br><br>
        En foi de quoi le présent certificat lui est délivré pour servir et valoir ce que de droit.
    </div>

    <!-- Signatures & Sécurité QR -->
    <table class="cert-footer-table">
        <tr>
            <td class="qr-box">
                @if(!empty($qrCodeBase64))
                    <img src="{{ $qrCodeBase64 }}" alt="QR Sécurité" style="width: 85px; height: 85px; display: block; margin: 0 auto;">
                    <div class="qr-caption">Authenticité infalsifiable certifiée<br>Scannez pour vérifier</div>
                @endif
            </td>
            <td class="sign-box">
                <div class="sign-place">Fait à {{ $etablissement->ville ?? ($etablissement->institution?->ville ?? 'Fongo-Tongo') }}, le {{ $dateDelivrance }}</div>
                <div class="sign-role">{{ $etablissement->titre_chef ?? "Le Chef d'Établissement" }}</div>
                <div class="sign-stamp">(Signature et Cachet Officiel)</div>
            </td>
        </tr>
    </table>

    <div class="security-notice">
        Document officiel généré par le Système Intégré de Gestion Scolaire SYGES &bull; Aucune surcharge ou rature n'est tolérée
    </div>

</body>
</html>
