<?php

declare(strict_types=1);

return array_replace(
    // Basic Type Validations
    array_combine(
        ['required', 'filled', 'string', 'integer', 'numeric', 'boolean', 'array', 'nullable', 'present', 'enum'],
        [
            \Infocyph\ReqShield\Rules\Required::class,
            \Infocyph\ReqShield\Rules\Filled::class,
            \Infocyph\ReqShield\Rules\StringRule::class,
            \Infocyph\ReqShield\Rules\IntegerRule::class,
            \Infocyph\ReqShield\Rules\Numeric::class,
            \Infocyph\ReqShield\Rules\Boolean::class,
            \Infocyph\ReqShield\Rules\ArrayRule::class,
            \Infocyph\ReqShield\Rules\Nullable::class,
            \Infocyph\ReqShield\Rules\Present::class,
            \Infocyph\ReqShield\Rules\EnumRule::class,
        ],
    ),
    // Format Validations
    array_combine(
        ['email', 'url', 'active_url', 'ip', 'json', 'uuid', 'ulid', 'mac', 'hex_color', 'timezone'],
        [
            \Infocyph\ReqShield\Rules\Email::class,
            \Infocyph\ReqShield\Rules\Url::class,
            \Infocyph\ReqShield\Rules\ActiveUrl::class,
            \Infocyph\ReqShield\Rules\Ip::class,
            \Infocyph\ReqShield\Rules\Json::class,
            \Infocyph\ReqShield\Rules\Uuid::class,
            \Infocyph\ReqShield\Rules\Ulid::class,
            \Infocyph\ReqShield\Rules\Mac::class,
            \Infocyph\ReqShield\Rules\HexColor::class,
            \Infocyph\ReqShield\Rules\Timezone::class,
        ],
    ),
    // String Validations
    array_combine(
        ['alpha', 'alpha_num', 'alpha_dash', 'ascii', 'lowercase', 'uppercase', 'starts_with', 'ends_with', 'contains', 'doesnt_contain', 'doesnt_start_with', 'doesnt_end_with'],
        [
            \Infocyph\ReqShield\Rules\Alpha::class,
            \Infocyph\ReqShield\Rules\AlphaNum::class,
            \Infocyph\ReqShield\Rules\AlphaDash::class,
            \Infocyph\ReqShield\Rules\Ascii::class,
            \Infocyph\ReqShield\Rules\Lowercase::class,
            \Infocyph\ReqShield\Rules\Uppercase::class,
            \Infocyph\ReqShield\Rules\StartsWith::class,
            \Infocyph\ReqShield\Rules\EndsWith::class,
            \Infocyph\ReqShield\Rules\Contains::class,
            \Infocyph\ReqShield\Rules\DoesntContain::class,
            \Infocyph\ReqShield\Rules\DoesntStartWith::class,
            \Infocyph\ReqShield\Rules\DoesntEndWith::class,
        ],
    ),
    // Numeric Validations
    array_combine(
        ['min', 'max', 'between', 'size', 'digits', 'digits_between', 'min_digits', 'max_digits', 'decimal', 'multiple_of', 'gt', 'gte', 'lt', 'lte'],
        [
            \Infocyph\ReqShield\Rules\Min::class,
            \Infocyph\ReqShield\Rules\Max::class,
            \Infocyph\ReqShield\Rules\Between::class,
            \Infocyph\ReqShield\Rules\Size::class,
            \Infocyph\ReqShield\Rules\Digits::class,
            \Infocyph\ReqShield\Rules\DigitsBetween::class,
            \Infocyph\ReqShield\Rules\MinDigits::class,
            \Infocyph\ReqShield\Rules\MaxDigits::class,
            \Infocyph\ReqShield\Rules\Decimal::class,
            \Infocyph\ReqShield\Rules\MultipleOf::class,
            \Infocyph\ReqShield\Rules\GreaterThan::class,
            \Infocyph\ReqShield\Rules\GreaterThanOrEqual::class,
            \Infocyph\ReqShield\Rules\LessThan::class,
            \Infocyph\ReqShield\Rules\LessThanOrEqual::class,
        ],
    ),
    // Date/Time Validations
    array_combine(
        ['date', 'date_format', 'date_equals', 'before', 'before_or_equal', 'after', 'after_or_equal'],
        [
            \Infocyph\ReqShield\Rules\Date::class,
            \Infocyph\ReqShield\Rules\DateFormat::class,
            \Infocyph\ReqShield\Rules\DateEquals::class,
            \Infocyph\ReqShield\Rules\Before::class,
            \Infocyph\ReqShield\Rules\BeforeOrEqual::class,
            \Infocyph\ReqShield\Rules\After::class,
            \Infocyph\ReqShield\Rules\AfterOrEqual::class,
        ],
    ),
    // Conditional Validations
    array_combine(
        ['required_if', 'required_unless', 'required_with', 'required_with_all', 'required_without', 'required_without_all', 'required_array_keys', 'required_if_accepted', 'required_if_declined', 'present_if', 'present_unless', 'present_with', 'present_with_all', 'missing', 'missing_if', 'missing_unless', 'prohibited', 'prohibited_if', 'prohibited_unless', 'prohibits', 'exclude', 'exclude_if', 'exclude_unless', 'exclude_with', 'exclude_without'],
        [
            \Infocyph\ReqShield\Rules\RequiredIf::class,
            \Infocyph\ReqShield\Rules\RequiredUnless::class,
            \Infocyph\ReqShield\Rules\RequiredWith::class,
            \Infocyph\ReqShield\Rules\RequiredWithAll::class,
            \Infocyph\ReqShield\Rules\RequiredWithout::class,
            \Infocyph\ReqShield\Rules\RequiredWithoutAll::class,
            \Infocyph\ReqShield\Rules\RequiredArrayKeys::class,
            \Infocyph\ReqShield\Rules\RequiredIfAccepted::class,
            \Infocyph\ReqShield\Rules\RequiredIfDeclined::class,
            \Infocyph\ReqShield\Rules\PresentIf::class,
            \Infocyph\ReqShield\Rules\PresentUnless::class,
            \Infocyph\ReqShield\Rules\PresentWith::class,
            \Infocyph\ReqShield\Rules\PresentWithAll::class,
            \Infocyph\ReqShield\Rules\Missing::class,
            \Infocyph\ReqShield\Rules\MissingIf::class,
            \Infocyph\ReqShield\Rules\MissingUnless::class,
            \Infocyph\ReqShield\Rules\Prohibited::class,
            \Infocyph\ReqShield\Rules\ProhibitedIf::class,
            \Infocyph\ReqShield\Rules\ProhibitedUnless::class,
            \Infocyph\ReqShield\Rules\Prohibits::class,
            \Infocyph\ReqShield\Rules\Exclude::class,
            \Infocyph\ReqShield\Rules\ExcludeIf::class,
            \Infocyph\ReqShield\Rules\ExcludeUnless::class,
            \Infocyph\ReqShield\Rules\ExcludeWith::class,
            \Infocyph\ReqShield\Rules\ExcludeWithout::class,
        ],
    ),
    // Database Validations
    array_combine(
        ['unique', 'exists'],
        [
            \Infocyph\ReqShield\Rules\Unique::class,
            \Infocyph\ReqShield\Rules\Exists::class,
        ],
    ),
    // File Validations
    array_combine(
        ['file', 'path', 'image', 'mimes', 'mimetypes', 'extensions', 'dimensions', 'secure_file', 'safe_filename', 'upload_meta', 'upload_id'],
        [
            \Infocyph\ReqShield\Rules\File::class,
            \Infocyph\ReqShield\Rules\Path::class,
            \Infocyph\ReqShield\Rules\Image::class,
            \Infocyph\ReqShield\Rules\Mimes::class,
            \Infocyph\ReqShield\Rules\MimeTypes::class,
            \Infocyph\ReqShield\Rules\Extensions::class,
            \Infocyph\ReqShield\Rules\Dimensions::class,
            \Infocyph\ReqShield\Rules\SecureFile::class,
            \Infocyph\ReqShield\Rules\SafeFilename::class,
            \Infocyph\ReqShield\Rules\UploadMeta::class,
            \Infocyph\ReqShield\Rules\UploadId::class,
        ],
    ),
    // Array Validations
    array_combine(
        ['in', 'not_in', 'in_array', 'distinct', 'is_list'],
        [
            \Infocyph\ReqShield\Rules\In::class,
            \Infocyph\ReqShield\Rules\NotIn::class,
            \Infocyph\ReqShield\Rules\InArray::class,
            \Infocyph\ReqShield\Rules\Distinct::class,
            \Infocyph\ReqShield\Rules\IsList::class,
        ],
    ),
    // Comparison Validations
    array_combine(
        ['same', 'different', 'confirmed'],
        [
            \Infocyph\ReqShield\Rules\Same::class,
            \Infocyph\ReqShield\Rules\Different::class,
            \Infocyph\ReqShield\Rules\Confirmed::class,
        ],
    ),
    // Pattern Validations
    array_combine(
        ['regex', 'not_regex'],
        [
            \Infocyph\ReqShield\Rules\Regex::class,
            \Infocyph\ReqShield\Rules\NotRegex::class,
        ],
    ),
    // Additional Validations
    array_combine(
        ['accepted', 'accepted_if', 'declined', 'declined_if', 'bail', 'callback', 'current_password'],
        [
            \Infocyph\ReqShield\Rules\Accepted::class,
            \Infocyph\ReqShield\Rules\AcceptedIf::class,
            \Infocyph\ReqShield\Rules\Declined::class,
            \Infocyph\ReqShield\Rules\DeclinedIf::class,
            \Infocyph\ReqShield\Rules\Bail::class,
            \Infocyph\ReqShield\Rules\Callback::class,
            \Infocyph\ReqShield\Rules\CurrentPassword::class,
        ],
    ),
);
