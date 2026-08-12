Nested and Wildcard Validation
==============================

Dot paths and wildcards are detected automatically. No enable call is required,
and optimized targeted traversal is the default.

.. code-block:: php

    $validator = Validator::make([
        'user.email' => 'required|email',
        'groups.*.members.*.email' => 'required|email',
    ]);

    $result = $validator->validate($data);

Errors use concrete indexed paths such as
``groups.0.members.2.email``. Multiple wildcards, wildcard aliases, custom
messages, sanitizers, casts, and database rules share the same expansion engine.

Traversal Modes
---------------

``targeted`` is the default and extracts only validation targets and their
compiled dependency paths. The old ``required`` value is accepted as an alias.
Use ``setNestedFlattenMode('all')`` only when every flattened input path is
needed. ``enableNestedValidation()`` remains as a traversal-mode convenience;
it is not needed to activate nested behavior.

Resource Limits
---------------

Nested traversal and wildcard expansion are bounded. Generous defaults protect
long-running workers from pathological input amplification. Override them for a
known workload:

.. code-block:: php

    $validator->limits(
        maxDepth: 32,
        maxFields: 10_000,
        maxWildcardExpansions: 10_000,
        maxFlattenedPaths: 10_000,
    );

Crossing a limit throws ``InputLimitException``. Compiled and wildcard-plan
caches are bounded and scoped so closure or mutable-object schemas are not put
in the process-wide pure-schema cache.
