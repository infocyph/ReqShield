Nested Validation
=================

ReqShield supports nested arrays with dot notation and wildcard rules.
Enable this with ``enableNestedValidation()``.

Enable Nested Validation
------------------------

.. code-block:: php

    $validator = Validator::make([
        // Dot/wildcard rules
    ])->enableNestedValidation();

Flattening modes:

* ``enableNestedValidation()`` or ``enableNestedValidation(true)``: flatten all paths.
* ``enableNestedValidation(false)``: flatten only required paths (lower memory for large payloads).
* ``setNestedFlattenMode('all'|'required')``: explicit control.

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

    $validator = Validator::make([
        'user.email' => 'required|email',
        'user.name' => 'required|min:3',
        'user.profile.age' => 'required|integer|min:18',
        'user.profile.bio' => 'string|max:500',
    ])->enableNestedValidation();

    $result = $validator->validate($nestedData);

    if ($result->passes()) {
        echo "✓ Nested validation works!\n";

        // Validated data uses dot keys for nested paths
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

    $result = $validator->validate($invalidNested);

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

Wildcard Array Validation
-------------------------

Use ``*`` to validate each item in indexed arrays.

.. code-block:: php

    $validator = Validator::make([
        'contacts.*.email' => 'required|email',
        'contacts.*.name' => 'required|min:2',
    ])->enableNestedValidation();

    $data = [
        'contacts' => [
            ['email' => 'john@example.com', 'name' => 'John'],
            ['email' => 'jane@example.com', 'name' => 'Jane'],
        ],
    ];

    $result = $validator->validate($data);

ReqShield expands wildcard rules into concrete indexed fields (for example ``contacts.0.email``).

If a specific item fails, the error key includes its index:

.. code-block:: php

    $invalid = [
        'contacts' => [
            ['email' => 'bad-email', 'name' => 'A'],
        ],
    ];

    $result = $validator->validate($invalid);
    $errors = $result->errors();
    /*
    [
        'contacts.0.email' => ['The contacts.0.email must be a valid email address.'],
        'contacts.0.name' => ['The contacts.0.name must be at least 2.']
    ]
    */

Wildcard Aliases and Messages
-----------------------------

Wildcard aliases and custom messages also work for nested arrays.

.. code-block:: php

    $validator = Validator::make([
        'contacts.*.email' => 'required|email',
    ])->enableNestedValidation()
      ->setFieldAliases([
          'contacts.*.email' => 'Contact Email',
      ])
      ->setCustomMessages([
          'contacts.*.email.email' => 'Each :field must be valid.',
      ]);
