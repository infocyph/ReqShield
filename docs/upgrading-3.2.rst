Upgrading to ReqShield 3.2
==========================

ReqShield 3.2 is a compatibility-preserving ownership and persistent-runtime
hardening release. Existing mutable ``Validator`` configuration APIs remain
available. The new registry/profile/compiled APIs are additive.

DBLayer 5.1
-----------

DBLayer remains optional. ReqShield's development/reference floor is now
``infocyph/dblayer:^5.1`` and the package includes
``Infocyph\ReqShield\Bridge\DBLayerDatabaseProvider``.

Persistent runtimes should construct the bridge with an execution-owned
connection resolver:

.. code-block:: php

    $provider = new DBLayerDatabaseProvider(
        static fn (): Connection => $applicationDatabase->connection(),
    );

The resolver runs once per provider operation. ReqShield retains no resolved
execution-scoped connection after that operation. DBLayer 5.1 remains
authoritative for physical bind-limit sizing.

Database Correlation IDs
------------------------

``DatabaseProvider`` correlation IDs are explicitly integers in 3.2. ReqShield
generates dense integer IDs internally and rejects unknown, malformed or
duplicate provider-returned IDs. Custom providers should return only integer
IDs that ReqShield supplied for the current batch.

Validator Profiles
------------------

Use ``ValidatorProfile`` when the same normalized validator configuration is
reused across schemas/requests. Profiles are immutable and sparse, and support
immutable overlay of aliases, messages, sanitizers, casts, locale packs and
limits.

Schema Registry
---------------

``SchemaRegistry`` provides instance-owned named schema topology with an
idempotent ``freeze()`` step. Existing static fragment APIs remain available in
3.x but are now documented as legacy/bootstrap helpers; persistent runtimes
should prefer an instance registry.

Compiled Validators
-------------------

``CompiledValidator`` is now a frozen snapshot of validator topology. Changes
to the source builder after compilation do not change the compiled instance,
and ReqShield mutators reached through callbacks fail closed after freeze.

Custom rules placed in a registry or compiled validator must detach nested
mutable objects in ``__clone()``. Unsafe shared state now throws
``InvalidSchemaException``; ordinary mutable validator construction keeps its
existing cloning behavior. See :doc:`custom-rules` for supported snapshot state
and shared-runtime rule requirements.

Transport-Neutral Exceptions
----------------------------

ReqShield no longer injects HTTP status ``422`` into thrown
``ValidationException`` objects. The default exception code is ``0`` unless a
caller explicitly supplies another code. HTTP status selection belongs to the
application/framework mapper.

If application code previously treated ``ValidationException::getCode()`` as
an HTTP status, migrate that mapping explicitly before upgrading.

Path and Process Security Boundaries
------------------------------------

ReqShield path/upload rules validate syntax and metadata. They do not establish
filesystem containment or storage authorization. Use Pathwise 4.1 for canonical
path/storage trust decisions.

ReqShield also does not blacklist dangerous function names, sanitize arbitrary
shell commands, authorize privileged operations, execute processes or provide a
sandbox. Validate structured operation identifiers/arguments, then let the
application authorize them and a dedicated process/runtime layer execute them.

Compatibility Summary
---------------------

* PHP remains 8.4+.
* DBLayer remains optional.
* Existing ``Validator`` setters remain available.
* Static fragments remain available for 3.x compatibility.
* Existing validation-result projection helpers remain available.
* The observable exception-code change above is intentional and should be
  migrated at the application transport layer.
