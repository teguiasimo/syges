<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Convocation au Conseil de Discipline - {{ $dossier->eleve->matricule }}</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 15mm 18mm 18mm 18mm;
        }
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 9.5pt;
            color: #0f172a;
            line-height: 1.45;
            margin: 0;
            padding: 0;
        }
        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        .header-table td {
            vertical-align: top;
        }
        .header-left, .header-right {
            width: 38%;
            font-size: 7.5pt;
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
        .title-banner {
            border: 2px solid #b91c1c;
            border-radius: 4px;
            padding: 10px;
            text-align: center;
            background-color: #fef2f2;
            margin: 15px 0 20px 0;
        }
        .title-text {
            font-size: 13pt;
            font-weight: 900;
            color: #b91c1c;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .title-sub {
            font-size: 8.5pt;
            color: #7f1d1d;
            margin-top: 2px;
            font-weight: 600;
        }
        .destinataire {
            margin-bottom: 20px;
            padding: 10px 14px;
            background-color: #f8fafc;
            border-left: 4px solid #1e3a8a;
            border-radius: 2px;
            font-size: 9pt;
        }
        .content-body {
            text-align: justify;
            margin-bottom: 20px;
            font-size: 9.5pt;
        }
        .table-details {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
            font-size: 9pt;
        }
        .table-details td {
            padding: 6px 10px;
            border: 1px solid #cbd5e1;
        }
        .table-details td.label {
            width: 30%;
            background-color: #f1f5f9;
            font-weight: bold;
            color: #1e293b;
        }
        .signatures-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 25px;
            margin-bottom: 30px;
        }
        .signatures-table td {
            width: 50%;
            text-align: center;
            vertical-align: top;
            font-size: 9pt;
        }
        .tear-line {
            border-top: 1.5px dashed #64748b;
            margin: 25px 0 15px 0;
            text-align: center;
            font-size: 7.5pt;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 1px;
            padding-top: 5px;
        }
        .coupon-title {
            font-size: 9.5pt;
            font-weight: bold;
            text-align: center;
            color: #1e3a8a;
            margin-bottom: 8px;
        }
        .coupon-content {
            font-size: 8pt;
            line-height: 1.35;
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

    <!-- Bannière de Titre -->
    <div class="title-banner">
        <div class="title-text">CONVOCATION OFFICIELLE AU CONSEIL DE DISCIPLINE</div>
        <div class="title-sub">Réf : {{ $conseil->numero_session }} / DOSSIER N° {{ $dossier->id }}</div>
    </div>

    <!-- Destinataire -->
    <div class="destinataire">
        <strong>À l'attention des Parents / Tuteurs de l'élève :</strong><br>
        <span style="font-size: 11pt; font-weight: bold; color: #1e3a8a;">
            {{ $dossier->eleve->nom }} {{ $dossier->eleve->prenom }}
        </span><br>
        Matricule : <strong>{{ $dossier->eleve->matricule }}</strong> | Classe : <strong>{{ $dossier->inscription->classe->nom ?? '-' }}</strong>
    </div>

    <!-- Corps de la convocation -->
    <div class="content-body">
        <p>Madame, Monsieur, Cher Parent / Tuteur,</p>
        <p>
            Vous êtes formellement convoqué(e) à assister, aux côtés de votre enfant susnommé(e), à la session ordinaire du Conseil de Discipline de l'établissement qui se tiendra selon les modalités ci-après :
        </p>

        <table class="table-details">
            <tr>
                <td class="label">Date de la séance :</td>
                <td><strong>{{ $conseil->date_conseil->format('d/m/Y') }}</strong></td>
            </tr>
            <tr>
                <td class="label">Heure précise :</td>
                <td><strong>{{ $conseil->date_conseil->format('H:i') }}</strong></td>
            </tr>
            <tr>
                <td class="label">Lieu :</td>
                <td>{{ $conseil->lieu }}</td>
            </tr>
            <tr>
                <td class="label">Motifs / Faits reprochés :</td>
                <td style="color: #b91c1c; font-weight: 600;">
                    {{ $dossier->motifs_renvoi }}
                </td>
            </tr>
        </table>

        <p style="font-size: 8.5pt; color: #475569; font-style: italic;">
            <strong>Avis important :</strong> Conformément aux dispositions du Règlement Intérieur de l'établissement et des textes régissant les établissements secondaires au Cameroun, la présence des parents ou du tuteur légal est vivement requise. Toutefois, en cas d'absence injustifiée, le Conseil de Discipline statuera valablement.
        </p>
    </div>

    <!-- Signatures -->
    <table class="signatures-table">
        <tr>
            <td>
                Fait à {{ $etablissement->institution->ville ?? 'Yaoundé' }}, le {{ $date_emission }}<br>
                <strong>Le Censeur / Surveillant Général</strong><br><br><br>
                [ Visa & Signature ]
            </td>
            <td>
                Pour le Conseil de Discipline,<br>
                <strong>Le Chef d'Établissement (Proviseur / Principal)</strong><br><br><br>
                <strong>{{ $etablissement->nom_chef_etablissement ?? 'La Direction' }}</strong>
            </td>
        </tr>
    </table>

    <!-- Talon détachable d'accusé de réception -->
    <div class="tear-line">
        ✂ ------------------------- TALON D'ACCUSÉ DE RÉCEPTION (À RETOURNER SIGNÉ À LA SURVEILLANCE GÉNÉRALE) -------------------------
    </div>

    <div class="coupon-title">ACCUSÉ DE RÉCEPTION DE LA CONVOCATION AU CONSEIL DE DISCIPLINE</div>
    <div class="coupon-content">
        Je soussigné(e), M. / Mme ...................................................................................., 
        Parent / Tuteur de l'élève <strong>{{ $dossier->eleve->nom }} {{ $dossier->eleve->prenom }}</strong> (Classe : {{ $dossier->inscription->classe->nom ?? '-' }}), 
        reconnais avoir reçu en main propre la convocation pour la session du <strong>{{ $conseil->date_conseil->format('d/m/Y à H:i') }}</strong>.<br><br>
        <table style="width: 100%;">
            <tr>
                <td style="width: 50%;">Date de notification : ..... / ..... / 2026</td>
                <td style="width: 50%; text-align: right;"><strong>Signature & Téléphone du Parent :</strong> ......................................................</td>
            </tr>
        </table>
    </div>

</body>
</html>
