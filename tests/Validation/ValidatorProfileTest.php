<?php

declare(strict_types=1);

use Infocyph\ReqShield\Exceptions\InputLimitException;
use Infocyph\ReqShield\Exceptions\InvalidValidatorProfileException;
use Infocyph\ReqShield\Support\ValidatorProfile;
use Infocyph\ReqShield\Tests\Fixtures\Validation\ProfileDto;
use Infocyph\ReqShield\Validator;

test('validator profile normalizes Foundation-compatible scalar options', function () {
    $profile = ValidatorProfile::fromArray([
        'fail_fast' => 'off',
        'allow_unknown' => 0,
        'nested' => 'yes',
        'nested_mode' => 'required',
        'strict' => 'false',
        'strip_unknown' => '1',
        'throw_on_failure' => 0,
        'locale' => 'en_GB',
        'dto' => '',
    ]);

    expect($profile->toArray())->toMatchArray([
        'fail_fast' => false,
        'allow_unknown' => false,
        'nested' => true,
        'nested_mode' => 'targeted',
        'strict' => false,
        'strip_unknown' => true,
        'throw_on_failure' => false,
        'locale' => 'en_GB',
        'dto' => null,
    ]);
});

test('validator profile uses Foundation-compatible boolean fallbacks', function () {
    $profile = ValidatorProfile::fromArray([
        'allow_unknown' => null,
        'fail_fast' => null,
        'strict' => null,
    ]);

    expect($profile->toArray())->toMatchArray([
        'allow_unknown' => true,
        'fail_fast' => true,
        'strict' => false,
    ]);
});

test('validator profile overlays nested option maps without mutating the base profile', function () {
    $base = ValidatorProfile::fromArray([
        'fail_fast' => true,
        'messages' => ['email.required' => 'Email is required.'],
        'aliases' => ['email' => 'Email'],
        'limits' => ['max_depth' => 64, 'max_fields' => 500],
    ]);
    $derived = $base->overlay([
        'fail_fast' => false,
        'messages' => ['email.email' => 'Email is invalid.'],
        'limits' => ['max_fields' => 100],
    ]);

    expect($base->toArray()['limits'])->toBe([
        'max_depth' => 64,
        'max_fields' => 500,
    ])->and($derived->toArray())->toMatchArray([
        'fail_fast' => false,
        'messages' => [
            'email.required' => 'Email is required.',
            'email.email' => 'Email is invalid.',
        ],
        'aliases' => ['email' => 'Email'],
        'limits' => [
            'max_depth' => 64,
            'max_fields' => 100,
        ],
    ]);
});

test('validator profile rejects unknown options and invalid limits', function () {
    expect(fn() => ValidatorProfile::fromArray(['unknown' => true]))
        ->toThrow(InvalidValidatorProfileException::class)
        ->and(fn() => ValidatorProfile::fromArray([
            'limits' => ['max_fields' => 0],
        ]))->toThrow(InvalidValidatorProfileException::class)
        ->and(fn() => ValidatorProfile::fromArray([
            'limits' => ['mystery' => 10],
        ]))->toThrow(InvalidValidatorProfileException::class);
});

test('validator profile applies sanitization casting nesting and unknown-field precedence', function () {
    $profile = ValidatorProfile::fromArray([
        'fail_fast' => false,
        'nested' => true,
        'nested_mode' => 'required',
        'strip_unknown' => true,
        'strict' => true,
        'allow_unknown' => true,
        'aliases' => ['profile.name' => 'Profile Name'],
        'sanitizers' => ['email' => ['trim', 'lowercase']],
        'casts' => ['age' => 'integer'],
        'limits' => ['max_fields' => 10],
    ]);

    $validator = $profile->apply(Validator::make([
        'email' => 'required|email',
        'age' => 'required|integer',
        'profile.name' => 'required|string',
    ]));

    $result = $validator->validate([
        'email' => ' USER@EXAMPLE.COM ',
        'age' => '21',
        'profile' => ['name' => 'Ada'],
        'unknown' => 'discard',
    ]);

    expect($result->passes())->toBeTrue()
        ->and($result->typed())->toBe([
            'email' => 'user@example.com',
            'age' => 21,
            'profile.name' => 'Ada',
        ])
        ->and($result->validated())->not->toHaveKey('unknown');
});

test('validator profile strict policy wins over allow unknown when strip is disabled', function () {
    $validator = ValidatorProfile::fromArray([
        'strip_unknown' => false,
        'strict' => true,
        'allow_unknown' => true,
    ])->apply(Validator::make([
        'email' => 'required|email',
    ]));

    expect($validator->validate([
        'email' => 'valid@example.com',
        'extra' => 'rejected',
    ])->errors())->toHaveKey('extra');
});

test('validator profile applies sparse limits with ReqShield defaults', function () {
    $validator = ValidatorProfile::fromArray([
        'limits' => ['max_fields' => 1],
    ])->apply(Validator::make([
        'email' => 'required|email',
    ]));

    expect(fn() => $validator->validate([
        'email' => 'valid@example.com',
        'extra' => 'bounded',
    ]))->toThrow(InputLimitException::class);
});

test('validator profile applies messages locale packs and dto mapping', function () {
    $validator = ValidatorProfile::fromArray([
        'messages' => ['email.required' => 'Email required by profile.'],
        'locale_packs' => [
            'en' => ['required' => 'The :attribute is required.'],
        ],
        'dto' => ProfileDto::class,
        'casts' => ['age' => 'integer'],
    ])->apply(Validator::make([
        'email' => 'required|email',
        'age' => 'required|integer',
    ]));

    expect($validator->validate([
        'age' => '21',
    ])->first('email'))->toBe('Email required by profile.');

    $dto = $validator->validate([
        'email' => 'ada@example.com',
        'age' => '21',
    ])->toDTO();

    expect($dto)->toBeInstanceOf(ProfileDto::class)
        ->and($dto->email)->toBe('ada@example.com')
        ->and($dto->age)->toBe(21);
});

test('validator profile construction and application retain no request data', function () {
    $profile = ValidatorProfile::fromArray([
        'sanitizers' => ['email' => ['trim', 'lowercase']],
    ]);
    $validator = $profile->apply(Validator::make([
        'email' => 'required|email',
    ]));

    $first = $validator->validate(['email' => ' FIRST@EXAMPLE.COM '])->typed();
    $second = $validator->validate(['email' => ' SECOND@EXAMPLE.COM '])->typed();

    expect($first['email'])->toBe('first@example.com')
        ->and($second['email'])->toBe('second@example.com')
        ->and($profile->toArray())->not->toHaveKey('email');
});


test('validator profile application keeps non-database validation database-cold', function () {
    $provider = new class implements \Infocyph\ReqShield\Contracts\DatabaseProvider {
        public int $calls = 0;

        public function batchExists(string $table, array $checks): array
        {
            unset($table, $checks);
            ++$this->calls;

            return [];
        }

        public function batchUnique(string $table, array $checks): array
        {
            unset($table, $checks);
            ++$this->calls;

            return [];
        }
    };

    $profile = ValidatorProfile::fromArray([
        'sanitizers' => ['email' => ['trim', 'lowercase']],
    ]);
    $result = $profile->apply(Validator::make([
        'email' => 'required|email',
    ], $provider))->validate([
        'email' => ' USER@EXAMPLE.COM ',
    ]);

    expect($result->passes())->toBeTrue()
        ->and($provider->calls)->toBe(0);
});
