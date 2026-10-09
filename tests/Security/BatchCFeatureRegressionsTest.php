<?php

declare(strict_types=1);

use Infocyph\ReqShield\Exceptions\CastException;
use Infocyph\ReqShield\Rules\SecureFile;
use Infocyph\ReqShield\Support\InputCaster;
use Infocyph\ReqShield\Validator;

test('integral floats outside native integer range are rejected without conversion warnings', function () {
    foreach ([1.0e30, -1.0e30, INF, -INF, NAN] as $value) {
        expect(InputCaster::tryInteger($value))->toBeNull();
    }

    expect(InputCaster::tryInteger((float) PHP_INT_MIN))->toBe(PHP_INT_MIN)
        ->and(InputCaster::tryInteger((float) PHP_INT_MAX + 1.0))->toBeNull()
        ->and(InputCaster::tryInteger(42.0))->toBe(42);
});

test('invalid integral float cast throws a cast exception rather than overflowing', function () {
    $validator = Validator::make(['value' => 'numeric'])->setCasts(['value' => 'integer']);

    expect(fn() => $validator->validate(['value' => 1.0e30]))
        ->toThrow(CastException::class);
});

test('compiled composite SecureFile rules snapshot independent nested objects', function () {
    $compiled = Validator::compile(['file' => new SecureFile()]);

    expect($compiled->validate([
        'file' => ['name' => '../bad.php', 'tmp_name' => __FILE__, 'error' => UPLOAD_ERR_OK],
    ])->fails())->toBeTrue();

    expect(Validator::compile(['file' => 'secure_file'])->validate([
        'file' => ['name' => '../bad.php', 'tmp_name' => __FILE__, 'error' => UPLOAD_ERR_OK],
    ])->fails())->toBeTrue();
});

test('terminal wildcard schema exports scalar and nested item properties', function () {
    $scalar = Validator::make(['items.*' => 'required|integer'])->exportSchema();
    expect($scalar['properties']['items']['items']['type'])->toBe('integer');

    $nested = Validator::make(['groups.*.items.*' => 'required|string'])->exportSchema();
    expect($nested['properties']['groups']['items']['properties']['items']['items']['type'])->toBe('string');
});
