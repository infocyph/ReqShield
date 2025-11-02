<?php

use Infocyph\ReqShield\Validator;

// --- Example 1, 8, 9, 10, 11, 12, 14, 15, 23, 26, 27, 28, 31, 32, 33 ---

test('basic validation passes', function () {
    $validator = Validator::make([
        'email' => 'required|email|max:255',
        'username' => 'required|string|min:3|max:50',
        'age' => 'required|integer|min:18|max:120',
        'password' => 'required|min:8',
        'password_confirmation' => 'required|same:password',
    ]);

    $data = [
        'email' => 'john@example.com',
        'username' => 'johndoe',
        'age' => 25,
        'password' => 'secret123',
        'password_confirmation' => 'secret123',
    ];

    $result = $validator->validate($data);

    expect($result->passes())->toBeTrue();
    expect($result->fails())->toBeFalse();
    expect($result->validated())->toEqual($data);
});

test('string validation rules pass', function () {
    $validator = Validator::make([
        'alpha' => 'alpha',
        'alpha_num' => 'alpha_num',
        'alpha_dash' => 'alpha_dash',
        'ascii' => 'ascii',
        'lowercase' => 'lowercase',
        'uppercase' => 'uppercase',
        'starts_with' => 'starts_with:hello',
        'ends_with' => 'ends_with:world',
    ]);

    $data = [
        'alpha' => 'abcdef',
        'alpha_num' => 'abc123',
        'alpha_dash' => 'abc-def_123',
        'ascii' => 'hello',
        'lowercase' => 'hello',
        'uppercase' => 'WORLD',
        'starts_with' => 'hello there',
        'ends_with' => 'brave world',
    ];

    $result = $validator->validate($data);
    expect($result->passes())->toBeTrue();
});

test('string negation rules pass', function () {
    $validator = Validator::make([
        'username' => 'required|doesnt_start_with:admin,root,system',
        'filename' => 'required|doesnt_end_with:.exe,.bat,.sh',
        'description' => 'required|doesnt_contain:spam,viagra,casino',
    ]);

    $data = [
        'username' => 'john_doe',
        'filename' => 'document.pdf',
        'description' => 'This is a legitimate description',
    ];

    $result = $validator->validate($data);
    expect($result->passes())->toBeTrue();
});

test('string negation rules fail', function () {
    $validator = Validator::make([
        'username' => 'doesnt_start_with:admin',
        'filename' => 'doesnt_end_with:.exe',
        'description' => 'doesnt_contain:spam',
    ]);

    $data = [
        'username' => 'admin_user',
        'filename' => 'virus.exe',
        'description' => 'This is spam',
    ];

    $result = $validator->validate($data);
    expect($result->fails())->toBeTrue();
    expect($result->errors())->toHaveKeys(['username', 'filename', 'description']);
});


test('numeric validation rules pass', function () {
    $validator = Validator::make([
        'integer' => 'integer|min:10|max:100',
        'numeric' => 'numeric|between:1,50',
        'digits' => 'digits:4',
        'digits_between' => 'digits_between:3,5',
        'min_digits' => 'min_digits:3',
        'max_digits' => 'max_digits:5',
        'decimal' => 'decimal:2',
        'multiple_of' => 'multiple_of:5',
    ]);

    $data = [
        'integer' => 50,
        'numeric' => 25,
        'digits' => '1234',
        'digits_between' => '1234',
        'min_digits' => '123',
        'max_digits' => '12345',
        'decimal' => '10.25',
        'multiple_of' => 25,
    ];

    $result = $validator->validate($data);
    expect($result->passes())->toBeTrue();
});

test('numeric comparison rules pass', function () {
    $validator = Validator::make([
        'original_price' => 'required|numeric|min:0.01',
        'sale_price' => 'required|numeric|lt:original_price',
        'min_order' => 'required|integer|min:1',
        'max_order' => 'required|integer|max:100|gte:min_order',
        'current_stock' => 'required|integer|min:0',
        'reorder_level' => 'required|integer|lte:current_stock',
    ]);

    $data = [
        'original_price' => 99.99,
        'sale_price' => 79.99,
        'min_order' => 1,
        'max_order' => 10,
        'current_stock' => 50,
        'reorder_level' => 20,
    ];

    $result = $validator->validate($data);
    expect($result->passes())->toBeTrue();
});

