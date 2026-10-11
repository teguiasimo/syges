<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Certificat de Scolarité - {{ $eleve->matricule }}</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 15mm 18mm 18mm 18mm;
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
            margin-bottom: 12px;
        }
        .header-table td {
            vertical-align: top;
        }
        .header-left, .header-right {
            width: 38%;
            font-size: 8pt;
            text-align: center;
            line-height: 1.25;
        }
        .header-center {
            width: 24%;
            text-align: center;
        }
        .school-name {
            font-size: 12pt;
            font-weight: bold;
            color: #1e3a8a;
            text-transform: uppercase;
            margin-top: 4px;
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
                MINISTÈRE DES ENSEIGNEMENTS SECONDAIRES<br>
                DÉLÉGATION RÉGIONALE DU CENTRE<br>
                DÉLÉGATION DÉPARTEMENTALE DU MFOUNDI
            </td>
            <td class="header-center">
                @if($etablissement && $etablissement->logo_url)
                    <img src="{{ public_path($etablissement->logo_url) }}" alt="Logo" style="max-height: 52px; max-width: 65px;">
                @else
                    <div style="font-size: 24pt; font-weight: bold; color: #1e3a8a;">SYGES</div>
                @endif
            </td>
            <td class="header-right">
                <strong>REPUBLIC OF CAMEROON</strong><br>
                <em>Peace – Work – Fatherland</em><br>
                MINISTRY OF SECONDARY EDUCATION<br>
                REGIONAL DELEGATION OF CENTRE<br>
                DIVISIONAL DELEGATION OF MFOUNDI
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
        Je soussigné, <strong>Le Chef d'Établissement</strong> de l'établissement <span class="highlight">{{ $etablissement->nom }}</span>, certifie par la présente que :
        <br><br>
        L'élève : <span class="highlight" style="font-size: 12pt; text-transform: uppercase;">{{ $eleve->nom }} {{ $eleve->prenom }}</span><br>
        Matricule interne : <span class="highlight">{{ $eleve->matricule }}</span><br>
        Né(e) le : <span class="highlight">{{ $eleve->date_naissance ? \Carbon\Carbon::parse($eleve->date_naissance)->format('d-m-Y') : '---' }}</span> à <span class="highlight">{{ $eleve->lieu_naissance ?? '---' }}</span><br>
        Sexe : <span class="highlight">{{ $eleve->sexe === 'F' ? 'Féminin' : 'Masculin' }}</span> &bull; Nationalité : <span class="highlight">{{ $eleve->nationalite ?? 'CAMEROUNAISE' }}</span><br>
        Fils / Fille de : <span class="highlight">{{ $eleve->nom_pere ?: '---' }}</span> et de : <span class="highlight">{{ $eleve->nom_mere ?: '---' }}</span><br>
        <br>
        Est régulièrement inscrit(e) et fréquente assidûment les cours au sein de notre établissement dans la classe de : <span class="highlight" style="font-size: 12pt; text-decoration: underline;">{{ $classe->nom }}</span> au titre de l'année scolaire <span class="highlight">{{ $annee->nom ?? $annee->code }}</span>.
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
                <div class="sign-place">Fait à {{ $etablissement->ville ?? 'Yaoundé' }}, le {{ $dateDelivrance }}</div>
                <div class="sign-role">Le Chef d'Établissement</div>
                <div class="sign-stamp">(Signature et Cachet Officiel)</div>
            </td>
        </tr>
    </table>

    <div class="security-notice">
        Document officiel généré par le Système Intégré de Gestion Scolaire SYGES &bull; Aucune surcharge ou rature n'est tolérée
    </div>

</body>
</html>
