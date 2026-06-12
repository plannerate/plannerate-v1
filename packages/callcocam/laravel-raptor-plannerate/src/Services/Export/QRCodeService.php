<?php

namespace Callcocam\LaravelRaptorPlannerate\Services\Export;

use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Renderer\GDLibRenderer;
use BaconQrCode\Writer;

class QRCodeService
{
    /**
     * Gera QR Code para uma URL
     */
    public function generateForUrl(string $url, int $size = 300): string
    {
        $png = $this->writer($size)->writeString($url, 'UTF-8', ErrorCorrectionLevel::H());

        return 'data:image/png;base64,'.base64_encode($png);
    }

    /**
     * Gera QR Code para uma gôndola
     * URL: /gondola/{gondolaId}/share (visualização pública, sem auth)
     */
    public function generateForGondola(string $gondolaId, ?string $baseUrl = null): string
    {
        $baseUrl = $baseUrl ?? config('app.url');
        $url = "{$baseUrl}/gondola/{$gondolaId}/share";

        return $this->generateForUrl($url);
    }

    /**
     * Gera QR Code para uma seção (módulo)
     */
    public function generateForSection(string $sectionId, ?string $baseUrl = null): string
    {
        $baseUrl = $baseUrl ?? config('app.url');
        $url = "{$baseUrl}/tenant/section/{$sectionId}";

        return $this->generateForUrl($url);
    }

    /**
     * Gera QR Code como arquivo PNG
     */
    public function generateToFile(string $url, string $path, int $size = 300): void
    {
        $this->writer($size)->writeFile($url, $path, 'UTF-8', ErrorCorrectionLevel::H());
    }

    private function writer(int $size): Writer
    {
        return new Writer(new GDLibRenderer($size, 10));
    }
}
