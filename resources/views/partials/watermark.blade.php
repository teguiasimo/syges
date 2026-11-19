{{--
    Vue partielle réutilisable de filigrane officiel pour tous les états PDF SYGES (DomPDF).

    Résout l'image de filigrane de manière hiérarchique et générique :
    1. Une Data URI Base64 explicitement transmise (`$filigraneSrc`),
    2. Le filigrane configuré sur l'établissement (`$etablissement->getEffectiveFiligraneBase64()`),
    3. À défaut (vue consolidée multi-établissements), celui de l'institution faîtière (`$institution`).

    N'affiche rien si aucun filigrane n'est configuré, afin de préserver la sobriété des documents.
--}}
@php
    if (empty($skipWatermark)) {
        $resolvedWatermarkSrc = $filigraneSrc ?? null;

        if (empty($resolvedWatermarkSrc) && ! empty($etablissement) && is_object($etablissement) && method_exists($etablissement, 'getEffectiveFiligraneBase64')) {
            $resolvedWatermarkSrc = $etablissement->getEffectiveFiligraneBase64();
        }

        if (empty($resolvedWatermarkSrc)) {
            $instObj = (! empty($institution) && is_object($institution))
                ? $institution
                : ($etablissement->institution ?? \App\Models\Institution::first());

            if ($instObj && method_exists($instObj, 'getEffectiveFiligraneBase64')) {
                $resolvedWatermarkSrc = $instObj->getEffectiveFiligraneBase64();
            }
        }
    } else {
        $resolvedWatermarkSrc = null;
    }
@endphp

@if(! empty($resolvedWatermarkSrc))
    {{-- Positionnement fixe centré avec marges négatives pour compatibilité totale DomPDF sur A4 Portrait/Paysage --}}
    <div style="position: fixed; top: 50%; left: 50%; width: 340px; height: 340px; margin-top: -170px; margin-left: -170px; text-align: center; opacity: 0.08; z-index: -1000; pointer-events: none;">
        <img src="{{ $resolvedWatermarkSrc }}" style="width: 340px; height: 340px; object-fit: contain;" alt="">
    </div>
@endif

