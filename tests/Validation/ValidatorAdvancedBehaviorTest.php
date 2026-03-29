<?php

declare(strict_types=1);

use Infocyph\ReqShield\Contracts\DatabaseProvider;
use Infocyph\ReqShield\Rules\Unique;
use Infocyph\ReqShield\Validator;

if (!class_exists('ReqShieldFeatureDto')) {
    class ReqShieldFeatureDto
    {
        public int $age = 0;

        public bool $active = false;
    }
}

if (!class_exists('ReqShieldCtorDto')) {
    class ReqShieldCtorDto
    {
        public function __construct(
            public int $age,
            public bool $active,
        ) {
        }
    }
}

test('custom messages support placeholder interpolation', function () {
    $validator = Validator::make([
        'name' => 'required|min:3',
    ])->setFieldAliases([
        'name' => 'Full Name',
    ])->setCustomMessages([
        'name.min' => 'The :field needs :min chars (:rule).',
    ]);

    $result = $validator->validate(['name' => 'ab']);

    expect($result->fails())->toBeTrue();
    expect($result->errors()['name'][0])->toBe('The Full Name needs 3 chars (min).');
});

test('locale packs can override default rule messages', function () {
    $validator = Validator::make([
        'email' => 'required|email',
    ])->addLocalePack('es', [
        'required' => 'El campo :field es obligatorio.',
    ])->setLocale('es');

    $result = $validator->validate([]);

    expect($result->fails())->toBeTrue();
    expect($result->errors()['email'][0])->toBe('El campo Email es obligatorio.');
});

test('locale fallback resolves regional locale to base pack', function () {
    $validator = Validator::make([
        'name' => 'required|string',
    ])->setLocale('en_US');

    $result = $validator->validate([]);

    expect($result->fails())->toBeTrue();
    expect($result->errors()['name'][0])->toContain('required');
});

test('validation result exposes failed rule metadata', function () {
    $validator = Validator::make([
        'email' => 'required|email',
    ]);

    $result = $validator->validate(['email' => 'not-an-email']);
    $failures = $result->failures();

    expect($result->fails())->toBeTrue();
    expect($failures)->toHaveCount(1);
    expect($failures[0])->toHaveKeys(['field', 'rule', 'message', 'value']);
    expect($failures[0]['field'])->toBe('email');
    expect($result->failuresFor('email'))->toHaveCount(1);
});

test('wildcard field aliases apply to nested indexed paths', function () {
    $validator = Validator::make([
        'contacts.*.email' => 'required|email',
    ])->enableNestedValidation()->setFieldAliases([
        'contacts.*.email' => 'Contact Email',
    ]);

    $result = $validator->validate([
        'contacts' => [
            ['email' => 'bad-email'],
        ],
    ]);

    expect($result->fails())->toBeTrue();
    expect($result->errors()['contacts.0.email'][0])->toContain('Contact Email');
});

test('validator applies global sanitizers before validation', function () {
    $validator = Validator::make([
        'email' => 'required|email',
    ])->setSanitizers([
        'email' => ['trim', 'lowercase'],
    ]);

    $result = $validator->validate([
        'email' => '  USER@EXAMPLE.COM  ',
    ]);

    expect($result->passes())->toBeTrue();
    expect($result->validated()['email'])->toBe('user@example.com');
});

test('schema-level sanitizers are applied from rule definition', function () {
    $validator = Validator::make([
        'username' => [
            'rules' => 'required|alpha_dash',
            'sanitize' => ['trim', 'lowercase'],
        ],
    ]);

    $result = $validator->validate([
        'username' => '  USER_NAME  ',
    ]);

    expect($result->passes())->toBeTrue();
    expect($result->validated()['username'])->toBe('user_name');
});

test('sometimes adds rules conditionally', function () {
    $validator = Validator::make([
        'type' => 'required|in:personal,business',
        'vat' => 'string',
    ])->sometimes('vat', 'required', fn (array $data): bool => ($data['type'] ?? null) === 'business');

    $business = $validator->validate(['type' => 'business']);
    $personal = $validator->validate(['type' => 'personal']);

    expect($business->fails())->toBeTrue();
    expect($personal->passes())->toBeTrue();
});

test('when callback can merge dynamic schema', function () {
    $validator = Validator::make([
        'country' => 'required|string',
    ])->when(
        fn (array $data): bool => ($data['country'] ?? null) === 'US',
        fn (): array => ['state' => 'required|string'],
    );

    $us = $validator->validate(['country' => 'US']);
    $ca = $validator->validate(['country' => 'CA']);

    expect($us->fails())->toBeTrue();
    expect($ca->passes())->toBeTrue();
});

