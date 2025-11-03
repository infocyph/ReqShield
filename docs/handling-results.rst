Handling the Validation Result
==============================

The ``validate()`` method returns a ``ValidationResult`` object, which provides a fluent API for inspecting the outcome.

.. code-block:: php

    $result = $validator->validate($data);

Checking Success or Failure
---------------------------

You can easily check if the validation passed or failed.

.. code-block:: php

    if ($result->passes()) {
        // Validation was successful
    }

    if ($result->fails()) {
        // Validation failed
    }

Retrieving Validated Data
-------------------------

When validation passes, you can get the array of validated data. This array only includes fields that were defined in your validation rules, providing a "safe" list of data.

.. code-block:: php

    $validatedData = $result->validated();

    // Example:
    // $data = ['email' => 'test@ex.com', 'extra' => 'ignored']
    // $rules = ['email' => 'required|email']
    // $result->validated() will be ['email' => 'test@ex.com']

You can also retrieve specific subsets of the validated data:

.. code-block:: php

    // Get only the 'email' and 'name' fields
    $subset = $result->only(['email', 'name']);

    // Get all validated data *except* 'age'
    $subset = $result->except(['age']);

    // Check if a validated key exists
    if ($result->has('email')) {
        // ...
    }

Retrieving Errors
-----------------

When validation fails, you can get the full array of error messages.

.. code-block:: php

    $errors = $result->errors();
    /*
    [
        'email' => [
            'The email must be a valid email address.'
        ],
        'age' => [
            'The age must be at least 18.'
        ]
    ]
    */

You can also get the first error for a specific field or the very first error in the list:

.. code-block:: php

    // Get the first error for the 'email' field
    $firstEmailError = $result->first('email');

    // Get the very first error message for any field
    $firstError = $result->firstError();

    // Check if a specific field has an error
    if ($result->hasError('age')) {
        // ...
    }

Fluent Callbacks
----------------

You can chain ``whenPasses()`` and ``whenFails()`` to execute callbacks based on the result.

.. code-block:: php

    $result
        ->whenPasses(function ($validatedData) {
            // Runs only if validation passed
            echo "✓ Success!";
            // $validatedData is passed to the callback
        })
        ->whenFails(function ($errors) {
            // Runs only if validation failed
            echo "✗ Failed!";
            // $errors array is passed to the callback
        });
