<!DOCTYPE html>
<html lang="fr">
<head>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}?v=syges3">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}?v=syges3">
    <link rel="shortcut icon" href="{{ asset('favicon.ico') }}?v=syges3">
    <meta charset="UTF-8">
    <title>Relevé Individuel des Versements - {{ $eleve->nom }} {{ $eleve->prenom }}</title>
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
            width: 32%;
            font-size: 7pt;
            text-align: center;
            line-height: 1.2;
        }
        .header-center {
            width: 36%;
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
            border: 1.5px solid #0f172a;
            background-color: #f8fafc;
            border-radius: 4px;
            padding: 6px;
            margin: 6px 0 10px 0;
            text-align: center;
        }
        .doc-title {
            font-size: 11pt;
            font-weight: bold;
            text-transform: uppercase;
            color: #0f172a;
        }
        .doc-subtitle {
            font-size: 7.5pt;
            color: #475569;
        }
        .student-card {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
            border: 1px solid #cbd5e1;
            background-color: #f8fafc;
        }
        .student-card td {
            padding: 6px 10px;
            font-size: 8pt;
            border: 1px solid #e2e8f0;
        }
        .summary-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
        }
        .summary-table td {
            padding: 6px 8px;
            border: 1px solid #cbd5e1;
            font-size: 8pt;
            text-align: center;
        }
        .summary-header {
            font-weight: bold;
            background-color: #f1f5f9;
            font-size: 7.5pt;
            text-transform: uppercase;
            color: #475569;
        }
        .summary-val {
            font-weight: bold;
            font-size: 9.5pt;
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }
        .data-table th, .data-table td {
            border: 1px solid #cbd5e1;
            padding: 5px 7px;
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
        .in-words {
            background-color: #f1f5f9;
            padding: 6px 10px;
            border-left: 3px solid #047857;
            font-size: 8pt;
            margin-bottom: 18px;
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
            margin-bottom: 40px;
            text-transform: uppercase;
        }
    </style>
</head>
<body>

    @include('finances.partials.header', ['serviceTitle' => "Service de l'Intendance & Comptabilité Scolaire"])

    <div class="doc-banner">
        <div class="doc-title">Relevé Individuel des Versements de l'Élève</div>
        <div class="doc-subtitle">
            Situation du compte financier au <strong>{{ \Carbon\Carbon::now()->format('d-m-Y à H:i') }}</strong>
            • Année Scolaire : <strong>{{ $annee->code ?? '2026-2027' }}</strong>
        </div>
    </div>

    {{-- Carte d'identité de l'élève --}}
    <table class="student-card">
        <tr>
            <td style="width: 50%;">
                Matricule : <strong style="font-family: monospace;">{{ $eleve->matricule }}</strong><br>
                Nom & Prénoms : <strong>{{ $eleve->nom }} {{ $eleve->prenom }}</strong><br>
                Sexe : <strong>{{ $eleve->sexe ?? 'N/A' }}</strong>
            </td>
            <td style="width: 50%;">
                Classe : <strong>{{ $classe }}</strong><br>
                Contact Parent : <strong>{{ $eleve->telephone_parent ?? $eleve->telephone_tuteur ?? $eleve->telephone ?? 'N/A' }}</strong><br>
                Date & Lieu de Naissance : <strong>{{ $eleve->date_naissance ? \Carbon\Carbon::parse($eleve->date_naissance)->format('d-m-Y') : 'N/A' }} {{ $eleve->lieu_naissance ? 'à '.$eleve->lieu_naissance : '' }}</strong>
            </td>
        </tr>
    </table>

    {{-- Synthèse de la situation financière --}}
    <table class="summary-table">
        <tr class="summary-header">
            <td style="width: 25%;">Montant Initial Dû</td>
            <td style="width: 25%;">Bourse / Réduction</td>
            <td style="width: 25%;">Net Versé</td>
            <td style="width: 25%;">Reste à Payer</td>
        </tr>
        <tr>
            <td class="summary-val">{{ number_format($a_verser, 0, ',', ' ') }} FCFA</td>
            <td class="summary-val" style="color: #64748b;">{{ number_format($reduction, 0, ',', ' ') }} FCFA</td>
            <td class="summary-val" style="color: #047857;">{{ number_format($total_verse, 0, ',', ' ') }} FCFA</td>
            <td class="summary-val" style="color: {{ $solde_restant > 0 ? '#dc2626' : '#047857' }};">
                {{ number_format($solde_restant, 0, ',', ' ') }} FCFA
            </td>
        </tr>
    </table>

    {{-- Historique chronologique des versements --}}
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 5%;" class="text-center">#</th>
                <th style="width: 17%;">Date & Heure</th>
                <th style="width: 20%;">N° Reçu</th>
                <th style="width: 28%;">Frais / Rubriques</th>
                <th style="width: 12%;">Mode</th>
                <th style="width: 18%;" class="text-right">Montant Versé</th>
            </tr>
        </thead>
        <tbody>
            @forelse($versements as $idx => $v)
                <tr>
                    <td class="text-center">{{ $idx + 1 }}</td>
                    <td>{{ $v['date'] }}</td>
                    <td class="font-bold" style="font-family: monospace;">{{ $v['reference_recu'] }}</td>
                    <td style="color: #475569;">{{ $v['rubriques'] ?? 'Versement scolarité' }}</td>
                    <td style="text-transform: uppercase;">{{ str_replace('_', ' ', $v['mode_paiement']) }}</td>
                    <td class="text-right font-bold" style="color: #047857;">
                        {{ number_format($v['montant'], 0, ',', ' ') }} FCFA
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="text-center" style="padding: 20px; color: #64748b;">
                        Aucun versement n'a encore été enregistré pour cet élève sur l'année sélectionnée.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="in-words">
        Total des versements effectués à ce jour :
        <strong style="text-transform: uppercase; color: #047857;">{{ $total_verse_lettres ?? number_format($total_verse, 0, ',', ' ') . ' Francs CFA' }}</strong>.
        @if($solde_restant <= 0)
            <br><span style="color: #047857; font-weight: bold;">✓ Compte soldé et en règle pour l'année scolaire en cours.</span>
        @else
            <br><span style="color: #dc2626; font-weight: bold;">⚠ Reste à payer pour solder l'année scolaire : {{ number_format($solde_restant, 0, ',', ' ') }} FCFA.</span>
        @endif
    </div>

    <table class="signatures-table">
        <tr>
            <td>
                <div class="sign-title">L'Économe / Caissier</div>
                <div style="font-size: 7.5pt; color: #64748b;">(Visa et cachet officiel)</div>
            </td>
            <td>
                <div class="sign-title">Le Chef d'Établissement / Direction</div>
                <div style="font-size: 7.5pt; color: #64748b;">(Pour confirmation)</div>
            </td>
        </tr>
    </table>

</body>
</html>

