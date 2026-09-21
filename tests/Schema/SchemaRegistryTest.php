<?php

declare(strict_types=1);

use Infocyph\ReqShield\Exceptions\FrozenSchemaRegistryException;
use Infocyph\ReqShield\Exceptions\InvalidSchemaException;
use Infocyph\ReqShield\Schema\SchemaRegistry;

test('schema registry defines reads and lists named schemas', function () {
    $registry = new SchemaRegistry();
    $registry->define('users.store', [
        'email' => 'required|email',
    ]);

    expect($registry->has('users.store'))->toBeTrue()
        ->and($registry->get('users.store'))->toBe([
            'email' => 'required|email',
        ])
        ->and($registry->schema('missing'))->toBeNull()
        ->and($registry->all())->toHaveKey('users.store');
});

test('schema registry rejects duplicate definitions and requires explicit replacement', function () {
    $registry = new SchemaRegistry([
        'users.store' => ['email' => 'required|email'],
    ]);

    expect(fn() => $registry->define('users.store', ['name' => 'required']))
        ->toThrow(InvalidSchemaException::class);

    $registry->replace('users.store', ['name' => 'required|string']);

    expect($registry->get('users.store'))->toBe([
        'name' => 'required|string',
    ]);
});

test('schema registry extends schemas with ReqShield composition semantics', function () {
    $registry = new SchemaRegistry([
        'users.store' => [
            'email' => 'required|email',
        ],
    ]);

    $registry->extend('users.store', [
        'email' => 'max:255',
        'name' => 'required|string',
    ])->extend('users.create', [
        'id' => 'required|integer',
    ]);

    expect($registry->get('users.store'))->toBe([
        'email' => ['required', 'email', 'max:255'],
        'name' => 'required|string',
    ])->and($registry->get('users.create'))->toBe([
        'id' => 'required|integer',
    ]);
});

test('schema registry supports explicit removal before freeze', function () {
    $registry = new SchemaRegistry([
        'users.store' => ['email' => 'required|email'],
    ]);

    $registry->remove('users.store');

    expect($registry->has('users.store'))->toBeFalse()
        ->and(fn() => $registry->remove('users.store'))
        ->toThrow(InvalidSchemaException::class);
});

test('schema registry freeze is idempotent and rejects every topology mutation', function () {
    $registry = new SchemaRegistry([
        'users.store' => ['email' => 'required|email'],
    ]);

    expect($registry->freeze()->freeze()->isFrozen())->toBeTrue();

    foreach ([
        static fn() => $registry->define('users.create', ['email' => 'required']),
        static fn() => $registry->extend('users.store', ['name' => 'string']),
        static fn() => $registry->replace('users.store', ['name' => 'string']),
        static fn() => $registry->remove('users.store'),
    ] as $mutation) {
        expect($mutation)->toThrow(FrozenSchemaRegistryException::class);
    }
});

test('schema registry returns value copies that cannot mutate frozen topology', function () {
    $registry = (new SchemaRegistry([
        'users.store' => ['email' => 'required|email'],
    ]))->freeze();

    $schema = $registry->get('users.store');
    $all = $registry->all();
    $schema['email'] = 'string';
    $all['users.store']['email'] = 'nullable';

    expect($registry->get('users.store'))->toBe([
        'email' => 'required|email',
    ]);
});

test('schema registry instances and interleaved Fiber reads remain isolated', function () {
    $first = (new SchemaRegistry([
        'shared' => ['value' => 'required|string'],
    ]))->freeze();
    $second = (new SchemaRegistry([
        'shared' => ['value' => 'required|integer'],
    ]))->freeze();

    $fiberA = new Fiber(static function () use ($first): array {
        Fiber::suspend('a');

        return $first->get('shared');
    });
    $fiberB = new Fiber(static function () use ($second): array {
        Fiber::suspend('b');

        return $second->get('shared');
    });

    expect($fiberA->start())->toBe('a')
        ->and($fiberB->start())->toBe('b');

    $fiberB->resume();
    $fiberA->resume();

    expect($fiberA->getReturn())->toBe(['value' => 'required|string'])
        ->and($fiberB->getReturn())->toBe(['value' => 'required|integer']);
});

test('schema registry rejects empty names and invalid field keys', function () {
    expect(fn() => new SchemaRegistry([
        '' => ['value' => 'required'],
    ]))->toThrow(InvalidSchemaException::class)
        ->and(fn() => new SchemaRegistry([
            'invalid' => [0 => 'required'],
        ]))->toThrow(InvalidSchemaException::class);
});


test('schema registry snapshots mutable rule objects on write and read', function () {
    $rule = new class implements \Infocyph\ReqShield\Contracts\Rule {
        public bool $passes = true;

        public function cost(): int
        {
            return 1;
        }

        public function message(string $field): string
        {
            return "The {$field} is invalid.";
        }

        public function passes(mixed $value, string $field, array $data): bool
        {
            unset($value, $field, $data);

            return $this->passes;
        }
    };

    $registry = (new SchemaRegistry([
        'mutable' => ['value' => [$rule]],
    ]))->freeze();
    $rule->passes = false;

    $first = $registry->get('mutable');
    $storedRule = $first['value'][0];
    expect($storedRule)->toBeInstanceOf(\Infocyph\ReqShield\Contracts\Rule::class)
        ->and($storedRule->passes(null, 'value', []))->toBeTrue();

    $storedRule->passes = false;
    $second = $registry->get('mutable');
    $freshRule = $second['value'][0];

    expect($freshRule->passes(null, 'value', []))->toBeTrue();
});
