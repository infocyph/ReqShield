Security and Runtime Boundaries
===============================

ReqShield validates and transforms application input. It is not a PHP/process
sandbox, command runner, authorization layer, filesystem trust engine, malware
scanner, or operating-system policy layer.

Process and Capability Inputs
-----------------------------

Ordinary strings are ordinary strings. ReqShield does not globally blacklist
names such as ``exec``, ``system``, ``shell_exec``, ``proc_open``,
``pcntl_fork`` or ``posix_kill``. A blacklist would create false security and
would also corrupt legitimate text fields.

Applications that accept a privileged operation identifier should validate the
identifier against their registered operation names, then perform capability
authorization and execution outside ReqShield:

.. code-block:: php

    $result = Validator::make([
        'operation' => 'required|in:deploy,restart',
    ])->validate($input);

    // Application / Runwire layer:
    // 1. resolve the registered operation;
    // 2. authorize the caller;
    // 3. execute through the runtime capability boundary.

Validation does not resolve an executable, inspect source code, spawn a process
or prove that execution is safe.

Filesystem and Upload Trust
---------------------------

ReqShield rules such as ``path``, ``safe_filename``, ``upload_id``,
``upload_meta`` and ``secure_file`` validate syntax, payload shape, upload
metadata and rule-specific file properties. Passing those rules is not a
filesystem authorization or containment decision.

**Pathwise 4.1** owns canonical path resolution/containment, trusted storage
roots, filesystem publication trust and filesystem authorization. Application
or storage policy owns malware/quarantine policy. ReqShield intentionally has
no Pathwise dependency.

A syntactically valid path may therefore pass ReqShield even when it does not
exist or is not authorized for a particular storage operation. Resolve that
decision at the Pathwise/application boundary immediately before filesystem I/O.

Transport Boundary
------------------

ReqShield ``ValidationException`` is transport-neutral. Validation failures do
not assign an HTTP status code. Frameworks/applications decide whether a
validation failure maps to HTTP 400, 409, 422 or another transport outcome.

``ValidationResult::toJsonApiErrors()`` and ``toProblemJson()`` are presentation
helpers. Their defaults preserve existing web-oriented output, but callers can
select the status they expose. The exception/runtime layer itself does not own
HTTP policy.

Persistent Runtimes
-------------------

Under Runwire or another persistent runtime, use frozen schema/validator
topology, execution-scoped database resolvers and application-owned capability
authorization. ReqShield must not become a substitute for Runwire process
isolation or Pathwise filesystem trust.
