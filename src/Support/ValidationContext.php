<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Support;

final class ValidationContext
{
    /**
     * @param array<int|string,mixed> $data
     * @param array<string,array<int,string>> $errors
     * @param array<int,array{field:string,rule:string,message:string,value:mixed}> $failures
     * @param array<string,mixed> $validated
     */
    public function __construct(
        private readonly array $data,
        private array &$errors,
        private array &$failures,
        private array &$validated,
    ) {}

    public function addError(string $field, string $message): void
    {
        if ($field === '' || $message === '') {
            return;
        }

        $this->errors[$field][] = $message;
        $this->failures[] = [
            'field' => $field,
            'rule' => 'after',
            'message' => $message,
            'value' => $this->data[$field] ?? null,
        ];
        unset($this->validated[$field]);
    }

    public function addFailure(
        string $field,
        string $rule,
        string $message,
        mixed $value = null,
    ): void {
        if ($field === '' || $rule === '' || $message === '') {
            return;
        }

        $this->errors[$field][] = $message;
        $this->failures[] = [
            'field' => $field,
            'rule' => $rule,
            'message' => $message,
            'value' => $value,
        ];
        unset($this->validated[$field]);
    }

    /** @return array<int|string,mixed> */
    public function data(): array
    {
        return $this->data;
    }

    /** @return array<string,array<int,string>> */
    public function errors(): array
    {
        return $this->errors;
    }

    /** @return array<int,array{field:string,rule:string,message:string,value:mixed}> */
    public function failures(): array
    {
        return $this->failures;
    }

    public function get(string $field, mixed $default = null): mixed
    {
        return $this->data[$field] ?? $default;
    }

    public function has(string $field): bool
    {
        return array_key_exists($field, $this->data);
    }

    /** @return array<string,mixed> */
    public function validated(): array
    {
        return $this->validated;
    }
}
