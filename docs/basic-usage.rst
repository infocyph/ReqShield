Basic Usage
===========

This guide covers the fundamentals of validating data, handling errors, and customizing the validator.

Creating a Validator
--------------------

The primary way to create a validator is by using the static ``make()`` method on the ``Validator`` class.

.. code-block:: php

    use Infocyph\ReqShield\Validator;

    $validator = Validator::make([
        'email' => 'required|email|max:255',
        'username' => 'required|string|min:3|max:50',
        'password' => 'required|min:8',
        'password_confirmation' => 'required|same:password',
    ]);

Rules are defined as a pipe-separated string. You can also pass an array of rules:

.. code-block:: php

    use Infocyph\ReqShield\Rules\Callback; // For custom rules

    $validator = Validator::make([
        'email' => ['required', 'email', 'max:255'],
        'code' => [
            'required',
            new Callback(
                callback: fn ($value) => $value % 2 === 0,
                message: 'Number must be even'
            ),
        ],
    ]);

Validating Data
---------------

Once you have a validator instance, call the ``validate()`` method with your data.

.. code-block:: php

    $data = [
        'email' => 'john@example.com',
        'username' => 'johndoe',
        'password' => 'secret123',
        'password_confirmation' => 'secret123',
    ];

    $result = $validator->validate($data);

The ``validate()`` method returns a ``ValidationResult`` object, which you can use to check if the validation passed or failed.

See :doc:`handling-results` for more details.

Handling Validation Results
----------------------------

Checking Pass/Fail
~~~~~~~~~~~~~~~~~~

.. code-block:: php

    if ($result->passes()) {
        echo "✓ Validation passed!";
        $validatedData = $result->validated();
    }

    if ($result->fails()) {
        echo "✗ Validation failed!";
        $errors = $result->errors();
    }

Getting Validated Data
~~~~~~~~~~~~~~~~~~~~~~

Only the fields that passed validation are included in the validated data.

.. code-block:: php

    $validatedData = $result->validated();
    // ['email' => 'john@example.com', 'username' => 'johndoe', ...]

Getting Errors
~~~~~~~~~~~~~~

Errors are returned as an array with field names as keys.

.. code-block:: php

    $errors = $result->errors();
    /*
    [
        'email' => ['The email must be a valid email address.'],
        'username' => ['The username must be at least 3 characters.']
    ]
    */

    // Get errors for a specific field
    $emailErrors = $result->errors('email');

    // Get the first error for a field
    $firstError = $result->errors('email')[0] ?? null;

Throwing Exceptions on Failure
-------------------------------

By default, you check the ``ValidationResult`` object. However, you can instruct the validator to throw an exception on failure using ``throwOnFailure()``.

.. code-block:: php

    use Infocyph\ReqShield\Exceptions\ValidationException;
    use Infocyph\ReqShield\Validator;

    $validator = Validator::make([
        'email' => 'required|email',
        'age' => 'required|integer|min:18',
    ])->throwOnFailure();

    try {
        $result = $validator->validate([
            'email' => 'invalid',
            'age' => 15,
        ]);
        // Code here will only run if validation passes
        $validatedData = $result->validated();

    } catch (ValidationException $e) {
        echo "✗ Validation failed:\n";
        
        // Get all errors
        print_r($e->getErrors());
        
        // Get specific error details
        echo 'Error count: ' . $e->getErrorCount() . " field(s)\n";
        echo 'First error for email: ' . $e->getFirstFieldError('email') . "\n";
        
        // Get HTTP status code (default: 422)
        echo 'Status code: ' . $e->getCode() . "\n";
    }

Customizing Field Names
-----------------------

You can provide human-readable names for your fields to make error messages clearer using ``setFieldAliases()``.

.. code-block:: php

    $validator = Validator::make([
        'user_email' => 'required|email',
        'pwd' => 'required|min:8',
        'pwd_confirm' => 'required|same:pwd',
    ]);

    $validator->setFieldAliases([
        'user_email' => 'Email Address',
        'pwd' => 'Password',
        'pwd_confirm' => 'Password Confirmation',
    ]);

    $result = $validator->validate(['user_email' => 'not-an-email']);

    // The error message will now be:
    // "The Email Address must be a valid email address."
    print_r($result->errors()['user_email'][0]);

Batch Field Aliases
~~~~~~~~~~~~~~~~~~~

For setting many aliases at once, use the ``FieldAlias`` utility:

.. code-block:: php

    use Infocyph\ReqShield\Support\FieldAlias;

    FieldAlias::setBatch([
        'user_email' => 'Email Address',
        'user_name' => 'Full Name',
        'pwd' => 'Password',
        'pwd_confirm' => 'Password Confirmation',
    ]);

Custom Error Messages
---------------------

You can override the default error message for specific fields using ``setCustomMessage()``.

.. code-block:: php

    $validator = Validator::make([
        'email' => 'required|email',
        'age' => 'required|integer|min:18',
    ]);

    $validator->setCustomMessage('email', 'Please provide a valid email address.');
    $validator->setCustomMessage('age', 'You must be at least 18 years old.');

    $result = $validator->validate([
        'email' => 'invalid',
        'age' => 15,
    ]);

    // Custom messages will be used
    print_r($result->errors());

Fail-Fast vs. Collect All Errors
---------------------------------

ReqShield is "fail-fast" by default, meaning it stops validating a *single field* as soon as one of its rules fails.

Default Behavior (Fail-Fast per Field)
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

.. code-block:: php

    'email' => 'required|email|max:10'

