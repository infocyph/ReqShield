Custom Rules
============

ReqShield provides two ways to add your own validation logic:
1.  **Simple (inline) with ``Callback``**: For quick, one-off rules.
2.  **Advanced (reusable) with ``Rule``**: For complex, reusable rules.

1. Simple Callbacks
-------------------

For simple, single-use rules, you can pass an instance of ``Infocyph\ReqShield\Rules\Callback`` directly into your rule array.

The ``Callback`` constructor accepts:
* ``callback``: A ``callable`` (like a closure) that receives ``($value)`` and returns ``true`` (pass) or ``false`` (fail).
* ``cost`` (optional): An integer cost. Keep it low (e.g., ``20``) unless it's a slow operation.
* ``message`` (optional): A custom error message.

.. code-block:: php

    use Infocyph\ReqShield\Rules\Callback;
    use Infocyph\ReqShield\Validator;

    $validator = Validator::make([
        'code' => [
            'required',
            // Example: Must be a specific format
            new Callback(
                callback: fn ($value) => preg_match('/^[A-Z]{3}-\d{4}$/', $value),
                cost: 20,
                message: 'Code must be in format ABC-1234'
            ),
        ],
        'even_number' => [
            'required',
            'integer',
            // Example: Must be an even number
            new Callback(
                callback: fn ($value) => $value % 2 === 0,
                cost: 5,
                message: 'Number must be even'
            ),
        ],
    ]);

    $result = $validator->validate([
        'code' => 'ABC-1234',
        'even_number' => 42,
    ]);
    // $result->passes() will be true

2. Reusable Rule Classes
------------------------

For complex logic or rules you want to reuse, you can create your own class that implements the ``Infocyph\ReqShield\Contracts\Rule`` interface.

.. code-block:: php

    <?php
    namespace MyApp\Rules;

    use Infocyph\ReqShield\Contracts\Rule;
    use Infocyph\ReqShield\Rules\BaseRule; // (Optional) for helper methods

    class IsEven extends BaseRule implements Rule
    {
        /**
         * Set the performance cost.
         * 1-49 (cheap), 50-99 (medium), 100+ (expensive)
         */
        public function cost(): int {
            return 5; // This is a very cheap check
        }

        /**
         * The validation logic.
         * Return true if it passes, false if it fails.
         */
        public function passes(mixed $value, string $field, array $data): bool {
            return is_numeric($value) && $value % 2 === 0;
        }

        /**
         * The error message if validation fails.
         */
        public function message(string $field): string {
            return "The {$field} field must be an even number.";
        }
    }

Using Your Reusable Rule
~~~~~~~~~~~~~~~~~~~~~~~~

You can use your new rule class just like the ``Callback`` rule.

.. code-block:: php

    use MyApp\Rules\IsEven;

    $validator = Validator::make([
        'even_number' => ['required', 'integer', new IsEven()],
    ]);

    $result = $validator->validate(['even_number' => 42]); // Passes
    $result2 = $validator->validate(['even_number' => 7]); // Fails
