# ReqShield

[![Security & Standards](https://github.com/infocyph/ReqShield/actions/workflows/security-standards.yml/badge.svg)](https://github.com/infocyph/ReqShield/actions/workflows/security-standards.yml)
![Packagist Downloads](https://img.shields.io/packagist/dt/infocyph/ReqShield?color=green&link=https%3A%2F%2Fpackagist.org%2Fpackages%2Finfocyph%2FReqShield)
[![License: MIT](https://img.shields.io/badge/License-MIT-green.svg)](https://opensource.org/licenses/MIT)
![Packagist Version](https://img.shields.io/packagist/v/infocyph/ReqShield)
![Packagist PHP Version](https://img.shields.io/packagist/dependency-v/infocyph/ReqShield/php)
![GitHub Code Size](https://img.shields.io/github/languages/code-size/infocyph/ReqShield)
[![Documentation](https://img.shields.io/badge/docs-readthedocs-blue.svg)](https://docs.infocyph.com/projects/reqshield)

**Fast, modern PHP request validation and sanitization.** Schema-based rules, fail-fast execution, intelligent batching, and 108 built-in validation rules.

```php
$validator = Validator::make([
    'email' => 'required|email|max:255',
    'age' => 'required|integer|min:18',
])->setSanitizers([
    'email' => ['trim', 'lowercase'],
])->setCasts([
    'age' => 'integer',
]);

$result = $validator->validate($data);

if ($result->passes()) {
    $clean = $result->typed();
    // All good!
}
```

## Features

-  **108 Built-in Rules** - Basic types, conditional rules, files, database checks, enums, and more
-  **46 Built-in Sanitizers** - Manual sanitization or built-in sanitize+validate pipeline
-  **Intelligent Batching** - Expensive DB checks are batched automatically
-  **Fail-Fast + Full Collection Modes** - Per-field fail-fast with configurable behavior
-  **Nested + Wildcard Validation** - Dot notation with wildcard expansion
-  **Custom Messages + Placeholders** - `:field`, `:rule`, `:min`, and more
-  **Locale Packs** - Per-rule localized message templates with fallback
-  **Failure Metadata** - Structured failures (`field`, `rule`, `message`, `value`)
-  **Schema Fragments + Composition** - Reuse validation contracts across endpoints
-  **Conditional Closures** - `sometimes()` and `when()` for dynamic rule activation
-  **Schema Export** - JSON Schema, OpenAPI shape, and introspection metadata
-  **Typed Output + DTO Mapping** - Cast map + `toDTO()` support
-  **Uploaded File Object Support** - Array-style uploads and PSR-7 style objects
- ️ **Upload Hardening Rules** - `safe_filename`, `upload_meta`, `upload_id`, `secure_file`
- ️ **PHP 8.4+** - Built with modern PHP features

## Installation

```bash
composer require infocyph/reqshield
```

**Requirements:** PHP 8.4+ and `ext-hash` with `xxh3` support

## Quick Start

### Basic Validation

```php
use Infocyph\ReqShield\Validator;

$validator = Validator::make([
    'email' => [
        'rules' => 'required|email|max:255',
        'sanitize' => ['trim', 'lowercase'],
        'alias' => 'Email Address',
    ],
    'password' => 'required|string|min:8|confirmed',
    'age' => 'required|integer|min:18',
])->setCasts([
    'age' => 'integer',
])->setCustomMessages([
    'email.required' => ':field is required.',
    '*.min' => ':field must be at least :min.',
]);

$result = $validator->validate($data);

if ($result->passes()) {
    $validated = $result->typed();
    // Process your data...
} else {
    $errors = $result->errors();
    $failures = $result->failures();
    // Handle validation errors...
}
```

### Sanitization

Manual sanitization:

```php
use Infocyph\ReqShield\Sanitizer;

$clean = [
    'email' => Sanitizer::email($input['email']),           // 'john@example.com'
    'username' => Sanitizer::alphaDash($input['username']), // 'john_doe'
    'age' => Sanitizer::integer($input['age']),             // 25
    'bio' => Sanitizer::string($input['bio']),              // Strips HTML tags
];

$result = $validator->validate($clean);
```

Or built-in sanitize+validate pipeline:

```php
$validator = Validator::make([
    'email' => 'required|email',
    'contacts.*.email' => 'required|email',
])->setSanitizers([
    'email' => ['trim', 'lowercase'],
    'contacts.*.email' => ['trim', 'lowercase'],
]);
```

Or use the helper:

```php
$clean = sanitize('  TEST@ex.com  ', 'email');           // 'TEST@ex.com'
$clean = sanitize('<b>TEXT</b>', ['string', 'lowercase']); // 'text'
```

## Available Rules (108)

ReqShield includes 108 validation rules covering several common scenarios:

- Basic Types
- Formats
- Strings
- Numbers
- Dates
- Conditionals
- Database
- Files
- Arrays
- Comparison
- Patterns
- Additional

**[View Complete Rule Reference](https://docs.infocyph.com/projects/reqshield/en/latest/rule-reference.html)**

### Upload Hardening Rules

Use upload-focused rules for request metadata and filename safety:

```php
$validator = Validator::make([
    'upload' => 'required|secure_file',
    'filename' => 'required|safe_filename',
    'upload_id' => 'required|upload_id',
]);
```

`secure_file` combines `file` and `upload_meta` so you can enforce
payload validity and safe upload metadata in one rule.

---

## Available Sanitizers (46 Built-in)

ReqShield includes 46 built-in sanitizers covering several common scenarios:

- Basic Types
- Case Conversions
- Text Processing
- Special Formats
- Alphanumeric Filters
- Security & HTML
- Encoding
- Array Operations

**[View Complete Sanitizer Reference](https://docs.infocyph.com/projects/reqshield/en/latest/sanitization.html)**

---

## Advanced Features

### Request Input Helpers

```php
$result = Validator::fromArray($rules, $data);
$result = Validator::fromQuery($rules, $_GET);
$result = Validator::fromBody($rules, $body);
$result = Validator::fromFiles($rules, $_FILES);
```

PSR-style request objects are supported through `fromServerRequest()` when compatible accessors are available.

```php
$result = Validator::fromServerRequest($rules, $request);
```

### Strict Unknown Field Handling

```php
$validator = Validator::make($rules)
    ->strict();            // same as allowUnknown(false)

$validator = Validator::make($rules)
    ->stripUnknown();      // remove unknown fields instead of failing
```

### Enum Validation and Casting

```php
'status' => 'required|enum:App\\Enums\\OrderStatus'
```

```php
'status' => [
    'rules' => 'required|enum:App\\Enums\\OrderStatus',
    'cast' => App\\Enums\\OrderStatus::class,
]
```

### After Validation Hooks

```php
use Infocyph\ReqShield\Support\ValidationContext;

$validator->after(function (ValidationContext $ctx): void {
    if ((string) $ctx->get('start_date') > (string) $ctx->get('end_date')) {
        $ctx->addError('end_date', 'End date must be after start date.');
    }
});
```

### API Error Formatters and Input Bag

```php
$result->toProblemJson();
$result->toJsonApiErrors();
$result->toApiErrors();
$result->toFlatErrors();
```

```php
$input = $result->input();
$input->string('email');
$input->int('age');
$input->only(['email', 'age']);
```

### Nested Validation

Validate deeply nested arrays using dot notation:

```php
$validator = Validator::make([
    'user.email' => 'required|email',
    'user.name' => 'required|min:3',
    'user.profile.age' => 'required|integer|min:18',
    'user.profile.bio' => 'string|max:500',
])->enableNestedValidation();

$data = [
    'user' => [
        'email' => 'john@example.com',
        'name' => 'John Doe',
        'profile' => [
            'age' => 25,
            'bio' => 'Software developer',
        ],
    ],
];

$result = $validator->validate($data);
```

Use `enableNestedValidation(false)` to flatten only required paths for large nested payloads.

### Custom Field Names

Make error messages user-friendly:

```php
$validator->setFieldAliases([
    'user_email' => 'Email Address',
    'contacts.*.email' => 'Contact Email',
]);
```

### Custom Messages + Locale Packs

```php
$validator
    ->setCustomMessages([
        'email.required' => ':field is required.',
        '*.min' => ':field must be at least :min.',
        'contacts.*.email.email' => 'Each :field must be valid.',
    ])
    ->addLocalePack('es', [
        'required' => 'El campo :field es obligatorio.',
        '*' => 'El campo :field no es valido.',
    ])
    ->setLocale('es');
```

### Throw Exceptions on Failure

```php
use Infocyph\ReqShield\Exceptions\ValidationException;

$validator = Validator::make($rules)->throwOnFailure();

try {
    $result = $validator->validate($data);
    $validated = $result->validated();
} catch (ValidationException $e) {
    echo $e->getMessage();              // "Validation failed"
    print_r($e->getErrors());           // All errors
    echo $e->getErrorCount();           // Number of failed fields
    echo $e->getFirstFieldError('email'); // First error for specific field
    echo $e->getCode();                 // 422
}
```

### Failure Metadata for APIs

```php
$result = $validator->validate($data);

if ($result->fails()) {
    return [
        'errors' => $result->errors(),
        'failures' => $result->failures(), // field, rule, message, value
    ];
}
```

### Conditional Rules

```php
$validator
    ->sometimes('vat', 'required', fn(array $data) => ($data['type'] ?? null) === 'business')
    ->when(
        fn(array $data) => ($data['country'] ?? null) === 'US',
        fn() => ['state' => 'required|string'],
    );
```

### Schema Fragments

```php
Validator::defineFragment('address', [
    'line1' => 'required|string|max:120',
    'zip' => 'required|digits:5',
]);

$validator = Validator::make([
    'name' => 'required|string',
])->useFragment('address', 'billing');
```

### Typed Output + DTO

```php
$validator = Validator::make([
    'age' => 'required|integer',
    'active' => 'required|boolean',
])->setCasts([
    'age' => 'integer',
    'active' => 'boolean',
])->setDtoClass(App\DTO\UserInput::class);

$result = $validator->validate($data);
$typed = $result->typed();
$dto = $result->toDTO();
```

### Compiled Validator Wrapper

`Validator::compile()` returns a reusable validator wrapper.

```php
$compiled = Validator::compile([
    'email' => 'required|email',
    'age' => 'required|integer|min:18',
]);

$result = $compiled->validate($data);
```

### Custom Rules (Simple)

Use callbacks for quick custom validation:

```php
use Infocyph\ReqShield\Rules\Callback;

$validator = Validator::make([
    'code' => [
        'required',
        new Callback(
            callback: fn($value, $field, $data) => $value % 2 === 0,
            message: 'The code must be an even number'
        ),
    ],
]);
```

### Custom Rules (Advanced)

Create reusable rule classes:

```php
use Infocyph\ReqShield\Contracts\Rule;

class StrongPassword implements Rule
{
    public function passes(mixed $value, string $field, array $data): bool
    {
        return strlen($value) >= 12 
            && preg_match('/[A-Z]/', $value)
            && preg_match('/[a-z]/', $value)
            && preg_match('/[0-9]/', $value)
            && preg_match('/[^A-Za-z0-9]/', $value);
    }

    public function message(string $field): string
    {
        return "The {$field} must be at least 12 characters with uppercase, lowercase, number, and special character.";
    }

    public function cost(): int { return 20; }
    public function isBatchable(): bool { return false; }
}

// Usage
$validator = Validator::make([
    'password' => ['required', new StrongPassword()],
]);
```

### Database Validation

Validate against your database:

```php
use Infocyph\ReqShield\Validator;
use Infocyph\ReqShield\Contracts\DatabaseProvider;

// Implement your database provider
class MyDatabaseProvider implements DatabaseProvider
{
    // Implement required methods...
}

$db = new MyDatabaseProvider();

$validator = Validator::make([
    'email' => 'required|email|unique:users,email',
    'category_id' => 'required|exists:categories,id',
], $db);
```

**Benefits:**
- **Automatic batching** - Multiple checks become one query
- **Update support** - `unique:users,email,5` ignores ID 5
- **Soft-delete aware unique** - `unique:users,email,,id,false,deleted_at`

### Schema Export / Introspection

```php
$jsonSchema = $validator->exportSchema('json_schema');
$openApiShape = $validator->exportSchema('openapi');
$introspection = $validator->exportSchema('introspection');
```

### Stop on First Error

For maximum performance, stop all validation on first error:

```php
$validator = Validator::make($rules)
    ->setStopOnFirstError(true);

// Stops immediately when any field fails
$result = $validator->validate($data);
```

---

## Performance

ReqShield is built for speed:

### 1. **Cost-Based Rule Sorting**

### 2. **Intelligent Batching**
Database rules are automatically batched:
```php
// 3 separate rules...
'user_id' => 'exists:users,id',
'email' => 'unique:users,email',
'category_id' => 'exists:categories,id',

// ...become just 2 queries (50x faster!)
// - One batch for exists checks
// - One batch for unique checks
```

### 3. **Fail-Fast Execution**
Stops validating a field on first rule failure:
```php
'email' => 'required|email|max:255'
// If empty → fails on 'required', skips 'email' and 'max:255'
```

### 4. **Zero Overhead for Simple Cases**
Nested validation only activates if you use dot notation. No performance cost for simple flat arrays.

## Security

Protected by [PHPForge](https://github.com/infocyph/PHPForge) — an automated quality and security gate for PHP projects.

---

<div style="text-align:center;">
  <sub><strong>Made with ❤️ for the PHP community</strong></sub><br />
  <sub><a href="LICENSE">MIT Licensed</a></sub><br />
  <a href="https://docs.infocyph.com/projects/reqshield">Documentation</a> •
  <a href="SECURITY.md">Security</a> •
  <a href="CODE_OF_CONDUCT.md">Code of Conduct</a> •
  <a href="https://github.com/infocyph/reqshield/issues">Report Bug</a> •
  <a href="https://github.com/infocyph/reqshield/issues">Request Feature</a>
</div>
