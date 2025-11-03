<?php

declare(strict_types=1);

/**
 * Rule Map Configuration
 *
 * This file contains the mapping of rule names (as strings) to their
 * corresponding Rule class implementations. This allows for cleaner code in
 * SchemaCompiler and makes it easy to add, remove, or modify rule mappings.
 *
 * Categories:
 * - Basic: Type checks and fundamental validations
 * - Format: String format validations (email, URL, etc.)
 * - Numeric: Number-related validations
 * - String: String manipulation and checks
 * - Date/Time: Date and time validations
 * - Conditional: Conditional validation rules
 * - Database: Database-dependent validations
 * - File: File and upload validations
 * - Array: Array-specific validations
 * - Comparison: Field comparison rules
 * - Additional: Other specialized rules
 */
return [
    // ==========================================
    // Basic Type Validations
    // ==========================================
  'required' => 'Infocyph\ReqShield\Rules\Required',
  'filled' => 'Infocyph\ReqShield\Rules\Filled',
  'string' => 'Infocyph\ReqShield\Rules\StringRule',
  'integer' => 'Infocyph\ReqShield\Rules\IntegerRule',
  'numeric' => 'Infocyph\ReqShield\Rules\Numeric',
  'boolean' => 'Infocyph\ReqShield\Rules\Boolean',
  'array' => 'Infocyph\ReqShield\Rules\ArrayRule',
  'nullable' => 'Infocyph\ReqShield\Rules\Nullable',
  'present' => 'Infocyph\ReqShield\Rules\Present',

    // ==========================================
    // Format Validations
    // ==========================================
  'email' => 'Infocyph\ReqShield\Rules\Email',
  'url' => 'Infocyph\ReqShield\Rules\Url',
  'active_url' => 'Infocyph\ReqShield\Rules\ActiveUrl',
  'ip' => 'Infocyph\ReqShield\Rules\Ip',
  'json' => 'Infocyph\ReqShield\Rules\Json',
  'uuid' => 'Infocyph\ReqShield\Rules\Uuid',
  'ulid' => 'Infocyph\ReqShield\Rules\Ulid',
  'mac' => 'Infocyph\ReqShield\Rules\Mac',
  'hex_color' => 'Infocyph\ReqShield\Rules\HexColor',
  'timezone' => 'Infocyph\ReqShield\Rules\Timezone',

    // ==========================================
    // String Validations
    // ==========================================
  'alpha' => 'Infocyph\ReqShield\Rules\Alpha',
  'alpha_num' => 'Infocyph\ReqShield\Rules\AlphaNum',
  'alpha_dash' => 'Infocyph\ReqShield\Rules\AlphaDash',
  'ascii' => 'Infocyph\ReqShield\Rules\Ascii',
  'lowercase' => 'Infocyph\ReqShield\Rules\Lowercase',
  'uppercase' => 'Infocyph\ReqShield\Rules\Uppercase',
  'starts_with' => 'Infocyph\ReqShield\Rules\StartsWith',
  'ends_with' => 'Infocyph\ReqShield\Rules\EndsWith',
  'contains' => 'Infocyph\ReqShield\Rules\Contains',
  'doesnt_contain' => 'Infocyph\ReqShield\Rules\DoesntContain',
  'doesnt_start_with' => 'Infocyph\ReqShield\Rules\DoesntStartWith',
  'doesnt_end_with' => 'Infocyph\ReqShield\Rules\DoesntEndWith',

    // ==========================================
    // Numeric Validations
    // ==========================================
  'min' => 'Infocyph\ReqShield\Rules\Min',
  'max' => 'Infocyph\ReqShield\Rules\Max',
  'between' => 'Infocyph\ReqShield\Rules\Between',
  'size' => 'Infocyph\ReqShield\Rules\Size',
  'digits' => 'Infocyph\ReqShield\Rules\Digits',
  'digits_between' => 'Infocyph\ReqShield\Rules\DigitsBetween',
  'min_digits' => 'Infocyph\ReqShield\Rules\MinDigits',
  'max_digits' => 'Infocyph\ReqShield\Rules\MaxDigits',
  'decimal' => 'Infocyph\ReqShield\Rules\Decimal',
  'multiple_of' => 'Infocyph\ReqShield\Rules\MultipleOf',
  'gt' => 'Infocyph\ReqShield\Rules\GreaterThan',
  'gte' => 'Infocyph\ReqShield\Rules\GreaterThanOrEqual',
  'lt' => 'Infocyph\ReqShield\Rules\LessThan',
  'lte' => 'Infocyph\ReqShield\Rules\LessThanOrEqual',

    // ==========================================
    // Date/Time Validations
    // ==========================================
  'date' => 'Infocyph\ReqShield\Rules\Date',
  'date_format' => 'Infocyph\ReqShield\Rules\DateFormat',
  'date_equals' => 'Infocyph\ReqShield\Rules\DateEquals',
  'before' => 'Infocyph\ReqShield\Rules\Before',
  'before_or_equal' => 'Infocyph\ReqShield\Rules\BeforeOrEqual',
  'after' => 'Infocyph\ReqShield\Rules\After',
  'after_or_equal' => 'Infocyph\ReqShield\Rules\AfterOrEqual',

    // ==========================================
    // Conditional Validations
    // ==========================================
  'required_if' => 'Infocyph\ReqShield\Rules\RequiredIf',
  'required_unless' => 'Infocyph\ReqShield\Rules\RequiredUnless',
  'required_with' => 'Infocyph\ReqShield\Rules\RequiredWith',
  'required_with_all' => 'Infocyph\ReqShield\Rules\RequiredWithAll',
  'required_without' => 'Infocyph\ReqShield\Rules\RequiredWithout',
  'required_without_all' => 'Infocyph\ReqShield\Rules\RequiredWithoutAll',
  'required_array_keys' => 'Infocyph\ReqShield\Rules\RequiredArrayKeys',
  'required_if_accepted' => 'Infocyph\ReqShield\Rules\RequiredIfAccepted',
  'required_if_declined' => 'Infocyph\ReqShield\Rules\RequiredIfDeclined',
  'present_if' => 'Infocyph\ReqShield\Rules\PresentIf',
  'present_unless' => 'Infocyph\ReqShield\Rules\PresentUnless',
  'present_with' => 'Infocyph\ReqShield\Rules\PresentWith',
  'present_with_all' => 'Infocyph\ReqShield\Rules\PresentWithAll',
  'missing' => 'Infocyph\ReqShield\Rules\Missing',
  'missing_if' => 'Infocyph\ReqShield\Rules\MissingIf',
  'missing_unless' => 'Infocyph\ReqShield\Rules\MissingUnless',
  'prohibited' => 'Infocyph\ReqShield\Rules\Prohibited',
  'prohibited_if' => 'Infocyph\ReqShield\Rules\ProhibitedIf',
  'prohibited_unless' => 'Infocyph\ReqShield\Rules\ProhibitedUnless',
  'prohibits' => 'Infocyph\ReqShield\Rules\Prohibits',
  'exclude' => 'Infocyph\ReqShield\Rules\Exclude',
  'exclude_if' => 'Infocyph\ReqShield\Rules\ExcludeIf',
  'exclude_unless' => 'Infocyph\ReqShield\Rules\ExcludeUnless',
  'exclude_with' => 'Infocyph\ReqShield\Rules\ExcludeWith',
  'exclude_without' => 'Infocyph\ReqShield\Rules\ExcludeWithout',

    // ==========================================
    // Database Validations
    // ==========================================
  'unique' => 'Infocyph\ReqShield\Rules\Unique',
  'exists' => 'Infocyph\ReqShield\Rules\Exists',

    // ==========================================
    // File Validations
    // ==========================================
  'file' => 'Infocyph\ReqShield\Rules\File',
  'path' => 'Infocyph\ReqShield\Rules\Path',
  'image' => 'Infocyph\ReqShield\Rules\Image',
  'mimes' => 'Infocyph\ReqShield\Rules\Mimes',
  'mimetypes' => 'Infocyph\ReqShield\Rules\MimeTypes',
  'extensions' => 'Infocyph\ReqShield\Rules\Extensions',
  'dimensions' => 'Infocyph\ReqShield\Rules\Dimensions',

    // ==========================================
    // Array Validations
    // ==========================================
  'in' => 'Infocyph\ReqShield\Rules\In',
  'not_in' => 'Infocyph\ReqShield\Rules\NotIn',
  'in_array' => 'Infocyph\ReqShield\Rules\InArray',
  'distinct' => 'Infocyph\ReqShield\Rules\Distinct',
  'is_list' => 'Infocyph\ReqShield\Rules\IsList',

    // ==========================================
    // Comparison Validations
    // ==========================================
  'same' => 'Infocyph\ReqShield\Rules\Same',
  'different' => 'Infocyph\ReqShield\Rules\Different',
  'confirmed' => 'Infocyph\ReqShield\Rules\Confirmed',

    // ==========================================
    // Pattern Validations
    // ==========================================
  'regex' => 'Infocyph\ReqShield\Rules\Regex',
  'not_regex' => 'Infocyph\ReqShield\Rules\NotRegex',

    // ==========================================
    // Additional Validations
    // ==========================================
  'accepted' => 'Infocyph\ReqShield\Rules\Accepted',
  'accepted_if' => 'Infocyph\ReqShield\Rules\AcceptedIf',
  'declined' => 'Infocyph\ReqShield\Rules\Declined',
  'declined_if' => 'Infocyph\ReqShield\Rules\DeclinedIf',
  'bail' => 'Infocyph\ReqShield\Rules\Bail',
  'callback' => 'Infocyph\ReqShield\Rules\Callback',
  'current_password' => 'Infocyph\ReqShield\Rules\CurrentPassword',
];
