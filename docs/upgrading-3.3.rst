Upgrading to ReqShield 3.3
===========================

ReqShield 3.3 combines the security/correctness repairs from audit findings
R01–R11 with optional passed-instance Runwire 2.1.1 integration. The normal
``validate(array $data)`` method, user-defined rule APIs, provider
interface and PHP 8.4+ minimum remain compatible.

Input and Safe Output
---------------------

* Distinct input shapes cannot reuse one wildcard plan signature.
* Conflicting dotted and nested representations fail closed.
* Associative wildcard dependencies and distinct groups use schema
  capture positions rather than numeric-key assumptions.
* Strict unknown fields include integer root keys, and resource limits
  are enforced after sanitization expansion.
* Safe validated parent arrays no longer retain unknown, excluded, or
  invalid descendants. Applications that relied on reading those values
  from ``validated()`` must explicitly use unvalidated input when needed.
* Image rules refuse HTTP/HTTPS paths, stream wrappers, invalid upload
  status, and malformed upload metadata. Seekable streams use bounded
  reads with cursor restoration; filesystem authorization remains the
  responsibility of storage policy.

Database and Rule Semantics
---------------------------

DBLayer **6.0** is the current optional reference integration. The
existing DBLayer 5.1 provider still works with ordinary ``validate()``.
Batching preserves individual bound SQL values and the database engine's
comparison semantics rather than coercing all candidates to one SQL type.
Queries can use additional parameters and smaller chunks to respect
DBLayer security limits. Do not assume the previous physical query count
remains unchanged.

Integral floats outside the native signed integer range are rejected.
Compiled composite secure-file rules detach nested mutable rule objects;
JSON Schema exports retain terminal wildcard item types.

Optional Host-Owned Runwire
---------------------------

Install ``infocyph/runwire:2.1.1`` only if the application's host uses
Runwire. The additive method

``validateWithRunwire($data, $runtime, $request, $scope)``

accepts existing host-owned objects on ``Validator`` and
``CompiledValidator``. DBLayer 6's ``withRunwire()`` binding is
temporary and restored after the complete logical batch. A cancelled
request propagates cancellation rather than producing a validation error.
ReqShield does not own application workers, global runtime bindings or
database connections. See :doc:`runwire-integration`.

Before Release
--------------

Validate strict-field behavior, safe results, SQL type parity, custom
mutable-rule cloning, and host cancellation paths in your application.
Use :doc:`release-3.3-verification` for the remaining production-host
throughput, engine matrix, soak and final-commit acceptance requirements.
