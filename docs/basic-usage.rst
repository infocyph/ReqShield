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

Handling Failures
-----------------

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
        // $e->getErrors() returns the array of errors
        print_r($e->getErrors());

        // Get specific error details
        echo 'Error count: '.$e->getErrorCount()." field(s)\n";
        echo 'First error for email: '.$e->getFirstFieldError('email')."\n";
    }

Customizing Error Messages
--------------------------

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

    // The error message will be:
    // "The Email Address must be a valid email address."
    print_r($result->errors()['user_email'][0]);

Fail-Fast vs. Bail
------------------

ReqShield is "fail-fast" by default, meaning it stops validating a *single field* as soon as one of its rules fails.

* ``'email' => 'required|email|max:10'``
    * If ``email`` is empty, it fails on ``required`` and **stops**. It will not check ``email`` or ``max:10``.
    * This is fast and provides the most relevant error first.

You can control this behavior in two ways:

1.  **``setStopOnFirstError(true)``**: This tells the validator to stop validating *all fields* as soon as *any* field fails. This is useful for performance when you only care about the first error.

    .. code-block:: php

        $validator->setStopOnFirstError(true);
        $result = $validator->validate(['field1' => '', 'field2' => '']);
        // $result->errors() will only contain the error for 'field1'

2.  **``bail`` Rule**: Add ``bail`` as the *first* rule in a chain to stop validation *for that field* on the first failure. This is the default behavior, but the ``bail`` rule makes it explicit and allows for more control if you want to change the default.

    .. code-block:: php

        // 'bail' ensures validation stops at the first error for this field
        $rules = ['email' => ['bail', 'required', 'email', 'max:255']];
