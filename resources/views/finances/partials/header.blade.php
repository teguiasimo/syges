{{-- 
    Partial d'en-tête officiel bilingue pour les documents et états financiers SYGES.
    Aligne la charte visuelle des finances sur celle des bulletins scolaires officiels :
    - Format tricolonne officiel de la République du Cameroun (Français à gauche, Anglais à droite)
    - Ministère de tutelle dynamique (MINEDUB pour Maternelle/Primaire, MINESEC pour Secondaire)
    - Intégration du logo effectif en Data URI Base64 pour un rendu infaillible sous DomPDF
    - Coordonnées de l'établissement (ou du Complexe Scolaire en vue consolidée), Devise et Année Scolaire
--}}
@php
    $hasEtab = !empty($etablissement) && is_object($etablissement);
    $institution = $hasEtab
        ? ($etablissement->institution ?? \App\Models\Institution::first())
        : \App\Models\Institution::first();

    // Ministère de tutelle dynamique selon l'ordre d'enseignement du campus ou des campus rattachés
    if ($hasEtab) {
        $ministereFr = $etablissement->ministere_tutelle ?? "MINISTÈRE DES ENSEIGNEMENTS SECONDAIRES";
        $ministereEn = $etablissement->ministere_tutelle_en ?? "MINISTRY OF SECONDARY EDUCATION";
    } else {
        $ordres = $institution?->etablissements()->pluck('ordre_enseignement')->unique()->values() ?? collect();
        $hasBase = $ordres->contains(fn ($o) => in_array($o, ['Maternelle', 'Primaire'], true));
        $hasSec = $ordres->contains(fn ($o) => in_array($o, ['Secondaire_General', 'Secondaire_Technique', 'Polyvalent'], true));

        if ($hasBase && ! $hasSec) {
            $ministereFr = "MINISTÈRE DE L'ÉDUCATION DE BASE";
            $ministereEn = "MINISTRY OF BASIC EDUCATION";
        } elseif ($hasBase && $hasSec) {
            $ministereFr = "MINESEC / MINEDUB";
            $ministereEn = "MINESEC / MINEDUB";
        } else {
            $ministereFr = "MINISTÈRE DES ENSEIGNEMENTS SECONDAIRES";
            $ministereEn = "MINISTRY OF SECONDARY EDUCATION";
        }
    }

    // Délégations régionale et départementale avec repli automatique sur l'institution
    $delegationRegFr = $hasEtab
        ? ($etablissement->delegation_regionale ?? $institution?->delegation_regionale ?? 'DÉLÉGATION RÉGIONALE')
        : ($institution?->delegation_regionale ?? 'DÉLÉGATION RÉGIONALE');
    $delegationRegEn = $hasEtab
        ? ($etablissement->delegation_regionale_en ?? $institution?->delegation_regionale_en ?? 'REGIONAL DELEGATION')
        : ($institution?->delegation_regionale_en ?? 'REGIONAL DELEGATION');

    $delegationDepFr = $hasEtab
        ? ($etablissement->delegation_departementale ?? $institution?->delegation_departementale ?? null)
        : ($institution?->delegation_departementale ?? null);
    $delegationDepEn = $hasEtab
        ? ($etablissement->delegation_departementale_en ?? $institution?->delegation_departementale_en ?? null)
        : ($institution?->delegation_departementale_en ?? null);

    // Nom de l'établissement ou de l'institution faîtière en vue consolidée
    $schoolNomFr = $hasEtab ? $etablissement->nom : ($institution?->nom ?: 'ÉTABLISSEMENT SCOLAIRE');
    $schoolNomEn = $hasEtab ? ($etablissement->nom_en ?: $etablissement->nom) : ($institution?->nom_en ?: $institution?->nom ?: 'EDUCATIONAL INSTITUTION');

    // Devise officielle
    $devise = $hasEtab
        ? ($etablissement->devise ?: ($institution?->devise ?: 'Discipline - Travail - Succès'))
        : ($institution?->devise ?: 'Discipline - Travail - Succès');

    // Contacts & Boîte Postale
    $bp = $hasEtab ? ($etablissement->boite_postale ?: '---') : ($institution?->boite_postale ?: '---');
    $telephone = $hasEtab
        ? ($etablissement->telephone_1 ?: ($etablissement->telephone ?: ($institution?->telephone_1 ?: '---')))
        : ($institution?->telephone_1 ?: '---');

    // Année scolaire formatée
    $anneeLibelle = !empty($annee)
        ? (is_object($annee) ? ($annee->code ?? $annee->nom ?? date('Y')) : (string)$annee)
        : (isset($annee_scolaire) && is_array($annee_scolaire) ? ($annee_scolaire['nom'] ?? date('Y')) : date('Y'));
    $anneeLibelleClean = preg_replace('/^\s*Ann[ée]e\s+scolaire\s*[:\-]?\s*/ui', '', $anneeLibelle);

    // Résolution sécurisée du logo effectif en Base64 (campus -> institution -> défaut)
    $logoSrc = null;
    if ($hasEtab && method_exists($etablissement, 'getEffectiveLogoBase64')) {
        $logoSrc = $etablissement->getEffectiveLogoBase64();
    } elseif ($institution && ! empty($institution->logo)) {
        $logoSrc = \App\Models\Institution::resolveImageBase64($institution->logo);
    }
    if (! $logoSrc) {
        foreach (['/logo.png', '/logo.jpg', '/images/logo.png'] as $cand) {
            $logoSrc = \App\Models\Institution::resolveImageBase64($cand);
            if ($logoSrc) {
                break;
            }
        }
    }
    $isCompact = ! empty($compact);
    $headerLogoMaxHeight = $isCompact ? '40px' : '68px';
    $headerLogoMaxWidth = $isCompact ? '78px' : '110px';
    $headerTableMargin = $isCompact ? '3px' : '6px';
