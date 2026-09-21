<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Support;

use Infocyph\ReqShield\Exceptions\InvalidValidatorProfileException;
use Infocyph\ReqShield\Validator;

/**
 * @phpstan-type SanitizerPipeline string|callable|list<string|callable>
 * @phpstan-type Limits array{
 *   max_depth?:int,
 *   max_fields?:int,
 *   max_wildcard_expansions?:int,
 *   max_flattened_paths?:int
 * }
 * @phpstan-type ProfileOptions array{
 *   aliases?:array<string,string>,
 *   allow_unknown?:bool,
 *   casts?:array<string,mixed>,
 *   dto?:string|null,
 *   fail_fast?:bool,
 *   limits?:Limits,
 *   locale?:string|null,
 *   locale_packs?:array<string,array<string,mixed>>,
 *   messages?:array<string,string>,
 *   nested?:bool,
 *   nested_mode?:string,
 *   sanitizers?:array<string,SanitizerPipeline>,
 *   stop_on_first_error?:bool,
 *   strict?:bool,
 *   strip_unknown?:bool,
 *   throw_on_failure?:bool
 * }
 */
final readonly class ValidatorProfile
{
    private const array MAP_OPTIONS = [
        'aliases',
        'casts',
        'limits',
        'locale_packs',
        'messages',
        'sanitizers',
    ];

    private const array OPTIONS = [
        'aliases',
        'allow_unknown',
        'casts',
        'dto',
        'fail_fast',
        'limits',
        'locale',
        'locale_packs',
        'messages',
        'nested',
        'nested_mode',
        'sanitizers',
        'stop_on_first_error',
        'strict',
        'strip_unknown',
        'throw_on_failure',
    ];

    /** @param ProfileOptions $options */
    private function __construct(
        private array $options,
    ) {}

    /** @param array<string,mixed> $options */
    public static function fromArray(array $options = []): self
    {
        self::assertKnownOptions($options);

        return new self(self::normalizeOptions($options));
    }

    public function apply(Validator $validator): Validator
    {
        $this->applyExecution($validator);
        $this->applyMessages($validator);
        $this->applyInput($validator);
        $this->applyUnknownFields($validator);
        $this->applyOutput($validator);
        $this->applyLimits($validator);

        return $validator;
    }

    /** @param self|array<string,mixed> $overrides */
    public function overlay(self|array $overrides): self
    {
        $incoming = $overrides instanceof self
            ? $overrides->options
            : self::fromArray($overrides)->options;
        $merged = array_replace($this->options, $incoming);

        foreach (self::MAP_OPTIONS as $key) {
            if (!array_key_exists($key, $incoming)) {
                continue;
            }

            $merged[$key] = array_replace(
                $this->mapOption($key),
                $incoming[$key],
            );
        }

        /** @var ProfileOptions $merged */
        return new self($merged);
    }

    /** @return ProfileOptions */
    public function toArray(): array
    {
        return $this->options;
    }

    /** @return array<string,mixed> */
    private static function assertKnownOptions(array $options): void
    {
        foreach (array_keys($options) as $key) {
            if (!in_array($key, self::OPTIONS, true)) {
                throw InvalidValidatorProfileException::forOption(
                    $key,
                    'unknown option.',
                );
            }
        }
    }

    private static function associativeArray(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $normalized = [];
        foreach ($value as $key => $item) {
            if (is_string($key)) {
                $normalized[$key] = $item;
            }
        }

        return $normalized;
    }

    private static function boolean(mixed $value, bool $default): bool
    {
        return match (true) {
            is_bool($value) => $value,
            is_string($value) => in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true),
            is_int($value) => $value !== 0,
            default => $default,
        };
    }

    private static function limits(mixed $value): array
    {
        $limits = self::associativeArray($value);
        $allowed = [
            'max_depth',
            'max_fields',
            'max_flattened_paths',
            'max_wildcard_expansions',
        ];
        $normalized = [];

        foreach ($limits as $key => $item) {
            if (!in_array($key, $allowed, true)) {
                throw InvalidValidatorProfileException::forOption(
                    "limits.{$key}",
                    'unknown limit.',
                );
            }

            $normalized[$key] = self::positiveInt($item, $key);
        }

        /** @var Limits $normalized */
        return $normalized;
    }

    private static function localePacks(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $packs = [];
        foreach ($value as $locale => $messages) {
            if (is_string($locale) && $locale !== '' && is_array($messages)) {
                $packs[$locale] = self::associativeArray($messages);
            }
        }

        return $packs;
    }

    private static function nestedMode(mixed $value): string
    {
        $mode = is_string($value) && $value !== '' ? $value : 'all';
        if ($mode === 'required') {
            return 'targeted';
        }
        if (!in_array($mode, ['all', 'targeted'], true)) {
            throw InvalidValidatorProfileException::forOption(
                'nested_mode',
                "unsupported mode: {$mode}",
            );
        }

        return $mode;
    }

    /**
     * @param array<string,mixed> $normalized
     * @param array<string,mixed> $options
     */
    private static function normalizeBooleanOption(
        array &$normalized,
        array $options,
        string $key,
        bool $default,
    ): void {
        if (array_key_exists($key, $options)) {
            $normalized[$key] = self::boolean($options[$key], $default);
        }
    }

    /**
     * @param array<string,mixed> $normalized
     * @param array<string,mixed> $options
     */
    private static function normalizeNullableStringOption(
        array &$normalized,
        array $options,
        string $key,
    ): void {
        if (array_key_exists($key, $options)) {
            $normalized[$key] = self::nullableString($options[$key]);
        }
    }

    /**
     * @param array<string,mixed> $options
     * @return ProfileOptions
     */
    private static function normalizeOptions(array $options): array
    {
        $normalized = [];

        self::normalizeBooleanOption($normalized, $options, 'allow_unknown', true);
        self::normalizeBooleanOption($normalized, $options, 'fail_fast', true);
        foreach ([
            'nested',
            'stop_on_first_error',
            'strict',
            'strip_unknown',
            'throw_on_failure',
        ] as $key) {
            self::normalizeBooleanOption($normalized, $options, $key, false);
        }

        self::normalizeNullableStringOption($normalized, $options, 'dto');
        self::normalizeNullableStringOption($normalized, $options, 'locale');

        if (array_key_exists('nested_mode', $options)) {
            $normalized['nested_mode'] = self::nestedMode($options['nested_mode']);
        }
        if (array_key_exists('aliases', $options)) {
            $normalized['aliases'] = self::stringMap($options['aliases']);
        }
        if (array_key_exists('messages', $options)) {
            $normalized['messages'] = self::stringMap($options['messages']);
        }
        if (array_key_exists('sanitizers', $options)) {
            $normalized['sanitizers'] = self::sanitizerMap($options['sanitizers']);
        }
        if (array_key_exists('casts', $options)) {
            $normalized['casts'] = self::associativeArray($options['casts']);
        }
        if (array_key_exists('locale_packs', $options)) {
            $normalized['locale_packs'] = self::localePacks($options['locale_packs']);
        }
        if (array_key_exists('limits', $options)) {
            $normalized['limits'] = self::limits($options['limits']);
        }

        /** @var ProfileOptions $normalized */
        return $normalized;
    }

    private static function nullableString(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    private static function positiveInt(mixed $value, string $name): int
    {
        if (!is_int($value) && !(is_string($value) && preg_match('/^\d+$/D', $value) === 1)) {
            throw InvalidValidatorProfileException::forOption(
                "limits.{$name}",
                'must be a positive integer.',
            );
        }

        $resolved = (int) $value;
        if ($resolved < 1) {
            throw InvalidValidatorProfileException::forOption(
                "limits.{$name}",
                'must be a positive integer.',
            );
        }

        return $resolved;
    }

    private static function sanitizerMap(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $normalized = [];
        foreach ($value as $key => $pipeline) {
            if (!is_string($key) || $key === '') {
                continue;
            }
            if (is_string($pipeline) || is_callable($pipeline)) {
                $normalized[$key] = $pipeline;

                continue;
            }
            if (!is_array($pipeline)) {
                continue;
            }

            $steps = array_values(array_filter(
                $pipeline,
                static fn(mixed $step): bool => is_string($step) || is_callable($step),
            ));
            if ($steps !== []) {
                $normalized[$key] = $steps;
            }
        }

        return $normalized;
    }

    private static function stringMap(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $normalized = [];
        foreach ($value as $key => $item) {
            if (is_string($key) && is_string($item)) {
                $normalized[$key] = $item;
            }
        }

        return $normalized;
    }

    private function applyExecution(Validator $validator): void
    {
        if (isset($this->options['fail_fast'])) {
            $validator->setFailFast($this->options['fail_fast']);
        }
        if (isset($this->options['stop_on_first_error'])) {
            $validator->setStopOnFirstError($this->options['stop_on_first_error']);
        }
    }

    private function applyInput(Validator $validator): void
    {
        if (($this->options['casts'] ?? []) !== []) {
            $validator->setCasts($this->options['casts']);
        }
        if (isset($this->options['locale'])) {
            $validator->setLocale($this->options['locale']);
        }
        if (($this->options['locale_packs'] ?? []) !== []) {
            $validator->setLocalePacks($this->options['locale_packs']);
        }
        if (($this->options['nested'] ?? false) === true) {
            $validator->setNestedFlattenMode($this->options['nested_mode'] ?? 'all');
        }
    }

    private function applyLimits(Validator $validator): void
    {
        $limits = $this->options['limits'] ?? [];
        if ($limits === []) {
            return;
        }

        $validator->limits(
            maxDepth: $limits['max_depth'] ?? 32,
            maxFields: $limits['max_fields'] ?? 10_000,
            maxWildcardExpansions: $limits['max_wildcard_expansions'] ?? 10_000,
            maxFlattenedPaths: $limits['max_flattened_paths'] ?? 10_000,
        );
    }

    private function applyMessages(Validator $validator): void
    {
        if (($this->options['aliases'] ?? []) !== []) {
            $validator->setFieldAliases($this->options['aliases']);
        }
        if (($this->options['messages'] ?? []) !== []) {
            $validator->setCustomMessages($this->options['messages']);
        }
        if (($this->options['sanitizers'] ?? []) !== []) {
            $validator->setSanitizers($this->options['sanitizers']);
        }
    }

    private function applyOutput(Validator $validator): void
    {
        if (isset($this->options['throw_on_failure'])) {
            $validator->throwOnFailure($this->options['throw_on_failure']);
        }
        if (array_key_exists('dto', $this->options)) {
            $validator->setDtoClass($this->options['dto']);
        }
    }

    private function applyUnknownFields(Validator $validator): void
    {
        if (($this->options['strip_unknown'] ?? false) === true) {
            $validator->stripUnknown();

            return;
        }
        if (($this->options['strict'] ?? false) === true) {
            $validator->strict();

            return;
        }
        if (isset($this->options['allow_unknown'])) {
            $validator->allowUnknown($this->options['allow_unknown']);
        }
    }

    /** @return array<string,mixed> */
    private function mapOption(string $key): array
    {
        $value = $this->options[$key] ?? [];

        return is_array($value) ? $value : [];
    }
}
