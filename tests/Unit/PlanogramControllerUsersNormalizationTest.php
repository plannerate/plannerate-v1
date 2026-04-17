<?php

use App\Http\Controllers\Tenant\PlanogramController;
use App\Models\Planogram;
use Callcocam\LaravelRaptor\Support\Form\Form;
use Tests\TestCase;

uses(TestCase::class);

it('normalizes users payload from supported formats', function (mixed $payload, array $expected) {
    $controller = new class extends PlanogramController
    {
        public function normalizeUsersPayload(mixed $payload): array
        {
            return $this->normalizeWorkflowConfigUsers($payload);
        }
    };

    expect($controller->normalizeUsersPayload($payload))->toBe($expected);
})->with([
    'csv string' => ['01A,01B,01C', ['01A', '01B', '01C']],
    'single string' => ['01A', ['01A']],
    'simple array' => [['01A', '01B'], ['01A', '01B']],
    'objects array' => [[['id' => '01A'], ['value' => '01B']], ['01A', '01B']],
    'mixed and duplicated' => [['01A', ['id' => '01A'], ['value' => '01B'], '', null], ['01A', '01B']],
    'empty string' => ['', []],
    'null payload' => [null, []],
]);

it('casts non-array scalar payload into array of one user id', function () {
    $controller = new class extends PlanogramController
    {
        public function normalizeUsersPayload(mixed $payload): array
        {
            return $this->normalizeWorkflowConfigUsers($payload);
        }
    };

    expect($controller->normalizeUsersPayload(12345))->toBe(['12345']);
});

it('builds form for existing planogram model without throwing errors', function () {
    $controller = new class extends PlanogramController
    {
        public function buildFormForModel(Planogram $planogram): Form
        {
            $planogram->exists = true;

            return $this->form(new Form($planogram));
        }
    };

    $planogram = Planogram::factory()->make();

    expect(fn () => $controller->buildFormForModel($planogram))->not->toThrow(Throwable::class);
});
