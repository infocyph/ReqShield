<?php

declare(strict_types=1);

use Infocyph\ReqShield\CompiledValidator;
use Infocyph\ReqShield\Contracts\DatabaseBatchRule;
use Infocyph\ReqShield\Contracts\DatabaseProvider;
use Infocyph\ReqShield\Contracts\Rule as RuleContract;
use Infocyph\ReqShield\Exceptions\CastException;
use Infocyph\ReqShield\Exceptions\DatabaseProviderRequiredException;
use Infocyph\ReqShield\Exceptions\InputLimitException;
use Infocyph\ReqShield\Rule;
use Infocyph\ReqShield\Rules\MimeTypes;
use Infocyph\ReqShield\Support\FieldPlan;
use Infocyph\ReqShield\Support\NestedValidator;
use Infocyph\ReqShield\Support\ValidationResult;
use Infocyph\ReqShield\Validator;

test('database and batch rule contracts have the minimal 3.0 surface', function () {
    $methods = array_map(
        static fn(ReflectionMethod $method): string => $method->getName(),
        (new ReflectionClass(DatabaseProvider::class))->getMethods(),
    );

    expect($methods)->toBe(['batchExists', 'batchUnique'])
        ->and(method_exists(RuleContract::class, 'isBatchable'))->toBeFalse()
        ->and(is_subclass_of(DatabaseBatchRule::class, RuleContract::class))->toBeTrue();
});

test('database rules require a provider for flat nested wildcard and mixed schemas', function () {
    $schemas = [
        ['team_id' => 'exists:teams,id'],
        ['email' => Rule::unique('users', 'email')],
        ['profile.team_id' => 'exists:teams,id'],
        ['contacts.*.team_id' => 'exists:teams,id'],
        ['email' => 'required|email|unique:users,email'],
    ];

    foreach ($schemas as $schema) {
        expect(fn() => Validator::make($schema))
            ->toThrow(DatabaseProviderRequiredException::class);
    }
});

test('compiled plans and validation results are immutable', function () {
    $compiled = Validator::compile(['name' => 'required|string']);
    $compiledProperties = (new ReflectionClass($compiled))->getProperties();

    expect($compiled)->toBeInstanceOf(CompiledValidator::class)
        ->and((new ReflectionClass($compiled))->isReadOnly())->toBeTrue()
        ->and(array_any(
            $compiledProperties,
            static fn(ReflectionProperty $property): bool => $property->getType()?->__toString() === Validator::class,
        ))->toBeFalse()
        ->and((new ReflectionClass(FieldPlan::class))->isReadOnly())->toBeTrue()
        ->and((new ReflectionClass(ValidationResult::class))->isReadOnly())->toBeTrue()
        ->and(method_exists(ValidationResult::class, 'filter'))->toBeFalse()
        ->and(method_exists(ValidationResult::class, 'map'))->toBeFalse()
        ->and(method_exists(ValidationResult::class, 'merge'))->toBeFalse();
});

test('nested and multiple wildcard validation is automatic and limited', function () {
    $validator = Validator::make([
        'groups.*.members.*.email' => 'required|email',
    ]);

    $result = $validator->validate([
        'groups' => [
            ['members' => [['email' => 'valid@example.com'], ['email' => 'invalid']]],
        ],
    ]);

    expect($result->errors())->toHaveKey('groups.0.members.1.email');

    expect(fn() => NestedValidator::expandWildcards(
        ['items' => [['id' => 1], ['id' => 2]]],
        NestedValidator::parseRules(['items.*.id' => 'integer']),
        1,
    ))->toThrow(InputLimitException::class);
});

test('structured schema composition preserves pipeline and replacement semantics', function () {
    $schema = Validator::composeSchemas(
        ['email' => [
            'rules' => 'required|email',
            'sanitize' => ['trim'],
            'cast' => 'string',
            'alias' => 'Primary email',
        ]],
        ['email' => [
            'rules' => ['max:255'],
            'sanitize' => ['lowercase'],
            'cast' => 'integer',
            'alias' => 'Account email',
        ]],
    );

    expect($schema['email'])->toMatchArray([
        'rules' => ['required', 'email', 'max:255'],
        'sanitize' => ['trim', 'lowercase'],
        'cast' => 'integer',
        'alias' => 'Account email',
    ]);
});

test('invalid casts fail explicitly', function () {
    foreach ([
        ['not-an-integer', 'integer'],
        ['not-a-float', 'float'],
        ['{broken', 'json'],
    ] as [$value, $cast]) {
        $validator = Validator::make([
            'value' => ['rules' => 'present', 'cast' => $cast],
        ]);
        expect(fn() => $validator->validate(['value' => $value]))->toThrow(CastException::class);
    }

    expect(fn() => Validator::make([
        'value' => ['rules' => 'present', 'cast' => 'not_a_cast'],
    ]))->toThrow(CastException::class);
});

test('mime detection defaults to strict and offers explicit compatible fallback', function () {
    $upload = new class {
        public function getError(): int
        {
            return UPLOAD_ERR_OK;
        }

        public function getClientMediaType(): string
        {
            return 'text/plain';
        }

        public function getStream(): object
        {
            return new class {
                public function getMetadata(string $key): ?string
                {
                    return $key === 'uri' ? '/missing/reqshield-upload.txt' : null;
                }
            };
        }

        public function getSize(): int
        {
            return 1;
        }
    };

    expect(Validator::make(['file' => new MimeTypes('text/plain')])
        ->validate(['file' => $upload])->fails())->toBeTrue()
        ->and(Validator::make(['file' => (new MimeTypes('text/plain'))->compatible()])
            ->validate(['file' => $upload])->passes())->toBeTrue();
});

test('autoloaded helpers stay in the package namespace', function () {
    expect(function_exists('validator'))->toBeFalse()
        ->and(function_exists('Infocyph\\ReqShield\\validator'))->toBeTrue();
});
