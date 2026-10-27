{{--
    Vue Blade : Planche A4 de Cartes Scolaires - VERSO
    Rôle : Génère une planche industrielle A4 de 10 cartes par page (2 colonnes x 5 lignes) côté Verso
    Dimensions : Format A4 portrait (210mm x 297mm), chaque carte au format ISO CR-80 (86mm x 54mm)
    Conforme aux normes camerounaises et calibré sans saut de page parasite DomPDF.
--}}
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Planche Cartes Scolaires - Verso</title>
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

        /* Boîtier individuel Verso de la carte scolaire (Format CR-80 : 86mm x 53.5mm) */
        .card-box {
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

        /* En-tête du Verso */
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

        /* Zone centrale : Grand Logo & QR Code */
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

        /* Contacts urgence & Règlement */
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

        /* Bandeau Inférieur de Devise */
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
        <div class="page-sheet">
            <table class="tbl-sheet-grid">
                @foreach($planche->chunk(2) as $pair)
                    <tr>
                        @foreach($pair as $index => $c)
                            <td class="{{ $index === 0 ? 'cell-left' : 'cell-right' }}">
                                <div class="card-box">
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
