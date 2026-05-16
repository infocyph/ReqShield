<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Enums;

use Infocyph\ReqShield\Contracts\Rule;

enum BuiltinRule: string
{
    case Accepted = 'accepted';

    case AcceptedIf = 'accepted_if';

    case ActiveUrl = 'active_url';

    case After = 'after';

    case AfterOrEqual = 'after_or_equal';

    case Alpha = 'alpha';

    case AlphaDash = 'alpha_dash';

    case AlphaNum = 'alpha_num';

    case ArrayRule = 'array';

    case Ascii = 'ascii';

    case Bail = 'bail';

    case Before = 'before';

    case BeforeOrEqual = 'before_or_equal';

    case Between = 'between';

    case Boolean = 'boolean';

    case Callback = 'callback';

    case Confirmed = 'confirmed';

    case Contains = 'contains';

    case CurrentPassword = 'current_password';

    case Date = 'date';

    case DateEquals = 'date_equals';

    case DateFormat = 'date_format';

    case Decimal = 'decimal';

    case Declined = 'declined';

    case DeclinedIf = 'declined_if';

    case Different = 'different';

    case Digits = 'digits';

    case DigitsBetween = 'digits_between';

    case Dimensions = 'dimensions';

    case Distinct = 'distinct';

    case DoesntContain = 'doesnt_contain';

    case DoesntEndWith = 'doesnt_end_with';

    case DoesntStartWith = 'doesnt_start_with';

    case Email = 'email';

    case EndsWith = 'ends_with';

    case EnumRule = 'enum';

    case Exclude = 'exclude';

    case ExcludeIf = 'exclude_if';

    case ExcludeUnless = 'exclude_unless';

    case ExcludeWith = 'exclude_with';

    case ExcludeWithout = 'exclude_without';

    case Exists = 'exists';

    case Extensions = 'extensions';

    case File = 'file';

    case Filled = 'filled';

    case GreaterThan = 'gt';

    case GreaterThanOrEqual = 'gte';

    case HexColor = 'hex_color';

    case Image = 'image';

    case In = 'in';

    case InArray = 'in_array';

    case IntegerRule = 'integer';

    case Ip = 'ip';

    case IsList = 'is_list';

    case Json = 'json';

    case LessThan = 'lt';

    case LessThanOrEqual = 'lte';

    case Lowercase = 'lowercase';

    case Mac = 'mac';

    case Max = 'max';

    case MaxDigits = 'max_digits';

    case Mimes = 'mimes';

    case MimeTypes = 'mimetypes';

    case Min = 'min';

    case MinDigits = 'min_digits';

    case Missing = 'missing';

    case MissingIf = 'missing_if';

    case MissingUnless = 'missing_unless';

    case MultipleOf = 'multiple_of';

    case NotIn = 'not_in';

    case NotRegex = 'not_regex';

    case Nullable = 'nullable';

    case Numeric = 'numeric';

    case Path = 'path';

    case Present = 'present';

    case PresentIf = 'present_if';

    case PresentUnless = 'present_unless';

    case PresentWith = 'present_with';

    case PresentWithAll = 'present_with_all';

    case Prohibited = 'prohibited';

    case ProhibitedIf = 'prohibited_if';

    case ProhibitedUnless = 'prohibited_unless';

    case Prohibits = 'prohibits';

    case Regex = 'regex';

    case Required = 'required';

    case RequiredArrayKeys = 'required_array_keys';

    case RequiredIf = 'required_if';

    case RequiredIfAccepted = 'required_if_accepted';

    case RequiredIfDeclined = 'required_if_declined';

    case RequiredUnless = 'required_unless';

    case RequiredWith = 'required_with';

    case RequiredWithAll = 'required_with_all';

    case RequiredWithout = 'required_without';

    case RequiredWithoutAll = 'required_without_all';

    case SafeFilename = 'safe_filename';

    case Same = 'same';

    case SecureFile = 'secure_file';

    case Size = 'size';

    case StartsWith = 'starts_with';

    case StringRule = 'string';

    case Timezone = 'timezone';

    case Ulid = 'ulid';

    case Unique = 'unique';

    case UploadId = 'upload_id';

    case UploadMeta = 'upload_meta';

    case Uppercase = 'uppercase';

    case Url = 'url';

    case Uuid = 'uuid';

    public static function isExistsRule(string $ruleName): bool
    {
        return $ruleName === self::Exists->value;
    }

    public static function isUniqueRule(string $ruleName): bool
    {
        return $ruleName === self::Unique->value;
    }

    public static function resolve(string $name): ?string
    {
        $maps = self::maps();

        return $maps['tokenToClass'][$name] ?? null;
    }

