<?php

use Infocyph\ReqShield\Validator;

describe('Required Conditional Rules', function () {
    test('required_if rule validates conditional requirement', function () {
        $validator = Validator::make([
            'account_type' => 'required',
            'company_name' => 'required_if:account_type,business',
        ]);
        
        expect($validator->validate(['account_type' => 'business', 'company_name' => 'Acme'])->passes())->toBeTrue();
        expect($validator->validate(['account_type' => 'personal'])->passes())->toBeTrue();
        expect($validator->validate(['account_type' => 'business'])->fails())->toBeTrue();
    });

    test('required_unless rule validates requirement unless condition', function () {
        $validator = Validator::make([
            'account_type' => 'required',
            'personal_id' => 'required_unless:account_type,business',
        ]);
        
        expect($validator->validate(['account_type' => 'business'])->passes())->toBeTrue();
        expect($validator->validate(['account_type' => 'personal', 'personal_id' => '123'])->passes())->toBeTrue();
        expect($validator->validate(['account_type' => 'personal'])->fails())->toBeTrue();
    });

    test('required_with rule validates requirement with other field', function () {
        $validator = Validator::make([
            'company_name' => 'string',
            'vat_number' => 'required_with:company_name',
        ]);
        
        expect($validator->validate(['company_name' => 'Acme', 'vat_number' => 'VAT123'])->passes())->toBeTrue();
        expect($validator->validate([])->passes())->toBeTrue();
        expect($validator->validate(['company_name' => 'Acme'])->fails())->toBeTrue();
    });

    test('required_with_all rule validates requirement with all fields', function () {
        $validator = Validator::make([
            'field1' => 'string',
            'field2' => 'string',
            'field3' => 'required_with_all:field1,field2',
        ]);
        
        expect($validator->validate(['field1' => 'a', 'field2' => 'b', 'field3' => 'c'])->passes())->toBeTrue();
        expect($validator->validate(['field1' => 'a'])->passes())->toBeTrue();
        expect($validator->validate(['field1' => 'a', 'field2' => 'b'])->fails())->toBeTrue();
    });

    test('required_without rule validates requirement without other field', function () {
        $validator = Validator::make([
            'email' => 'email',
            'phone' => 'required_without:email',
        ]);
        
        expect($validator->validate(['email' => 'test@example.com'])->passes())->toBeTrue();
        expect($validator->validate(['phone' => '1234567890'])->passes())->toBeTrue();
        expect($validator->validate([])->fails())->toBeTrue();
    });

    test('required_without_all rule validates requirement without all fields', function () {
        $validator = Validator::make([
            'email' => 'email',
            'username' => 'string',
            'phone' => 'required_without_all:email,username',
        ]);
        
        expect($validator->validate(['email' => 'test@example.com'])->passes())->toBeTrue();
        expect($validator->validate(['username' => 'john'])->passes())->toBeTrue();
        expect($validator->validate([])->fails())->toBeTrue();
    });
});

describe('Present Conditional Rules', function () {
    test('present_if rule validates field presence conditionally', function () {
        $validator = Validator::make([
            'status' => 'required',
            'draft_notes' => 'present_if:status,draft',
        ]);
        
        expect($validator->validate(['status' => 'draft', 'draft_notes' => ''])->passes())->toBeTrue();
        expect($validator->validate(['status' => 'published'])->passes())->toBeTrue();
        expect($validator->validate(['status' => 'draft'])->fails())->toBeTrue();
    });

    test('present_unless rule validates field presence unless condition', function () {
        $validator = Validator::make([
            'status' => 'required',
            'published_date' => 'present_unless:status,draft',
        ]);
        
        expect($validator->validate(['status' => 'draft'])->passes())->toBeTrue();
        expect($validator->validate(['status' => 'published', 'published_date' => '2024-01-01'])->passes())->toBeTrue();
        expect($validator->validate(['status' => 'published'])->fails())->toBeTrue();
    });

    test('present_with rule validates field presence with other field', function () {
        $validator = Validator::make([
            'date' => 'date',
            'tags' => 'present_with:date',
        ]);
        
        expect($validator->validate(['date' => '2024-01-01', 'tags' => []])->passes())->toBeTrue();
        expect($validator->validate([])->passes())->toBeTrue();
        expect($validator->validate(['date' => '2024-01-01'])->fails())->toBeTrue();
    });

    test('present_with_all rule validates field presence with all fields', function () {
        $validator = Validator::make([
            'field1' => 'string',
            'field2' => 'string',
            'field3' => 'present_with_all:field1,field2',
        ]);
        
        expect($validator->validate(['field1' => 'a', 'field2' => 'b', 'field3' => ''])->passes())->toBeTrue();
        expect($validator->validate(['field1' => 'a'])->passes())->toBeTrue();
        expect($validator->validate(['field1' => 'a', 'field2' => 'b'])->fails())->toBeTrue();
    });
});

