<?php

use Infocyph\ReqShield\Validator;

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