@endphp

{{-- Filigrane officiel discret appliqué automatiquement sur tous les états financiers --}}
@include('partials.watermark', ['etablissement' => $etablissement ?? null, 'institution' => $institution ?? null, 'skipWatermark' => $skipWatermark ?? false])

<table style="width: 100%; border-collapse: collapse; margin-bottom: {{ $headerTableMargin }}; font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;">
    <tr>
        {{-- Colonne Gauche (Français) : République, Ministère, Délégations, École, Contacts et Année Scolaire sur une seule ligne unitaire --}}
        <td style="width: 38%; text-align: center; vertical-align: top; font-size: 6.5pt; line-height: 1.15; padding: 0 2px;">
            <div style="font-weight: bold; text-transform: uppercase; font-size: 6.8pt; color: #0f172a; white-space: nowrap;">RÉPUBLIQUE DU CAMEROUN</div>
            <div style="font-style: italic; font-size: 5.6pt; color: #475569; white-space: nowrap;">Paix - Travail - Patrie</div>
            <div style="text-transform: uppercase; font-weight: 600; font-size: 5.8pt; color: #1e293b; margin-top: 1px; white-space: nowrap;">{{ $ministereFr }}</div>
            <div style="text-transform: uppercase; font-size: 5.2pt; color: #475569; white-space: nowrap;">{{ $delegationRegFr }}</div>
            @if($delegationDepFr)
                <div style="text-transform: uppercase; font-size: 4.8pt; color: #64748b; white-space: nowrap;">{{ $delegationDepFr }}</div>
            @endif
            <div style="font-weight: bold; color: #1e3a8a; text-transform: uppercase; font-size: 6.4pt; margin-top: 1.5px; white-space: nowrap;">{{ $schoolNomFr }}</div>
            <div style="font-size: 5.4pt; color: #475569; white-space: nowrap;">BP : {{ $bp }} | Tél : {{ $telephone }}</div>
            {{-- L'année scolaire est placée de part et d'autre (FR/EN) pour libérer de la hauteur au centre --}}
            <div style="font-size: 6.0pt; font-weight: bold; color: #1e3a8a; margin-top: 1.5px; text-transform: uppercase; white-space: nowrap;">
                ANNÉE SCOLAIRE : {{ $anneeLibelleClean }}
            </div>
        </td>

        {{-- Colonne Centrale : Logo agrandi, Devise officielle du complexe/établissement et Service émetteur --}}
        <td style="width: 24%; text-align: center; vertical-align: middle; padding: 0 2px;">
            @if($logoSrc)
                <img src="{{ $logoSrc }}" style="max-height: {{ $headerLogoMaxHeight }}; max-width: {{ $headerLogoMaxWidth }}; object-fit: contain; margin-bottom: 2px;" alt="Logo">
            @else
                <div style="font-weight: bold; font-size: 13pt; color: #1e3a8a; margin-top: 2px; text-transform: uppercase;">
                    {{ $hasEtab ? ($etablissement->sigle ?: $etablissement->code ?: 'SYGES') : ($institution?->sigle ?: $institution?->code ?: 'SYGES') }}
                </div>
            @endif
            @if(!empty($devise))
                <div style="font-size: 5.4pt; font-style: italic; font-weight: 600; color: #1e3a8a; line-height: 1.15; margin-top: 1px;">
                    « {{ $devise }} »
                </div>
            @endif
            @if(!empty($serviceTitle))
                <div style="font-size: 5.2pt; font-weight: 600; color: #334155; text-transform: uppercase; margin-top: 1.5px;">
                    {{ $serviceTitle }}
                </div>
            @endif
        </td>

        {{-- Colonne Droite (Anglais) : Republic, Ministry, Delegations, School, Contacts et Academic Year sur une seule ligne unitaire --}}
        <td style="width: 38%; text-align: center; vertical-align: top; font-size: 6.5pt; line-height: 1.15; padding: 0 2px;">
            <div style="font-weight: bold; text-transform: uppercase; font-size: 6.8pt; color: #0f172a; white-space: nowrap;">REPUBLIC OF CAMEROON</div>
            <div style="font-style: italic; font-size: 5.6pt; color: #475569; white-space: nowrap;">Peace - Work - Fatherland</div>
            <div style="text-transform: uppercase; font-weight: 600; font-size: 5.8pt; color: #1e293b; margin-top: 1px; white-space: nowrap;">{{ $ministereEn }}</div>
            <div style="text-transform: uppercase; font-size: 5.2pt; color: #475569; white-space: nowrap;">{{ $delegationRegEn }}</div>
            @if($delegationDepEn)
                <div style="text-transform: uppercase; font-size: 4.8pt; color: #64748b; white-space: nowrap;">{{ $delegationDepEn }}</div>
            @endif
            <div style="font-weight: bold; color: #1e3a8a; text-transform: uppercase; font-size: 6.4pt; margin-top: 1.5px; white-space: nowrap;">{{ $schoolNomEn }}</div>
            <div style="font-size: 5.4pt; color: #475569; white-space: nowrap;">PO Box : {{ $bp }} | Phone : {{ $telephone }}</div>
            <div style="font-size: 6.0pt; font-weight: bold; color: #1e3a8a; margin-top: 1.5px; text-transform: uppercase; white-space: nowrap;">
                ACADEMIC YEAR : {{ $anneeLibelleClean }}
            </div>
        </td>
    </tr>
</table>

