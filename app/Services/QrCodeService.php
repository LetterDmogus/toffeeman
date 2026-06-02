<?php

namespace App\Services;

use App\Models\Table;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Facades\Storage;

class QrCodeService
{
    /**
     * Generate a QR code SVG for a table and save to storage.
     * Returns the public-accessible path.
     */
    public function generateForTable(Table $table): string
    {
        $url = url("/kiosk/{$table->qr_code}");

        $svgContent = $this->generateSvg($url);

        $path = "qr-codes/table-{$table->id}.svg";
        Storage::disk('public')->put($path, $svgContent);

        $table->update(['qr_image_path' => $path]);

        return $path;
    }

    /**
     * Generate a standalone printable SVG string for a table (with label).
     * Used for the download endpoint.
     */
    public function generatePrintableSvg(Table $table): string
    {
        $url = url("/kiosk/{$table->qr_code}");
        $label = 'Meja ' . $table->number . ($table->name ? ' · ' . $table->name : '');

        $qrSvgContent = $this->generateSvg($url, size: 320);

        // Extract just the inner SVG body (without the XML declaration)
        $qrInner = preg_replace('/<\?xml[^?]*\?>\s*/', '', $qrSvgContent);

        // Wrap in a printable card SVG
        return <<<SVG
<?xml version="1.0" encoding="UTF-8"?>
<svg xmlns="http://www.w3.org/2000/svg" width="400" height="480" viewBox="0 0 400 480">
  <!-- Card background -->
  <rect width="400" height="480" rx="16" fill="#ffffff" stroke="#e5e7eb" stroke-width="2"/>

  <!-- Header -->
  <rect x="0" y="0" width="400" height="60" rx="16" fill="#7c6ff7"/>
  <rect x="0" y="44" width="400" height="20" fill="#7c6ff7"/>
  <text x="200" y="38" font-family="Arial, sans-serif" font-size="20" font-weight="bold"
        fill="white" text-anchor="middle">Scan untuk Pesan</text>

  <!-- QR Code (embedded, shifted down) -->
  <g transform="translate(40, 70)">
    {$qrInner}
  </g>

  <!-- Table label -->
  <text x="200" y="430" font-family="Arial, sans-serif" font-size="16" font-weight="bold"
        fill="#1f2937" text-anchor="middle">{$label}</text>

  <!-- Footer hint -->
  <text x="200" y="460" font-family="Arial, sans-serif" font-size="11"
        fill="#9ca3af" text-anchor="middle">Scan dengan kamera HP kamu</text>
</svg>
SVG;
    }

    /**
     * Generate a plain QR code SVG string for a given URL.
     */
    private function generateSvg(string $url, int $size = 400): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle($size),
            new SvgImageBackEnd()
        );

        $writer = new Writer($renderer);

        return $writer->writeString($url);
    }
}
