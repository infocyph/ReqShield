<?php

declare(strict_types=1);

use Infocyph\ReqShield\Rule;
use Infocyph\ReqShield\Validator;
use Infocyph\ReqShield\Exceptions\UnsupportedRequestObjectException;
use Infocyph\ReqShield\Support\ValidationContext;
use Infocyph\ReqShield\Tests\Fixtures\ReqShieldState;
use Infocyph\ReqShield\Tests\Fixtures\ReqShieldStatus;

test('request helper static constructors validate arrays', function () {
    $schema = ['email' => 'required|email'];
    $data = ['email' => 'tester@example.com'];

    expect(Validator::fromArray($schema, $data)->passes())->toBeTrue();
    expect(Validator::fromQuery($schema, $data)->passes())->toBeTrue();
    expect(Validator::fromBody($schema, $data)->passes())->toBeTrue();
    expect(Validator::fromFiles($schema, $data)->passes())->toBeTrue();
});

test('server request helper resolves psr style accessors', function () {
    $request = new class {
        public function getQueryParams(): array
        {
            return ['status' => 'draft'];
        }

        public function getParsedBody(): array
        {
            return ['name' => 'Article'];
        }

        public function getUploadedFiles(): array
        {
            return [];
        }

        public function getAttributes(): array
        {
            return ['author_id' => 10];
        }
    };

    $result = Validator::fromServerRequest([
        'status' => 'required|enum:' . ReqShieldStatus::class,
        'name' => 'required|string',
        'author_id' => 'required|integer',
    ], $request);

    expect($result->passes())->toBeTrue();
});

test('server request helper rejects unsupported objects', function () {
    Validator::fromServerRequest(['email' => 'required|email'], new stdClass());
})->throws(UnsupportedRequestObjectException::class);

test('strict unknown mode rejects non schema fields', function () {
    $result = Validator::make([
        'email' => 'required|email',
    ])->strict()->validate([
        'email' => 'demo@example.com',
        'unexpected' => 'value',
    ]);

    expect($result->fails())->toBeTrue();
    expect($result->errors())->toHaveKey('unexpected');
    expect($result->failures()[0]['rule'])->toBe('unknown');
});

test('allow unknown false behaves like strict mode', function () {
    $result = Validator::make([
        'email' => 'required|email',
    ])->allowUnknown(false)->validate([
        'email' => 'demo@example.com',
        'unexpected' => 'value',
    ]);

    expect($result->fails())->toBeTrue();
    expect($result->errors())->toHaveKey('unexpected');
    expect($result->failures()[0]['rule'])->toBe('unknown');
});

test('strip unknown mode removes non schema fields without failing', function () {
    $result = Validator::make([
        'email' => 'required|email',
    ])->stripUnknown()->validate([
        'email' => 'demo@example.com',
        'unexpected' => 'value',
    ]);

    expect($result->passes())->toBeTrue();
    expect($result->validated())->toHaveKey('email');
});

test('enum rules support backed and unit enums', function () {
    $backed = Validator::make([
        'status' => 'required|enum:' . ReqShieldStatus::class,
    ])->validate(['status' => 'published']);

    $unit = Validator::make([
        'state' => ['required', Rule::enum(ReqShieldState::class)],
    ])->validate(['state' => 'Approved']);

    expect($backed->passes())->toBeTrue();
    expect($unit->passes())->toBeTrue();
});

test('enum casts resolve to enum instances when possible', function () {
    $result = Validator::make([
        'status' => [
            'rules' => 'required|enum:' . ReqShieldStatus::class,
            'cast' => ReqShieldStatus::class,
        ],
    ])->validate([
        'status' => 'draft',
    ]);

    expect($result->passes())->toBeTrue();
    expect($result->typed()['status'])->toBeInstanceOf(ReqShieldStatus::class);
});

test('enum rules are exported as json schema enum values', function () {
    $schema = Validator::make([
        'status' => 'required|enum:' . ReqShieldStatus::class,
    ])->exportSchema('json_schema');

    expect($schema['properties']['status']['enum'])->toEqual(['draft', 'published']);
});

test('after validation callback can append cross field failures', function () {
    $result = Validator::make([
        'start_date' => 'required|date',
        'end_date' => 'required|date',
    ])->after(function (ValidationContext $ctx): void {
        $start = (string) $ctx->get('start_date', '');
        $end = (string) $ctx->get('end_date', '');
        if ($start !== '' && $end !== '' && $start > $end) {
            $ctx->addFailure('end_date', 'after', 'End date must be after start date.', $end);
        }
    })->validate([
        'start_date' => '2025-12-31',
        'end_date' => '2025-01-01',
    ]);

    expect($result->fails())->toBeTrue();
    expect($result->errors()['end_date'][0])->toBe('End date must be after start date.');
});

test('validation result exposes formatter outputs and immutable input', function () {
    $result = Validator::make([
        'email' => 'required|email',
        'age' => 'required|integer',
        'status' => [
            'rules' => 'required|enum:' . ReqShieldStatus::class,
            'cast' => ReqShieldStatus::class,
        ],
    ])->validate([
        'email' => 'demo@example.com',
        'age' => '21',
        'status' => 'draft',
    ]);

    $input = $result->input();
    $problem = $result->toProblemJson();
    $jsonApi = $result->toJsonApiErrors();
    $apiErrors = $result->toApiErrors();

    expect($input->string('email'))->toBe('demo@example.com');
    expect($input->int('age'))->toBe(21);
    expect($input->enum('status', ReqShieldStatus::class))->toBe(ReqShieldStatus::Draft);
    expect($input->only(['email']))->toEqual(['email' => 'demo@example.com']);
    expect($input->except(['age']))->toHaveKeys(['email', 'status']);
    expect($problem['status'])->toBe(422);
    expect($jsonApi)->toHaveKey('errors');
    expect($apiErrors)->toHaveKeys(['ok', 'message', 'errors', 'failures']);
});

test('formatter outputs include expected failure metadata', function () {
    $result = Validator::make([
        'email' => 'required|email',
    ])->validate([
        'email' => 'bad-email',
    ]);

    $flat = $result->toFlatErrors();
    $problem = $result->toProblemJson();
    $jsonApi = $result->toJsonApiErrors();

    expect($result->fails())->toBeTrue();
    expect($flat)->toHaveCount(1);
    expect($flat[0])->toHaveKeys(['field', 'rule', 'message', 'value']);
    expect($problem)->toHaveKeys(['type', 'title', 'status', 'detail', 'errors', 'failures']);
    expect($jsonApi)->toHaveKey('errors');
    expect($jsonApi['errors'][0])->toHaveKeys(['status', 'source', 'title', 'detail', 'meta']);
});

test('compiled validator reuses schema for repeated payloads', function () {
    $compiled = Validator::compile([
        'email' => 'required|email',
        'age' => 'required|integer|min:18',
    ]);

    $pass = $compiled->validate([
        'email' => 'demo@example.com',
        'age' => 22,
    ]);
    $fail = $compiled->validate([
        'email' => 'bad-email',
        'age' => 10,
    ]);

    expect($pass->passes())->toBeTrue();
    expect($fail->fails())->toBeTrue();
});
