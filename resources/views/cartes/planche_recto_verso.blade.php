<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Planches Cartes Scolaires - Recto & Verso Alternés</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 10mm 12mm 10mm 12mm;
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
        .page-sheet {
            width: 186mm;
            height: 277mm;
            page-break-after: always;
            position: relative;
        }
        .page-sheet:last-child {
            page-break-after: avoid;
        }
        .cards-grid {
            display: table;
            width: 100%;
            border-collapse: separate;
            border-spacing: 5mm 5mm;
        }
        .cards-row {
            display: table-row;
        }
        .card-cell {
            display: table-cell;
            width: 86mm;
            height: 54mm;
            vertical-align: top;
            position: relative;
        }
        .card-box {
            width: 86mm;
            height: 54mm;
            border: 0.8px solid #94a3b8;
            border-radius: 4px;
            padding: 2.5mm 3mm;
            position: relative;
            background-color: #ffffff;
            overflow: hidden;
        }
        .card-box-verso {
            padding: 3mm 3.5mm 0 3.5mm;
            text-align: center;
        }

        /* Repères de coupe (Crop Marks) */
        .crop-mark { position: absolute; background: #cbd5e1; }
        .crop-tl-h { top: -2.5mm; left: -2.5mm; width: 2mm; height: 0.4px; }
        .crop-tl-v { top: -2.5mm; left: -2.5mm; width: 0.4px; height: 2mm; }
        .crop-tr-h { top: -2.5mm; right: -2.5mm; width: 2mm; height: 0.4px; }
        .crop-tr-v { top: -2.5mm; right: -2.5mm; width: 0.4px; height: 2mm; }
        .crop-bl-h { bottom: -2.5mm; left: -2.5mm; width: 2mm; height: 0.4px; }
        .crop-bl-v { bottom: -2.5mm; left: -2.5mm; width: 0.4px; height: 2mm; }
        .crop-br-h { bottom: -2.5mm; right: -2.5mm; width: 2mm; height: 0.4px; }
        .crop-br-v { bottom: -2.5mm; right: -2.5mm; width: 0.4px; height: 2mm; }

        /* Styles Recto */
        .header-table { width: 100%; border-collapse: collapse; font-size: 3.8pt; line-height: 1.1; text-align: center; margin-bottom: 1.5mm; }
        .header-table td { vertical-align: middle; padding: 0; }
        .txt-officiel { font-weight: bold; color: #0f172a; text-transform: uppercase; }
        .motto { font-style: italic; font-size: 3.4pt; color: #475569; }
        .logo-img { max-width: 10mm; max-height: 10mm; object-fit: contain; }
        .school-name { font-size: 6.5pt; font-weight: 800; color: #0284c7; text-align: center; text-transform: uppercase; letter-spacing: 0.3px; margin-bottom: 1.2mm; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .title-banner { width: 100%; height: 4.8mm; border-radius: 2px; overflow: hidden; margin-bottom: 1.2mm; border: 0.5px solid #0284c7; background: #e0f2fe; display: table; }
        .banner-flag { display: table-cell; width: 7mm; height: 4.8mm; vertical-align: middle; }
        .flag-table { width: 100%; height: 100%; border-collapse: collapse; }
        .flag-table td { height: 100%; padding: 0; }
        .flag-green { width: 33.3%; background: #15803d; }
        .flag-red { width: 33.4%; background: #dc2626; color: #facc15; font-size: 3.5pt; text-align: center; line-height: 4.8mm; }
        .flag-yellow { width: 33.3%; background: #eab308; }
        .banner-text { display: table-cell; vertical-align: middle; text-align: center; font-size: 6.2pt; font-weight: 900; color: #0369a1; text-transform: uppercase; letter-spacing: 0.5px; }
        .year-label { font-size: 5.5pt; font-weight: bold; color: #b91c1c; text-align: center; margin-bottom: 1.5mm; letter-spacing: 0.3px; }
        .body-table { width: 100%; border-collapse: collapse; }
        .body-table td { vertical-align: top; padding: 0; }
        .photo-cell { width: 19mm; padding-right: 2.5mm; }
        .photo-frame { width: 18mm; height: 22mm; border: 0.8px solid #94a3b8; border-radius: 3px; overflow: hidden; background: #f1f5f9; text-align: center; }
        .student-photo { width: 100%; height: 100%; object-fit: cover; }
        .photo-placeholder { line-height: 22mm; font-size: 5pt; color: #94a3b8; font-weight: bold; }
        .info-cell { width: 42mm; padding-right: 1.5mm; }
        .info-row { font-size: 4.6pt; line-height: 1.4; color: #1e293b; margin-bottom: 0.6mm; white-space: nowrap; }
        .info-label { font-weight: bold; color: #334155; display: inline-block; width: 14mm; }
        .info-val { font-weight: 700; color: #0f172a; }
        .val-matricule { color: #dc2626; font-size: 5.2pt; }
        .val-nom { color: #1e3a8a; font-size: 5pt; }
        .val-classe { color: #b91c1c; font-size: 5pt; }
        .stamp-cell { width: 19mm; text-align: center; position: relative; }
        .stamp-circle { width: 16mm; height: 16mm; border: 0.8px dashed #818cf8; border-radius: 50%; margin: 0 auto 0.8mm auto; position: relative; background: rgba(238, 242, 255, 0.4); }
        .stamp-text { font-size: 2.8pt; color: #4338ca; text-transform: uppercase; font-weight: bold; position: absolute; top: 25%; left: 5%; right: 5%; text-align: center; line-height: 1.1; }
        .signature-name { font-size: 3.8pt; font-weight: bold; color: #1e3a8a; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

        /* Styles Verso */
        .school-title { font-size: 6.5pt; font-weight: 800; color: #0284c7; text-transform: uppercase; letter-spacing: 0.3px; margin-bottom: 0.8mm; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .school-contacts { font-size: 4.8pt; font-weight: bold; color: #1e293b; margin-bottom: 2mm; }
        .center-table { width: 100%; border-collapse: collapse; margin-bottom: 1.5mm; }
        .center-table td { vertical-align: middle; text-align: center; padding: 0; }
        .large-logo { max-width: 22mm; max-height: 22mm; object-fit: contain; }
        .qr-wrapper { text-align: center; width: 22mm; }
        .qr-img { width: 17mm; height: 17mm; display: inline-block; }
        .qr-caption { font-size: 3.2pt; font-weight: bold; color: #64748b; text-transform: uppercase; margin-top: 0.5mm; }
        .rules-text { font-size: 3.6pt; line-height: 1.25; color: #475569; text-align: justify; margin-bottom: 1.5mm; padding: 0 1mm; }
        .emergency-info { font-size: 4.2pt; font-weight: bold; color: #0f172a; margin-bottom: 1.5mm; }
        .motto-banner { position: absolute; bottom: 0; left: 0; right: 0; height: 5.5mm; background: #0284c7; color: #ffffff; font-size: 6.2pt; font-weight: bold; line-height: 5.5mm; text-align: center; letter-spacing: 0.5px; }
    </style>
</head>
<body>
    @foreach($planches as $planche)
        {{-- 1. PAGE RECTO --}}
        <div class="page-sheet">
            <div class="cards-grid">
                @foreach($planche->chunk(2) as $pair)
                    <div class="cards-row">
                        @foreach($pair as $c)
                            <div class="card-cell">
                                <div class="crop-mark crop-tl-h"></div>
                                <div class="crop-mark crop-tl-v"></div>
                                <div class="crop-mark crop-tr-h"></div>
                                <div class="crop-mark crop-tr-v"></div>
                                <div class="crop-mark crop-bl-h"></div>
                                <div class="crop-mark crop-bl-v"></div>
                                <div class="crop-mark crop-br-h"></div>
                                <div class="crop-mark crop-br-v"></div>

                                <div class="card-box">
                                    <table class="header-table">
                                        <tr>
                                            <td style="width: 38%; text-align: left;">
                                                <div class="txt-officiel">RÉPUBLIQUE DU CAMEROUN</div>
                                                <div class="motto">Paix – Travail – Patrie</div>
                                                <div class="txt-officiel">{{ $c['delegation_regionale'] }}</div>
                                                <div class="txt-officiel">{{ $c['delegation_departementale'] }}</div>
                                            </td>
                                            <td style="width: 24%; text-align: center;">
                                                @if(!empty($c['logo_url']) && file_exists(public_path($c['logo_url'])))
                                                    <img src="{{ public_path($c['logo_url']) }}" class="logo-img" alt="Logo">
                                                @else
                                                    <div style="font-size: 10pt; color: #0284c7; font-weight: bold;">★</div>
                                                @endif
                                            </td>
                                            <td style="width: 38%; text-align: right;">
                                                <div class="txt-officiel">REPUBLIC OF CAMEROON</div>
                                                <div class="motto">Peace – Work – Fatherland</div>
                                                <div class="txt-officiel">{{ $c['delegation_regionale_en'] }}</div>
                                                <div class="txt-officiel">{{ $c['delegation_departementale_en'] }}</div>
                                            </td>
                                        </tr>
                                    </table>

                                    <div class="school-name">{{ $c['etablissement_nom'] }}</div>

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

                                    <div class="year-label">Année Scolaire {{ $c['annee_nom'] }}</div>

                                    <table class="body-table">
                                        <tr>
                                            <td class="photo-cell">
                                                <div class="photo-frame">
                                                    @if(!empty($c['photo_url']) && file_exists(public_path($c['photo_url'])))
                                                        <img src="{{ public_path($c['photo_url']) }}" class="student-photo" alt="Photo">
                                                    @else
                                                        <div class="photo-placeholder">PHOTO</div>
                                                    @endif
                                                </div>
                                            </td>
                                            <td class="info-cell">
                                                <div class="info-row">
                                                    <span class="info-label">Matricule :</span>
                                                    <span class="info-val val-matricule">{{ $c['matricule'] }}</span>
                                                </div>
                                                <div class="info-row">
                                                    <span class="info-label">Nom :</span>
                                                    <span class="info-val val-nom">{{ $c['nom_complet'] }}</span>
                                                </div>
                                                <div class="info-row">
                                                    <span class="info-label">Né(e) le :</span>
                                                    <span class="info-val">{{ $c['date_naissance'] }} A {{ $c['lieu_naissance'] }}</span>
                                                </div>
                                                <div class="info-row">
                                                    <span class="info-label">Classe :</span>
                                                    <span class="info-val val-classe">{{ $c['classe_nom'] }}</span>
                                                </div>
                                                <div class="info-row">
                                                    <span class="info-label">Direction :</span>
                                                    <span class="info-val" style="color: #047857;">{{ $c['telephone_direction'] }}</span>
                                                </div>
                                                <div class="info-row">
                                                    <span class="info-label">Sexe :</span>
                                                    <span class="info-val" style="color: #b91c1c;">{{ $c['sexe'] }}</span>
                                                </div>
                                            </td>
                                            <td class="stamp-cell">
                                                <div class="stamp-circle">
                                                    <div class="stamp-text">
                                                        CACHET OFFICIEL<br>DIRECTION
                                                    </div>
                                                </div>
                                                <div class="signature-name">{{ $c['nom_chef'] }}</div>
                                            </td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endforeach
            </div>
        </div>

        {{-- 2. PAGE VERSO (En miroir exact pour le verso de chaque carte) --}}
        <div class="page-sheet">
            <div class="cards-grid">
                @foreach($planche->chunk(2) as $pair)
                    <div class="cards-row">
                        @foreach($pair->reverse() as $c)
                            <div class="card-cell">
                                <div class="crop-mark crop-tl-h"></div>
                                <div class="crop-mark crop-tl-v"></div>
                                <div class="crop-mark crop-tr-h"></div>
                                <div class="crop-mark crop-tr-v"></div>
                                <div class="crop-mark crop-bl-h"></div>
                                <div class="crop-mark crop-bl-v"></div>
                                <div class="crop-mark crop-br-h"></div>
                                <div class="crop-mark crop-br-v"></div>

                                <div class="card-box card-box-verso">
                                    <div class="school-title">{{ $c['etablissement_nom'] }}</div>

                                    <div class="school-contacts">
                                        Adresse : {{ $c['adresse'] }}
                                        @if($c['boite_postale'] && $c['boite_postale'] !== '---') | B.P: {{ $c['boite_postale'] }} @endif
                                        | Tél: {{ $c['telephone_direction'] }}
                                    </div>

                                    <table class="center-table">
                                        <tr>
                                            <td style="width: 50%;">
                                                @if(!empty($c['logo_url']) && file_exists(public_path($c['logo_url'])))
                                                    <img src="{{ public_path($c['logo_url']) }}" class="large-logo" alt="Logo">
                                                @else
                                                    <div style="font-size: 26pt; color: #cbd5e1; font-weight: bold;">★</div>
                                                @endif
                                            </td>
                                            <td style="width: 50%;">
                                                <div class="qr-wrapper">
                                                    <img src="{{ $c['qr_code'] }}" class="qr-img" alt="QR Code">
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
                            </div>
                        @endforeach
                    </div>
                @endforeach
            </div>
        </div>
    @endforeach
</body>
</html>
