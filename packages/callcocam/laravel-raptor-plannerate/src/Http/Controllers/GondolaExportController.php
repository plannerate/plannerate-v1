<?php

namespace Callcocam\LaravelRaptorPlannerate\Http\Controllers;

use Callcocam\LaravelRaptorPlannerate\Services\Export\GondolaPrintService;
use Callcocam\LaravelRaptorPlannerate\Services\Export\QRCodeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GondolaExportController extends Controller
{
    public function __construct(
        protected GondolaPrintService $printService,
        protected QRCodeService $qrCodeService
    ) {}

    public function generateQrCode(Request $request, string $gondolaId): JsonResponse
    {
        // Usa o host do request atual (subdomínio do tenant), não o config('app.url') (domínio base)
        $qrCode = $this->qrCodeService->generateForGondola($gondolaId, $request->getSchemeAndHttpHost());

        return response()->json([
            'success' => true,
            'qr_code' => $qrCode,
        ]);
    }

    public function generateSectionQrCode(Request $request, string $sectionId): JsonResponse
    {
        // Usa o host do request atual (subdomínio do tenant), não o config('app.url') (domínio base)
        $qrCode = $this->qrCodeService->generateForSection($sectionId, $request->getSchemeAndHttpHost());

        return response()->json([
            'success' => true,
            'qr_code' => $qrCode,
        ]);
    }

    public function exportReport(string $gondolaId): JsonResponse
    {
        $data = $this->printService->prepareGondolaData($gondolaId);

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }
}
