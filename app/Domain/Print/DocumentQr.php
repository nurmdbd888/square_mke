<?php

namespace App\Domain\Print;

use App\Models\Document;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/**
 * Inline SVG QR codes for printed invoices (no GD extension required).
 */
final class DocumentQr
{
    public function forDocument(Document $document, int $size = 120): string
    {
        $payload = $document->number.'|'.$document->net_total.'|'.$document->doc_date->format('Y-m-d');
        $svg = (new Writer(new ImageRenderer(new RendererStyle($size, 1), new SvgImageBackEnd)))->writeString($payload);

        return (string) preg_replace('/^<\?xml[^>]*>\s*/', '', $svg);
    }
}
