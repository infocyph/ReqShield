# 🛡️ ReqShield

[![PHP Version](https://img.shields.io/badge/PHP-%3E%3D8.4-777BB4?logo=php&logoColor=white)](https://www.php.net/)
[![License](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE)
[![Tests](https://img.shields.io/badge/tests-passing-brightgreen.svg)](tests/)
[![Documentation](https://img.shields.io/badge/docs-readthedocs-blue.svg)](https://reqshield.readthedocs.io)

**Fast, modern PHP request validation and sanitization.** Schema-based rules, fail-fast execution, intelligent batching, and 100+ validation rules out of the box.

```php
$validator = Validator::make([
    'email' => 'required|email|max:255',
    'username' => 'required|alpha_dash|min:3|max:50',
    'age' => 'required|integer|min:18',
]);

$result = $validator->validate($data);

if ($result->passes()) {
    $clean = $result->validated();
    // ✅ All good!
}
```

---

## ✨ Features

- 🚀 **103 Built-in Rules** - From basic types to complex conditionals and database checks
- 🧹 **50+ Sanitizers** - Clean and normalize data before validation
- ⚡ **Intelligent Batching** - Database rules are batched automatically (50x faster)
- 🎯 **Fail-Fast Execution** - Stops at first error per field for maximum performance
- 📦 **Cost-Based Optimization** - Rules auto-sorted by complexity (cheap → expensive)
- 🔗 **Nested Validation** - Validate deeply nested arrays with dot notation
- 💾 **Database Validation** - Built-in `unique` and `exists` rules with custom provider support
- 🎨 **Custom Rules** - Simple callbacks or full OOP rule classes
- 🌐 **PSR-7 Friendly** - Works seamlessly with modern PHP frameworks
- 🛠️ **PHP 8.4+** - Built with modern PHP features

---

## 📦 Installation

```bash
composer require infocyph/reqshield
```

**Requirements:** PHP 8.4 or higher

---

## 🚀 Quick Start

### Basic Validation

```php
use Infocyph\ReqShield\Validator;

$validator = Validator::make([
    'email' => 'required|email|max:255',
    'username' => 'required|string|min:3|max:50',
    'password' => 'required|min:8',
    'password_confirmation' => 'required|same:password',
]);

$result = $validator->validate($data);

if ($result->passes()) {
    $validated = $result->validated();
    // Process your data...
} else {
    $errors = $result->errors();
    // Handle validation errors...
}
```

### Sanitization

Always sanitize before validating:

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

Or use the helper:

```php
$clean = sanitize('  TEST@ex.com  ', 'email');           // 'TEST@ex.com'
$clean = sanitize('<b>TEXT</b>', ['string', 'lowercase']); // 'text'
```

---

## 📚 Available Rules (103)

ReqShield includes 103 validation rules covering every common scenario:

### Basic Types
`required` • `filled` • `string` • `integer` • `numeric` • `boolean` • `array` • `nullable` • `present`

### Formats
`email` • `url` • `active_url` • `ip` • `json` • `uuid` • `ulid` • `mac` • `hex_color` • `timezone`

### Strings
`alpha` • `alpha_num` • `alpha_dash` • `ascii` • `lowercase` • `uppercase` • `starts_with` • `ends_with` • `contains` • `doesnt_contain` • `doesnt_start_with` • `doesnt_end_with`

### Numbers
`min` • `max` • `between` • `size` • `digits` • `digits_between` • `min_digits` • `max_digits` • `decimal` • `multiple_of` • `gt` • `gte` • `lt` • `lte`

### Dates
`date` • `date_format` • `date_equals` • `before` • `before_or_equal` • `after` • `after_or_equal`

### Conditionals (27 rules!)
`required_if` • `required_unless` • `required_with` • `required_with_all` • `required_without` • `required_without_all` • `required_array_keys` • `required_if_accepted` • `required_if_declined` • `present_if` • `present_unless` • `present_with` • `present_with_all` • `missing` • `missing_if` • `missing_unless` • `prohibited` • `prohibited_if` • `prohibited_unless` • `prohibits` • `exclude` • `exclude_if` • `exclude_unless` • `exclude_with` • `exclude_without`

### Database
`unique:table,column` • `exists:table,column`

### Files
`file` • `image` • `mimes` • `mimetypes` • `extensions` • `dimensions`

### Arrays
`in` • `not_in` • `in_array` • `distinct` • `is_list`

### Comparison
`same` • `different` • `confirmed`

### Patterns
`regex` • `not_regex`

### Additional
`accepted` • `accepted_if` • `declined` • `declined_if` • `bail` • `callback`

**[📖 View Complete Rule Reference](https://reqshield.readthedocs.io/en/latest/rule-reference.html)**

---

## 🧹 Available Sanitizers (50+)

### Basic Types
`string` • `integer` • `float` • `boolean` • `email` • `url`

### Case Conversions
`lowercase` • `uppercase` • `camelCase` • `pascalCase` • `snakeCase` • `kebabCase` • `titleCase` • `sentenceCase`

### Text Processing
`trim` • `slug` • `truncate` • `truncateWords` • `normalizeWhitespace` • `removeLineBreaks` • `stripWhitespace`

### Special Formats
`phone` • `currency` • `formatCurrency` • `filename` • `domain`

### Alphanumeric Filters
`alpha` • `alphanumeric` • `alphaDash` • `alphanumericSpace` • `numeric`

### Security & HTML
`htmlEncode` • `htmlDecode` • `stripTags` • `stripUnsafeTags` • `removeXss` • `escapeLike` • `removeSqlPatterns`

### Encoding
`base64Encode` • `base64Decode` • `jsonEncode` • `jsonDecode`

### Array Operations
`array` • `batch` • `apply`

**[📖 View Complete Sanitizer Reference](https://reqshield.readthedocs.io/en/latest/sanitization.html)**

---

## 🎯 Advanced Features

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

### Custom Field Names

Make error messages user-friendly:

```php
$validator->setFieldAliases([
    'user_email' => 'Email Address',
    'pwd' => 'Password',
    'pwd_confirm' => 'Password Confirmation',
]);

// Error: "The Email Address must be a valid email address."
// Instead of: "The user_email must be a valid email address."
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

### Custom Rules (Simple)

Use callbacks for quick custom validation:

```php
use Infocyph\ReqShield\Rules\Callback;

$validator = Validator::make([
    'code' => [
        'required',
        new Callback(
            callback: fn($value) => $value % 2 === 0,
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

Validator::setDatabaseProvider(new MyDatabaseProvider());

$validator = Validator::make([
    'email' => 'required|email|unique:users,email',
    'category_id' => 'required|exists:categories,id',
]);
```

**Benefits:**
- 🚀 **Automatic batching** - Multiple checks become one query
- ⚡ **50x faster** than individual queries
- 🎯 **Update support** - `unique:users,email,5` ignores ID 5

### Stop on First Error

For maximum performance, stop all validation on first error:

```php
$validator = Validator::make($rules)
    ->setStopOnFirstError(true);

// Stops immediately when any field fails
$result = $validator->validate($data);
```

---

## ⚡ Performance

ReqShield is built for speed:

### 1. **Cost-Based Rule Sorting**
Rules automatically execute in order of complexity:
- **Cheap** (< 50): Type checks, empty checks
- **Medium** (50-99): String operations, regex
- **Expensive** (100+): Database queries, API calls

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

---

## 📖 Documentation

Comprehensive documentation is available on ReadTheDocs:

- **[Getting Started](https://reqshield.readthedocs.io/en/latest/basic-usage.html)**
- **[Complete Rule Reference (103 rules)](https://reqshield.readthedocs.io/en/latest/rule-reference.html)**
- **[Complete Sanitizer Reference (50+ methods)](https://reqshield.readthedocs.io/en/latest/sanitization.html)**
- **[Custom Rules](https://reqshield.readthedocs.io/en/latest/custom-rules.html)**
- **[Nested Validation](https://reqshield.readthedocs.io/en/latest/nested-validation.html)**
- **[Handling Results](https://reqshield.readthedocs.io/en/latest/handling-results.html)**

---

## 🧪 Testing

ReqShield uses [Pest PHP](https://pestphp.com/) for testing:

```bash
# Run all tests
composer test

# Run tests with coverage
composer test:code

# Run code style checks
composer test:lint

# Run refactoring checks
composer test:refactor

# Run all checks
composer tests
```

---

## 🤝 Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add some amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

Please ensure your code:
- Follows PSR-12 coding standards
- Includes tests for new features
- Updates documentation as needed

---

## 📄 License

ReqShield is open-sourced software licensed under the [MIT license](LICENSE).

---

## 👨‍💻 Author

**abmmhasan** (infocyph)
- Email: abmmhasan@gmail.com
- GitHub: [@infocyph](https://github.com/infocyph)

---

## 🌟 Show Your Support

If you find ReqShield helpful, please consider giving it a ⭐️ on GitHub!

---

## 🔗 Related Projects

- **[Laravel Validation](https://laravel.com/docs/validation)** - Inspiration for rule syntax
- **[Respect/Validation](https://github.com/Respect/Validation)** - Another great PHP validation library
- **[Symfony Validator](https://symfony.com/doc/current/validation.html)** - Symfony's validation component

---

## 🎯 Why ReqShield?

| Feature | ReqShield | Others |
|---------|-----------|--------|
| **Rules** | 103 built-in | ~50-80 |
| **Sanitizers** | 50+ methods | Limited or none |
| **Database Batching** | ✅ Automatic | ❌ Manual |
| **Nested Validation** | ✅ Dot notation | ⚠️ Complex |
| **Performance** | ⚡ Cost-optimized | 🐌 Linear |
| **PHP Version** | 8.4+ (modern) | 7.x-8.x (legacy) |
| **PSR-7 Ready** | ✅ Yes | ⚠️ Varies |
| **Custom Rules** | ✅ Simple & OOP | ⚠️ Complex |

---

<div align="center">

**Made with ❤️ for the PHP community**

[Documentation](https://reqshield.readthedocs.io) • [Report Bug](https://github.com/infocyph/reqshield/issues) • [Request Feature](https://github.com/infocyph/reqshield/issues)

</div>
