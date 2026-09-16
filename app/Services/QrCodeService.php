<?php

namespace App\Services;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/**
 * Service utilitaire de génération de QR Codes sécurisés (Gesco V1.1).
 *
 * Utilise la bibliothèque BaconQrCode pour produire des flux SVG vectoriels ou Data URI Base64
 * infalsifiables et parfaitement rendus dans les flux PDF générés par DomPDF.
 */
class QrCodeService
{
    /**
     * Génère le balisage XML SVG pur d'un QR code pour un contenu textuel donné.
     *
     * @param string $content Contenu textuel encodé dans le QR code
     * @param int $size Taille en pixels de côté (par défaut 120px)
     * @return string Balisage SVG complet
     */
    public function generateSvg(string $content, int $size = 120): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle($size, 2),
            new SvgImageBackEnd()
        );

        $writer = new Writer($renderer);

        return $writer->writeString($content);
    }

    /**
     * Génère un Data URI en Base64 du QR code au format SVG, directement injectable dans une balise <img>.
     *
     * @param string $content Contenu textuel encodé dans le QR code
     * @param int $size Taille en pixels (par défaut 120px)
     * @return string Chaîne Data URI (ex: data:image/svg+xml;base64,...)
     */
    public function generateBase64Svg(string $content, int $size = 120): string
    {
        $svg = $this->generateSvg($content, $size);

        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }
}