test('when callback returning null does not duplicate rules', function () {
    $validator = Validator::make([
        'email' => 'required|email',
    ])->setFailFast(false)->when(
        true,
        fn () => null,
    );

    $result = $validator->validate([
        'email' => 'not-an-email',
    ]);

    expect($result->fails())->toBeTrue();
    expect($result->errors()['email'])->toHaveCount(1);
});

test('schema fragments can be reused and prefixed', function () {
    Validator::defineFragment('address_fragment', [
        'line1' => 'required|string',
        'zip' => 'required|digits:5',
    ]);

    $validator = Validator::make([
        'name' => 'required|string',
    ])->useFragment('address_fragment', 'billing');

    $result = $validator->validate([
        'name' => 'John',
        'billing.line1' => 'Street 1',
        'billing.zip' => '12345',
    ]);

    expect($result->passes())->toBeTrue();
});

test('unique rule respects soft-delete filter in batch query', function () {
    $provider = new class implements DatabaseProvider {
        public array $queries = [];

        public function batchExistsCheck(string $table, array $checks): array
        {
            return [];
        }

        public function batchUniqueCheck(string $table, array $checks): array
        {
            return [];
        }

        public function compositeUnique(
            string $table,
            array $columns,
            ?int $ignoreId = null,
        ): bool {
            return true;
        }

        public function exists(
            string $table,
            string $column,
            $value,
            ?int $ignoreId = null,
        ): bool {
            return false;
        }

        public function query(string $query, array $params = []): array
        {
            $this->queries[] = $query;

            if (str_contains($query, '`deleted_at` IS NULL')) {
                return [];
            }

            return [['email' => 'taken@example.com', 'id' => 10]];
        }
    };

    $softAware = Validator::make([
        'email' => [new Unique('users', 'email')],
    ], $provider);

    $withTrashed = Validator::make([
        'email' => [new Unique('users', 'email', null, 'id', true, 'deleted_at')],
    ], $provider);

    expect($softAware->validate(['email' => 'taken@example.com'])->passes())->toBeTrue();
    expect($withTrashed->validate(['email' => 'taken@example.com'])->fails())->toBeTrue();
    expect(implode("\n", $provider->queries))->toContain('`deleted_at` IS NULL');
});

test('unique batching separates query groups by id column', function () {
    $provider = new class implements DatabaseProvider {
        public array $queries = [];

        public function batchExistsCheck(string $table, array $checks): array
        {
            return [];
        }

        public function batchUniqueCheck(string $table, array $checks): array
        {
            return [];
        }

        public function compositeUnique(
            string $table,
            array $columns,
            ?int $ignoreId = null,
        ): bool {
            return true;
        }

        public function exists(
            string $table,
            string $column,
            $value,
            ?int $ignoreId = null,
        ): bool {
            return false;
        }

        public function query(string $query, array $params = []): array
        {
            $this->queries[] = $query;

            return [];
        }
    };

    $validator = Validator::make([
        'email_a' => [new Unique('users', 'email', null, 'id', true)],
        'email_b' => [new Unique('users', 'email', null, 'uuid', true)],
    ], $provider);

    $validator->validate([
        'email_a' => 'a@example.com',
        'email_b' => 'b@example.com',
    ]);

    expect($provider->queries)->toHaveCount(2);
    expect(implode("\n", $provider->queries))->toContain('`id`');
    expect(implode("\n", $provider->queries))->toContain('`uuid`');
});

test('file rules support uploaded file objects', function () {
    $path = tempnam(sys_get_temp_dir(), 'rqf');
    file_put_contents($path, 'hello world');

    $stream = new class($path) {
        public function __construct(private string $path)
        {
        }

        public function getMetadata(?string $key = null): mixed
        {
            if ($key === 'uri') {
                return $this->path;
            }

            return ['uri' => $this->path];
        }
    };

    $uploaded = new class($stream) {
        public function __construct(private object $stream)
        {
        }

        public function getClientFilename(): string
        {
            return 'hello.txt';
        }

        public function getClientMediaType(): string
        {
            return 'text/plain';
        }

        public function getError(): int
        {
            return UPLOAD_ERR_OK;
        }

        public function getSize(): int
        {
            return 11;
        }

        public function getStream(): object
        {
            return $this->stream;
        }
    };

    $validator = Validator::make([
        'file' => 'required|file|mimes:txt',
    ]);

    $result = $validator->validate(['file' => $uploaded]);
    @unlink($path);

    expect($result->passes())->toBeTrue();
});

