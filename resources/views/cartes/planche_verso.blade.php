<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Planche Cartes Scolaires - Verso</title>
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
            padding: 3mm 3.5mm 0 3.5mm;
            position: relative;
            background-color: #ffffff;
            overflow: hidden;
            text-align: center;
        }

        /* Repères de coupe (Crop Marks) */
        .crop-mark {
            position: absolute;
            background: #cbd5e1;
        }
        .crop-tl-h { top: -2.5mm; left: -2.5mm; width: 2mm; height: 0.4px; }
        .crop-tl-v { top: -2.5mm; left: -2.5mm; width: 0.4px; height: 2mm; }
        .crop-tr-h { top: -2.5mm; right: -2.5mm; width: 2mm; height: 0.4px; }
        .crop-tr-v { top: -2.5mm; right: -2.5mm; width: 0.4px; height: 2mm; }
        .crop-bl-h { bottom: -2.5mm; left: -2.5mm; width: 2mm; height: 0.4px; }
        .crop-bl-v { bottom: -2.5mm; left: -2.5mm; width: 0.4px; height: 2mm; }
        .crop-br-h { bottom: -2.5mm; right: -2.5mm; width: 2mm; height: 0.4px; }
        .crop-br-v { bottom: -2.5mm; right: -2.5mm; width: 0.4px; height: 2mm; }

        /* Éléments du Verso */
        .school-title {
            font-size: 6.5pt;
            font-weight: 800;
            color: #0284c7;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            margin-bottom: 0.8mm;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .school-contacts {
            font-size: 4.8pt;
            font-weight: bold;
            color: #1e293b;
            margin-bottom: 2mm;
        }

        /* Zone Centrale : Grand Logo & QR Code */
        .center-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 1.5mm;
        }
        .center-table td {
            vertical-align: middle;
            text-align: center;
            padding: 0;
        }
        .large-logo {
            max-width: 22mm;
            max-height: 22mm;
            object-fit: contain;
        }
        .qr-wrapper {
            text-align: center;
            width: 22mm;
        }
        .qr-img {
            width: 17mm;
            height: 17mm;
            display: inline-block;
        }
        .qr-caption {
            font-size: 3.2pt;
            font-weight: bold;
            color: #64748b;
            text-transform: uppercase;
            margin-top: 0.5mm;
        }

        /* Mentions Légales & Urgence */
        .rules-text {
            font-size: 3.6pt;
            line-height: 1.25;
            color: #475569;
            text-align: justify;
            margin-bottom: 1.5mm;
            padding: 0 1mm;
        }
        .emergency-info {
            font-size: 4.2pt;
            font-weight: bold;
            color: #0f172a;
            margin-bottom: 1.5mm;
        }

        /* Bandeau Inférieur de Devise */
        .motto-banner {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 5.5mm;
            background: #0284c7;
            color: #ffffff;
            font-size: 6.2pt;
            font-weight: bold;
            line-height: 5.5mm;
            text-align: center;
            letter-spacing: 0.5px;
            text-transform: capitalize;
        }
    </style>
</head>
<body>
    @foreach($planches as $planche)
        <div class="page-sheet">
            <div class="cards-grid">
                @foreach($planche->chunk(2) as $pair)
                    <div class="cards-row">
                        {{-- Ordre inversé pour le verso en miroir exact (duplex massicot) --}}
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

                                <div class="card-box">
                                    {{-- Titre de l'établissement --}}
                                    <div class="school-title">{{ $c['etablissement_nom'] }}</div>

                                    {{-- Coordonnées officielles --}}
                                    <div class="school-contacts">
                                        Adresse : {{ $c['adresse'] }}
                                        @if($c['boite_postale'] && $c['boite_postale'] !== '---') | B.P: {{ $c['boite_postale'] }} @endif
                                        | Tél: {{ $c['telephone_direction'] }}
                                    </div>

                                    {{-- Zone centrale (Logo de l'école + QR Code d'authenticité) --}}
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

                                    {{-- Contact d'Urgence --}}
                                    <div class="emergency-info">
                                        En cas d'urgence : Parent / Tuteur Tél. {{ $c['contact_urgence'] }}
                                    </div>

                                    {{-- Règlement Intérieur Abrégé --}}
                                    <div class="rules-text">
                                        Cette carte est strictement personnelle et obligatoire dans l'enceinte de l'établissement. En cas de perte, prière de la rapporter à la Direction ou de contacter le numéro ci-dessus.
                                    </div>

                                    {{-- Bandeau Devise Officielle (Ex: Respect - Travail - Réussite) --}}
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
