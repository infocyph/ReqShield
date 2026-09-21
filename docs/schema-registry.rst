Schema Registry
===============

``Infocyph\ReqShield\Schema\SchemaRegistry`` provides instance-owned named
schema topology for applications and persistent runtimes. The registry contains
schema definitions only; it does not store request data, validation results,
database connections, container state, or application configuration sources.

Build and Freeze
----------------

Build topology during application/bootstrap composition, then freeze it before
serving requests or jobs:

.. code-block:: php

    use Infocyph\ReqShield\Schema\SchemaRegistry;

    $schemas = new SchemaRegistry([
        'users.store' => [
            'email' => 'required|email',
        ],
    ]);

    $schemas->extend('users.store', [
        'name' => 'required|string|min:2',
    ]);

    $schemas->freeze();

    $rules = $schemas->get('users.store');

``freeze()`` is idempotent. After freezing, ``define()``, ``extend()``,
``replace()`` and ``remove()`` throw ``FrozenSchemaRegistryException``.
Reads remain available through ``get()``, ``schema()``, ``has()`` and
``all()``.

Mutation Semantics
------------------

* ``define()`` requires a new name and rejects duplicates.
* ``replace()`` requires an existing name.
* ``remove()`` requires an existing name.
* ``extend()`` composes fields with ReqShield's normal
  ``Validator::composeSchemas()`` semantics and may create a missing schema.
* Returned PHP arrays are values; mutating a returned array does not mutate the
  registry's stored topology.
* Rule objects are cloned on registration and retrieval. Nested mutable state
  must be detached by the rule's ``__clone()`` method; unsafe snapshots throw
  ``InvalidSchemaException``. See :doc:`custom-rules`.

Persistent Runtime Boundary
---------------------------

A registry belongs to one application/generation instance. Do not place
request/job-specific values into it. Separate registries do not share state and
a frozen registry is safe for repeated/Fiber-interleaved reads.

Legacy Static Fragments
-----------------------

The existing ``Validator::defineFragment()``, ``fragment()``,
``useFragment()`` and ``clearFragments()`` APIs remain available for
ReqShield 3.x compatibility. They use process-global static topology and should
be treated as legacy bootstrap helpers. New framework and persistent-runtime
integrations should prefer an instance-owned ``SchemaRegistry`` and freeze it
when topology construction finishes.