    public static function resolveNameForClass(string $class): ?string
    {
        $maps = self::maps();
        $normalizedClass = ltrim($class, '\\');

        return $maps['classToToken'][$normalizedClass]
            ?? $maps['classToToken'][$class]
            ?? null;
    }

    public static function singleValuePlaceholderKey(string $ruleName): ?string
    {
        return match ($ruleName) {
            self::Min->value => 'min',
            self::Max->value => 'max',
            self::Size->value => 'size',
            self::Digits->value => 'digits',
            self::MinDigits->value => 'min',
            self::MaxDigits->value => 'max',
            self::MultipleOf->value => 'multiple',
            default => null,
        };
    }

    public static function supportsAcceptedDeclinedPlaceholder(string $ruleName): bool
    {
        return match ($ruleName) {
            self::RequiredIfAccepted->value,
            self::RequiredIfDeclined->value => true,
            default => false,
        };
    }

    public static function supportsAggregateOtherPlaceholder(string $ruleName): bool
    {
        return match ($ruleName) {
            self::RequiredWith->value,
            self::RequiredWithAll->value,
            self::RequiredWithout->value,
            self::RequiredWithoutAll->value,
            self::PresentWith->value,
            self::PresentWithAll->value,
            self::ExcludeWith->value,
            self::ExcludeWithout->value,
            self::Prohibits->value => true,
            default => false,
        };
    }

    public static function supportsBetweenRangePlaceholder(string $ruleName): bool
    {
        return match ($ruleName) {
            self::Between->value,
            self::DigitsBetween->value => true,
            default => false,
        };
    }

    public static function supportsComparisonPlaceholder(string $ruleName): bool
    {
        return match ($ruleName) {
            self::Same->value,
            self::Different->value,
            self::GreaterThan->value,
            self::GreaterThanOrEqual->value,
            self::LessThan->value,
            self::LessThanOrEqual->value => true,
            default => false,
        };
    }

    public static function supportsConditionalPlaceholder(string $ruleName): bool
    {
        return match ($ruleName) {
            self::RequiredIf->value,
            self::RequiredUnless->value,
            self::PresentIf->value,
            self::PresentUnless->value,
            self::MissingIf->value,
            self::MissingUnless->value,
            self::ProhibitedIf->value,
            self::ProhibitedUnless->value,
            self::AcceptedIf->value,
            self::DeclinedIf->value => true,
            default => false,
        };
    }

    public static function supportsDatePlaceholder(string $ruleName): bool
    {
        return match ($ruleName) {
            self::Before->value,
            self::BeforeOrEqual->value,
            self::After->value,
            self::AfterOrEqual->value,
            self::DateEquals->value,
            self::DateFormat->value => true,
            default => false,
        };
    }

    public static function supportsDecimalRangePlaceholder(string $ruleName): bool
    {
        return $ruleName === self::Decimal->value;
    }

    public static function supportsPatternPlaceholder(string $ruleName): bool
    {
        return match ($ruleName) {
            self::Regex->value,
            self::NotRegex->value => true,
            default => false,
        };
    }

    public static function supportsValuesPlaceholder(string $ruleName): bool
    {
        return match ($ruleName) {
            self::In->value,
            self::NotIn->value,
            self::Contains->value,
            self::DoesntContain->value,
            self::StartsWith->value,
            self::EndsWith->value,
            self::DoesntStartWith->value,
            self::DoesntEndWith->value,
            self::RequiredArrayKeys->value => true,
            default => false,
        };
    }

    /**
     * @return array<string, class-string<Rule>>
     */
    public static function tokenToClassMap(): array
    {
        $maps = self::maps();

        return $maps['tokenToClass'];
    }

    /**
     * @return class-string<Rule>
     */
    public function ruleClass(): string
    {
        return 'Infocyph\\ReqShield\\Rules\\' . $this->name;
    }

    /**
     * @return array{
     *     tokenToClass: array<string, class-string<Rule>>,
     *     classToToken: array<string, string>
     * }
     */
    private static function maps(): array
    {
        /** @var array{
         *     tokenToClass: array<string, class-string<Rule>>,
         *     classToToken: array<string, string>
         * }|null $cache */
        static $cache = null;

        if (is_array($cache)) {
            return $cache;
        }

        /** @var array<string, class-string<Rule>> $tokenToClass */
        $tokenToClass = [];
        /** @var array<string, string> $classToToken */
        $classToToken = [];

        foreach (self::cases() as $case) {
            $ruleClass = $case->ruleClass();
            $tokenToClass[$case->value] = $ruleClass;
            $classToToken[$ruleClass] = $case->value;
        }

        $cache = [
            'tokenToClass' => $tokenToClass,
            'classToToken' => $classToToken,
        ];

        return $cache;
    }
}
