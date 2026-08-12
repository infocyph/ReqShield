Advanced Features
=================

Message Overrides and Placeholders
----------------------------------

Use ``setCustomMessages()`` to override rule messages.

Supported key styles:

* ``field.rule`` (exact rule for exact field)
* ``field.*`` (any rule for a field)
* ``*.rule`` (same rule on any field)
* ``field`` (fallback for a field)
* ``rule`` (global fallback by rule name)
* Wildcard field paths (for example ``contacts.*.email.required``)

.. code-block:: php

    $validator->setCustomMessages([
        'email.required' => ':field is required.',
        '*.min' => ':field must be at least :min.',
        'contacts.*.email.email' => 'Each contact email must be valid.',
    ]);

Common placeholders:

* ``:field`` / ``:attribute`` (resolved alias)
* ``:rule``
* ``:value``
* Rule parameters such as ``:min``, ``:max``, ``:size``, ``:other``, ``:values``

Locale Packs
------------

Use locale packs when you want centralized message translation.

.. code-block:: php

    $validator
        ->addLocalePack('es', [
            'required' => 'El campo :field es obligatorio.',
            '*' => 'El campo :field no es valido.',
        ])
        ->setLocale('es');

You can also replace all packs with ``setLocalePacks()``.
Locale fallback supports variants like ``en_US`` to base ``en``.

Sanitize + Validate Pipeline
----------------------------

ReqShield can sanitize automatically before validation.

.. code-block:: php

    $validator = Validator::make([
        'username' => [
            'rules' => 'required|alpha_dash|min:3',
            'sanitize' => ['trim', 'lowercase'],
        ],
        'email' => 'required|email',
    ])->setSanitizers([
        'email' => ['trim', 'lowercase'],
    ]);

Global ``setSanitizers()`` merges with schema-level ``sanitize``/``sanitizers``.
Wildcard pipelines are supported (example: ``contacts.*.email``).

Conditional Rules
-----------------

Use ``sometimes()`` for field-targeted rule activation.

.. code-block:: php

    $validator->sometimes(
        'vat',
        'required',
        fn (array $data): bool => ($data['type'] ?? null) === 'business',
    );

Use ``when()`` to inject dynamic rule arrays.

.. code-block:: php

    $validator->when(
        fn (array $data): bool => ($data['country'] ?? null) === 'US',
        fn (): array => ['state' => 'required|string'],
        fn (): ?array => null, // optional default callback
    );

The callback must return ``array`` or ``null``.

After Validation Hooks
----------------------

Use ``after()`` for cross-field checks that do not fit a single rule.

.. code-block:: php

    use Infocyph\ReqShield\Support\ValidationContext;

    $validator->after(function (ValidationContext $ctx): void {
        if ((string) $ctx->get('start_date') > (string) $ctx->get('end_date')) {
            $ctx->addError('end_date', 'End date must be after start date.');
        }
    });

Enum Rules and Enum Casting
---------------------------

Use enum rules with either string syntax or rule objects.

.. code-block:: php

    use App\Enums\OrderStatus;
    use Infocyph\ReqShield\Rule;

    $validator = Validator::make([
        'status' => 'required|enum:' . OrderStatus::class,
        'state' => ['required', Rule::enum(OrderStatus::class)],
    ])->setCasts([
        'status' => OrderStatus::class,
    ]);

Compiled Validator Wrapper
--------------------------

``Validator::compile()`` returns a reusable validator wrapper.
It keeps one precompiled validator for repeated payloads, avoiding construction
and schema compilation on every call.

.. code-block:: php

    $compiled = Validator::compile([
        'email' => 'required|email',
        'age' => 'required|integer|min:18',
    ]);

    $result = $compiled->validate($input);

Schema Fragments and Composition
--------------------------------

Reuse schema pieces across endpoints.

.. code-block:: php

    Validator::defineFragment('address', [
        'line1' => 'required|string',
        'zip' => 'required|digits:5',
    ]);

    $validator = Validator::make(['name' => 'required|string'])
        ->useFragment('address', 'billing');

Static helpers:

* ``Validator::fragment($name, $prefix = '')``
* ``Validator::hasFragment($name)``
* ``Validator::composeSchemas(...$schemas)``

Typed Output and DTO Mapping
----------------------------

Use ``setCasts()`` to convert validated values and ``setDtoClass()`` for DTO output.

.. code-block:: php

    $validator = Validator::make([
        'age' => 'required|integer',
        'active' => 'required|boolean',
    ])->setCasts([
        'age' => 'integer',
        'active' => 'boolean',
    ])->setDtoClass(App\DTO\UserInput::class);

    $result = $validator->validate($input);
    $typed = $result->typed();
    $dto = $result->toDTO();

Failure Metadata
----------------

For API/UI mapping, use structured failures.

.. code-block:: php

    $result->failures();
    // [
    //   ['field' => 'email', 'rule' => 'email', 'message' => '...', 'value' => 'bad'],
    // ]

    $result->failuresFor('email');

Schema Export and Introspection
-------------------------------

Supported exports:

* ``exportSchema('json_schema')``
* ``exportSchema('openapi')``
* ``exportSchema('introspection')``

Introspection is also available through ``schemaIntrospection()`` and rule stats through ``getSchemaStats()``.
JSON Schema export uses ``x-reqshield-*`` extensions for DNS, database,
cross-field, conditional-presence, and custom date-format semantics that standard
JSON Schema cannot represent exactly. Conditional fields are not incorrectly
listed as unconditionally required.
