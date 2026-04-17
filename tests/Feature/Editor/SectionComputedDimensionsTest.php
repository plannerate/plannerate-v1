<?php

use App\Models\Editor\Gondola;
use App\Models\Editor\Section;

it('uses default scale factor when gondola relation is not loaded', function () {
    $section = new Section([
        'width' => 130,
        'height' => 170,
        'cremalheira_width' => 3,
    ]);

    expect($section->section_height)->toBe(510.0)
        ->and($section->section_width)->toBe(141.0);
});

it('uses loaded gondola scale factor for computed dimensions and hides gondola relation', function () {
    $section = new Section([
        'width' => 130,
        'height' => 170,
        'cremalheira_width' => 3,
    ]);

    $section->setRelation('gondola', new Gondola([
        'scale_factor' => 2,
    ]));

    $data = $section->toArray();

    expect($section->section_height)->toBe(340.0)
        ->and($section->section_width)->toBe(138.0)
        ->and($data)->not->toHaveKey('gondola');
});
