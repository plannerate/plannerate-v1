<?php

use Illuminate\Support\Facades\Storage;

/**
 * O disco `public` deve gerar URLs root-relative para que as imagens sejam
 * servidas a partir do mesmo host que renderiza a página (subdomínio do tenant
 * ou domínio central). URL absoluta com host fixo (APP_URL) causava bloqueio de
 * CORS ao carregar imagens em canvas/crossOrigin no editor de mapas (Konva).
 */
it('gera url root-relative para o disco public quando ASSET_URL não está definido', function (): void {
    expect(config('filesystems.disks.public.url'))->toBe('/storage');

    expect(Storage::disk('public')->url('trade/maps/exemplo/img.webp'))
        ->toBe('/storage/trade/maps/exemplo/img.webp');
});
