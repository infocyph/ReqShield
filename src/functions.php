<?php

declare(strict_types=1);

use Infocyph\ReqShield\Contracts\DatabaseProvider;
use Infocyph\ReqShield\Support\Sanitizer;
use Infocyph\ReqShield\Validator;

if (! function_exists('validate')) {
    /**
     * Create a new validator instance.
     *
     *
     * @param array $rules Validation rules
     * @param DatabaseProvider|null $db Optional database provider
     * @return Validator
     *
     * @example validate(['email' => 'required|email'])->validate($data);
     */
    function validate(array $rules, ?DatabaseProvider $db = null): Validator
    {
        return new Validator($rules, $db);
    }
}

if (! function_exists('validator')) {
    /**
     * Alias for validate function.
     *
     *
     * @param array $rules Validation rules
     * @param DatabaseProvider|null $db Optional database provider
     * @return Validator
     *
     * @example validator(['name' => 'required'])->validate($data);
     */
    function validator(array $rules, ?DatabaseProvider $db = null): Validator
    {
        return validate($rules, $db);
    }
}

if (! function_exists('sanitize')) {
    /**
     * Sanitize a value using specified sanitizers.
     *
     *
     * @param mixed $value Value to sanitize
     * @param string|array $sanitizers Sanitizer name(s)
     * @return mixed Sanitized value
     *
     * @example sanitize($input, 'email'); // or sanitize($input, ['trim', 'lowercase']);
     */
    function sanitize(mixed $value, string|array $sanitizers): mixed
    {
        if (is_string($sanitizers)) {
            return Sanitizer::$sanitizers($value);
        }

        return Sanitizer::apply($value, $sanitizers);
    }
}