test('date validation rules pass', function () {
    $validator = Validator::make([
        'date' => 'date',
        'date_format' => 'date_format:Y-m-d',
        'before' => 'before:2030-01-01',
        'after' => 'after:2020-01-01',
        'before_or_equal' => 'before_or_equal:2025-12-31',
        'after_or_equal' => 'after_or_equal:2024-01-01',
        'date_equals' => 'date_equals:2024-12-25',
    ]);

    $data = [
        'date' => '2024-05-15',
        'date_format' => '2024-05-15',
        'before' => '2025-06-01',
        'after' => '2024-03-01',
        'before_or_equal' => '2025-12-31',
        'after_or_equal' => '2024-01-01',
        'date_equals' => '2024-12-25',
    ];

    $result = $validator->validate($data);
    expect($result->passes())->toBeTrue();
});

test('format validation rules pass', function () {
    $validator = Validator::make([
        'email' => 'email',
        'url' => 'url',
        'ip_any' => 'ip',
        'ip_v4' => 'ip:v4',
        'ip_v6' => 'ip:v6',
        'ip_public' => 'ip:public',
        'mac' => 'mac',
        'uuid' => 'uuid',
        'uuid_v4' => 'uuid:4',
        'ulid' => 'ulid',
        'json' => 'json',
        'timezone' => 'timezone',
        'hex_color' => 'hex_color',
        'live_url' => 'active_url',
    ]);

    $data = [
        'email' => 'test@example.com',
        'url' => 'https://www.example.com',
        'ip_any' => '192.168.1.1',
        'ip_v4' => '192.168.1.1',
        'ip_v6' => '2001:0db8:85a3:0000:0000:8a2e:0370:7334',
        'ip_public' => '8.8.8.8',
        'mac' => '00:1B:44:11:3A:B7',
        'uuid' => '550e8400-e29b-41d4-a716-446655440000',
        'uuid_v4' => '550e8400-e29b-41d4-a716-446655440000',
        'ulid' => '01ARZ3NDEKTSV4RRFFQ69G5FAV',
        'json' => '{"key":"value"}',
        'timezone' => 'America/New_York',
        'hex_color' => '#FF5733',
        'live_url' => 'https://google.com', // Assumes google.com is reachable
    ];

    $result = $validator->validate($data);
    if (!$result->passes()) {
        // Handle potential network failure for active_url
        if (isset($result->errors()['live_url'])) {
            unset($data['live_url']);
            $validator = Validator::make(array_diff_key($validator->getRules(), ['live_url' => '']));
            $result = $validator->validate($data);
        }
    }

    expect($result->passes())->toBeTrue();
});

test('array validation rules pass', function () {
    $validator = Validator::make([
        'array' => 'array|min:1|max:5',
        'in' => 'in:admin,user,guest',
        'not_in' => 'not_in:banned,suspended',
        'distinct' => 'array|distinct',
        'roles' => 'required|array',
        'primary_role' => 'required|in_array:roles',
        'items' => 'required|array|is_list',
    ]);

    $data = [
        'array' => ['a', 'b', 'c'],
        'in' => 'admin',
        'not_in' => 'active',
        'distinct' => ['x', 'y', 'z'],
        'roles' => ['admin', 'editor', 'viewer'],
        'primary_role' => 'admin',
        'items' => ['item1', 'item2', 'item3'],
    ];

    $result = $validator->validate($data);
    expect($result->passes())->toBeTrue();
});

test('comparison validation rules pass', function () {
    $validator = Validator::make([
        'password' => 'required|min:8',
        'password_confirmation' => 'required|same:password',
        'new_email' => 'required|email|different:old_email',
        'confirm_email' => 'required|confirmed',
    ]);

    $data = [
        'password' => 'secret123',
        'password_confirmation' => 'secret123',
        'new_email' => 'new@example.com',
        'old_email' => 'old@example.com',
        'confirm_email' => 'test@example.com',
        'confirm_email_confirmation' => 'test@example.com',
    ];

    $result = $validator->validate($data);
    expect($result->passes())->toBeTrue();
});

