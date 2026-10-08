<?php

declare(strict_types=1);

use Infocyph\ReqShield\Exceptions\InputLimitException;
use Infocyph\ReqShield\Support\NestedValidator;
use Infocyph\ReqShield\Validator;

test('wildcard shape cache does not reuse a different canonical shape', function () {
    $validator = Validator::make([
        'items' => 'required|array',
        'items.*' => 'integer',
    ]);
    $first = ['items' => ['a' => 1, 'b' => 2]];
    $second = ['items' => ['a;s;k:b' => 'INVALID']];

    expect(NestedValidator::shapeSignature($first))
        ->not->toBe(NestedValidator::shapeSignature($second));
    expect($validator->validate($first)->passes())->toBeTrue();
    expect($validator->validate($second)->fails())->toBeTrue();
    expect(Validator::make(['items' => 'required|array', 'items.*' => 'integer'])
        ->validate($second)->fails())->toBeTrue();
});

test('conflicting dotted and nested payload representations fail closed', function () {
    $validator = Validator::make([
        'user' => 'array',
        'user.age' => 'integer',
    ]);

    expect(fn() => $validator->validate([
        'user' => ['age' => 'INVALID'],
        'user.age' => 1,
    ]))->toThrow(InvalidArgumentException::class);
});

test('associative wildcard conditional rules bind dependency names', function () {
    $rules = ['items.*.value' => 'required_if:items.*.enabled,1'];
    $input = ['items' => ['alice' => ['enabled' => '1']]];

    expect(Validator::make($rules)->validate($input)->fails())->toBeTrue();
});

test('associative wildcard distinct accepts unique values', function () {
    $result = Validator::make(['items.*.code' => 'distinct'])->validate([
        'items' => [
            'alice' => ['code' => 'alpha'],
            'bob' => ['code' => 'beta'],
        ],
    ]);

    expect($result->passes())->toBeTrue();
});

test('sanitizer expansion remains within configured maximum fields', function () {
    $validator = Validator::make(['payload.*' => 'integer'])
        ->limits(maxFields: 3)
        ->setSanitizers(['payload' => ['jsonDecode']]);

    expect(fn() => $validator->validate([
        'payload' => json_encode(range(1, 20), JSON_THROW_ON_ERROR),
    ]))->toThrow(InputLimitException::class);
});

test('strict policy checks integer root keys', function () {
    expect(Validator::make(['name' => 'required|string'])
        ->strict()
        ->validate(['name' => 'ok', 0 => 'unregistered'])
        ->fails())->toBeTrue();
});

test('nested dotted collisions are rejected at any depth', function () {
    $validator = Validator::make(['user.profile.age' => 'integer']);

    expect(fn() => $validator->validate([
        'user' => [
            'profile.age' => 2,
            'profile' => ['age' => 'INVALID'],
        ],
    ]))->toThrow(InvalidArgumentException::class);
});

test('identical duplicate input representations remain valid', function () {
    $validator = Validator::make(['user.age' => 'integer']);

    expect($validator->validate([
        'user' => ['age' => 2],
        'user.age' => 2,
    ])->passes())->toBeTrue();
});

test('distinct rejects duplicate associative wildcard members', function () {
    $validator = Validator::make(['items.*.code' => 'distinct']);

    expect($validator->validate([
        'items' => [
            'alice' => ['code' => 'same'],
            'bob' => ['code' => 'same'],
        ],
    ])->fails())->toBeTrue();
});

test('distinct scopes multiple wildcard levels to the inner collection', function () {
    $validator = Validator::make(['groups.*.items.*.code' => 'distinct']);

    expect($validator->validate([
        'groups' => [
            'first' => ['items' => ['one' => ['code' => 'abc'], 'two' => ['code' => 'xyz']]],
            'second' => ['items' => ['one' => ['code' => 'abc']]],
        ],
    ])->passes())->toBeTrue();
});

test('wildcard members containing literal dots are rejected rather than silently skipped', function () {
    $validator = Validator::make(['items.*.code' => 'required|integer']);

    expect(fn() => $validator->validate([
        'items' => ['alice.smith' => ['code' => 'INVALID']],
    ]))->toThrow(InvalidArgumentException::class);
});

test('distinct works with an object rule definition', function () {
    $validator = Validator::make(['items.*.code' => [new \Infocyph\ReqShield\Rules\Distinct()]]);

    expect($validator->validate([
        'items' => [
            'alice' => ['code' => 'one'],
            'bob' => ['code' => 'two'],
        ],
    ])->passes())->toBeTrue();
});
