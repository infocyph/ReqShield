<?php

declare(strict_types=1);

require_once __DIR__.'/vendor/autoload.php';

use Infocyph\ReqShield\Rules\Callback;
use Infocyph\ReqShield\Validator;

echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║             ReqShield - Usage Examples                    ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n\n";

// ============================================
// Example 1: Basic Validation
// ============================================

echo "=== Example 1: Basic Validation ===\n\n";

$validator = new Validator([
    'email' => ['required', 'email', 'max:255'],
    'username' => ['required', 'string', 'min:3', 'max:50'],
    'age' => ['required', 'integer', 'min:18', 'max:120'],
    'password' => ['required', 'min:8'],
    'password_confirmation' => ['required', 'same:password'],
]);

$data = [
    'email' => 'john@example.com',
    'username' => 'johndoe',
    'age' => 25,
    'password' => 'secret123',
    'password_confirmation' => 'secret123',
];

$result = $validator->validate($data);

if ($result->passes()) {
    echo "✓ Validation passed!\n";
    echo 'Validated data: '.json_encode($result->validated(), JSON_PRETTY_PRINT)."\n";
} else {
    echo "✗ Validation failed!\n";
    echo 'Errors: '.json_encode($result->errors(), JSON_PRETTY_PRINT)."\n";
}

// ============================================
// Example 2: Failed Validation
// ============================================

echo "\n=== Example 2: Failed Validation ===\n\n";

$invalidData = [
    'email' => 'not-an-email',
    'username' => 'ab', // Too short
    'age' => 15, // Too young
    'password' => 'short',
    'password_confirmation' => 'different',
];

$result2 = $validator->validate($invalidData);

if ($result2->fails()) {
    echo "✗ Validation failed as expected!\n";
    echo "Errors:\n";
    foreach ($result2->errors() as $field => $errors) {
        echo "  - {$field}: ".implode(', ', $errors)."\n";
    }
}

// ============================================
// Example 3: Using Helper Functions
// ============================================

echo "\n=== Example 3: Using Helper Functions ===\n\n";

$result3 = validate([
    'name' => ['required', 'string', 'min:2'],
    'email' => ['required', 'email'],
])->validate([
    'name' => 'John Doe',
    'email' => 'john@example.com',
]);

if ($result3->passes()) {
    echo "✓ Validation passed using helper function!\n";
}

// ============================================
// Example 4: Advanced Rules
// ============================================

echo "\n=== Example 4: Advanced Rules ===\n\n";

$advancedValidator = new Validator([
    'role' => ['required', 'in:admin,user,moderator'],
    'tags' => ['required', 'array', 'min:1', 'max:5'],
    'website' => ['url'],
    'birthdate' => ['required', 'date', 'before:2010-01-01'],
    'slug' => ['required', 'alpha_dash'],
]);

$advancedData = [
    'role' => 'admin',
    'tags' => ['php', 'validation', 'performance'],
    'website' => 'https://example.com',
    'birthdate' => '1995-05-15',
    'slug' => 'my-awesome-post',
];

$result5 = $advancedValidator->validate($advancedData);

if ($result5->passes()) {
    echo "✓ Advanced validation passed!\n";
    echo 'Validated '.count($result5->validated())." fields\n";
}

// ============================================
// Example 5: Custom Rules
// ============================================

echo "\n=== Example 5: Custom Rules with Callback ===\n\n";

$customValidator = new Validator([
    'code' => [
        'required',
        new Callback(
            callback: fn ($value) => preg_match('/^[A-Z]{3}-\d{4}$/', $value),
            cost: 20,
            message: 'Code must be in format ABC-1234'
        ),
    ],
]);

$codeData = ['code' => 'ABC-1234'];
$result6 = $customValidator->validate($codeData);

if ($result6->passes()) {
    echo "✓ Custom validation passed!\n";
    echo 'Code format is correct: '.$result6->get('code')."\n";
}

// Test failure
$invalidCode = ['code' => 'invalid'];
$result6b = $customValidator->validate($invalidCode);
if ($result6b->fails()) {
    echo "✗ Custom validation failed (as expected)!\n";
    echo 'Error: '.$result6b->firstError('code')."\n";
}

// ============================================
// Example 6: Optional Fields
// ============================================

echo "\n=== Example 6: Optional Fields ===\n\n";

$optionalValidator = new Validator([
    'name' => ['required', 'string'],
    'bio' => ['string', 'max:500'], // Optional (no 'required')
    'website' => ['url'], // Optional
]);

