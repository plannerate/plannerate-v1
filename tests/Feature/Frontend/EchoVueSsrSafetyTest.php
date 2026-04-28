<?php

use Illuminate\Support\Facades\File;

test('echo vue composables are guarded from server side rendering', function () {
    $unsafeComponents = collect(File::allFiles(resource_path('js')))
        ->filter(fn (SplFileInfo $file): bool => $file->getExtension() === 'vue')
        ->filter(fn (SplFileInfo $file): bool => str_contains(File::get($file->getPathname()), '@laravel/echo-vue'))
        ->reject(fn (SplFileInfo $file): bool => str_contains(File::get($file->getPathname()), "typeof window !== 'undefined'"))
        ->map(fn (SplFileInfo $file): string => $file->getRelativePathname())
        ->values();

    expect($unsafeComponents)->toBeEmpty();
});
