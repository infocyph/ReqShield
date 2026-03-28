Basic Usage
===========

This page covers the core validator flow and the most-used runtime options.

Create a Validator
------------------

.. code-block:: php

    use Infocyph\ReqShield\Validator;

    $validator = Validator::make([
        'email' => 'required|email|max:255',
        'username' => 'required|string|min:3|max:50',
        'password' => 'required|string|min:8|confirmed',
    ]);

Rules can be:

* A pipe string (``required|email``)
* An array of strings/rule objects
* A schema definition array with ``rules``, ``sanitize``, ``cast``, ``alias``

.. code-block:: php

    $validator = Validator::make([
        'email' => [
            'rules' => 'required|email',
            'sanitize' => ['trim', 'lowercase'],
            'alias' => 'Email Address',
        ],
        'age' => [
            'rules' => 'required|integer|min:18',
            'cast' => 'integer',
        ],
    ]);

Validate Data
-------------

.. code-block:: php

    $result = $validator->validate($input);

    if ($result->passes()) {
        $data = $result->validated();
    } else {
        $errors = $result->errors();
    }

See :doc:`handling-results` for all result helpers.

Field Aliases
-------------

Use aliases for human-readable messages.

.. code-block:: php

    $validator->setFieldAliases([
        'user_email' => 'Email Address',
        'contacts.*.email' => 'Contact Email',
    ]);

Message Overrides
-----------------

Use ``setCustomMessages()`` to override default rule messages.

.. code-block:: php

    $validator->setCustomMessages([
        'email.required' => ':field is required.',
        '*.min' => ':field must be at least :min.',
        'contacts.*.email.email' => 'Each contact email must be valid.',
    ]);

Message keys can be ``field.rule``, ``field.*``, ``*.rule``, ``field``, ``rule``, and wildcard field paths.

Runtime Behavior
----------------

.. code-block:: php

    $validator
        ->setFailFast(false)         // collect all failing rules per field
        ->setStopOnFirstError(false) // continue all fields
        ->throwOnFailure(false);     // return ValidationResult instead of throwing

Throw on Failure
----------------

.. code-block:: php

    use Infocyph\ReqShield\Exceptions\ValidationException;

    try {
        $validator->throwOnFailure()->validate($input);
    } catch (ValidationException $e) {
        $errors = $e->getErrors();
    }

Conditional Rules
-----------------

Use ``sometimes()`` for field-specific conditional rules.

.. code-block:: php

    $validator->sometimes(
        'vat',
        'required',
        fn (array $data): bool => ($data['type'] ?? null) === 'business',
    );

Use ``when()`` to merge dynamic schemas.

.. code-block:: php

    $validator->when(
        fn (array $data): bool => ($data['country'] ?? null) === 'US',
        fn (): array => ['state' => 'required|string'],
    );

Sanitizers, Casts, DTO
----------------------

.. code-block:: php

    $validator
        ->setSanitizers([
            'email' => ['trim', 'lowercase'],
        ])
        ->setCasts([
            'age' => 'integer',
            'active' => 'boolean',
        ])
        ->setDtoClass(App\DTO\UserInput::class);

    $result = $validator->validate($input);
    $typed = $result->typed();
    $dto = $result->toDTO();

Schema Fragments
----------------

.. code-block:: php

    Validator::defineFragment('address', [
        'line1' => 'required|string|max:120',
        'zip' => 'required|digits:5',
    ]);

    $validator = Validator::make([
        'name' => 'required|string',
    ])->useFragment('address', 'billing');

Next Steps
----------

* See :doc:`quick-usage` for real-life endpoint examples
* See :doc:`advanced-features` for locale packs, schema export, and composition APIs
* See :doc:`rule-reference` for all built-in rules
