<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Certificat de Radiation - {{ $eleve->matricule }}</title>
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
            border: 2px solid #b91c1c;
            border-radius: 6px;
            padding: 10px;
            text-align: center;
            background-color: #fef2f2;
            margin: 20px 0 25px 0;
        }
        .cert-title {
            font-size: 15pt;
            font-weight: 900;
            color: #b91c1c;
            text-transform: uppercase;
            letter-spacing: 1.5px;
        }
        .cert-ref {
            font-size: 9pt;
            color: #7f1d1d;
            margin-top: 4px;
            font-family: monospace;
            font-weight: bold;
        }
        .cert-body {
            font-size: 10.5pt;
            text-align: justify;
            line-height: 1.8;
            margin: 25px 0;
        }
        .highlight {
            font-weight: bold;
            color: #0f172a;
        }
        .financial-clearance-box {
            border: 1px solid #86efac;
            background-color: #f0fdf4;
            padding: 10px 14px;
            border-radius: 4px;
            margin: 20px 0;
            font-size: 9.5pt;
            color: #166534;
        }
        .cert-footer-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 35px;
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
            color: #b91c1c;
            margin-bottom: 60px;
        }
        .sign-stamp {
            font-size: 8pt;
            color: #94a3b8;
            font-style: italic;
        }
        .security-notice {
            margin-top: 45px;
            border-top: 1px dashed #cbd5e1;
            padding-top: 8px;
            font-size: 7pt;
            color: #64748b;
            text-align: center;
        }
    </style>
</head>
<body>

    <!-- En-tête bilingue officiel -->
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
        <div class="cert-title">Certificat de Radiation / Transfert</div>
        <div class="cert-ref">RÉF : {{ $numeroRadiation }}</div>
    </div>

    <!-- Corps officiel -->
    <div class="cert-body">
        Le Chef d'Établissement soussigné certifie que :
        <br><br>
        L'élève : <span class="highlight" style="font-size: 12pt; text-transform: uppercase;">{{ $eleve->nom }} {{ $eleve->prenom }}</span><br>
        Matricule : <span class="highlight">{{ $eleve->matricule }}</span><br>
        Né(e) le : <span class="highlight">{{ $eleve->date_naissance ? \Carbon\Carbon::parse($eleve->date_naissance)->format('d-m-Y') : '---' }}</span> à <span class="highlight">{{ $eleve->lieu_naissance ?? '---' }}</span><br>
        Sexe : <span class="highlight">{{ $eleve->sexe === 'F' ? 'Féminin' : 'Masculin' }}</span> &bull; Nationalité : <span class="highlight">{{ $eleve->nationalite ?? 'CAMEROUNAISE' }}</span><br>
        <br>
        Qui était régulièrement inscrit(e) et suivait les enseignements dans la classe de : <span class="highlight">{{ $classe->nom }}</span> durant l'année scolaire <span class="highlight">{{ $annee->nom ?? $annee->code }}</span>,
        <br><br>
        A été officiellement <strong>radié(e) des contrôles de fréquentation</strong> de notre établissement en date du <span class="highlight">{{ $dateRadiation }}</span>.
        <br><br>
        <strong>Motif du départ :</strong> {{ $motif }}<br>
        <strong>Établissement d'accueil / Destination :</strong> {{ $destination }}
    </div>

    <!-- Quitus Financier -->
    <div class="financial-clearance-box">
        <strong>&bull; QUITUS DE SOLVABILITÉ FINANCIÈRE :</strong><br>
        @if($estSolvable)
            L'élève est entièrement quitte de tout droit d'écolage et frais scolaires auprès de l'intendance à la date de sa radiation. Aucun arriéré n'est enregistré à son compte.
        @else
            <em>Attestation avec réserve : Un solde débiteur restant de {{ number_format($solde, 0, ',', ' ') }} FCFA est actuellement enregistré au compte financier de l'élève.</em>
        @endif
    </div>

    <div style="font-size: 10.5pt; margin-top: 10px;">
        En foi de quoi le présent certificat de radiation lui est délivré pour lui permettre de poursuivre ses études dans tout établissement de son choix.
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
                <div class="sign-place">Fait à {{ $etablissement->ville ?? 'Yaoundé' }}, le {{ $dateRadiation }}</div>
                <div class="sign-role">Le Chef d'Établissement</div>
                <div class="sign-stamp">(Signature et Cachet Officiel)</div>
            </td>
        </tr>
    </table>

    <div class="security-notice">
        Document officiel généré par le Système Intégré de Gestion Scolaire SYGES &bull; Toute rature ou falsification expose aux sanctions pénales
    </div>

</body>
</html>