describe('Missing and Prohibited Rules', function () {
    test('missing rule validates field absence', function () {
        $validator = Validator::make(['coupon' => 'missing']);
        
        expect($validator->validate([])->passes())->toBeTrue();
        expect($validator->validate(['coupon' => 'CODE123'])->fails())->toBeTrue();
    });

    test('missing_if rule validates field absence conditionally', function () {
        $validator = Validator::make([
            'account_type' => 'required',
            'promo_code' => 'missing_if:account_type,premium',
        ]);
        
        expect($validator->validate(['account_type' => 'premium'])->passes())->toBeTrue();
        expect($validator->validate(['account_type' => 'free', 'promo_code' => 'PROMO'])->passes())->toBeTrue();
        expect($validator->validate(['account_type' => 'premium', 'promo_code' => 'PROMO'])->fails())->toBeTrue();
    });

    test('missing_unless rule validates field absence unless condition', function () {
        $validator = Validator::make([
            'account_type' => 'required',
            'trial_days' => 'missing_unless:account_type,free',
        ]);
        
        expect($validator->validate(['account_type' => 'free', 'trial_days' => 30])->passes())->toBeTrue();
        expect($validator->validate(['account_type' => 'premium'])->passes())->toBeTrue();
        expect($validator->validate(['account_type' => 'premium', 'trial_days' => 30])->fails())->toBeTrue();
    });

    test('prohibited rule validates field is not present', function () {
        $validator = Validator::make(['delete_permission' => 'prohibited']);
        
        expect($validator->validate([])->passes())->toBeTrue();
        expect($validator->validate(['delete_permission' => true])->fails())->toBeTrue();
    });

    test('prohibited_if rule validates field prohibition conditionally', function () {
        $validator = Validator::make([
            'user_type' => 'required',
            'sudo_access' => 'prohibited_if:user_type,user',
        ]);
        
        expect($validator->validate(['user_type' => 'user'])->passes())->toBeTrue();
        expect($validator->validate(['user_type' => 'admin', 'sudo_access' => true])->passes())->toBeTrue();
        expect($validator->validate(['user_type' => 'user', 'sudo_access' => true])->fails())->toBeTrue();
    });

    test('prohibited_unless rule validates field prohibition unless condition', function () {
        $validator = Validator::make([
            'user_type' => 'required',
            'admin_panel' => 'prohibited_unless:user_type,admin',
        ]);
        
        expect($validator->validate(['user_type' => 'admin', 'admin_panel' => true])->passes())->toBeTrue();
        expect($validator->validate(['user_type' => 'user'])->passes())->toBeTrue();
        expect($validator->validate(['user_type' => 'user', 'admin_panel' => true])->fails())->toBeTrue();
    });

    test('prohibits rule validates mutual exclusion', function () {
        $validator = Validator::make([
            'full_access' => 'prohibits:limited_access',
            'limited_access' => 'boolean',
        ]);
        
        expect($validator->validate(['full_access' => true])->passes())->toBeTrue();
        expect($validator->validate(['limited_access' => true])->passes())->toBeTrue();
        expect($validator->validate(['full_access' => true, 'limited_access' => true])->fails())->toBeTrue();
    });
});

describe('Exclude Rules', function () {
    test('exclude rule removes field from validated data', function () {
        $validator = Validator::make([
            'name' => 'required|string',
            'internal_id' => 'exclude',
        ]);
        
        $result = $validator->validate(['name' => 'John', 'internal_id' => '12345']);
        $validated = $result->validated();
        
        expect($validated)->toHaveKey('name');
        expect($validated)->not->toHaveKey('internal_id');
    });

    test('exclude_if rule removes field conditionally', function () {
        $validator = Validator::make([
            'user_role' => 'required',
            'debug_info' => 'exclude_if:user_role,guest',
        ]);
        
        $result = $validator->validate(['user_role' => 'guest', 'debug_info' => 'test']);
        $validated = $result->validated();
        
        expect($validated)->not->toHaveKey('debug_info');
    });

    test('exclude_unless rule removes field unless condition', function () {
        $validator = Validator::make([
            'user_role' => 'required',
            'admin_notes' => 'exclude_unless:user_role,admin',
        ]);
        
        $result = $validator->validate(['user_role' => 'user', 'admin_notes' => 'notes']);
        $validated = $result->validated();
        
        expect($validated)->not->toHaveKey('admin_notes');
    });

    test('exclude_with rule removes field when other field present', function () {
        $validator = Validator::make([
            'permanent_token' => 'string',
            'temp_token' => 'exclude_with:permanent_token',
        ]);
        
        $result = $validator->validate(['temp_token' => 'abc', 'permanent_token' => 'xyz']);
        $validated = $result->validated();
        
        expect($validated)->not->toHaveKey('temp_token');
    });

    test('exclude_without rule removes field when other field absent', function () {
        $validator = Validator::make([
            'primary_email' => 'email',
            'backup_email' => 'exclude_without:primary_email',
        ]);
        
        $result = $validator->validate(['backup_email' => 'backup@example.com']);
        $validated = $result->validated();
        
        expect($validated)->not->toHaveKey('backup_email');
    });
});

