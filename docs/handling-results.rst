Handling the Validation Result
==============================

``validate()`` returns ``ValidationResult``.

.. code-block:: php

    $result = $validator->validate($data);

Pass/Fail Checks
----------------

.. code-block:: php

    $result->passes();
    $result->fails();

Validated and Typed Data
------------------------

``validated()`` returns validated values.
``typed()`` returns casted values when casts are configured; otherwise it matches ``validated()``.

.. code-block:: php

    $all = $result->validated();
    $typed = $result->typed();

    $subset = $result->only(['email', 'name']);
    $withoutAge = $result->except(['age']);
    $safe = $result->safe(['optional_field']);

    $result->has('email');
    $result->get('email');

Errors and Messages
-------------------

.. code-block:: php

    $errors = $result->errors();
    $emailErrors = $result->errorsFor('email');

    $firstForEmail = $result->first('email');
    $firstAny = $result->firstError();

    $result->hasError('email');
    $result->errorCount();
    $result->allErrors();
    $result->messages(); // MessageBag

Failure Metadata
----------------

Use failure metadata for API/UI mapping.

.. code-block:: php

    $result->failures();
    // [
    //   [
    //     'field' => 'email',
    //     'rule' => 'email',
    //     'message' => 'The Email must be a valid email address.',
    //     'value' => 'bad-email'
    //   ]
    // ]

    $result->failuresFor('email');

DTO and Serialization
---------------------

.. code-block:: php

    $dto = $result->toDTO();     // uses setDtoClass() if configured
    $arr = $result->toArray();   // includes errors, failures, validated, typed
    $json = $result->toJson();   // errors JSON

When a DTO class is configured, constructor parameter names are matched from typed payload keys when possible.

Data Utilities
--------------

.. code-block:: php

    $filtered = $result->filter(fn ($value, $key) => str_starts_with($key, 'user_'));
    $mapped = $result->map(fn ($value) => is_string($value) ? trim($value) : $value);

    $merged = $result->merge($anotherResult);

Fluent Helpers
--------------

.. code-block:: php

    $result
        ->whenPasses(function (array $typedData) {
            // Runs on success
        })
        ->whenFails(function (array $errors) {
            // Runs on failure
        });

    $result->throw(); // throws ValidationException when failed
