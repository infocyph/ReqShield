<?php

declare(strict_types=1);

use Infocyph\ReqShield\Exceptions\InputLimitException;
use Infocyph\ReqShield\Support\HashAlgorithm;
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


test('canonical shape digest remains byte-identical across bounded hash buffer flushes', function () {
    $payload = [];
    for ($index = 0; $index < 1_200; ++$index) {
        $payload['element_' . $index] = ['value' => $index];
    }

    $encode = static function (array $data) use (&$encode): string {
        $bytes = '{';
        foreach ($data as $key => $value) {
            $keyString = (string) $key;
            $bytes .= (is_int($key) ? 'i' : 's') . strlen($keyString) . ':' . $keyString;
            $bytes .= is_array($value) ? $encode($value) : 's;';
        }

        return $bytes . '}';
    };

    expect(NestedValidator::shapeSignature($payload))
        ->toBe(hash('sha256', $encode($payload)));
});

test('wildcard cache shape keys preserve byte-exact key types and large inputs', function () {
    $left = ['items' => ['k;{s:' => 7]];
    $right = ['items' => ['k' => ['s:' => 7]]];

    expect(HashAlgorithm::shapeCacheKey($left))
        ->toStartWith('raw:')
        ->not->toBe(HashAlgorithm::shapeCacheKey($right));

    $large = ['items' => []];
    for ($index = 0; $index < 1_500; ++$index) {
        $large['items']['key_' . $index] = $index;
    }

    expect(HashAlgorithm::shapeCacheKey($large))
        ->toBe('sha256:' . HashAlgorithm::shapeSignature($large));
});

test('wildcard captures cannot inject rule delimiters into dependency expressions', function (string $key, bool $compiled, bool $arrayRules) {
    $definition = 'required_if:items.*.enabled,1|integer';
    $rules = ['items.*.value' => $arrayRules ? explode('|', $definition) : $definition];
    $validator = $compiled ? Validator::compile($rules) : Validator::make($rules);

    expect($validator->validate(['items' => ['alice' => ['enabled' => '1', 'value' => 1]]])->passes())->toBeTrue();
    expect(fn() => $validator->validate(['items' => [$key => ['enabled' => '1']]]))
        ->toThrow(InvalidArgumentException::class);
    expect(fn() => $validator->validate(['items' => [$key => ['enabled' => '1', 'value' => 'INVALID']]]))
        ->toThrow(InvalidArgumentException::class);
})->with(['comma' => 'alice,1', 'pipe injection' => 'alice,1|exclude|same:ignored'])
    ->with([false, true])->with([false, true]);
