{{--
    Vue Blade : Badge Unitaire d'Élève (Format Standard ISO/IEC 7810 ID-1 / CR-80)
    Rôle : Affiche et génère le PDF du badge individuel officiel (Recto et Verso)
    Dimensions : 85.6mm x 53.98mm (243pt x 153pt), strictement calibré sur 2 pages (sans débordement DomPDF).
--}}
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Badge Scolaire - {{ $carte['matricule'] }}</title>
    <style>
        @page {
            size: 243pt 153pt landscape;
            margin: 0;
        }
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            color: #0f172a;
            background: #ffffff;
            font-size: 5pt;
            line-height: 1.15;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        .card {
            width: 231pt;
            margin: 0 auto;
            padding-top: 3pt;
            background: #ffffff;
            overflow: hidden;
        }
        .card-verso {
            page-break-before: always;
            padding-top: 4pt;
        }

        /* En-tête bilingue officiel République du Cameroun */
        .tbl-hdr {
            width: 100%;
            border-collapse: collapse;
        }
        .tbl-hdr td {
            padding: 0;
            vertical-align: middle;
        }
        .hdr-rep {
            font-size: 3.3pt;
            font-weight: bold;
            text-transform: uppercase;
            color: #0f172a;
            line-height: 1.1;
        }
        .hdr-sub {
            font-size: 2.7pt;
            font-style: italic;
            color: #475569;
        }
        .hdr-del {
            font-size: 2.9pt;
            font-weight: bold;
            color: #334155;
            white-space: nowrap;
            overflow: hidden;
        }
        .hdr-logo {
            text-align: center;
            vertical-align: middle;
        }
        .logo-img {
            max-height: 22pt;
            max-width: 26pt;
        }

        /* Nom officiel de l'établissement scolaire */
        .school-name {
            font-size: 6.8pt;
            font-weight: 900;
            color: #0284c7;
            text-transform: uppercase;
            text-align: center;
            margin-top: 1pt;
            margin-bottom: 2pt;
            white-space: nowrap;
            overflow: hidden;
            letter-spacing: 0.2px;
        }

        /* Bandeau titre tricolore officiel avec étoile dorée */
        .tbl-banner {
            width: 100%;
            border-collapse: collapse;
            background: #e0f2fe;
            border: 0.6px solid #0284c7;
            margin-bottom: 2pt;
            border-radius: 2px;
        }
        .tbl-banner td {
            padding: 0;
            vertical-align: middle;
        }
        .flag-g { width: 5.5pt; height: 10pt; background: #15803d; }
        .flag-r { width: 7.5pt; height: 10pt; background: #dc2626; color: #facc15; text-align: center; font-size: 4.5pt; font-family: 'DejaVu Sans', sans-serif; line-height: 10pt; }
        .flag-y { width: 5.5pt; height: 10pt; background: #eab308; }
        .banner-title {
            text-align: center;
            font-size: 6.2pt;
            font-weight: 900;
            color: #0369a1;
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }
        .banner-year {
            text-align: right;
            padding-right: 5pt !important;
            font-size: 4.8pt;
            font-weight: bold;
            color: #dc2626;
            white-space: nowrap;
        }

        /* Corps de la carte : Photo + Infos Élève + Cachet */
        .tbl-body {
            width: 100%;
            border-collapse: collapse;
            margin-top: 2pt;
        }
        .tbl-body td {
            padding: 0;
            vertical-align: top;
        }
        .photo-box {
            width: 48pt;
            height: 58pt;
            border: 0.8px solid #94a3b8;
            border-radius: 2px;
            background: #f8fafc;
            text-align: center;
            overflow: hidden;
        }
        .photo-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .photo-placeholder {
            line-height: 58pt;
            font-size: 6pt;
            font-weight: bold;
            color: #94a3b8;
        }

        /* Lignes de renseignements de l'élève */
        .info-row {
            font-size: 4.9pt;
            line-height: 1.35;
            margin-bottom: 1.2pt;
            white-space: nowrap;
        }
        .info-lbl {
            font-weight: bold;
            color: #475569;
            display: inline-block;
            width: 34pt;
        }
        .info-val {
            font-weight: bold;
            color: #0f172a;
        }
        .val-mat {
            color: #dc2626;
            font-family: monospace;
            font-size: 5.6pt;
        }
        .val-nom {
            color: #1e3a8a;
            font-size: 5.6pt;
        }
        .val-cls {
            color: #dc2626;
            font-size: 5.4pt;
        }

        /* Cachet officiel et signature */
        .stamp-box {
            width: 36pt;
            height: 36pt;
            border: 0.8px dashed #6366f1;
            border-radius: 50%;
            margin: 1pt auto 0 auto;
            text-align: center;
            background: rgba(238, 242, 255, 0.45);
        }
        .stamp-txt {
            font-size: 2.9pt;
            font-weight: bold;
            color: #4338ca;
            text-transform: uppercase;
            padding-top: 8.5pt;
            line-height: 1.05;
        }
        .stamp-sign {
            font-size: 3.2pt;
            font-weight: bold;
            color: #1e3a8a;
            text-align: center;
            margin-top: 2pt;
            white-space: nowrap;
            overflow: hidden;
        }

        /* Styles spécifiques au Verso */
        .v-header {
            text-align: center;
            font-size: 7.2pt;
            font-weight: 900;
            color: #0284c7;
            text-transform: uppercase;
            margin-top: 1pt;
            white-space: nowrap;
            overflow: hidden;
            letter-spacing: 0.2px;
        }
        .v-sub {
            text-align: center;
            font-size: 4.8pt;
            font-weight: bold;
            color: #334155;
            margin-top: 1pt;
            margin-bottom: 3.5pt;
        }
        .tbl-v-mid {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 3.5pt;
        }
        .tbl-v-mid td {
            padding: 0;
            vertical-align: middle;
            text-align: center;
        }
        .v-logo {
            max-height: 38pt;
            max-width: 46pt;
            opacity: 0.88;
        }
        .v-qr {
            width: 38pt;
            height: 38pt;
        }
        .v-qr-lbl {
            font-size: 3.2pt;
            font-weight: bold;
            color: #64748b;
            text-transform: uppercase;
            margin-top: 1.5pt;
        }
        .v-urg {
            text-align: center;
            font-size: 4.9pt;
            font-weight: bold;
            color: #dc2626;
            margin-bottom: 2.5pt;
        }
        .v-rules {
            text-align: justify;
            font-size: 3.8pt;
            line-height: 1.25;
            color: #475569;
            padding: 0 4pt;
            margin-bottom: 3.5pt;
        }
        .v-devise {
            background: #0284c7;
            color: #ffffff;
            font-size: 5.5pt;
            font-weight: bold;
            text-align: center;
            padding: 2.5pt 0;
            border-radius: 2px;
            letter-spacing: 0.5px;
        }
    </style>
</head>
<body>

{{-- =========================================================================
     PAGE 1 : RECTO DE LA CARTE D'IDENTITÉ SCOLAIRE
     ========================================================================= --}}
<div class="card">
    <table class="tbl-hdr">
        <tr>
            <td style="width: 38%; text-align: left;">
                <div class="hdr-rep">RÉPUBLIQUE DU CAMEROUN</div>
                <div class="hdr-sub">Paix – Travail – Patrie</div>
                <div class="hdr-del">{{ $carte['delegation_regionale'] }}</div>
                <div class="hdr-del">{{ $carte['delegation_departementale'] }}</div>
            </td>
            <td style="width: 24%;" class="hdr-logo">
                @if(!empty($carte['logo_base64']))
                    <img src="{{ $carte['logo_base64'] }}" class="logo-img" alt="Logo">
                @elseif(!empty($carte['logo_url']) && file_exists(public_path($carte['logo_url'])))
                    <img src="{{ public_path($carte['logo_url']) }}" class="logo-img" alt="Logo">
                @else
                    <div style="font-size: 14pt; color: #0284c7; font-weight: bold;">SYGES</div>
                @endif
            </td>
            <td style="width: 39%; text-align: right;">
                <div class="hdr-rep">REPUBLIC OF CAMEROON</div>
                <div class="hdr-sub">Peace – Work – Fatherland</div>
                <div class="hdr-del">{{ $carte['delegation_regionale_en'] }}</div>
                <div class="hdr-del">{{ $carte['delegation_departementale_en'] }}</div>
            </td>
        </tr>
    </table>

    <div class="school-name">{{ $carte['etablissement_nom'] }}</div>

    <table class="tbl-banner">
        <tr>
            <td class="flag-g"></td>
            <td class="flag-r">&#9733;</td>
            <td class="flag-y"></td>
            <td class="banner-title">CARTE D'IDENTITÉ SCOLAIRE</td>
            <td class="banner-year">Année {{ $carte['annee_nom'] }}</td>
        </tr>
    </table>

    <table class="tbl-body">
        <tr>
            <td style="width: 50pt;">
                <div class="photo-box">
                    @if(!empty($carte['photo_base64']))
                        <img src="{{ $carte['photo_base64'] }}" class="photo-img" alt="Photo">
                    @elseif(!empty($carte['photo_url']) && file_exists(public_path($carte['photo_url'])))
                        <img src="{{ public_path($carte['photo_url']) }}" class="photo-img" alt="Photo">
                    @else
                        <div class="photo-placeholder">PHOTO</div>
                    @endif
                </div>
            </td>
            <td style="padding-left: 5pt; width: 131pt;">
                <div class="info-row">
                    <span class="info-lbl">Matricule :</span>
                    <span class="info-val val-mat">{{ $carte['matricule'] }}</span>
                    @if(!empty($carte['matricule_national']))
                        <span style="font-size: 4pt; color: #64748b;"> &bull; Nat: {{ $carte['matricule_national'] }}</span>
                    @endif
                </div>
                <div class="info-row">
                    <span class="info-lbl">Nom :</span>
                    <span class="info-val val-nom">{{ $carte['nom_complet'] }}</span>
                </div>
                <div class="info-row">
                    <span class="info-lbl">Né(e) le :</span>
                    <span class="info-val">{{ $carte['date_naissance'] }} à {{ $carte['lieu_naissance'] }}</span>
                </div>
                <div class="info-row">
                    <span class="info-lbl">Classe :</span>
                    <span class="info-val val-cls">{{ $carte['classe_nom'] }}</span>
                    &nbsp;&nbsp;<span class="info-lbl" style="width: auto;">Sexe :</span>
                    <span class="info-val" style="color: #dc2626;">{{ $carte['sexe'] }}</span>
                </div>
                <div class="info-row">
                    <span class="info-lbl">Direction :</span>
                    <span class="info-val" style="color: #047857;">{{ $carte['telephone_direction'] }}</span>
                </div>
            </td>
            <td style="width: 50pt; text-align: center; vertical-align: top;">
                <div class="stamp-box">
                    <div class="stamp-txt">CACHET<br>DIRECTION</div>
                </div>
                <div class="stamp-sign">{{ $carte['nom_chef'] }}</div>
            </td>
        </tr>
    </table>
</div>

{{-- =========================================================================
     PAGE 2 : VERSO DE LA CARTE D'IDENTITÉ SCOLAIRE
     ========================================================================= --}}
<div class="card card-verso">
    <div class="v-header">{{ $carte['etablissement_nom'] }}</div>
    <div class="v-sub">
        Adresse : {{ $carte['adresse'] }}
        @if(!empty($carte['boite_postale']) && $carte['boite_postale'] !== '---')
            | B.P : {{ $carte['boite_postale'] }}
        @endif
        | Tél : {{ $carte['telephone_direction'] }}
    </div>

    <table class="tbl-v-mid">
        <tr>
            <td style="width: 50%;">
                @if(!empty($carte['logo_base64']))
                    <img src="{{ $carte['logo_base64'] }}" class="v-logo" alt="Logo">
                @elseif(!empty($carte['logo_url']) && file_exists(public_path($carte['logo_url'])))
                    <img src="{{ public_path($carte['logo_url']) }}" class="v-logo" alt="Logo">
                @else
                    <div style="font-size: 26pt; color: #cbd5e1; font-weight: bold;">★</div>
                @endif
            </td>
            <td style="width: 50%;">
                <img src="{{ $carte['qr_code'] }}" class="v-qr" alt="QR Code">
                <div class="v-qr-lbl">Contrôle de Sécurité</div>
            </td>
        </tr>
    </table>

    <div class="v-urg">
        En cas d'urgence : Parent / Tuteur Tél. {{ $carte['contact_urgence'] }}
    </div>

    <div class="v-rules">
        Cette carte est strictement personnelle et obligatoire dans l'enceinte de l'établissement. En cas de perte, prière de la rapporter à la Direction ou de contacter le numéro ci-dessus.
    </div>

    <div class="v-devise">
        {{ $carte['devise'] }}
    </div>
</div>

</body>
</html>
