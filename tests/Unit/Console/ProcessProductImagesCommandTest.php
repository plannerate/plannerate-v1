<?php

use App\Console\Commands\ProcessProductImages;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Console\Input\ArrayInput;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    $landlordPath = database_path('testing_process_product_images_unit_landlord.sqlite');
    if (! file_exists($landlordPath)) {
        touch($landlordPath);
    }

    Config::set('database.connections.landlord', [
        'driver' => 'sqlite',
        'database' => $landlordPath,
        'prefix' => '',
        'foreign_key_constraints' => false,
    ]);

    DB::purge('landlord');

    Schema::connection('landlord')->dropIfExists('ean_references');
    Schema::connection('landlord')->create('ean_references', function (Blueprint $table): void {
        $table->string('id')->primary();
        $table->string('ean')->unique();
        $table->string('image_front_url')->nullable();
        $table->timestamps();
        $table->timestamp('deleted_at')->nullable();
    });
});

function setCommandInput(ProcessProductImages $command, array $input): void
{
    $reflection = new ReflectionClass($command);
    $inputProperty = $reflection->getParentClass()->getProperty('input');
    $inputProperty->setAccessible(true);
    $inputProperty->setValue($command, new ArrayInput($input, $command->getDefinition()));
}

test('command loads only active ean references with image path', function (): void {
    DB::connection('landlord')->table('ean_references')->insert([
        [
            'id' => '01jts31n2rpz1tyy4n6xv4qap1',
            'ean' => '7891000000001',
            'image_front_url' => 'repositorioimages/frente/7891000000001.webp',
            'created_at' => now(),
            'updated_at' => now(),
            'deleted_at' => null,
        ],
        [
            'id' => '01jts31n2rpz1tyy4n6xv4qap2',
            'ean' => '7891000000002',
            'image_front_url' => null,
            'created_at' => now(),
            'updated_at' => now(),
            'deleted_at' => null,
        ],
        [
            'id' => '01jts31n2rpz1tyy4n6xv4qap3',
            'ean' => '7891000000003',
            'image_front_url' => 'repositorioimages/frente/7891000000003.webp',
            'created_at' => now(),
            'updated_at' => now(),
            'deleted_at' => Carbon::now(),
        ],
    ]);

    $command = new ProcessProductImages;

    $reflection = new ReflectionClass($command);
    $method = $reflection->getMethod('loadEanReferencesForEans');
    $result = $method->invoke($command, [
        '7891000000001',
        '7891000000002',
        '7891000000003',
        '7891000000004',
    ]);

    expect($result)->toBe([
        '7891000000001' => 'repositorioimages/frente/7891000000001.webp',
    ]);
});

test('command signature contains set-url-null option', function (): void {
    $command = new ProcessProductImages;

    expect($command->getDefinition()->hasOption('set-url-null'))->toBeTrue();
});

test('command signature contains force option', function (): void {
    $command = new ProcessProductImages;

    expect($command->getDefinition()->hasOption('force'))->toBeTrue();
});

test('command selects only missing image references when not forced', function (): void {
    DB::connection('landlord')->table('ean_references')->insert([
        [
            'id' => '01jts31n2rpz1tyy4n6xv4qbp1',
            'ean' => '7891000000011',
            'image_front_url' => null,
            'created_at' => now(),
            'updated_at' => now(),
            'deleted_at' => null,
        ],
        [
            'id' => '01jts31n2rpz1tyy4n6xv4qbp2',
            'ean' => '7891000000012',
            'image_front_url' => 'repositorioimages/frente/7891000000012.webp',
            'created_at' => now(),
            'updated_at' => now(),
            'deleted_at' => null,
        ],
    ]);

    $command = new ProcessProductImages;
    setCommandInput($command, []);

    $reflection = new ReflectionClass($command);
    $method = $reflection->getMethod('resolveEanReferencesForProcessing');
    $result = $method->invoke($command);

    expect($result)->toHaveCount(1)
        ->and($result->first()->ean)->toBe('7891000000011');
});

test('command includes references with image when forced', function (): void {
    DB::connection('landlord')->table('ean_references')->insert([
        [
            'id' => '01jts31n2rpz1tyy4n6xv4qcp1',
            'ean' => '7891000000021',
            'image_front_url' => null,
            'created_at' => now(),
            'updated_at' => now(),
            'deleted_at' => null,
        ],
        [
            'id' => '01jts31n2rpz1tyy4n6xv4qcp2',
            'ean' => '7891000000022',
            'image_front_url' => 'repositorioimages/frente/7891000000022.webp',
            'created_at' => now(),
            'updated_at' => now(),
            'deleted_at' => null,
        ],
    ]);

    $command = new ProcessProductImages;
    setCommandInput($command, ['--force' => true]);

    $reflection = new ReflectionClass($command);
    $method = $reflection->getMethod('resolveEanReferencesForProcessing');
    $result = $method->invoke($command);

    expect($result)->toHaveCount(2);
});
