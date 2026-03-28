<?php

use Infocyph\ReqShield\Validator;

// --- Example 4 ---

test('nested validation passes', function () {
    $validator = Validator::make([
        'user.email' => 'required|email',
        'user.name' => 'required|min:3',
        'user.profile.age' => 'required|integer|min:18',
        'user.profile.bio' => 'string|max:500',
    ])->enableNestedValidation();

    $data = [
        'user' => [
            'email' => 'nested@example.com',
            'name' => 'John Doe',
            'profile' => [
                'age' => 25,
                'bio' => 'Software developer',
            ],
        ],
    ];

    $result = $validator->validate($data);

    expect($result->passes())->toBeTrue();
    $validated = $result->validated();
    expect($validated)->toHaveKeys(['user.email', 'user.name', 'user.profile.age', 'user.profile.bio']);
    expect($validated['user.email'])->toBe('nested@example.com');
    expect($validated['user.profile.age'])->toBe(25);
});

test('nested validation fails', function () {
    $validator = Validator::make([
        'user.email' => 'required|email',
        'user.name' => 'required|min:3',
        'user.profile.age' => 'required|integer|min:18',
    ])->enableNestedValidation();

    $data = [
        'user' => [
            'email' => 'not-an-email',
            'name' => 'Jo',
            'profile' => [
                'age' => 15,
            ],
        ],
    ];

    $result = $validator->validate($data);

    expect($result->fails())->toBeTrue();
    expect($result->errors())->toHaveKeys(['user.email', 'user.name', 'user.profile.age']);
});

test('nested validation fails with missing keys', function () {
    $validator = Validator::make([
        'user.email' => 'required|email',
        'user.profile.age' => 'required|integer|min:18',
    ])->enableNestedValidation();

    $data = [
        'user' => [
            'email' => 'test@example.com',
            // 'profile' key is missing
        ],
    ];

    $result = $validator->validate($data);

    expect($result->fails())->toBeTrue();
    expect($result->errors())->toHaveKey('user.profile.age');
    expect($result->errors()['user.profile.age'][0])->toContain('required');
});

test('nested wildcard validation passes', function () {
    $validator = Validator::make([
        'contacts.*.email' => 'required|email',
        'contacts.*.name' => 'required|min:2',
    ])->enableNestedValidation();

    $data = [
        'contacts' => [
            ['email' => 'john@example.com', 'name' => 'John'],
            ['email' => 'jane@example.com', 'name' => 'Jane'],
        ],
    ];

    $result = $validator->validate($data);

    expect($result->passes())->toBeTrue();
    expect($result->validated())->toHaveKeys([
        'contacts.0.email',
        'contacts.0.name',
        'contacts.1.email',
        'contacts.1.name',
    ]);
});

test('nested wildcard validation fails with indexed errors', function () {
    $validator = Validator::make([
        'contacts.*.email' => 'required|email',
        'contacts.*.name' => 'required|min:2',
    ])->enableNestedValidation();

    $data = [
        'contacts' => [
            ['email' => 'bad-email', 'name' => 'A'],
            ['email' => 'ok@example.com', 'name' => 'Jane'],
        ],
    ];

    $result = $validator->validate($data);

    expect($result->fails())->toBeTrue();
    expect($result->errors())->toHaveKeys(['contacts.0.email', 'contacts.0.name']);
});

test('nested wildcard rules support wildcard custom messages', function () {
    $validator = Validator::make([
        'contacts.*.email' => 'required|email',
    ])->enableNestedValidation()->setCustomMessages([
        'contacts.*.email.required' => 'Contact email is required.',
        'contacts.*.email.email' => 'Contact email must be valid.',
    ]);

    $missingResult = $validator->validate([
        'contacts' => [
            [],
        ],
    ]);

    $invalidResult = $validator->validate([
        'contacts' => [
            ['email' => 'not-an-email'],
        ],
    ]);

    expect($missingResult->fails())->toBeTrue();
    expect($missingResult->errors()['contacts.0.email'])->toContain('Contact email is required.');

    expect($invalidResult->fails())->toBeTrue();
    expect($invalidResult->errors()['contacts.0.email'])->toContain('Contact email must be valid.');
});
