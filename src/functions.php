<?php

declare(strict_types=1);

use Infocyph\ReqShield\Contracts\DatabaseProvider;
use Infocyph\ReqShield\Support\Sanitizer;
use Infocyph\ReqShield\Validator;

if (! function_exists('validator')) {
    /**
     * Create a new validator instance.
     *
     * @param  array  $rules  Validation rules
     * @param  DatabaseProvider|null  $db  Optional database provider
     *
     * @example validator(['email' => 'required|email'])->validate($data);
     */
    function validator(array $rules, ?DatabaseProvider $db = null): Validator
    {
        return Validator::make($rules, $db);
    }
}

if (! function_exists('validate')) {
    /**
     * Create and immediately validate data.
     *
     * This is a convenience function that creates a validator and validates in one call.
     * For more control, use validator() instead.
     *
     * @param  array  $rules  Validation rules
     * @param  array  $data  Data to validate
     * @param  DatabaseProvider|null  $db  Optional database provider
     *
     * @throws \Infocyph\ReqShield\Exceptions\ValidationException
     *
     * @example
     * $result = validate(['email' => 'required|email'], $_POST);
     * if ($result->passes()) { ... }
     */
    function validate(array $rules, array $data, ?DatabaseProvider $db = null): mixed
    {
        return Validator::make($rules, $db)->validate($data);
    }
}

if (! function_exists('sanitize')) {
    /**
     * Sanitize a value using specified sanitizers.
     *
     * @param  mixed  $value  Value to sanitize
     * @param  string|array  $sanitizers  Sanitizer name(s)
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

if (! function_exists('passes')) {
    /**
     * Quick validation check - returns true if validation passes.
     *
     * @param  array  $rules  Validation rules
     * @param  array  $data  Data to validate
     * @param  DatabaseProvider|null  $db  Optional database provider
     *
     * @throws \Infocyph\ReqShield\Exceptions\ValidationException
     *
     * @example if (passes(['email' => 'required|email'], $_POST)) { ... }
     */
    function passes(array $rules, array $data, ?DatabaseProvider $db = null): bool
    {
        return Validator::make($rules, $db)->validate($data)->passes();
    }
}

if (! function_exists('fails')) {
    /**
     * Quick validation check - returns true if validation fails.
     *
     * @param  array  $rules  Validation rules
     * @param  array  $data  Data to validate
     * @param  DatabaseProvider|null  $db  Optional database provider
     *
     * @throws \Infocyph\ReqShield\Exceptions\ValidationException
     *
     * @example if (fails(['email' => 'required|email'], $_POST)) { ... }
     */
    function fails(array $rules, array $data, ?DatabaseProvider $db = null): bool
    {
        return Validator::make($rules, $db)->validate($data)->fails();
    }
}
