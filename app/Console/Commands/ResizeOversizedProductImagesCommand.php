<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\Laravel\Facades\Image;

/**
 * Reescala imagens de produto já derivadas (WebP no disco public) que ficaram
 * grandes demais — tipicamente quando o produto não tinha width/height e o
 * pipeline manteve a resolução ORIGINAL (ex.: 5750px, 2-5 MP).
 *
 * Imagens enormes travam a decodificação no canvas do planograma (~1s no
 * primeiro clique de cada módulo). Este comando aplica o mesmo teto de dimensão
 * que o pipeline passou a usar, reescrevendo só os arquivos acima do limiar.
 *
 * Idempotente: rodar de novo não altera nada já dentro do teto.
 */
class ResizeOversizedProductImagesCommand extends Command
{
    protected $signature = 'plannerate:resize-oversized-images
        {--max-side= : Lado maior alvo em pixels após o resize (padrão: config plannerate.image.max_side)}
        {--threshold=400 : Só reprocessa arquivos cujo lado maior excede este valor}
        {--quality= : Qualidade do WebP de saída (padrão: config plannerate.image.quality)}
        {--dir=* : Diretórios no disco public a varrer (padrão: repositorioimages/frente e repositorioimagens/frente)}
        {--dry-run : Apenas lista o que seria alterado, sem reescrever nada}';

    protected $description = 'Reescala imagens de produto oversized para um teto de dimensão (decode rápido no canvas).';

    public function handle(): int
    {
        // Sem opção explícita, cai no padrão único de config/plannerate.php.
        $maxSide = (int) ($this->option('max-side') ?? config('plannerate.image.max_side', 384));
        $threshold = (int) $this->option('threshold');
        $quality = (int) ($this->option('quality') ?? config('plannerate.image.quality', 90));
        $dryRun = (bool) $this->option('dry-run');

        $dirs = $this->option('dir');
        if (empty($dirs)) {
            $dirs = ['repositorioimages/frente', 'repositorioimagens/frente'];
        }

        if ($maxSide < 1 || $threshold < $maxSide) {
            $this->error("Parâmetros inválidos: --threshold ({$threshold}) deve ser >= --max-side ({$maxSide}).");

            return self::FAILURE;
        }

        $disk = Storage::disk('public');

        if ($dryRun) {
            $this->warn('MODO DRY-RUN: nenhum arquivo será alterado.');
        }

        $this->info("Teto: {$maxSide}px | Limiar: {$threshold}px | Qualidade: {$quality}");
        $this->newLine();

        $scanned = 0;
        $changed = 0;
        $skipped = 0;
        $failed = 0;
        $savedBytes = 0;

        foreach ($dirs as $dir) {
            if (! $disk->exists($dir)) {
                $this->warn("Diretório inexistente, ignorado: {$dir}");

                continue;
            }

            foreach ($disk->files($dir) as $path) {
                if (! str_ends_with(strtolower($path), '.webp')) {
                    continue;
                }

                $scanned++;

                try {
                    $binary = $disk->get($path);

                    if (! is_string($binary) || $binary === '') {
                        $skipped++;

                        continue;
                    }

                    $image = Image::decodeBinary($binary);
                    $width = $image->width();
                    $height = $image->height();
                    $longest = max($width, $height);

                    if ($longest <= $threshold) {
                        $skipped++;

                        continue;
                    }

                    $scale = $maxSide / $longest;
                    $targetWidth = max(1, (int) round($width * $scale));
                    $targetHeight = max(1, (int) round($height * $scale));

                    $oldSize = strlen($binary);

                    if ($dryRun) {
                        $this->line(sprintf(
                            '[dry] %s  %dx%d → %dx%d  (%s KB)',
                            $path, $width, $height, $targetWidth, $targetHeight,
                            number_format($oldSize / 1024, 0),
                        ));
                        $changed++;

                        continue;
                    }

                    $image->resize($targetWidth, $targetHeight);
                    $encoded = (string) $image->encode(new WebpEncoder($quality));
                    $disk->put($path, $encoded);

                    $newSize = strlen($encoded);
                    $savedBytes += max(0, $oldSize - $newSize);
                    $changed++;

                    $this->line(sprintf(
                        '%s  %dx%d → %dx%d  %s KB → %s KB',
                        $path, $width, $height, $targetWidth, $targetHeight,
                        number_format($oldSize / 1024, 0),
                        number_format($newSize / 1024, 0),
                    ));
                } catch (\Throwable $e) {
                    $failed++;
                    $this->warn("Falha em {$path}: ".$e->getMessage());
                }
            }
        }

        $this->newLine();
        $this->info(sprintf(
            'Escaneados: %d | %s: %d | Ignorados: %d | Falhas: %d%s',
            $scanned,
            $dryRun ? 'A reescalar' : 'Reescalados',
            $changed,
            $skipped,
            $failed,
            $dryRun ? '' : sprintf(' | Economia: %s MB', number_format($savedBytes / 1048576, 1)),
        ));

        return self::SUCCESS;
    }
}
