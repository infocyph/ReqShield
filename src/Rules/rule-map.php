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
    'required' => \Infocyph\ReqShield\Rules\Required::class,
    'filled' => \Infocyph\ReqShield\Rules\Filled::class,
    'string' => \Infocyph\ReqShield\Rules\StringRule::class,
    'integer' => \Infocyph\ReqShield\Rules\IntegerRule::class,
    'numeric' => \Infocyph\ReqShield\Rules\Numeric::class,
    'boolean' => \Infocyph\ReqShield\Rules\Boolean::class,
    'array' => \Infocyph\ReqShield\Rules\ArrayRule::class,
    'nullable' => \Infocyph\ReqShield\Rules\Nullable::class,
    'present' => \Infocyph\ReqShield\Rules\Present::class,

    // ==========================================
    // Format Validations
    // ==========================================
    'email' => \Infocyph\ReqShield\Rules\Email::class,
    'url' => \Infocyph\ReqShield\Rules\Url::class,
    'active_url' => \Infocyph\ReqShield\Rules\ActiveUrl::class,
    'ip' => \Infocyph\ReqShield\Rules\Ip::class,
    'json' => \Infocyph\ReqShield\Rules\Json::class,
    'uuid' => \Infocyph\ReqShield\Rules\Uuid::class,
    'ulid' => \Infocyph\ReqShield\Rules\Ulid::class,
    'mac' => \Infocyph\ReqShield\Rules\Mac::class,
    'hex_color' => \Infocyph\ReqShield\Rules\HexColor::class,
    'timezone' => \Infocyph\ReqShield\Rules\Timezone::class,

    // ==========================================
    // String Validations
    // ==========================================
    'alpha' => \Infocyph\ReqShield\Rules\Alpha::class,
    'alpha_num' => \Infocyph\ReqShield\Rules\AlphaNum::class,
    'alpha_dash' => \Infocyph\ReqShield\Rules\AlphaDash::class,
    'ascii' => \Infocyph\ReqShield\Rules\Ascii::class,
    'lowercase' => \Infocyph\ReqShield\Rules\Lowercase::class,
    'uppercase' => \Infocyph\ReqShield\Rules\Uppercase::class,
    'starts_with' => \Infocyph\ReqShield\Rules\StartsWith::class,
    'ends_with' => \Infocyph\ReqShield\Rules\EndsWith::class,
    'contains' => \Infocyph\ReqShield\Rules\Contains::class,
    'doesnt_contain' => \Infocyph\ReqShield\Rules\DoesntContain::class,
    'doesnt_start_with' => \Infocyph\ReqShield\Rules\DoesntStartWith::class,
    'doesnt_end_with' => \Infocyph\ReqShield\Rules\DoesntEndWith::class,

    // ==========================================
    // Numeric Validations
    // ==========================================
    'min' => \Infocyph\ReqShield\Rules\Min::class,
    'max' => \Infocyph\ReqShield\Rules\Max::class,
    'between' => \Infocyph\ReqShield\Rules\Between::class,
    'size' => \Infocyph\ReqShield\Rules\Size::class,
    'digits' => \Infocyph\ReqShield\Rules\Digits::class,
    'digits_between' => \Infocyph\ReqShield\Rules\DigitsBetween::class,
    'min_digits' => \Infocyph\ReqShield\Rules\MinDigits::class,
    'max_digits' => \Infocyph\ReqShield\Rules\MaxDigits::class,
    'decimal' => \Infocyph\ReqShield\Rules\Decimal::class,
    'multiple_of' => \Infocyph\ReqShield\Rules\MultipleOf::class,
    'gt' => \Infocyph\ReqShield\Rules\GreaterThan::class,
    'gte' => \Infocyph\ReqShield\Rules\GreaterThanOrEqual::class,
    'lt' => \Infocyph\ReqShield\Rules\LessThan::class,
    'lte' => \Infocyph\ReqShield\Rules\LessThanOrEqual::class,

    // ==========================================
    // Date/Time Validations
    // ==========================================
    'date' => \Infocyph\ReqShield\Rules\Date::class,
    'date_format' => \Infocyph\ReqShield\Rules\DateFormat::class,
    'date_equals' => \Infocyph\ReqShield\Rules\DateEquals::class,
    'before' => \Infocyph\ReqShield\Rules\Before::class,
    'before_or_equal' => \Infocyph\ReqShield\Rules\BeforeOrEqual::class,
    'after' => \Infocyph\ReqShield\Rules\After::class,
    'after_or_equal' => \Infocyph\ReqShield\Rules\AfterOrEqual::class,

    // ==========================================
    // Conditional Validations
    // ==========================================
    'required_if' => \Infocyph\ReqShield\Rules\RequiredIf::class,
    'required_unless' => \Infocyph\ReqShield\Rules\RequiredUnless::class,
    'required_with' => \Infocyph\ReqShield\Rules\RequiredWith::class,
    'required_with_all' => \Infocyph\ReqShield\Rules\RequiredWithAll::class,
    'required_without' => \Infocyph\ReqShield\Rules\RequiredWithout::class,
    'required_without_all' => \Infocyph\ReqShield\Rules\RequiredWithoutAll::class,
    'required_array_keys' => \Infocyph\ReqShield\Rules\RequiredArrayKeys::class,
    'required_if_accepted' => \Infocyph\ReqShield\Rules\RequiredIfAccepted::class,
    'required_if_declined' => \Infocyph\ReqShield\Rules\RequiredIfDeclined::class,
    'present_if' => \Infocyph\ReqShield\Rules\PresentIf::class,
    'present_unless' => \Infocyph\ReqShield\Rules\PresentUnless::class,
    'present_with' => \Infocyph\ReqShield\Rules\PresentWith::class,
    'present_with_all' => \Infocyph\ReqShield\Rules\PresentWithAll::class,
    'missing' => \Infocyph\ReqShield\Rules\Missing::class,
    'missing_if' => \Infocyph\ReqShield\Rules\MissingIf::class,
    'missing_unless' => \Infocyph\ReqShield\Rules\MissingUnless::class,
    'prohibited' => \Infocyph\ReqShield\Rules\Prohibited::class,
    'prohibited_if' => \Infocyph\ReqShield\Rules\ProhibitedIf::class,
    'prohibited_unless' => \Infocyph\ReqShield\Rules\ProhibitedUnless::class,
    'prohibits' => \Infocyph\ReqShield\Rules\Prohibits::class,
    'exclude' => \Infocyph\ReqShield\Rules\Exclude::class,
    'exclude_if' => \Infocyph\ReqShield\Rules\ExcludeIf::class,
    'exclude_unless' => \Infocyph\ReqShield\Rules\ExcludeUnless::class,
    'exclude_with' => \Infocyph\ReqShield\Rules\ExcludeWith::class,
    'exclude_without' => \Infocyph\ReqShield\Rules\ExcludeWithout::class,

    // ==========================================
    // Database Validations
    // ==========================================
    'unique' => \Infocyph\ReqShield\Rules\Unique::class,
    'exists' => \Infocyph\ReqShield\Rules\Exists::class,

    // ==========================================
    // File Validations
    // ==========================================
    'file' => \Infocyph\ReqShield\Rules\File::class,
    'path' => \Infocyph\ReqShield\Rules\Path::class,
    'image' => \Infocyph\ReqShield\Rules\Image::class,
    'mimes' => \Infocyph\ReqShield\Rules\Mimes::class,
    'mimetypes' => \Infocyph\ReqShield\Rules\MimeTypes::class,
    'extensions' => \Infocyph\ReqShield\Rules\Extensions::class,
    'dimensions' => \Infocyph\ReqShield\Rules\Dimensions::class,
    'secure_file' => \Infocyph\ReqShield\Rules\SecureFile::class,
    'safe_filename' => \Infocyph\ReqShield\Rules\SafeFilename::class,
    'upload_meta' => \Infocyph\ReqShield\Rules\UploadMeta::class,
    'upload_id' => \Infocyph\ReqShield\Rules\UploadId::class,

    // ==========================================
    // Array Validations
    // ==========================================
    'in' => \Infocyph\ReqShield\Rules\In::class,
    'not_in' => \Infocyph\ReqShield\Rules\NotIn::class,
    'in_array' => \Infocyph\ReqShield\Rules\InArray::class,
    'distinct' => \Infocyph\ReqShield\Rules\Distinct::class,
    'is_list' => \Infocyph\ReqShield\Rules\IsList::class,

    // ==========================================
    // Comparison Validations
    // ==========================================
    'same' => \Infocyph\ReqShield\Rules\Same::class,
    'different' => \Infocyph\ReqShield\Rules\Different::class,
    'confirmed' => \Infocyph\ReqShield\Rules\Confirmed::class,

    // ==========================================
    // Pattern Validations
    // ==========================================
    'regex' => \Infocyph\ReqShield\Rules\Regex::class,
    'not_regex' => \Infocyph\ReqShield\Rules\NotRegex::class,

    // ==========================================
    // Additional Validations
    // ==========================================
    'accepted' => \Infocyph\ReqShield\Rules\Accepted::class,
    'accepted_if' => \Infocyph\ReqShield\Rules\AcceptedIf::class,
    'declined' => \Infocyph\ReqShield\Rules\Declined::class,
    'declined_if' => \Infocyph\ReqShield\Rules\DeclinedIf::class,
    'bail' => \Infocyph\ReqShield\Rules\Bail::class,
    'callback' => \Infocyph\ReqShield\Rules\Callback::class,
    'current_password' => \Infocyph\ReqShield\Rules\CurrentPassword::class,
];
