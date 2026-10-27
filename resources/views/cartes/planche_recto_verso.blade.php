{{--
    Vue Blade : Planche A4 de Cartes Scolaires - RECTO & VERSO ALTERNÉS (IMPRESSION RECTO-VERSO)
    Rôle : Génère pour chaque paquet de 10 cartes :
           1. Une page de 10 Rectos
           2. Une page de 10 Versos en miroir horizontal exact ($pair->reverse()) pour massicotage parfait
    Dimensions : Format A4 portrait (210mm x 297mm), chaque carte au format ISO CR-80 (86mm x 54mm)
--}}
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Planches Cartes Scolaires - Recto & Verso Alternés</title>
    <style>
        @page {
            size: 210mm 297mm portrait;
            margin: 8mm 12mm 8mm 12mm;
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
        .page-sheet {
            width: 100%;
            page-break-after: always;
        }
        .page-sheet:last-child {
            page-break-after: avoid;
        }

        /* Grille principale de la feuille : 5 lignes x 2 colonnes */
        .tbl-sheet-grid {
            width: 100%;
            border-collapse: collapse;
        }
        .cell-left {
            width: 50%;
            padding: 0 3mm 1.5mm 0;
            vertical-align: top;
        }
        .cell-right {
            width: 50%;
            padding: 0 0 1.5mm 3mm;
            vertical-align: top;
        }

        /* Boîtier individuel Recto */
        .card-box {
            width: 86mm;
            height: 53.5mm;
            border: 0.8px solid #0284c7;
            border-radius: 3px;
            padding: 2mm 2.5mm 0 2.5mm;
            background: #ffffff;
            overflow: hidden;
            position: relative;
        }

        /* Boîtier individuel Verso */
        .card-box-verso {
            width: 86mm;
            height: 53.5mm;
            border: 0.8px solid #0284c7;
            border-radius: 3px;
            padding: 2mm 2.5mm 0 2.5mm;
            background: #ffffff;
            overflow: hidden;
            position: relative;
            text-align: center;
        }

        /* --- STYLES RECTO --- */
        .tbl-hdr {
            width: 100%;
            border-collapse: collapse;
        }
        .tbl-hdr td {
            padding: 0;
            vertical-align: middle;
        }
        .hdr-rep {
            font-size: 3.2pt;
            font-weight: bold;
            text-transform: uppercase;
            color: #0f172a;
            line-height: 1.05;
        }
        .hdr-sub {
            font-size: 2.5pt;
            font-style: italic;
            color: #475569;
        }
        .hdr-del {
            font-size: 2.7pt;
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
            width: 20pt;
            height: 20pt;
        }
        .school-name {
            font-size: 6.2pt;
            font-weight: 900;
            color: #0284c7;
            text-transform: uppercase;
            text-align: center;
            margin-top: 1pt;
            margin-bottom: 1.2pt;
            white-space: nowrap;
            overflow: hidden;
            letter-spacing: 0.2px;
        }
        .tbl-banner {
            width: 100%;
            border-collapse: collapse;
            background: #e0f2fe;
            border: 0.5px solid #0284c7;
            margin-bottom: 1.5pt;
            border-radius: 1.5px;
        }
        .tbl-banner td {
            padding: 0;
            vertical-align: middle;
        }
        .flag-g { width: 5pt; height: 9pt; background: #15803d; }
        .flag-r { width: 7pt; height: 9pt; background: #dc2626; color: #facc15; text-align: center; font-size: 4pt; font-family: 'DejaVu Sans', sans-serif; line-height: 9pt; }
        .flag-y { width: 5pt; height: 9pt; background: #eab308; }
        .banner-title {
            text-align: center;
            font-size: 5.8pt;
            font-weight: 900;
            color: #0369a1;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        .banner-year {
            text-align: right;
            padding-right: 3.5pt !important;
            font-size: 4.5pt;
            font-weight: bold;
            color: #dc2626;
            white-space: nowrap;
        }
        .tbl-body {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1.5pt;
        }
        .tbl-body td {
            padding: 0;
            vertical-align: top;
        }
        .photo-box {
            width: 46pt;
            height: 52pt;
            border: 0.7px solid #94a3b8;
            border-radius: 2px;
            background: #f8fafc;
            text-align: center;
            overflow: hidden;
        }
        .photo-img {
            width: 46pt;
            height: 52pt;
            object-fit: cover;
        }
        .photo-placeholder {
            line-height: 52pt;
            font-size: 5.5pt;
            font-weight: bold;
            color: #94a3b8;
        }
        .info-row {
            font-size: 4.6pt;
            line-height: 1.32;
            margin-bottom: 1pt;
            white-space: nowrap;
        }
        .info-lbl {
            font-weight: bold;
            color: #475569;
            display: inline-block;
            width: 32pt;
        }
        .info-val {
            font-weight: bold;
            color: #0f172a;
        }
        .val-mat {
            color: #dc2626;
            font-family: monospace;
            font-size: 5.2pt;
        }
        .val-nom {
            color: #1e3a8a;
            font-size: 5.2pt;
        }
        .val-cls {
            color: #dc2626;
            font-size: 5pt;
        }
        .stamp-box {
            width: 32pt;
            height: 32pt;
            border: 0.7px dashed #6366f1;
            border-radius: 50%;
            margin: 1pt auto 0 auto;
            text-align: center;
            background: rgba(238, 242, 255, 0.45);
        }
        .stamp-txt {
            font-size: 2.7pt;
            font-weight: bold;
            color: #4338ca;
            text-transform: uppercase;
            padding-top: 7.5pt;
            line-height: 1.05;
        }
        .stamp-sign {
            font-size: 3pt;
            font-weight: bold;
            color: #1e3a8a;
            text-align: center;
            margin-top: 1.5pt;
            white-space: nowrap;
            overflow: hidden;
        }

        /* --- STYLES VERSO --- */
        .school-title {
            font-size: 6.8pt;
            font-weight: 900;
            color: #0284c7;
            text-transform: uppercase;
            text-align: center;
            margin-top: 1pt;
            white-space: nowrap;
            overflow: hidden;
            letter-spacing: 0.2px;
        }
        .school-contacts {
            font-size: 4.6pt;
            font-weight: bold;
            color: #334155;
            text-align: center;
            margin-top: 1pt;
            margin-bottom: 2pt;
        }
        .center-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 2pt;
        }
        .center-table td {
            vertical-align: middle;
            text-align: center;
            padding: 0;
        }
        .large-logo {
            width: 32pt;
            height: 32pt;
            object-fit: contain;
        }
        .qr-wrapper {
            text-align: center;
            display: inline-block;
        }
        .qr-img {
            width: 32pt;
            height: 32pt;
            display: inline-block;
        }
        .qr-caption {
            font-size: 3pt;
            font-weight: bold;
            color: #64748b;
            text-transform: uppercase;
            margin-top: 1pt;
        }
        .emergency-info {
            font-size: 4.6pt;
            font-weight: bold;
            color: #dc2626;
            text-align: center;
            margin-bottom: 1.5pt;
        }
        .rules-text {
            font-size: 3.6pt;
            line-height: 1.25;
            color: #475569;
            text-align: justify;
            padding: 0 3pt;
            margin-bottom: 2pt;
        }
        .motto-banner {
            background: #0284c7;
            color: #ffffff;
            font-size: 5.2pt;
            font-weight: bold;
            text-align: center;
            padding: 2pt 0;
            border-radius: 2px;
            letter-spacing: 0.4px;
        }
    </style>
</head>
<body>
    @foreach($planches as $planche)
        {{-- ==========================================
             PAGE 1 : RECTO (10 cartes)
             ========================================== --}}
        <div class="page-sheet">
            <table class="tbl-sheet-grid">
                @foreach($planche->chunk(2) as $pair)
                    <tr>
                        @foreach($pair as $index => $c)
                            <td class="{{ $index === 0 ? 'cell-left' : 'cell-right' }}">
                                <div class="card-box">
                                    <table class="tbl-hdr">
                                        <tr>
                                            <td style="width: 38%; text-align: left;">
                                                <div class="hdr-rep">RÉPUBLIQUE DU CAMEROUN</div>
                                                <div class="hdr-sub">Paix – Travail – Patrie</div>
                                                <div class="hdr-del">{{ $c['delegation_regionale'] }}</div>
                                                <div class="hdr-del">{{ $c['delegation_departementale'] }}</div>
                                            </td>
                                            <td style="width: 24%;" class="hdr-logo">
                                                @if(!empty($c['logo_base64']))
                                                    <img src="{{ $c['logo_base64'] }}" class="logo-img" width="20" height="20" alt="Logo">
                                                @elseif(!empty($c['logo_url']) && file_exists(public_path($c['logo_url'])))
                                                    <img src="{{ public_path($c['logo_url']) }}" class="logo-img" width="20" height="20" alt="Logo">
                                                @else
                                                    <div style="font-size: 9pt; color: #0284c7; font-weight: bold;">SYGES</div>
                                                @endif
                                            </td>
                                            <td style="width: 38%; text-align: right;">
                                                <div class="hdr-rep">REPUBLIC OF CAMEROON</div>
                                                <div class="hdr-sub">Peace – Work – Fatherland</div>
                                                <div class="hdr-del">{{ $c['delegation_regionale_en'] }}</div>
                                                <div class="hdr-del">{{ $c['delegation_departementale_en'] }}</div>
                                            </td>
                                        </tr>
                                    </table>

                                    <div class="school-name">{{ $c['etablissement_nom'] }}</div>

                                    <table class="tbl-banner">
                                        <tr>
                                            <td class="flag-g"></td>
                                            <td class="flag-r">&#9733;</td>
                                            <td class="flag-y"></td>
                                            <td class="banner-title">CARTE D'IDENTITÉ SCOLAIRE</td>
                                            <td class="banner-year">Année {{ $c['annee_nom'] }}</td>
                                        </tr>
                                    </table>

                                    <table class="tbl-body">
                                        <tr>
                                            <td style="width: 46pt;">
                                                <div class="photo-box">
                                                    @if(!empty($c['photo_base64']))
                                                        <img src="{{ $c['photo_base64'] }}" class="photo-img" width="46" height="52" alt="Photo">
                                                    @elseif(!empty($c['photo_url']) && file_exists(public_path($c['photo_url'])))
                                                        <img src="{{ public_path($c['photo_url']) }}" class="photo-img" width="46" height="52" alt="Photo">
                                                    @else
                                                        <div class="photo-placeholder">PHOTO</div>
                                                    @endif
                                                </div>
                                            </td>

                                            <td style="padding-left: 4pt; width: 130pt;">
                                                <div class="info-row">
                                                    <span class="info-lbl">Matricule :</span>
                                                    <span class="info-val val-mat">{{ $c['matricule'] }}</span>
                                                    @if(!empty($c['matricule_national']))
                                                        <span style="font-size: 3.8pt; color: #64748b;"> &bull; Nat: {{ $c['matricule_national'] }}</span>
                                                    @endif
                                                </div>
                                                <div class="info-row">
                                                    <span class="info-lbl">Nom :</span>
                                                    <span class="info-val val-nom">{{ $c['nom_complet'] }}</span>
                                                </div>
                                                <div class="info-row">
                                                    <span class="info-lbl">Né(e) le :</span>
                                                    <span class="info-val">{{ $c['date_naissance'] }} à {{ $c['lieu_naissance'] }}</span>
                                                </div>
                                                <div class="info-row">
                                                    <span class="info-lbl">Classe :</span>
                                                    <span class="info-val val-cls">{{ $c['classe_nom'] }}</span>
                                                    &nbsp;<span class="info-lbl" style="width: auto;">Sexe :</span>
                                                    <span class="info-val" style="color: #dc2626;">{{ $c['sexe'] }}</span>
                                                </div>
                                                <div class="info-row">
                                                    <span class="info-lbl">Direction :</span>
                                                    <span class="info-val" style="color: #047857;">{{ $c['telephone_direction'] }}</span>
                                                </div>
                                            </td>

                                            <td style="width: 46pt; text-align: center; vertical-align: top;">
                                                <div class="stamp-box">
                                                    <div class="stamp-txt">CACHET<br>DIRECTION</div>
                                                </div>
                                                <div class="stamp-sign">{{ $c['nom_chef'] }}</div>
                                            </td>
                                        </tr>
                                    </table>
                                </div>
                            </td>
                        @endforeach
                        @if(count($pair) === 1)
                            <td class="cell-right"></td>
                        @endif
                    </tr>
                @endforeach
            </table>
        </div>

        {{-- =========================================================================
             PAGE 2 : VERSO EN MIROIR HORIZONTAL EXACT (DUPLICATA DUPLEX PARFAIT)
             ========================================================================= --}}
        <div class="page-sheet">
            <table class="tbl-sheet-grid">
                @foreach($planche->chunk(2) as $pair)
                    <tr>
                        @foreach($pair->reverse() as $index => $c)
                            <td class="{{ $index === 0 ? 'cell-left' : 'cell-right' }}">
                                <div class="card-box-verso">
                                    <div class="school-title">{{ $c['etablissement_nom'] }}</div>

                                    <div class="school-contacts">
                                        Adresse : {{ $c['adresse'] }}
                                        @if(!empty($c['boite_postale']) && $c['boite_postale'] !== '---')
                                            | B.P : {{ $c['boite_postale'] }}
                                        @endif
                                        | Tél : {{ $c['telephone_direction'] }}
                                    </div>

                                    <table class="center-table">
                                        <tr>
                                            <td style="width: 50%;">
                                                @if(!empty($c['logo_base64']))
                                                    <img src="{{ $c['logo_base64'] }}" class="large-logo" width="32" height="32" alt="Logo">
                                                @elseif(!empty($c['logo_url']) && file_exists(public_path($c['logo_url'])))
                                                    <img src="{{ public_path($c['logo_url']) }}" class="large-logo" width="32" height="32" alt="Logo">
                                                @else
                                                    <div style="font-size: 20pt; color: #cbd5e1; font-weight: bold;">★</div>
                                                @endif
                                            </td>
                                            <td style="width: 50%;">
                                                <div class="qr-wrapper">
                                                    <img src="{{ $c['qr_code'] }}" class="qr-img" width="32" height="32" alt="QR Code">
                                                    <div class="qr-caption">Contrôle de Sécurité</div>
                                                </div>
                                            </td>
                                        </tr>
                                    </table>

                                    <div class="emergency-info">
                                        En cas d'urgence : Parent / Tuteur Tél. {{ $c['contact_urgence'] }}
                                    </div>

                                    <div class="rules-text">
                                        Cette carte est strictement personnelle et obligatoire dans l'enceinte de l'établissement. En cas de perte, prière de la rapporter à la Direction ou de contacter le numéro ci-dessus.
                                    </div>

                                    <div class="motto-banner">
                                        {{ $c['devise'] }}
                                    </div>
                                </div>
                            </td>
                        @endforeach
                        @if(count($pair) === 1)
                            <td class="cell-right"></td>
                        @endif
                    </tr>
                @endforeach
            </table>
        </div>
    @endforeach
</body>
</html>
