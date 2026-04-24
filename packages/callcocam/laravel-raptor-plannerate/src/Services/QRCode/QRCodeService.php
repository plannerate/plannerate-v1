<?php

namespace Callcocam\LaravelRaptorPlannerate\Services\QRCode;

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;

class QRCodeService
{
    /**
     * Gera QR Code para uma URL
     */
    public function generateForUrl(string $url, int $size = 300): string
    {
        $builder = new Builder(
            writer: new PngWriter,
            writerOptions: [],
            validateResult: false,
            data: $url,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::High,
            size: $size,
            margin: 10,
            roundBlockSizeMode: RoundBlockSizeMode::Margin,
        );

        $result = $builder->build();

        return $result->getDataUri();
    }

    /**
     * Gera QR Code para uma gôndola
     * URL: /export/gondola/{gondolaId}/view
     */
    public function generateForGondola(string $gondolaId, ?string $baseUrl = null): string
    {
        $baseUrl = $baseUrl ?? config('app.url');
        $url = "{$baseUrl}/export/gondola/{$gondolaId}/view";

        return $this->generateForUrl($url);
    }

    /**
     * Gera QR Code para uma seção (módulo)
     */
    public function generateForSection(string $sectionId, ?string $baseUrl = null): string
    {
        $baseUrl = $baseUrl ?? config('app.url');
        $url = "{$baseUrl}/client/section/{$sectionId}";

        return $this->generateForUrl($url);
    }

    /**
     * Gera QR Code como arquivo PNG
     */
    public function generateToFile(string $url, string $path, int $size = 300): void
    {
        $builder = new Builder(
            writer: new PngWriter,
            writerOptions: [],
            validateResult: false,
            data: $url,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::High,
            size: $size,
            margin: 10,
            roundBlockSizeMode: RoundBlockSizeMode::Margin,
        );

        $result = $builder->build();

        $result->saveToFile($path);
    }
}
