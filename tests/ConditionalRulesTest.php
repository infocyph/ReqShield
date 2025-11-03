<?php

declare(strict_types=1);

use Infocyph\ReqShield\Validator;

describe('Conditional Validation Rules', function () {
    // Required Conditional Rules
    test('required_if rule validates conditional requirement', function () {
        $validator = Validator::make([
          'account_type' => 'required|in:personal,business',
          'company_name' => 'required_if:account_type,business',
        ]);

        expect($validator->validate(['account_type' => 'business', 'company_name' => 'Acme Corp'])->passes())->toBeTrue();
        expect($validator->validate(['account_type' => 'personal'])->passes())->toBeTrue();
    });

    test('required_unless rule validates requirement unless condition', function () {
        $validator = Validator::make([
          'account_type' => 'required|in:personal,business',
          'personal_id' => 'required_unless:account_type,business',
        ]);

        expect($validator->validate(['account_type' => 'business'])->passes())->toBeTrue();
        expect($validator->validate(['account_type' => 'personal', 'personal_id' => 'ID-123'])->passes())->toBeTrue();
    });

    test('required_with rule validates requirement with other field', function () {
        $validator = Validator::make([
          'company_name' => 'string',
          'vat_number' => 'required_with:company_name',
        ]);

        expect($validator->validate(['company_name' => 'Acme Corp', 'vat_number' => 'VAT123'])->passes())->toBeTrue();
        expect($validator->validate([])->passes())->toBeTrue();
    });

    test('required_with_all rule validates requirement with all fields', function () {
        $validator = Validator::make([
          'field1' => 'string',
          'field2' => 'string',
          'field3' => 'required_with_all:field1,field2',
        ]);

        expect($validator->validate(['field1' => 'a', 'field2' => 'b', 'field3' => 'c'])->passes())->toBeTrue();
        expect($validator->validate(['field1' => 'a'])->passes())->toBeTrue();
    });

    test('required_without rule validates requirement without other field', function () {
        $validator = Validator::make([
          'email' => 'email',
          'phone' => 'required_without:email',
        ]);

        expect($validator->validate(['email' => 'test@example.com'])->passes())->toBeTrue();
        expect($validator->validate(['phone' => '1234567890'])->passes())->toBeTrue();
    });

    test('required_without_all rule validates requirement without all fields', function () {
        $validator = Validator::make([
          'email' => 'email',
          'phone' => 'string',
          'username' => 'required_without_all:email,phone',
        ]);

        expect($validator->validate(['email' => 'test@example.com'])->passes())->toBeTrue();
        expect($validator->validate(['phone' => '1234567890'])->passes())->toBeTrue();
        expect($validator->validate(['username' => 'john'])->passes())->toBeTrue();
    });

    // Present Conditional Rules
    test('present_if rule validates field presence conditionally', function () {
        $validator = Validator::make([
          'status' => 'required|in:draft,published',
          'draft_notes' => 'present_if:status,draft',
        ]);

        expect($validator->validate(['status' => 'draft', 'draft_notes' => ''])->passes())->toBeTrue();
        expect($validator->validate(['status' => 'published'])->passes())->toBeTrue();
    });

    test('present_unless rule validates field presence unless condition', function () {
        $validator = Validator::make([
          'status' => 'required|in:draft,published',
          'published_date' => 'present_unless:status,draft',
        ]);

        expect($validator->validate(['status' => 'draft'])->passes())->toBeTrue();
        expect($validator->validate(['status' => 'published', 'published_date' => '2024-01-01'])->passes())->toBeTrue();
    });

    test('present_with rule validates field presence with other field', function () {
        $validator = Validator::make([
          'published_date' => 'string',
          'tags' => 'present_with:published_date',
        ]);

        expect($validator->validate(['published_date' => '2024-01-01', 'tags' => []])->passes())->toBeTrue();
        expect($validator->validate([])->passes())->toBeTrue();
    });

    test('present_with_all rule validates field presence with all fields', function () {
        $validator = Validator::make([
          'published_date' => 'string',
          'tags' => 'array',
          'categories' => 'present_with_all:published_date,tags',
        ]);

        expect($validator->validate(['published_date' => '2024-01-01', 'tags' => ['tech'], 'categories' => ['technology']])->passes())->toBeTrue();
        expect($validator->validate(['published_date' => '2024-01-01'])->passes())->toBeTrue();
    });

    // Missing Rules
    test('missing rule validates field absence', function () {
        $validator = Validator::make(['coupon' => 'missing']);

        expect($validator->validate([])->passes())->toBeTrue();
        expect($validator->validate(['coupon' => 'CODE'])->fails())->toBeTrue();
    });

    test('missing_if rule validates field absence conditionally', function () {
        $validator = Validator::make([
          'account_type' => 'required|in:free,premium',
          'promo_code' => 'missing_if:account_type,premium',
        ]);

        expect($validator->validate(['account_type' => 'premium'])->passes())->toBeTrue();
        expect($validator->validate(['account_type' => 'free', 'promo_code' => 'CODE'])->passes())->toBeTrue();
        expect($validator->validate(['account_type' => 'premium', 'promo_code' => 'CODE'])->fails())->toBeTrue();
    });

    test('missing_unless rule validates field absence unless condition', function () {
        $validator = Validator::make([
          'account_type' => 'required|in:free,premium',
          'trial_days' => 'missing_unless:account_type,free',
        ]);

        expect($validator->validate(['account_type' => 'premium'])->passes())->toBeTrue();
        expect($validator->validate(['account_type' => 'free', 'trial_days' => '30'])->passes())->toBeTrue();
        expect($validator->validate(['account_type' => 'premium', 'trial_days' => '30'])->fails())->toBeTrue();
    });

    // Prohibited Rules
    test('prohibited rule validates field is not present', function () {
        $validator = Validator::make(['admin_access' => 'prohibited']);

        expect($validator->validate([])->passes())->toBeTrue();
        expect($validator->validate(['admin_access' => true])->fails())->toBeTrue();
    });

    test('prohibited_if rule validates field prohibition conditionally', function () {
        $validator = Validator::make([
          'user_type' => 'required|in:admin,user',
          'sudo_access' => 'prohibited_if:user_type,user',
        ]);

        expect($validator->validate(['user_type' => 'user'])->passes())->toBeTrue();
        expect($validator->validate(['user_type' => 'admin', 'sudo_access' => true])->passes())->toBeTrue();
        expect($validator->validate(['user_type' => 'user', 'sudo_access' => true])->fails())->toBeTrue();
    });

    test('prohibited_unless rule validates field prohibition unless condition', function () {
        $validator = Validator::make([
          'user_type' => 'required|in:admin,user',
          'admin_panel' => 'prohibited_unless:user_type,admin',
        ]);

        expect($validator->validate(['user_type' => 'admin', 'admin_panel' => true])->passes())->toBeTrue();
        expect($validator->validate(['user_type' => 'user'])->passes())->toBeTrue();
        expect($validator->validate(['user_type' => 'user', 'admin_panel' => true])->fails())->toBeTrue();
    });

    test('prohibits rule validates mutual exclusion', function () {
        $validator = Validator::make([
          'special_access' => 'prohibits:limited_access',
          'limited_access' => 'string',
        ]);

        expect($validator->validate(['special_access' => true])->passes())->toBeTrue();
        expect($validator->validate(['limited_access' => 'yes'])->passes())->toBeTrue();
        expect($validator->validate(['special_access' => true, 'limited_access' => 'yes'])->fails())->toBeTrue();
    });

    // Exclude Rules
    test('exclude rule removes field from validated data', function () {
        $validator = Validator::make([
          'name' => 'required',
          'internal_id' => 'exclude',
        ]);

        $result = $validator->validate(['name' => 'John', 'internal_id' => '12345']);
        $validated = $result->validated();

        expect($validated)->toHaveKey('name');
        expect($validated)->not->toHaveKey('internal_id');
    });

    test('exclude_if rule removes field conditionally', function () {
        $validator = Validator::make([
          'user_role' => 'required|in:admin,user,guest',
          'debug_info' => 'exclude_if:user_role,guest',
        ]);

        $result = $validator->validate(['user_role' => 'guest', 'debug_info' => 'test']);

        expect($result->validated())->not->toHaveKey('debug_info');
    });

    test('exclude_unless rule removes field unless condition', function () {
        $validator = Validator::make([
          'user_role' => 'required|in:admin,user',
          'admin_notes' => 'exclude_unless:user_role,admin',
        ]);

        $result = $validator->validate(['user_role' => 'user', 'admin_notes' => 'notes']);

        expect($result->validated())->not->toHaveKey('admin_notes');
    });

    test('exclude_with rule removes field when other field present', function () {
        $validator = Validator::make([
          'temp_token' => 'exclude_with:permanent_token',
          'permanent_token' => 'string',
        ]);

        $result = $validator->validate(['temp_token' => 'abc', 'permanent_token' => 'xyz']);

        expect($result->validated())->not->toHaveKey('temp_token');
    });

    test('exclude_without rule removes field when other field absent', function () {
        $validator = Validator::make([
          'backup_email' => 'exclude_without:primary_email',
          'primary_email' => 'email',
        ]);

        $result = $validator->validate(['backup_email' => 'backup@example.com']);

        expect($result->validated())->not->toHaveKey('backup_email');
    });
});