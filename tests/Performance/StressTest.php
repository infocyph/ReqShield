<?php

declare(strict_types=1);

use Infocyph\ReqShield\Rules\Callback;
use Infocyph\ReqShield\Support\RuleExpressionParser;
use Infocyph\ReqShield\Validator;

final readonly class ReqShieldStressDto
{
    public function __construct(public string $value) {}
}

test('wildcard schema cache stays capped under shape churn', function () {
    $validator = Validator::make([
        'users.*.email' => 'required|email',
        'users.*.age' => 'required|integer|min:18',
    ])->enableNestedValidation();

    $ref = new ReflectionClass($validator);
    $wildcardProp = $ref->getProperty('wildcardSchemaCache');
    $cap = (int)$ref->getConstant('MAX_WILDCARD_SCHEMA_CACHE');

    foreach (range(1, 96) as $size) {
        $users = [];
        foreach (range(1, $size) as $index) {
            $users[] = [
                'email' => "user{$index}@example.com",
                'age' => 20 + ($index % 30),
            ];
        }

        $result = $validator->validate(['users' => $users]);
        expect($result->passes())->toBeTrue();
    }

    $cache = $wildcardProp->getValue($validator);
    expect($cache)->toBeArray();
    expect(count($cache))->toBeLessThanOrEqual($cap);
})->group('stress');

test('compiled schema cache stays capped for dynamic runtime rules', function () {
    $validator = Validator::make([
        'email' => 'required|email',
    ])->when(
        true,
        static function (array $data): array {
            $variant = (string)($data['variant'] ?? 'none');

            return [
                "dynamic_{$variant}" => 'nullable|string|max:20',
            ];
        },
    );

    $ref = new ReflectionClass($validator);
    $compiledProp = $ref->getProperty('compiledSchemaCache');
    $cap = (int)$ref->getConstant('MAX_COMPILED_SCHEMA_CACHE');

    foreach (range(1, 120) as $variant) {
        $result = $validator->validate([
            'email' => 'load@test.com',
            'variant' => $variant,
        ]);

        expect($result->passes())->toBeTrue();
    }

    $cache = $compiledProp->getValue($validator);
    expect($cache)->toBeArray();
    expect(count($cache))->toBeLessThanOrEqual($cap);
})->group('stress');

test('field aliases remain isolated per validator instance', function () {
    $a = Validator::make([
        'email' => 'required|email',
    ])->setFieldAliases([
        'email' => 'Primary Email',
    ]);

    $b = Validator::make([
        'email' => 'required|email',
    ])->setFieldAliases([
        'email' => 'Login Email',
    ]);

    $resultA = $a->validate(['email' => 'invalid']);
    $resultB = $b->validate(['email' => 'invalid']);

    expect($resultA->fails())->toBeTrue();
    expect($resultB->fails())->toBeTrue();
    expect($resultA->errors()['email'][0])->toContain('Primary Email');
    expect($resultB->errors()['email'][0])->toContain('Login Email');
})->group('stress');

test('long-running validator workloads keep process caches bounded', function () {
    Validator::clearFragments();
    Validator::clearPlanCache();
    RuleExpressionParser::clearCache();
    Validator::defineFragment('worker_value', [
        'value' => 'required|string',
    ]);

    try {
        foreach (range(1, 1_100) as $iteration) {
            $callback = static fn(mixed $value): bool => is_string($value);
            $validator = Validator::make([
                'value' => [new Callback($callback)],
            ])->setLocale('en-US')
                ->addLocalePack('en-US', ['callback' => 'Invalid value.'])
                ->setDtoClass(ReqShieldStressDto::class);

            expect($validator->validate(['value' => 'ok'])->toDTO())
                ->toBeInstanceOf(ReqShieldStressDto::class);

            expect(Validator::make([
                "field_{$iteration}" => "required|string|max:{$iteration}",
            ])->validate(["field_{$iteration}" => 'x'])->passes())->toBeTrue();

            RuleExpressionParser::parse("max:{$iteration}");
            RuleExpressionParser::splitRules("required|string|max:{$iteration}");
        }

        $validatorReflection = new ReflectionClass(Validator::class);
        $parserReflection = new ReflectionClass(RuleExpressionParser::class);

        expect(count($validatorReflection->getStaticPropertyValue('processPlanCache')))
            ->toBeLessThanOrEqual((int)$validatorReflection->getConstant('MAX_PROCESS_PLAN_CACHE'))
            ->and(count($validatorReflection->getStaticPropertyValue('callableMaxArityCache')))
            ->toBeLessThanOrEqual((int)$validatorReflection->getConstant('MAX_CALLABLE_ARITY_CACHE'))
            ->and(count($parserReflection->getStaticPropertyValue('parseCache')))
            ->toBeLessThanOrEqual((int)$parserReflection->getConstant('MAX_PARSED_RULE_CACHE'))
            ->and(count($parserReflection->getStaticPropertyValue('splitCache')))
            ->toBeLessThanOrEqual((int)$parserReflection->getConstant('MAX_PARSED_RULE_CACHE'));
    } finally {
        Validator::clearFragments();
        Validator::clearPlanCache();
        RuleExpressionParser::clearCache();
    }
})->group('stress');
