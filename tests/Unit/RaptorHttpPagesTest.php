<?php

use Callcocam\LaravelRaptor\Support\Pages\Get;
use Callcocam\LaravelRaptor\Support\Pages\Patch;
use Callcocam\LaravelRaptor\Support\Pages\Post;
use Callcocam\LaravelRaptor\Support\Pages\Put;

it('defines expected http method defaults for loose route pages', function () {
    expect(Get::route('/foo')->getMethod())->toBe('GET')
        ->and(Post::route('/foo')->getMethod())->toBe('POST')
        ->and(Put::route('/foo')->getMethod())->toBe('PUT')
        ->and(Patch::route('/foo')->getMethod())->toBe('PATCH');
});

it('allows defining explicit actions for loose route pages', function () {
    $page = Post::route('/analysis/abc/analyze')->action('analyze');

    expect($page->getAction())->toBe('analyze');
});
