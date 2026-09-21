<?php

declare(strict_types=1);

use Infocyph\ReqShield\Exceptions\ValidationException;
use Infocyph\ReqShield\Validator;

test('validator thrown exceptions are transport neutral', function () {
    $exception = null;
    try {
        Validator::make([
            'email' => 'required|email',
        ])->throwOnFailure()->validate([
            'email' => 'invalid',
        ]);
    } catch (ValidationException $caught) {
        $exception = $caught;
    }

    expect($exception)->toBeInstanceOf(ValidationException::class)
        ->and($exception?->getCode())->toBe(0)
        ->and($exception?->getErrors())->toHaveKey('email');
});

test('validation result throw remains transport neutral', function () {
    $result = Validator::make([
        'email' => 'required|email',
    ])->validate([
        'email' => 'invalid',
    ]);

    $exception = null;
    try {
        $result->throw();
    } catch (ValidationException $caught) {
        $exception = $caught;
    }

    expect($exception)->toBeInstanceOf(ValidationException::class)
        ->and($exception?->getCode())->toBe(0);
});

test('presentation helpers preserve defaults and allow caller status control', function () {
    $result = Validator::make([
        'email' => 'required|email',
    ])->validate([
        'email' => 'invalid',
    ]);

    expect($result->toJsonApiErrors()['errors'][0]['status'])->toBe('422')
        ->and($result->toJsonApiErrors(409)['errors'][0]['status'])->toBe('409')
        ->and($result->toJsonApiErrors('400')['errors'][0]['status'])->toBe('400')
        ->and($result->toProblemJson(status: 409)['status'])->toBe(409);
});