- If ``email`` is empty, it fails on ``required`` and **stops**.
- It will not check ``email`` or ``max:10`` rules.
- This is fast and provides the most relevant error first.

Stop on First Field Error
~~~~~~~~~~~~~~~~~~~~~~~~~~

Use ``setStopOnFirstError(true)`` to stop validating *all fields* as soon as *any* field fails.

.. code-block:: php

    $validator = Validator::make([
        'field1' => 'required',
        'field2' => 'required',
        'field3' => 'required',
    ])->setStopOnFirstError(true);

    $result = $validator->validate(['field1' => '', 'field2' => '', 'field3' => '']);
    
    // Only the error for 'field1' will be present
    // Validation stops immediately after first field fails

Using the Bail Rule
~~~~~~~~~~~~~~~~~~~~

Add ``bail`` as a rule to stop validation for that field on the first failure. This is the default behavior, but ``bail`` makes it explicit.

.. code-block:: php

    $validator = Validator::make([
        'email' => ['bail', 'required', 'email', 'max:255'],
    ]);

Optional Fields
---------------

Fields are required by default. To make a field optional, use the ``nullable`` rule or omit the ``required`` rule.

.. code-block:: php

    $validator = Validator::make([
        'email' => 'required|email',      // Required
        'phone' => 'nullable|string',     // Optional, can be null
        'bio' => 'string|max:500',        // Optional, validates if present
    ]);

Conditional Validation
----------------------

You can make fields required based on the presence or value of other fields.

.. code-block:: php

    $validator = Validator::make([
        'payment_method' => 'required|in:card,paypal,bank',
        'card_number' => 'required_if:payment_method,card',
        'paypal_email' => 'required_if:payment_method,paypal|email',
        'bank_account' => 'required_if:payment_method,bank',
    ]);

See :doc:`rule-reference` for all conditional validation rules.

Complete Example
----------------

Here's a complete example putting it all together:

.. code-block:: php

    <?php

    use Infocyph\ReqShield\Validator;
    use Infocyph\ReqShield\Sanitizer;
    use Infocyph\ReqShield\Exceptions\ValidationException;

    // Step 1: Sanitize input
    $rawInput = [
        'email' => '  john@EXAMPLE.com  ',
        'username' => '  john_doe!  ',
        'age' => '25.5',
        'password' => 'secret123',
        'password_confirmation' => 'secret123',
    ];

    $cleanInput = [
        'email' => Sanitizer::email($rawInput['email']),
        'username' => Sanitizer::alphaDash($rawInput['username']),
        'age' => Sanitizer::integer($rawInput['age']),
        'password' => $rawInput['password'],
        'password_confirmation' => $rawInput['password_confirmation'],
    ];

    // Step 2: Create validator
    $validator = Validator::make([
        'email' => 'required|email|max:255',
        'username' => 'required|alpha_dash|min:3|max:50',
        'age' => 'required|integer|min:18|max:120',
        'password' => 'required|min:8',
        'password_confirmation' => 'required|same:password',
    ]);

    // Step 3: Set field aliases
    $validator->setFieldAliases([
        'email' => 'Email Address',
        'username' => 'Username',
        'age' => 'Age',
        'password' => 'Password',
        'password_confirmation' => 'Password Confirmation',
    ]);

    // Step 4: Validate
    $result = $validator->validate($cleanInput);

    // Step 5: Handle result
    if ($result->passes()) {
        $validatedData = $result->validated();
        echo "✓ Registration successful!";
        // Process registration...
    } else {
        $errors = $result->errors();
        echo "✗ Validation failed:\n";
        foreach ($errors as $field => $messages) {
            foreach ($messages as $message) {
                echo "  - {$message}\n";
            }
        }
    }

Performance Optimization
------------------------

ReqShield is optimized for speed with several built-in features:

Rule Cost Optimization
~~~~~~~~~~~~~~~~~~~~~~

Rules are automatically sorted by cost (complexity) and executed in optimal order:

- **Cheap rules** (cost < 50): Type checks, empty checks - run first
- **Medium rules** (cost 50-99): String operations, regex - run second
- **Expensive rules** (cost 100+): Database queries, API calls - batched and run last

Batch Database Operations
~~~~~~~~~~~~~~~~~~~~~~~~~

Database rules (``exists``, ``unique``) are automatically batched to reduce query count:

.. code-block:: php

    $validator = Validator::make([
        'user_id' => 'exists:users,id',
        'email' => 'unique:users,email',
        'category_id' => 'exists:categories,id',
    ]);

    // Instead of 3 separate queries, ReqShield batches them into 2 queries
    // (one for exists checks, one for unique checks)

Stop on First Error
~~~~~~~~~~~~~~~~~~~

Use ``setStopOnFirstError(true)`` for maximum performance when you only need to know if validation fails:

.. code-block:: php

    $validator->setStopOnFirstError(true);
    // Stops validation immediately on first error

Nested Field Optimization
~~~~~~~~~~~~~~~~~~~~~~~~~~

Nested validation automatically detects if nested rules are present and only flattens data when needed:

.. code-block:: php

    $validator = Validator::make([
        'user.email' => 'required|email',
        'user.name' => 'required',
    ])->enableNestedValidation();
    
    // Data is automatically flattened only if needed

Next Steps
----------

- Learn about all available rules in :doc:`rule-reference`
- Explore sanitization options in :doc:`sanitization`
- Create custom validation rules in :doc:`custom-rules`
- Handle validation results in :doc:`handling-results`
- Work with nested data in :doc:`nested-validation`
