<?php

namespace Callcocam\LaravelRaptorPlannerate\Http\Controllers;
 
use Callcocam\LaravelRaptorPlannerate\Services\Printing\GondolaPrintService;
use Callcocam\LaravelRaptorPlannerate\Services\QRCode\QRCodeService;
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
        $qrCode = $this->qrCodeService->generateForGondola($gondolaId);

        return response()->json([
            'success' => true,
            'qr_code' => $qrCode,
        ]);
    }

    public function generateSectionQrCode(Request $request, string $sectionId): JsonResponse
    {
        $qrCode = $this->qrCodeService->generateForSection($sectionId);

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
