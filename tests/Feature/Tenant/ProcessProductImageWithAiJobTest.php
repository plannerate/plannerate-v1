<?php

use App\Contracts\ProductImageAiEditor;
use App\Jobs\ProcessProductImageWithAiJob;
use App\Models\ProductImageAiOperation;
use Database\Seeders\LandlordRbacSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;

beforeEach(function (): void {
    config()->set('permission.rbac_enabled', true);

    Artisan::call('migrate:fresh', [
        '--database' => 'landlord',
        '--path' => 'database/migrations/landlord',
        '--force' => true,
        '--no-interaction' => true,
    ]);

    Artisan::call('migrate:fresh', [
        '--path' => 'database/migrations',
        '--force' => true,
        '--no-interaction' => true,
    ]);

    Artisan::call('db:seed', [
        '--class' => LandlordRbacSeeder::class,
        '--force' => true,
        '--no-interaction' => true,
    ]);
});

test('job marks operation as completed when editor succeeds', function (): void {
    $operation = ProductImageAiOperation::query()->create([
        'tenant_id' => (string) Str::ulid(),
        'source_path' => 'products/uploads/tenant/source.png',
        'status' => 'queued',
    ]);

    $editor = new class implements ProductImageAiEditor
    {
        public function process(string $sourcePath, string $targetPath): string
        {
            return $targetPath;
        }
    };

    $job = new ProcessProductImageWithAiJob($operation->id);
    $job->handle($editor);

    $operation->refresh();

    expect($operation->status)->toBe('completed');
    expect($operation->output_path)->toContain('products/processed/');
    expect($operation->error_message)->toBeNull();
});

test('job marks operation as failed when editor throws', function (): void {
    $operation = ProductImageAiOperation::query()->create([
        'tenant_id' => (string) Str::ulid(),
        'source_path' => 'products/uploads/tenant/source.png',
        'status' => 'queued',
    ]);

    $editor = new class implements ProductImageAiEditor
    {
        public function process(string $sourcePath, string $targetPath): string
        {
            throw new RuntimeException('error from editor');
        }
    };

    $job = new ProcessProductImageWithAiJob($operation->id);
    $job->handle($editor);

    $operation->refresh();

    expect($operation->status)->toBe('failed');
    expect($operation->error_message)->toBe('error from editor');
});
