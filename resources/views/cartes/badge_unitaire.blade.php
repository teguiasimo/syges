<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Badge Unitaire - {{ $carte['matricule'] }}</title>
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
            color: #1a1a1a;
            background: #ffffff;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        .badge-card {
            width: 243pt;
            height: 153pt;
            padding: 7pt 9pt;
            position: relative;
            background-color: #ffffff;
            overflow: hidden;
            page-break-after: always;
        }
        .badge-card:last-child {
            page-break-after: avoid;
        }

        /* Recto */
        .header-table { width: 100%; border-collapse: collapse; font-size: 3.8pt; line-height: 1.1; text-align: center; margin-bottom: 3pt; }
        .header-table td { vertical-align: middle; padding: 0; }
        .txt-officiel { font-weight: bold; color: #0f172a; text-transform: uppercase; }
        .motto { font-style: italic; font-size: 3.2pt; color: #475569; }
        .logo-img { max-width: 26pt; max-height: 26pt; object-fit: contain; }
        .school-name { font-size: 7pt; font-weight: 800; color: #0284c7; text-align: center; text-transform: uppercase; letter-spacing: 0.3px; margin-bottom: 3pt; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .title-banner { width: 100%; height: 13pt; border-radius: 2px; overflow: hidden; margin-bottom: 3pt; border: 0.5px solid #0284c7; background: #e0f2fe; display: table; }
        .banner-flag { display: table-cell; width: 18pt; height: 13pt; vertical-align: middle; }
        .flag-table { width: 100%; height: 100%; border-collapse: collapse; }
        .flag-table td { height: 100%; padding: 0; }
        .flag-green { width: 33.3%; background: #15803d; }
        .flag-red { width: 33.4%; background: #dc2626; color: #facc15; font-size: 4pt; text-align: center; line-height: 13pt; }
        .flag-yellow { width: 33.3%; background: #eab308; }
        .banner-text { display: table-cell; vertical-align: middle; text-align: center; font-size: 6.8pt; font-weight: 900; color: #0369a1; text-transform: uppercase; letter-spacing: 0.5px; }
        .year-label { font-size: 5.5pt; font-weight: bold; color: #b91c1c; text-align: center; margin-bottom: 4pt; letter-spacing: 0.3px; }
        .body-table { width: 100%; border-collapse: collapse; }
        .body-table td { vertical-align: top; padding: 0; }
        .photo-cell { width: 52pt; padding-right: 6pt; }
        .photo-frame { width: 48pt; height: 60pt; border: 0.8px solid #94a3b8; border-radius: 3px; overflow: hidden; background: #f1f5f9; text-align: center; }
        .student-photo { width: 100%; height: 100%; object-fit: cover; }
        .photo-placeholder { line-height: 60pt; font-size: 6pt; color: #94a3b8; font-weight: bold; }
        .info-cell { width: 115pt; padding-right: 4pt; }
        .info-row { font-size: 4.8pt; line-height: 1.4; color: #1e293b; margin-bottom: 1.5pt; white-space: nowrap; }
        .info-label { font-weight: bold; color: #334155; display: inline-block; width: 38pt; }
        .info-val { font-weight: 700; color: #0f172a; }
        .val-matricule { color: #dc2626; font-size: 5.5pt; }
        .val-nom { color: #1e3a8a; font-size: 5.2pt; }
        .val-classe { color: #b91c1c; font-size: 5.2pt; }
        .stamp-cell { width: 55pt; text-align: center; position: relative; }
        .stamp-circle { width: 42pt; height: 42pt; border: 0.8px dashed #818cf8; border-radius: 50%; margin: 0 auto 2pt auto; position: relative; background: rgba(238, 242, 255, 0.4); }
        .stamp-text { font-size: 3pt; color: #4338ca; text-transform: uppercase; font-weight: bold; position: absolute; top: 25%; left: 5%; right: 5%; text-align: center; line-height: 1.1; }
        .signature-name { font-size: 4pt; font-weight: bold; color: #1e3a8a; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

        /* Verso */
        .school-title { font-size: 7.2pt; font-weight: 800; color: #0284c7; text-transform: uppercase; letter-spacing: 0.3px; margin-bottom: 2pt; text-align: center; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .school-contacts { font-size: 5pt; font-weight: bold; color: #1e293b; margin-bottom: 5pt; text-align: center; }
        .center-table { width: 100%; border-collapse: collapse; margin-bottom: 4pt; }
        .center-table td { vertical-align: middle; text-align: center; padding: 0; }
        .large-logo { max-width: 50pt; max-height: 50pt; object-fit: contain; }
        .qr-wrapper { text-align: center; width: 60pt; }
        .qr-img { width: 44pt; height: 44pt; display: inline-block; }
        .qr-caption { font-size: 3.5pt; font-weight: bold; color: #64748b; text-transform: uppercase; margin-top: 1pt; }
        .rules-text { font-size: 3.8pt; line-height: 1.25; color: #475569; text-align: justify; margin-bottom: 4pt; padding: 0 4pt; }
        .emergency-info { font-size: 4.8pt; font-weight: bold; color: #0f172a; margin-bottom: 4pt; text-align: center; }
        .motto-banner { position: absolute; bottom: 0; left: 0; right: 0; height: 15pt; background: #0284c7; color: #ffffff; font-size: 6.5pt; font-weight: bold; line-height: 15pt; text-align: center; letter-spacing: 0.5px; }
    </style>
</head>
<body>
    {{-- PAGE 1 : RECTO --}}
    <div class="badge-card">
        <table class="header-table">
            <tr>
                <td style="width: 38%; text-align: left;">
                    <div class="txt-officiel">RÉPUBLIQUE DU CAMEROUN</div>
                    <div class="motto">Paix – Travail – Patrie</div>
                    <div class="txt-officiel">{{ $carte['delegation_regionale'] }}</div>
                    <div class="txt-officiel">{{ $carte['delegation_departementale'] }}</div>
                </td>
                <td style="width: 24%; text-align: center;">
                    @if(!empty($carte['logo_url']) && file_exists(public_path($carte['logo_url'])))
                        <img src="{{ public_path($carte['logo_url']) }}" class="logo-img" alt="Logo">
                    @else
                        <div style="font-size: 14pt; color: #0284c7; font-weight: bold;">★</div>
                    @endif
                </td>
                <td style="width: 38%; text-align: right;">
                    <div class="txt-officiel">REPUBLIC OF CAMEROON</div>
                    <div class="motto">Peace – Work – Fatherland</div>
                    <div class="txt-officiel">{{ $carte['delegation_regionale_en'] }}</div>
                    <div class="txt-officiel">{{ $carte['delegation_departementale_en'] }}</div>
                </td>
            </tr>
        </table>

        <div class="school-name">{{ $carte['etablissement_nom'] }}</div>

        <div class="title-banner">
            <div class="banner-flag">
                <table class="flag-table">
                    <tr>
                        <td class="flag-green"></td>
                        <td class="flag-red">★</td>
                        <td class="flag-yellow"></td>
                    </tr>
                </table>
            </div>
            <div class="banner-text">CARTE D'IDENTITE SCOLAIRE</div>
        </div>

        <div class="year-label">Année Scolaire {{ $carte['annee_nom'] }}</div>

        <table class="body-table">
            <tr>
                <td class="photo-cell">
                    <div class="photo-frame">
                        @if(!empty($carte['photo_url']) && file_exists(public_path($carte['photo_url'])))
                            <img src="{{ public_path($carte['photo_url']) }}" class="student-photo" alt="Photo">
                        @else
                            <div class="photo-placeholder">PHOTO</div>
                        @endif
                    </div>
                </td>
                <td class="info-cell">
                    <div class="info-row">
                        <span class="info-label">Matricule :</span>
                        <span class="info-val val-matricule">{{ $carte['matricule'] }}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Nom :</span>
                        <span class="info-val val-nom">{{ $carte['nom_complet'] }}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Né(e) le :</span>
                        <span class="info-val">{{ $carte['date_naissance'] }} A {{ $carte['lieu_naissance'] }}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Classe :</span>
                        <span class="info-val val-classe">{{ $carte['classe_nom'] }}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Direction :</span>
                        <span class="info-val" style="color: #047857;">{{ $carte['telephone_direction'] }}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Sexe :</span>
                        <span class="info-val" style="color: #b91c1c;">{{ $carte['sexe'] }}</span>
                    </div>
                </td>
                <td class="stamp-cell">
                    <div class="stamp-circle">
                        <div class="stamp-text">
                            CACHET OFFICIEL<br>DIRECTION
                        </div>
                    </div>
                    <div class="signature-name">{{ $carte['nom_chef'] }}</div>
                </td>
            </tr>
        </table>
    </div>

    {{-- PAGE 2 : VERSO --}}
    <div class="badge-card">
        <div class="school-title">{{ $carte['etablissement_nom'] }}</div>

        <div class="school-contacts">
            Adresse : {{ $carte['adresse'] }}
            @if($carte['boite_postale'] && $carte['boite_postale'] !== '---') | B.P: {{ $carte['boite_postale'] }} @endif
            | Tél: {{ $carte['telephone_direction'] }}
        </div>

        <table class="center-table">
            <tr>
                <td style="width: 50%;">
                    @if(!empty($carte['logo_url']) && file_exists(public_path($carte['logo_url'])))
                        <img src="{{ public_path($carte['logo_url']) }}" class="large-logo" alt="Logo">
                    @else
                        <div style="font-size: 32pt; color: #cbd5e1; font-weight: bold;">★</div>
                    @endif
                </td>
                <td style="width: 50%;">
                    <div class="qr-wrapper">
                        <img src="{{ $carte['qr_code'] }}" class="qr-img" alt="QR Code">
                        <div class="qr-caption">Contrôle de Sécurité</div>
                    </div>
                </td>
            </tr>
        </table>

        <div class="emergency-info">
            En cas d'urgence : Parent / Tuteur Tél. {{ $carte['contact_urgence'] }}
        </div>

        <div class="rules-text">
            Cette carte est strictement personnelle et obligatoire dans l'enceinte de l'établissement. En cas de perte, prière de la rapporter à la Direction ou de contacter le numéro ci-dessus.
        </div>

        <div class="motto-banner">
            {{ $carte['devise'] }}
        </div>
    </div>
</body>
</html>