test('mimes rule prefers detected mime over client media type', function () {
    $path = tempnam(sys_get_temp_dir(), 'rqf');
    file_put_contents($path, 'plain text content');

    $stream = new class($path) {
        public function __construct(private string $path)
        {
        }

        public function getMetadata(?string $key = null): mixed
        {
            if ($key === 'uri') {
                return $this->path;
            }

            return ['uri' => $this->path];
        }
    };

    $uploaded = new class($stream) {
        public function __construct(private object $stream)
        {
        }

        public function getClientFilename(): string
        {
            return 'payload.php';
        }

        public function getClientMediaType(): string
        {
            return 'application/x-httpd-php';
        }

        public function getError(): int
        {
            return UPLOAD_ERR_OK;
        }

        public function getSize(): int
        {
            return 18;
        }

        public function getStream(): object
        {
            return $this->stream;
        }
    };

    $allowedText = Validator::make([
        'file' => 'required|file|mimes:txt',
    ])->validate(['file' => $uploaded]);

    $allowedPhp = Validator::make([
        'file' => 'required|file|mimes:php',
    ])->validate(['file' => $uploaded]);

    @unlink($path);

    expect($allowedText->passes())->toBeTrue();
    expect($allowedPhp->fails())->toBeTrue();
})->skip(!function_exists('finfo_open') && !function_exists('mime_content_type'));

test('extensions rule supports uploaded file objects via client filename', function () {
    $path = tempnam(sys_get_temp_dir(), 'rqf');
    file_put_contents($path, 'image-bytes');

    $stream = new class($path) {
        public function __construct(private string $path)
        {
        }

        public function getMetadata(?string $key = null): mixed
        {
            if ($key === 'uri') {
                return $this->path;
            }

            return ['uri' => $this->path];
        }
    };

    $uploaded = new class($stream) {
        public function __construct(private object $stream)
        {
        }

        public function getClientFilename(): string
        {
            return 'avatar.jpg';
        }

        public function getClientMediaType(): string
        {
            return 'image/jpeg';
        }

        public function getError(): int
        {
            return UPLOAD_ERR_OK;
        }

        public function getSize(): int
        {
            return 11;
        }

        public function getStream(): object
        {
            return $this->stream;
        }
    };

    $result = Validator::make([
        'file' => 'required|file|extensions:jpg,png',
    ])->validate(['file' => $uploaded]);

    @unlink($path);

    expect($result->passes())->toBeTrue();
});

test('typed output applies casts and dto mapping', function () {
    $validator = Validator::make([
        'age' => 'required|integer',
        'active' => 'required|boolean',
    ])->setCasts([
        'age' => 'integer',
        'active' => 'boolean',
    ])->setDtoClass(ReqShieldFeatureDto::class);

    $result = $validator->validate([
        'age' => '42',
        'active' => '1',
    ]);
    $typed = $result->typed();
    $dto = $result->toDTO();

    expect($typed['age'])->toBeInt();
    expect($typed['active'])->toBeBool();
    expect($dto)->toBeInstanceOf(ReqShieldFeatureDto::class);
    expect($dto->age)->toBe(42);
    expect($dto->active)->toBeTrue();
});

test('casts support boolean false strings and constructor DTO mapping', function () {
    $validator = Validator::make([
        'age' => 'required|integer',
        'active' => 'required|boolean',
    ])->setCasts([
        'age' => 'integer',
        'active' => 'boolean',
    ])->setDtoClass(ReqShieldCtorDto::class);

    $result = $validator->validate([
        'age' => '21',
        'active' => '0',
    ]);
    $typed = $result->typed();
    $dto = $result->toDTO();

    expect($typed['active'])->toBeFalse();
    expect($dto)->toBeInstanceOf(ReqShieldCtorDto::class);
    expect($dto->age)->toBe(21);
    expect($dto->active)->toBeFalse();
});

test('schema export and introspection include rule hints', function () {
    $validator = Validator::make([
        'email' => 'required|email',
        'age' => 'integer|min:18',
    ]);

    $jsonSchema = $validator->exportSchema('json_schema');
    $introspection = $validator->schemaIntrospection();

    expect($jsonSchema['properties']['email']['format'])->toBe('email');
    expect($jsonSchema['required'])->toContain('email');
    expect($introspection['age']['rules'])->toContain('integer');
});

test('schema export includes nested constraints and required chain', function () {
    $validator = Validator::make([
        'user.email' => 'required|email',
        'user.profile.age' => 'required|integer|min:18|max:65',
    ])->enableNestedValidation();

    $schema = $validator->exportSchema('json_schema');

    expect($schema['properties']['user']['required'])->toContain('email');
    expect($schema['properties']['user']['required'])->toContain('profile');
    expect($schema['properties']['user']['properties']['profile']['required'])->toContain('age');
    expect($schema['properties']['user']['properties']['profile']['properties']['age']['minimum'])->toBe(18);
    expect($schema['properties']['user']['properties']['profile']['properties']['age']['maximum'])->toBe(65);
});