test('boolean and acceptance rules pass', function () {
    $validator = Validator::make([
        'is_active' => 'boolean',
        'terms_accepted' => 'accepted',
        'marketing_declined' => 'declined',
    ]);

    $data = [
        'is_active' => true,
        'terms_accepted' => 'yes',
        'marketing_declined' => 'no',
    ];

    $result = $validator->validate($data);
    expect($result->passes())->toBeTrue();
});

test('regex validation rules pass', function () {
    $validator = Validator::make([
        'phone' => ['required', 'regex:/^\+?[1-9]\d{1,14}$/'],
        'zipcode' => ['required', 'regex:/^\d{5}(-\d{4})?$/'],
        'username' => ['required', 'regex:/^[a-zA-Z0-9_]{3,20}$/', 'not_regex:/^(admin|root|system)$/i'],
    ]);

    $data = [
        'phone' => '+12125551234',
        'zipcode' => '12345',
        'username' => 'john_doe',
    ];

    $result = $validator->validate($data);
    expect($result->passes())->toBeTrue();
});

test('regex validation rules fail', function () {
    $validator = Validator::make([
        'zipcode' => ['regex:/^\d{5}$/'],
        'username' => ['not_regex:/^admin$/i'],
    ]);

    $data = [
        'zipcode' => 'abcde',
        'username' => 'admin',
    ];

    $result = $validator->validate($data);
    expect($result->fails())->toBeTrue();
    expect($result->errors())->toHaveKeys(['zipcode', 'username']);
});

test('existence rules pass', function () {
    $validator = Validator::make([
        'optional' => 'nullable|email',
        'must_exist' => 'present',
        'not_empty' => 'filled',
    ]);

    $data = [
        'optional' => null,
        'must_exist' => '',
        'not_empty' => 'some value',
    ];
    $result = $validator->validate($data);
    expect($result->passes())->toBeTrue();

    $data2 = [
        'optional' => 'test@example.com',
        'must_exist' => 'value',
        'not_empty' => 'some value',
    ];
    $result2 = $validator->validate($data2);
    expect($result2->passes())->toBeTrue();
});

test('existence rules fail', function () {
    $validator = Validator::make([
        'optional' => 'nullable|email',
        'must_exist' => 'present',
        'not_empty' => 'filled',
    ]);

    $data = [
        'optional' => 'not-an-email', // Fails email
        'not_empty' => '', // Fails filled
        // 'must_exist' is missing, fails present
    ];

    $result = $validator->validate($data);
    expect($result->fails())->toBeTrue();
    expect($result->errors())->toHaveKeys(['optional', 'must_exist', 'not_empty']);
});

test('file rules pass', function () {
    // Mock file info
    $fileInfo = [
        'name' => 'document.pdf',
        'type' => 'application/pdf',
        'size' => 512 * 1024, // 512 KB
        'tmp_name' => '/tmp/php123',
        'error' => UPLOAD_ERR_OK,
    ];

    $validator = Validator::make([
        'document' => 'required|max:10240', // 10MB
        'script' => 'required|mimes:pdf,txt',
        'source' => 'required|extensions:pdf,txt,md',
    ]);

    $data = [
        'document' => $fileInfo,
        'script' => $fileInfo,
        'source' => $fileInfo,
    ];

    $result = $validator->validate($data);
    expect($result->passes())->toBeTrue();
});

test('file rules fail', function () {
    // Mock file info
    $fileInfo = [
        'name' => 'image.jpg',
        'type' => 'image/jpeg',
        'size' => 2048 * 1024, // 2MB
        'tmp_name' => '/tmp/php456',
        'error' => UPLOAD_ERR_OK,
    ];

    $validator = Validator::make([
        'document' => 'required|max:1024', // 1MB limit
        'script' => 'required|mimes:pdf,txt',
        'source' => 'required|extensions:doc,xls',
    ]);

    $data = [
        'document' => $fileInfo,
        'script' => $fileInfo,
        'source' => $fileInfo,
    ];

    $result = $validator->validate($data);
    expect($result->fails())->toBeTrue();
    expect($result->errors())->toHaveKeys(['document', 'script', 'source']);
});
