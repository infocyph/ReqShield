<?php

declare(strict_types=1);

use Infocyph\ReqShield\CompiledValidator;
use Infocyph\ReqShield\Exceptions\FrozenValidatorException;
use Infocyph\ReqShield\Support\ValidationContext;
use Infocyph\ReqShield\Tests\Fixtures\Validation\MutableAuditRule;
use Infocyph\ReqShield\Validator;

test('compiled validator snapshots mutable builder configuration once', function () {
    $builder = Validator::make([
        'name' => 'required|string',
    ])->setCustomMessages([
        'name.required' => 'Original message.',
    ]);

    $compiled = new CompiledValidator($builder);

    $builder->setCustomMessages([
        'name.required' => 'Changed builder message.',
    ])->stripUnknown();

    expect($compiled->validate([])->first('name'))->toBe('Original message.');
});

test('compiled validator snapshots mutable rule objects', function () {
    $rule = new MutableAuditRule();
    $builder = Validator::make([
        'value' => [$rule],
    ]);
    $compiled = new CompiledValidator($builder);

    $rule->passes = false;

    expect($compiled->validate(['value' => 'ok'])->passes())->toBeTrue()
        ->and($builder->validate(['value' => 'ok'])->fails())->toBeTrue();
});

test('compiled validator rejects topology mutation reached through callbacks', function () {
    $compiled = new CompiledValidator(
        Validator::make([
            'name' => 'required|string',
        ])->when(
            true,
            static function (array $data, array $rules, Validator $validator): ?array {
                unset($data, $rules);
                $validator->setFailFast(false);

                return null;
            },
        ),
    );

    expect(fn() => $compiled->validate(['name' => 'Ada']))
        ->toThrow(FrozenValidatorException::class);
});

test('compiled validator supports sequential reuse without request leakage', function () {
    $compiled = Validator::compile([
        'email' => 'required|email',
    ]);

    expect($compiled->validate(['email' => 'first@example.com'])->passes())->toBeTrue()
        ->and($compiled->validate(['email' => 'invalid'])->fails())->toBeTrue()
        ->and($compiled->validate(['email' => 'second@example.com'])->passes())->toBeTrue();
});

test('same compiled validator remains isolated across interleaved Fibers', function () {
    $compiled = new CompiledValidator(
        Validator::make([
            'token' => 'required|string',
        ])->after(static function (ValidationContext $context): void {
            Fiber::suspend($context->get('token'));
        }),
    );

    $first = new Fiber(static fn() => $compiled->validate(['token' => 'first']));
    $second = new Fiber(static fn() => $compiled->validate(['token' => 'second']));

    expect($first->start())->toBe('first')
        ->and($second->start())->toBe('second');

    $second->resume();
    $first->resume();

    expect($first->getReturn()->validated())->toBe(['token' => 'first'])
        ->and($second->getReturn()->validated())->toBe(['token' => 'second']);
});

test('compiled validator wildcard reuse does not retain prior request values', function () {
    $compiled = Validator::compile([
        'contacts.*.email' => 'required|email',
    ]);

    $first = $compiled->validate([
        'contacts' => [
            ['email' => 'bad'],
        ],
    ]);
    $second = $compiled->validate([
        'contacts' => [
            ['email' => 'valid@example.com'],
        ],
    ]);

    expect($first->errors())->toHaveKey('contacts.0.email')
        ->and($second->passes())->toBeTrue()
        ->and($second->validated())->toHaveKey('contacts.0.email');
});


test('compiled validator keeps conditional and wildcard plan caches bounded', function () {
    $conditional = new CompiledValidator(
        Validator::make([
            'value' => 'required|string',
        ])->when(
            true,
            static fn(array $data): array => [
                'value' => 'max:' . max(1, (int) ($data['limit'] ?? 1)),
            ],
        ),
    );

    foreach (range(1, 100) as $limit) {
        $conditional->validate([
            'value' => 'x',
            'limit' => $limit,
        ]);
    }

    $wildcard = Validator::compile([
        'rows.*.value' => 'required|string',
    ]);
    foreach (range(1, 100) as $count) {
        $rows = array_fill(0, $count, ['value' => 'ok']);
        $wildcard->validate(['rows' => $rows]);
    }

    $compiledProperty = new ReflectionProperty(CompiledValidator::class, 'validator');
    $conditionalValidator = $compiledProperty->getValue($conditional);
    $wildcardValidator = $compiledProperty->getValue($wildcard);

    $compiledCache = new ReflectionProperty(Validator::class, 'compiledSchemaCache');
    $wildcardCache = new ReflectionProperty(Validator::class, 'wildcardSchemaCache');

    expect($compiledCache->getValue($conditionalValidator))->toHaveCount(64)
        ->and($wildcardCache->getValue($wildcardValidator))->toHaveCount(64);
});
