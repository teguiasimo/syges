<!DOCTYPE html>
<html lang="fr">
<head>
    {{-- Identité visuelle & Favicons officiels SYGES --}}
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}?v=syges3">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}?v=syges3">
    <link rel="shortcut icon" href="{{ asset('favicon.ico') }}?v=syges3">
    <meta charset="UTF-8">
    <title>Situation Financière - {{ $eleve->matricule }} - {{ $eleve->nom }}</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 10mm 12mm;
        }
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 8.5pt;
            color: #0f172a;
            line-height: 1.35;
            margin: 0;
            padding: 0;
        }
        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }
        .header-table td {
            vertical-align: top;
        }
        .header-left, .header-right {
            width: 36%;
            font-size: 7.5pt;
            text-align: center;
            line-height: 1.25;
        }
        .header-center {
            width: 28%;
            text-align: center;
        }
        .school-name {
            font-size: 11pt;
            font-weight: bold;
            color: #1e3a8a;
            text-transform: uppercase;
            margin-top: 3px;
        }
        .school-sub {
            font-size: 7pt;
            color: #475569;
        }
        .doc-banner {
            border: 1.5px solid #0f172a;
            background-color: #f8fafc;
            border-radius: 4px;
            padding: 8px 12px;
            margin: 10px 0 12px 0;
            text-align: center;
        }
        .doc-title {
            font-size: 13pt;
            font-weight: bold;
            text-transform: uppercase;
            color: #0f172a;
            letter-spacing: 0.5px;
        }
        .doc-subtitle {
            font-size: 8pt;
            color: #475569;
            margin-top: 2px;
        }
        .info-card {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
            border: 1px solid #cbd5e1;
            border-radius: 4px;
        }
        .info-card td {
            padding: 5px 8px;
            font-size: 8.5pt;
            border: 1px solid #e2e8f0;
        }
        .info-label {
            font-weight: bold;
            color: #334155;
            width: 20%;
            background-color: #f8fafc;
        }
        .info-val {
            color: #0f172a;
            width: 30%;
        }
        .section-title {
            font-size: 9.5pt;
            font-weight: bold;
            color: #1e3a8a;
            text-transform: uppercase;
            border-bottom: 2px solid #1e3a8a;
            padding-bottom: 3px;
            margin: 14px 0 8px 0;
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
        }
        .data-table th, .data-table td {
            border: 1px solid #cbd5e1;
            padding: 5px 6px;
            font-size: 8pt;
        }
        .data-table th {
            background-color: #f1f5f9;
            font-weight: bold;
            color: #1e293b;
            text-align: left;
        }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .font-bold { font-weight: bold; }
        .badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 7.5pt;
            font-weight: bold;
        }
        .badge-solde { background-color: #dcfce7; color: #166534; }
        .badge-retard { background-color: #fee2e2; color: #991b1b; }
        .badge-attente { background-color: #fef3c7; color: #92400e; }
        .summary-box {
            width: 100%;
            border-collapse: collapse;
            margin: 12px 0;
        }
        .summary-box td {
            padding: 8px 10px;
            border: 1.5px solid #0f172a;
            vertical-align: middle;
        }
        .summary-kpi {
            text-align: center;
        }
        .kpi-title {
            font-size: 7.5pt;
            text-transform: uppercase;
            color: #475569;
            font-weight: bold;
        }
        .kpi-val {
            font-size: 11pt;
            font-weight: bold;
            margin-top: 3px;
        }
        .signatures-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 25px;
        }
        .signatures-table td {
            width: 33.33%;
            text-align: center;
            vertical-align: top;
            font-size: 8pt;
        }
        .sign-title {
            font-weight: bold;
            margin-bottom: 45px;
            text-transform: uppercase;
            font-size: 8pt;
        }
        .footer-note {
            font-size: 6.5pt;
            color: #64748b;
            border-top: 1px solid #cbd5e1;
            padding-top: 4px;
            margin-top: 20px;
            text-align: center;
        }
    </style>
</head>
<body>

    {{-- En-tête Officiel --}}
    <table class="header-table">
        <tr>
            <td class="header-left">
                RÉPUBLIQUE DU CAMEROUN<br>
                <em>Paix - Travail - Patrie</em><br>
                MINISTÈRE DES ENSEIGNEMENTS SECONDAIRES<br>
                DÉLÉGATION RÉGIONALE
            </td>
            <td class="header-center">
                @if(!empty($etablissement->logo_url) && file_exists(public_path($etablissement->logo_url)))
                    <img src="{{ public_path($etablissement->logo_url) }}" style="max-height: 45px; max-width: 90px;" alt="Logo">
                @endif
                <div class="school-name">{{ $etablissement->nom ?? 'SYGES COMPLEXE SCOLAIRE' }}</div>
                <div class="school-sub">Année Scolaire : {{ $annee->code ?? '2026-2027' }}</div>
            </td>
            <td class="header-right">
                REPUBLIC OF CAMEROON<br>
                <em>Peace - Work - Fatherland</em><br>
                MINISTRY OF SECONDARY EDUCATION<br>
                REGIONAL DELEGATION
            </td>
        </tr>
    </table>

    {{-- Bannière du Document --}}
    <div class="doc-banner">
        <div class="doc-title">Situation Financière & Relevé de Compte Élève</div>
        <div class="doc-subtitle">Édité le {{ \Carbon\Carbon::now()->format('d-m-Y \à H:i') }} | Document Officiel de Scolarité</div>
    </div>

    {{-- Fiche Identité Élève --}}
    <table class="info-card">
        <tr>
            <td class="info-label">Matricule :</td>
            <td class="info-val font-bold">{{ $eleve->matricule }}</td>
            <td class="info-label">Classe :</td>
            <td class="info-val font-bold">{{ $compte->inscription?->classe?->nom ?? 'N/A' }}</td>
        </tr>
        <tr>
            <td class="info-label">Nom & Prénoms :</td>
            <td class="info-val font-bold">{{ $eleve->nom }} {{ $eleve->prenom }}</td>
            <td class="info-label">Statut Inscription :</td>
            <td class="info-val">{{ $compte->inscription?->statut_eleve === 'redoublant' ? 'Redoublant(e)' : 'Nouveau / Passant' }}</td>
        </tr>
        <tr>
            <td class="info-label">Tuteur / Parent :</td>
            <td class="info-val">{{ $eleve->nom_tuteur ?? $eleve->nom_parent ?? 'Non renseigné' }}</td>
            <td class="info-label">Téléphone Contact :</td>
            <td class="info-val font-bold">{{ $eleve->telephone_tuteur ?? $eleve->telephone ?? 'Non renseigné' }}</td>
        </tr>
    </table>

    {{-- 1. Échéancier Prévu & Services Optionnels --}}
    <div class="section-title">1. Échéancier Réglementaire & Prestations Souscrites</div>
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 5%;">#</th>
                <th style="width: 35%;">Rubrique / Prestation</th>
                <th style="width: 15%;">Catégorie</th>
                <th style="width: 15%;" class="text-center">Date Échéance</th>
                <th style="width: 15%;" class="text-right">Montant Dû</th>
                <th style="width: 15%;" class="text-center">État</th>
            </tr>
        </thead>
        <tbody>
            @forelse($echeancier['lignes'] as $idx => $ligne)
                <tr>
                    <td class="text-center">{{ $idx + 1 }}</td>
                    <td class="font-bold">{{ $ligne['libelle'] }}</td>
                    <td>{{ $ligne['categorie'] }}</td>
                    <td class="text-center">{{ $ligne['date_limite'] ?? 'À l\'inscription' }}</td>
                    <td class="text-right font-bold">{{ number_format($ligne['montant'], 0, ',', ' ') }} FCFA</td>
                    <td class="text-center">
                        @if($ligne['statut'] === 'solde')
                            <span class="badge badge-solde">RÉGLÉ</span>
                        @elseif($ligne['statut'] === 'en_retard')
                            <span class="badge badge-retard">EN RETARD</span>
                        @else
                            <span class="badge badge-attente">À ÉCHOIR</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="text-center" style="color: #64748b;">Aucun frais configuré pour ce niveau d'étude.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    {{-- Déductions & Bourses éventuelles --}}
    @if($compte->boursesRemises->isNotEmpty())
        <div style="background-color: #f5f3ff; border: 1px solid #ddd6fe; border-radius: 4px; padding: 6px 10px; margin-bottom: 10px; font-size: 7.5pt;">
            <strong style="color: #5b21b6;">Bourse / Réduction accordée :</strong>
            @foreach($compte->boursesRemises as $b)
                {{ $b->bourseRemise?->nom }} (-{{ number_format($b->montant_calcule, 0, ',', ' ') }} FCFA)
            @endforeach
        </div>
    @endif

    {{-- 2. Historique des Versements Effectués --}}
    <div class="section-title">2. Historique des Règlements Enregistrés à la Caisse</div>
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 20%;">N° Reçu</th>
                <th style="width: 15%;" class="text-center">Date & Heure</th>
                <th style="width: 20%;">Mode de Règlement</th>
                <th style="width: 25%;">Rubriques Ventilées</th>
                <th style="width: 20%;" class="text-right">Montant Payé</th>
            </tr>
        </thead>
        <tbody>
            @forelse($compte->versements->where('statut', 'valide') as $vers)
                <tr>
                    <td class="font-bold" style="font-family: monospace;">{{ $vers->reference_recu }}</td>
                    <td class="text-center">{{ \Carbon\Carbon::parse($vers->date_versement)->format('d-m-Y H:i') }}</td>
                    <td style="text-transform: uppercase;">{{ str_replace('_', ' ', $vers->mode_paiement) }}</td>
                    <td style="font-size: 7pt; color: #475569;">
                        @foreach($vers->ventilations as $vent)
                            • {{ $vent->libelle }} ({{ number_format($vent->montant, 0, ',', ' ') }} F)<br>
                        @endforeach
                    </td>
                    <td class="text-right font-bold" style="color: #047857;">{{ number_format($vers->montant_total, 0, ',', ' ') }} FCFA</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="text-center" style="color: #64748b;">Aucun versement enregistré à ce jour.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    {{-- 3. Synthèse Financière Consolidée --}}
    <table class="summary-box">
        <tr>
            <td class="summary-kpi" style="background-color: #f8fafc;">
                <div class="kpi-title">Montant Net Exigible</div>
                <div class="kpi-val" style="color: #1e293b;">{{ number_format($compte->montant_net_exigible, 0, ',', ' ') }} FCFA</div>
            </td>
            <td class="summary-kpi" style="background-color: #ecfdf5;">
                <div class="kpi-title" style="color: #065f46;">Total Déjà Réglé</div>
                <div class="kpi-val" style="color: #047857;">{{ number_format($compte->total_paye, 0, ',', ' ') }} FCFA</div>
            </td>
            <td class="summary-kpi" style="background-color: {{ $compte->solde_restant > 0 ? '#fef2f2' : '#f0fdf4' }};">
                <div class="kpi-title" style="color: {{ $compte->solde_restant > 0 ? '#991b1b' : '#166534' }};">Solde Restant Dû</div>
                <div class="kpi-val" style="color: {{ $compte->solde_restant > 0 ? '#dc2626' : '#15803d' }};">
                    {{ number_format($compte->solde_restant, 0, ',', ' ') }} FCFA
                </div>
            </td>
            <td class="summary-kpi" style="background-color: #f1f5f9; width: 28%;">
                <div class="kpi-title">Situation Vis-à-Vis des Échéances</div>
                @if($echeancier['montant_echu_en_retard'] > 0)
                    <div style="font-size: 8.5pt; font-weight: bold; color: #b91c1c; margin-top: 3px;">
                        Retard échu : {{ number_format($echeancier['montant_echu_en_retard'], 0, ',', ' ') }} FCFA
                    </div>
                @elseif($compte->solde_restant == 0)
                    <div style="font-size: 8.5pt; font-weight: bold; color: #15803d; margin-top: 3px;">
                        TOTALEMENT SOLDÉ (100%)
                    </div>
                @else
                    <div style="font-size: 8.5pt; font-weight: bold; color: #0284c7; margin-top: 3px;">
                        PARFAITEMENT À JOUR
                    </div>
                @endif
            </td>
        </tr>
    </table>

    {{-- Signatures & QR Code --}}
    <table class="signatures-table">
        <tr>
            <td>
                @if(!empty($qrCodeBase64))
                    <img src="{{ $qrCodeBase64 }}" alt="QR Code" style="width: 80px; height: 80px; margin-bottom: 4px;">
                    <div style="font-size: 6.5pt; color: #64748b;">Authentification Numérique Sécurisée</div>
                @endif
            </td>
            <td>
                <div class="sign-title">Le Caissier / Intendant</div>
                <div style="font-size: 7.5pt; color: #64748b;">(Visa & Cachet)</div>
            </td>
            <td>
                <div class="sign-title">Le Chef d'Établissement</div>
                <div style="font-size: 7.5pt; color: #64748b;">(Signature & Sceau)</div>
            </td>
        </tr>
    </table>

    <div class="footer-note">
        SYGES Cloud Platform • Ce document certifie la situation comptable scolaire de l'élève à la date d'émission. Toute altération manuelle est passible de poursuites.
    </div>

</body>
</html>

