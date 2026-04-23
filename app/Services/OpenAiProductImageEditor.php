<?php

namespace App\Services;

use App\Contracts\ProductImageAiEditor;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\Laravel\Facades\Image;
use RuntimeException;

class OpenAiProductImageEditor implements ProductImageAiEditor
{
    public function process(string $sourcePath, string $targetPath): string
    {
        $apiKey = (string) config('services.openai.api_key');

        if ($apiKey === '') {
            throw new RuntimeException('OPENAI_API_KEY nao configurada.');
        }

        if (! Storage::disk('public')->exists($sourcePath)) {
            throw new RuntimeException('Imagem de origem nao encontrada.');
        }

        $sourceBinary = Storage::disk('public')->get($sourcePath);
        $response = Http::withToken($apiKey)
            ->timeout(120)
            ->attach('image', $sourceBinary, basename($sourcePath))
            ->post('https://api.openai.com/v1/images/edits', [
                'model' => 'gpt-image-1',
                'prompt' => 'Standardize this product photo for e-commerce catalog. Remove/clean background, center the product, keep original colors and realistic details, improve sharpness, no text or watermark.',
                'size' => '1024x1024',
            ]);

        if ($response->failed()) {
            throw new RuntimeException('Falha ao processar imagem com OpenAI.');
        }

        $firstData = $response->json('data.0');

        if (! is_array($firstData)) {
            throw new RuntimeException('Resposta invalida da OpenAI para edicao de imagem.');
        }

        $editedBinary = null;

        $b64 = $firstData['b64_json'] ?? null;
        if (is_string($b64) && $b64 !== '') {
            $decoded = base64_decode($b64, true);
            if ($decoded !== false) {
                $editedBinary = $decoded;
            }
        }

        if ($editedBinary === null) {
            $url = $firstData['url'] ?? null;
            if (is_string($url) && $url !== '') {
                $download = Http::timeout(120)->get($url);
                if ($download->ok()) {
                    $editedBinary = $download->body();
                }
            }
        }

        if ($editedBinary === null || $editedBinary === '') {
            throw new RuntimeException('Nao foi possivel obter o conteudo da imagem processada.');
        }

        $image = Image::decodeBinary($editedBinary);
        $encodedImage = $image->encode(new WebpEncoder(90));
        Storage::disk('public')->put($targetPath, (string) $encodedImage);

        return $targetPath;
    }
}