describe('Acceptance Conditional Rules', function () {
    test('required_if_accepted rule validates requirement on acceptance', function () {
        $validator = Validator::make([
            'newsletter' => 'boolean',
            'email' => 'required_if_accepted:newsletter',
        ]);
        
        expect($validator->validate(['newsletter' => 'yes', 'email' => 'test@example.com'])->passes())->toBeTrue();
        expect($validator->validate(['newsletter' => 'no'])->passes())->toBeTrue();
        expect($validator->validate(['newsletter' => 'yes'])->fails())->toBeTrue();
    });

    test('required_if_declined rule validates requirement on declination', function () {
        $validator = Validator::make([
            'notifications' => 'boolean',
            'phone' => 'required_if_declined:notifications',
        ]);
        
        expect($validator->validate(['notifications' => 'no', 'phone' => '1234567890'])->passes())->toBeTrue();
        expect($validator->validate(['notifications' => 'yes'])->passes())->toBeTrue();
        expect($validator->validate(['notifications' => 'no'])->fails())->toBeTrue();
    });
});

describe('Complex Conditional Scenarios', function () {
    test('multiple conditional rules work together', function () {
        $validator = Validator::make([
            'account_type' => 'required|in:personal,business',
            'company_name' => 'required_if:account_type,business|min:3',
            'vat_number' => 'required_with:company_name',
            'personal_id' => 'required_unless:account_type,business',
            'tax_id' => 'required_without:vat_number',
        ]);

        // Business account scenario
        $businessData = [
            'account_type' => 'business',
            'company_name' => 'Acme Corp',
            'vat_number' => 'VAT123456',
        ];
        expect($validator->validate($businessData)->passes())->toBeTrue();

        // Personal account scenario
        $personalData = [
            'account_type' => 'personal',
            'personal_id' => 'ID-5678',
            'tax_id' => 'TAX-910',
        ];
        expect($validator->validate($personalData)->passes())->toBeTrue();
    });

    test('nested conditional validation', function () {
        $validator = Validator::make([
            'has_shipping' => 'boolean',
            'shipping_address' => 'required_if:has_shipping,1',
            'shipping_city' => 'required_with:shipping_address',
            'shipping_zip' => 'required_with:shipping_address',
            'shipping_country' => 'required_with:shipping_address',
        ]);

        $dataWithShipping = [
            'has_shipping' => true,
            'shipping_address' => '123 Main St',
            'shipping_city' => 'New York',
            'shipping_zip' => '10001',
            'shipping_country' => 'US',
        ];
        expect($validator->validate($dataWithShipping)->passes())->toBeTrue();

        $dataWithoutShipping = [
            'has_shipping' => false,
        ];
        expect($validator->validate($dataWithoutShipping)->passes())->toBeTrue();
    });

    test('conditional rules with excludes', function () {
        $validator = Validator::make([
            'user_role' => 'required|in:admin,user,guest',
            'internal_id' => 'exclude',
            'debug_info' => 'exclude_if:user_role,guest',
            'admin_notes' => 'exclude_unless:user_role,admin',
            'access_level' => 'required_unless:user_role,guest',
        ]);

        $guestData = [
            'user_role' => 'guest',
            'internal_id' => '12345',
            'debug_info' => 'test',
            'admin_notes' => 'notes',
        ];

        $result = $validator->validate($guestData);
        $validated = $result->validated();

        expect($validated)->toHaveKey('user_role');
        expect($validated)->not->toHaveKey('internal_id');
        expect($validated)->not->toHaveKey('debug_info');
        expect($validated)->not->toHaveKey('admin_notes');
    });

    test('acceptance rules with required conditionals', function () {
        $validator = Validator::make([
            'terms' => 'required|accepted',
            'age_verification' => 'required|accepted',
            'newsletter' => 'accepted_if:age_verification,yes',
            'notifications' => 'declined_if:newsletter,yes',
            'email' => 'required_if_accepted:newsletter',
            'phone' => 'required_if_declined:notifications',
        ]);

        $data = [
            'terms' => 'yes',
            'age_verification' => 'yes',
            'newsletter' => 'yes',
            'notifications' => 'no',
            'email' => 'user@example.com',
            'phone' => '+1234567890',
        ];

        expect($validator->validate($data)->passes())->toBeTrue();
    });
});
