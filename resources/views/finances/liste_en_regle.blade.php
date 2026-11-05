<!DOCTYPE html>
<html lang="fr">
<head>
    {{-- Identité visuelle & Favicons officiels SYGES --}}
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}?v=syges3">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}?v=syges3">
    <link rel="shortcut icon" href="{{ asset('favicon.ico') }}?v=syges3">
    <meta charset="UTF-8">
    <title>Liste des Élèves en Règle - {{ $date_reference }}</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 10mm 12mm;
        }
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 8.5pt;
            color: #0f172a;
            line-height: 1.3;
            margin: 0;
            padding: 0;
        }
        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8px;
        }
        .header-table td {
            vertical-align: top;
        }
        .header-left, .header-right {
            width: 35%;
            font-size: 7.5pt;
            text-align: center;
            line-height: 1.25;
        }
        .header-center {
            width: 30%;
            text-align: center;
        }
        .school-name {
            font-size: 11pt;
            font-weight: bold;
            color: #1e3a8a;
            text-transform: uppercase;
        }
        .school-sub {
            font-size: 7.5pt;
            color: #475569;
        }
        .doc-banner {
            border: 1.5px solid #059669;
            background-color: #ecfdf5;
            border-radius: 4px;
            padding: 6px;
            margin: 6px 0 10px 0;
            text-align: center;
        }
        .doc-title {
            font-size: 11pt;
            font-weight: bold;
            text-transform: uppercase;
            color: #065f46;
        }
        .doc-subtitle {
            font-size: 7.5pt;
            color: #475569;
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }
        .data-table th, .data-table td {
            border: 1px solid #cbd5e1;
            padding: 4px 6px;
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
        .badge-ok {
            background-color: #dcfce7;
            color: #166534;
            padding: 2px 5px;
            border-radius: 3px;
            font-size: 7pt;
            font-weight: bold;
        }
        .signatures-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 25px;
        }
        .signatures-table td {
            width: 50%;
            text-align: center;
            vertical-align: top;
            font-size: 8pt;
        }
        .sign-title {
            font-weight: bold;
            margin-bottom: 35px;
            text-transform: uppercase;
        }
    </style>
</head>
<body>

    <table class="header-table">
        <tr>
            <td class="header-left">
                RÉPUBLIQUE DU CAMEROUN<br>
                <em>Paix - Travail - Patrie</em><br>
                MINISTÈRE DES ENSEIGNEMENTS SECONDAIRES
            </td>
            <td class="header-center">
                <div class="school-name">{{ $etablissement->nom ?? 'SYGES COMPLEXE SCOLAIRE' }}</div>
                <div class="school-sub">Service Intendance & Caisse</div>
                <div class="school-sub">Année Scolaire : {{ $annee->code ?? '2026-2027' }}</div>
            </td>
            <td class="header-right">
                REPUBLIC OF CAMEROON<br>
                <em>Peace - Work - Fatherland</em><br>
                MINISTRY OF SECONDARY EDUCATION
            </td>
        </tr>
    </table>

    <div class="doc-banner">
        <div class="doc-title">État des Élèves en Règle Financière</div>
        <div class="doc-subtitle">
            Situation arrêtée au {{ $date_reference }}
            @if($classe) • Classe : <strong>{{ $classe->nom }}</strong> @endif
            @if($niveau && !$classe) • Niveau : <strong>{{ $niveau->nom }}</strong> @endif
            • <strong>{{ $nombre_en_regle }}</strong> élève(s) à jour de scolarité
        </div>
    </div>

    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 4%;" class="text-center">#</th>
                <th style="width: 14%;">Matricule</th>
                <th style="width: 32%;">Nom & Prénoms</th>
                <th style="width: 10%;">Classe</th>
                <th style="width: 15%;" class="text-right">Total Versé</th>
                <th style="width: 13%;" class="text-right">Reste Dû</th>
                <th style="width: 12%;" class="text-center">Statut</th>
            </tr>
        </thead>
        <tbody>
            @forelse($eleves as $idx => $e)
                <tr>
                    <td class="text-center">{{ $idx + 1 }}</td>
                    <td class="font-bold" style="font-family: monospace;">{{ $e['matricule'] }}</td>
                    <td class="font-bold">{{ $e['nom_complet'] }}</td>
                    <td>{{ $e['classe'] }}</td>
                    <td class="text-right font-bold" style="color: #047857;">{{ number_format($e['total_paye'], 0, ',', ' ') }} F</td>
                    <td class="text-right">{{ number_format($e['solde_restant'], 0, ',', ' ') }} F</td>
                    <td class="text-center">
                        <span class="badge-ok">{{ $e['statut'] }}</span>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="text-center" style="color: #64748b; padding: 15px;">
                        Aucun élève identifié en règle pour ces critères.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <table style="width: 100%; border-collapse: collapse; margin-top: 10px; background-color: #ecfdf5; border: 1.5px solid #059669;">
        <tr>
            <td style="padding: 6px 10px; font-weight: bold; color: #065f46; font-size: 8.5pt;">
                Total des Enregistrements Effectués : {{ number_format($total_paye, 0, ',', ' ') }} FCFA (sur {{ $nombre_en_regle }} élèves)
            </td>
        </tr>
    </table>

    <table class="signatures-table">
        <tr>
            <td>
                <div class="sign-title">L'Intendant / Caissier</div>
                <div style="font-size: 7.5pt; color: #64748b;">(Visa)</div>
            </td>
            <td>
                <div class="sign-title">Le Chef d'Établissement</div>
                <div style="font-size: 7.5pt; color: #64748b;">(Signature)</div>
            </td>
        </tr>
    </table>

</body>
</html>

