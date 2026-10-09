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
  Wildcard member keys containing dots, commas or pipes are rejected:
  those delimiters cannot safely represent a captured dependency path.
* Strict unknown fields include integer root keys, and resource limits
  are enforced after sanitization expansion.
  Unknown-field policies also inspect the effective sanitized structure;
  decoding a JSON field cannot introduce undeclared safe-output fields.
* Safe validated parent arrays no longer retain unknown, excluded, or
  invalid descendants. Applications that relied on reading those values
  from ``validated()`` must explicitly use unvalidated input when needed.
* Image rules refuse HTTP/HTTPS paths, stream wrappers, invalid upload
  status, and malformed upload metadata. Seekable streams use bounded
  reads with cursor restoration; filesystem authorization remains the
  responsibility of storage policy.

Database and Rule Semantics
---------------------------

DBLayer **6.0** is the minimum supported optional native integration.
DBLayer 5.x and ArrayKit below 5.3 are no longer supported and are
rejected by Composer; there is **no legacy adapter or fallback**. A
non-database application does not need to install DBLayer.
Batching preserves individual bound SQL values and the database engine's
comparison semantics rather than coercing all candidates to one SQL type.
Queries can use additional parameters and smaller chunks to respect
DBLayer security limits. Do not assume the previous physical query count
remains unchanged.

Integral floats outside the native signed integer range are rejected.
Compiled composite secure-file rules detach nested mutable rule objects;
JSON Schema exports retain terminal wildcard item types.

Slug normalization now handles libiconv's generated accent punctuation while
preserving caller punctuation. For example, ``Café déjà vu`` becomes
``cafe-deja-vu`` with a supported transliterator. Intl remains optional;
unsupported iconv implementations retain the documented fallback. See
:doc:`sanitization`.

Optional Host-Owned Runwire
---------------------------

Install ``infocyph/runwire:2.1.1`` only if the application's host uses
Runwire. The additive method

``validateWithRunwire($data, $runtime, $request, $scope)``

accepts existing host-owned objects on ``Validator`` and
``CompiledValidator``. DBLayer 6's ``withRunwire()`` binding is
temporary and restored after the complete logical batch. A cancelled
request propagates cancellation rather than producing a validation error.
Execution guards surround sanitizer, condition, rule, after-callback and
cast invocations. Cancellation or deadline expiry prevents later callbacks
and successful result delivery. A database exception raised while the host
is cancelled propagates host cancellation; other database failures retain
the sanitized database exception boundary.
ReqShield does not own application workers, global runtime bindings or
database connections. See :doc:`runwire-integration`.

Before Release
--------------

Validate strict-field behavior, safe results, SQL type parity, custom
mutable-rule cloning, and host cancellation paths in your application.
Use :doc:`benchmark` for the production-host
throughput, engine matrix, soak and final-commit acceptance requirements.