$optionalData = [
    'name' => 'John Doe',
    // bio and website are not provided
];

$result7 = $optionalValidator->validate($optionalData);

if ($result7->passes()) {
    echo "✓ Validation passed with optional fields!\n";
    echo 'Validated: '.json_encode($result7->validated())."\n";
}

// ============================================
// Example 7: Custom Messages
// ============================================

echo "\n=== Example 7: Custom Error Messages ===\n\n";

$messageValidator = new Validator([
    'email' => ['required', 'email'],
    'age' => ['required', 'integer', 'min:18'],
]);

$messageValidator->setCustomMessages([
    'email' => 'Please provide a valid email address!',
    'age' => 'You must be at least 18 years old!',
]);

$customData = [
    'email' => 'invalid',
    'age' => 15,
];

$result8 = $messageValidator->validate($customData);

if ($result8->fails()) {
    echo "Custom error messages:\n";
    foreach ($result8->errors() as $field => $errors) {
        echo "  - {$errors[0]}\n";
    }
}

// ============================================
// Example 8: Performance Benchmark
// ============================================

echo "\n=== Example 8: Performance Benchmark ===\n\n";

$perfValidator = new Validator([
    'email' => ['required', 'email', 'max:255'],
    'username' => ['required', 'string', 'min:3', 'max:50', 'alpha_dash'],
    'age' => ['required', 'integer', 'min:18'],
    'bio' => ['string', 'max:1000'],
]);

$perfData = [
    'email' => 'perf@test.com',
    'username' => 'perfuser',
    'age' => 30,
    'bio' => 'This is a test bio.',
];

$iterations = 10000;
$start = microtime(true);

for ($i = 0; $i < $iterations; $i++) {
    $perfValidator->validate($perfData);
}

$end = microtime(true);
$duration = ($end - $start) * 1000;

echo "Performed {$iterations} validations\n";
echo 'Total time: '.number_format($duration, 2)."ms\n";
echo 'Average per validation: '.number_format($duration / $iterations, 4)."ms\n";
echo 'Validations per second: '.number_format($iterations / ($duration / 1000), 0)."\n";

// ============================================
// Example 9: Schema Statistics
// ============================================

echo "\n=== Example 9: Schema Statistics ===\n\n";

$stats = $perfValidator->getSchemaStats();
echo "Schema statistics:\n";
echo "  Total fields: {$stats['total_fields']}\n";
foreach ($stats['fields'] as $field => $fieldStats) {
    echo "  {$field}:\n";
    echo "    - Cheap rules: {$fieldStats['cheap_rules']}\n";
    echo "    - Medium rules: {$fieldStats['medium_rules']}\n";
    echo "    - Expensive rules: {$fieldStats['expensive_rules']}\n";
}

// ============================================
// Example 10: Fail-Fast vs Collect All
// ============================================

echo "\n=== Example 10: Fail-Fast vs Collect All Errors ===\n\n";

$multiValidator = new Validator([
    'field1' => ['required', 'email'],
    'field2' => ['required', 'integer', 'min:10'],
]);

$multiData = ['field1' => '', 'field2' => ''];

// Fail-fast (default)
$multiValidator->setFailFast(true);
$result10a = $multiValidator->validate($multiData);
echo "Fail-fast mode:\n";
echo '  Errors found: '.$result10a->errorCount()." fields\n";

// Collect all errors
$multiValidator->setFailFast(false);
$result10b = $multiValidator->validate($multiData);
echo "Collect all errors mode:\n";
echo '  Errors found: '.$result10b->errorCount()." fields\n";
foreach ($result10b->errors() as $field => $errors) {
    echo "    {$field}: ".count($errors)." error(s)\n";
}

echo "\n╔════════════════════════════════════════════════════════════╗\n";
echo "║  All Examples Completed Successfully!                     ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n\n";

echo "Key Features Demonstrated:\n";
echo "  ✓ Cost-based rule execution (cheap → medium → expensive)\n";
echo "  ✓ Single-pass validation\n";
echo "  ✓ Fail-fast optimization\n";
echo "  ✓ Optional fields support\n";
echo "  ✓ Custom rules and messages\n";
echo '  ✓ High performance (~'.number_format($iterations / ($duration / 1000), 0)." validations/sec)\n";
echo "  ✓ Clean array-based syntax\n";
echo "\n";
