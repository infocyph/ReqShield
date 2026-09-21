Custom Rules
============

ReqShield provides two ways to add your own validation logic:
1.  **Simple (inline) with ``Callback``**: For quick, one-off rules.
2.  **Advanced (reusable) with ``Rule``**: For complex, reusable rules.

1. Simple Callbacks
-------------------

For simple, single-use rules, pass ``Infocyph\ReqShield\Rules\Callback`` in your rule list.

The ``Callback`` constructor accepts:
* ``callback``: callable signature ``fn (mixed $value, string $field, array $data): bool``.
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
                callback: fn ($value, $field, $data) => preg_match('/^[A-Z]{3}-\d{4}$/', $value),
                cost: 20,
                message: 'Code must be in format ABC-1234'
            ),
        ],
        'even_number' => [
            'required',
            'integer',
            // Example: Must be an even number
            new Callback(
                callback: fn ($value, $field, $data) => $value % 2 === 0,
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

For reusable logic, implement ``Infocyph\ReqShield\Contracts\Rule`` (or extend ``BaseRule``).

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

Register a Custom Rule Name
---------------------------

If you want to use a custom string token (for example ``even``), provide the
name-to-class map when the validator is constructed. Registration happens before
schema compilation, so an unknown token can never be hidden by construction order:

.. code-block:: php

    use App\Rules\IsEven;
    use Infocyph\ReqShield\Validator;

    $validator = Validator::make(
        ['number' => 'required|even'],
        null,
        ['even' => IsEven::class],
    );

    $result = $validator->validate(['number' => 8]);


Rule Snapshots in Registries and Compiled Validators
----------------------------------------------------

``SchemaRegistry`` and ``CompiledValidator`` require independent rule snapshots.
A custom rule containing nested mutable objects must implement ``__clone()``
to detach every mutable descendant. For example, when ``$config`` is a simple
object with scalar properties, the rule can define:

.. code-block:: php

    public function __clone(): void
    {
        $this->config = clone $this->config;
    }

If that configuration contains further mutable objects, clone those too.
ReqShield verifies that the resulting graph shares no mutable objects with the
source rule. A shallow clone that retains such objects throws
``InvalidSchemaException`` during registration, retrieval or compilation.
Private/inherited properties and nested arrays are included in this check.

Rule state containing PHP reference cells, resources, opaque internal containers
(such as ``SplObjectStorage``), or nesting deeper than 64 levels is rejected.
Use ordinary inspectable value/configuration objects for snapshot topology.
Enum cases and plain ``DateTimeImmutable`` values may be shared. Closures retain
the existing caller-owned-state contract: captured objects and references are
not frozen or copied by ReqShield.

This stricter snapshot check applies to registries and compiled validators.
Ordinary mutable ``Validator`` construction retains its existing clone behavior.
A custom rule used in a shared compiled validator must keep ``passes()`` and
``message()`` free of request-specific mutable state; freezing topology does not
make arbitrary application rule code reentrant.
