<?php

declare(strict_types=1);

use Infocyph\ReqShield\Support\ValidationContext;
use Infocyph\ReqShield\Validator;

test('stripped unknown descendants cannot survive in validated parent output', function () {
    $result = Validator::make([
        'user' => 'array',
        'user.name' => 'required|string',
    ])->stripUnknown()->validate([
        'user' => ['name' => 'ok', 'is_admin' => true],
    ]);

    expect($result->passes())->toBeTrue()
        ->and($result->validated()['user'])->toBe(['name' => 'ok'])
        ->and($result->typed()['user'])->toBe(['name' => 'ok'])
        ->and($result->safe()['user'])->toBe(['name' => 'ok'])
        ->and($result->input(false)->arrayValue('user'))->toBe(['name' => 'ok']);
});

test('excluded and invalid descendants never survive in a validated parent', function () {
    $result = Validator::make([
        'user' => 'array',
        'user.name' => 'required|string',
        'user.age' => 'integer',
        'user.secret' => 'exclude',
    ])->validate(['user' => [
        'name' => 'ok',
        'age' => 'INVALID',
        'secret' => 'sensitive',
    ]]);

    expect($result->fails())->toBeTrue()
        ->and($result->validated()['user'])->toBe(['name' => 'ok'])
        ->and($result->safe()['user'])->toBe(['name' => 'ok'])
        ->and($result->typed()['user'])->toBe(['name' => 'ok']);
});

test('after callback failure prunes a previously validated nested member', function () {
    $result = Validator::make([
        'user' => 'array',
        'user.name' => 'string',
        'user.age' => 'integer',
    ])->after(static function (ValidationContext $context): void {
        $context->addError('user.age', 'Rejected after validation');
    })->validate(['user' => ['name' => 'ok', 'age' => 42]]);

    expect($result->fails())->toBeTrue()
        ->and($result->validated()['user'])->toBe(['name' => 'ok'])
        ->and($result->validated())->not->toHaveKey('user.age');
});

test('image and dimensions reject every unsafe upload path', function (mixed $path, int $error) {
    foreach (['image', 'dimensions:min_width=1,min_height=1'] as $rule) {
        $result = Validator::make(['upload' => $rule])->validate([
            'upload' => ['tmp_name' => $path, 'error' => $error],
        ]);
        expect($result->fails())->toBeTrue();
    }
})->with([
    'remote HTTP URI' => ['http://127.0.0.1:65530/private-image', UPLOAD_ERR_OK],
    'remote HTTPS URI' => ['https://example.invalid/private-image', UPLOAD_ERR_OK],
    'PHP stream wrapper' => ['php://memory', UPLOAD_ERR_OK],
    'empty path' => ['', UPLOAD_ERR_OK],
    'NUL path' => ["bad\0path", UPLOAD_ERR_OK],
    'upload error' => [__FILE__, UPLOAD_ERR_PARTIAL],
]);

test('local uploaded image still passes image and dimension validation', function () {
    $path = tempnam(sys_get_temp_dir(), 'reqshield-image-');
    if ($path === false) {
        throw new RuntimeException('Cannot create test image');
    }

    $bytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jB3kAAAAASUVORK5CYII=', true);
    if ($bytes === false) {
        throw new RuntimeException('Invalid image fixture');
    }
    file_put_contents($path, $bytes);

    try {
        foreach (['image', 'dimensions:min_width=1,min_height=1'] as $rule) {
            $result = Validator::make(['upload' => $rule])->validate([
                'upload' => ['tmp_name' => $path, 'error' => UPLOAD_ERR_OK],
            ]);
            expect($result->passes())->toBeTrue();
        }
    } finally {
        unlink($path);
    }
});
