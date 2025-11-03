Helper Functions
================

ReqShield includes several global helper functions (loaded via ``composer.json``'s ``files`` autoload) to provide convenient shortcuts for common tasks.

``validator(array $rules, ?DatabaseProvider $db = null): Validator``
--------------------------------------------------------------------

Creates a new ``Validator`` instance. This is a shortcut for ``Validator::make()``.

.. code-block:: php

    $validator = validator(['email' => 'required|email']);
    $result = $validator->validate($data);

``validate(array $rules, array $data, ?DatabaseProvider $db = null): ValidationResult``
--------------------------------------------------------------------------------------

Creates a validator and immediately validates the given data.

.. code-block:: php

    $result = validate(['email' => 'required|email'], $_POST);

    if ($result->fails()) {
        // ...
    }

``sanitize(mixed $value, string|array $sanitizers): mixed``
----------------------------------------------------------

Sanitizes a value using one or more sanitizers from the ``Sanitizer`` class.

.. code-block:: php

    // Single sanitizer
    $email = sanitize('  TEST@ex.com  ', 'email');

    // Chain multiple sanitizers
    $username = sanitize('  <b>John!</b>  ', ['string', 'lowercase', 'alphaDash']);
    // 1. '  <b>John!</b>  '
    // 2. 'John!' (string)
    // 3. 'john!' (lowercase)
    // 4. 'john' (alphaDash)

``passes(array $rules, array $data, ?DatabaseProvider $db = null): bool``
------------------------------------------------------------------------

A quick check to see if validation passes. Returns ``true`` on success, ``false`` on failure.

.. code-block:: php

    if (passes(['email' => 'required|email'], $_POST)) {
        // All good
    } else {
        // Failed
    }

``fails(array $rules, array $data, ?DatabaseProvider $db = null): bool``
----------------------------------------------------------------------

A quick check to see if validation fails. Returns ``true`` on failure, ``false`` on success.

.. code-block:: php

    if (fails(['email' => 'required|email'], $_POST)) {
        // Handle errors
    }
