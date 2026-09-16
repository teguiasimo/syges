<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Cahier de Bulletins - Classe {{ $classe['nom'] }} - {{ $type_bulletin ?? 'Bilan' }}</title>
    <style>
        .page-break {
            page-break-after: always;
        }
    </style>
</head>
<body>
    @foreach($eleves as $eleve)
        @if(($type_bulletin ?? 'trimestriel') === 'sequentiel')
            @include('bulletins.bulletin_sequentiel', [
                'eleve' => $eleve,
                'classe' => $classe,
                'evaluation' => $evaluation,
                'periode' => $periode,
                'annee_scolaire' => $annee_scolaire,
                'stats_classe' => $stats_classe,
                'mode_bulletin' => $mode_bulletin,
            ])
        @elseif(($type_bulletin ?? 'trimestriel') === 'annuel')
            @include('bulletins.bulletin_annuel', [
                'eleve' => $eleve,
                'classe' => $classe,
                'periodes' => $periodes,
                'annee_scolaire' => $annee_scolaire,
                'stats_classe' => $stats_classe,
                'mode_bulletin' => $mode_bulletin,
            ])
        @else
            @include('bulletins.bulletin_trimestriel', [
                'eleve' => $eleve,
                'classe' => $classe,
                'periode' => $periode,
                'annee_scolaire' => $annee_scolaire,
                'stats_classe' => $stats_classe,
                'mode_bulletin' => $mode_bulletin,
            ])
        @endif

        @if(!$loop->last)
            <div class="page-break"></div>
        @endif
    @endforeach
</body>
</html>
