Nested Validation
=================

ReqShield supports validating nested array data using dot notation. To enable this feature, you must chain the ``enableNestedValidation()`` method when creating your validator.

Enabling Nested Validation
--------------------------

.. code-block:: php

    $nestedValidator = Validator::make([
        // Rules using dot notation
    ])->enableNestedValidation();

When enabled, ReqShield will automatically flatten your input data to match the dot-notation keys in your rules.

Example
-------

Let's say you have the following data structure:

.. code-block:: php

    $nestedData = [
        'user' => [
            'email' => 'nested@example.com',
            'name' => 'John Doe',
            'profile' => [
                'age' => 25,
                'bio' => 'Software developer',
            ],
        ],
    ];

You can define rules to validate this structure like so:

.. code-block:: php

    $nestedValidator = Validator::make([
        'user.email' => 'required|email',
        'user.name' => 'required|min:3',
        'user.profile.age' => 'required|integer|min:18',
        'user.profile.bio' => 'string|max:500', // Optional field
    ])->enableNestedValidation();

    $result = $nestedValidator->validate($nestedData);

    if ($result->passes()) {
        echo "✓ Nested validation works!\n";

        // The validated data will also be in a flat, dot-key array
        $validated = $result->validated();
        /*
        [
            'user.email' => 'nested@example.com',
            'user.name' => 'John Doe',
            'user.profile.age' => 25,
            'user.profile.bio' => 'Software developer'
        ]
        */
    }

Validation Failures
~~~~~~~~~~~~~~~~~~~

ReqShield correctly handles errors for nested fields, including when they are missing.

.. code-block:: php

    $invalidNested = [
        'user' => [
            'email' => 'not-an-email',
            'name' => 'Jo', // Fails min:3
            // 'profile' key is missing
        ],
    ];

    $result = $nestedValidator->validate($invalidNested);

    if ($result->fails()) {
        echo "✗ Nested validation catches errors:\n";
        $errors = $result->errors();
        /*
        [
            'user.email' => ['The user.email must be a valid email address.'],
            'user.name' => ['The user.name must be at least 3.'],
            'user.profile.age' => ['The user.profile.age field is required.']
        ]
        */
    }
