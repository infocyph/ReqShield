Compiled Validators
===================

``CompiledValidator`` is the frozen reusable execution form of a configured
ReqShield ``Validator``. Compilation snapshots validator topology once; each
``validate()`` call reuses that frozen snapshot rather than cloning the entire
validator per request.

Snapshot Semantics
------------------

.. code-block:: php

    use Infocyph\ReqShield\CompiledValidator;
    use Infocyph\ReqShield\Validator;

    $builder = Validator::make([
        'email' => 'required|email',
    ])->setFailFast(false);

    $compiled = new CompiledValidator($builder);

    // Later builder changes do not affect the compiled snapshot.
    $builder->setFailFast(true);

    $result = $compiled->validate([
        'email' => 'ada@example.com',
    ]);

``Validator::compile()`` remains the direct convenience path when no separate
configuration phase is required.

Frozen Topology
---------------

The snapshot is frozen after cloning. ReqShield configuration/topology mutators
such as aliases, messages, sanitizers, casts, locale configuration, validation
limits, conditional registrations, fragments and unknown-field policy reject
mutation with ``FrozenValidatorException`` when reached through a compiled
validator callback.

Validation itself remains allowed to update bounded internal metadata caches.
Those caches contain schema/shape execution plans and callable metadata, not
request scalar/object payloads.

Persistent Runtime Reuse
------------------------

A single compiled validator instance is designed for sequential reuse and for
interleaved Fiber execution. Per-validation errors, validated values and
expensive-rule batches are method-local. Wildcard/conditional plan caches are
bounded and keyed by reusable schema/shape metadata.

Custom rules must be stateless during validation to support shared sequential
and Fiber execution. Compilation checks rule snapshots as described in
:doc:`custom-rules`; a shallow clone that shares nested mutable state is rejected.

ReqShield does not freeze caller-owned state captured by rule, sanitizer, condition
or after-validation callables. Mutating external state inside those callables
remains the caller's responsibility.

Database providers are snapshotted by reference because provider lifetime and
execution-scoped connection resolution belong to the provider/caller contract.
For persistent runtimes, prefer the resolver-first DBLayer bridge documented in
:doc:`database-rules`.
