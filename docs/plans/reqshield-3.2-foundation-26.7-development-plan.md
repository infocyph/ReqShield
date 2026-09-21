# ReqShield 3.2 — Foundation 26.7 Extraction, DBLayer 5.1 & Persistent-Runtime Development Plan

**Status:** implementation plan  
**Target:** ReqShield 3.2  
**Branch:** `reqshield-3.2/foundation-26.7`  
**Foundation consumer:** `infocyph/Foundation` Point 26.7  
**Priority:** correctness → runtime ownership/isolation → security bounds → performance → ergonomics

> ReqShield remains the validation/sanitization/schema/rule engine. DBLayer remains the database runtime/query owner. Foundation remains the application composition, schema-selection, connection-selection, Webrick adaptation and HTTP error-mapping owner. This pass removes generic ReqShield↔DBLayer mechanics from Foundation without moving application policy into ReqShield.

---

## 1. Baseline

### 1.1 Whole-codebase audit basis and version decision

This plan was revalidated against the complete ReqShield source/test tree on `reqshield-3.2/foundation-26.7` and the Foundation 3 validation, configuration, auth/OAuth, database, runtime, worker and security integration surfaces on `foundation-3/close-26.6`.

The audit found four generic mechanics that should move further down into ReqShield:

1. the production ReqShield↔DBLayer database-rule bridge;
2. named schema registry/composition/freeze mechanics;
3. generic validation-profile normalization/application currently repeated in Foundation `ValidatorFactory`;
4. a real frozen/reentrant compiled-validator execution boundary for persistent runtimes.

It also found one lower-layer ownership leak inside ReqShield itself: `Validator::throwIfValidationShouldFail()` currently injects exception code `422`, even though Foundation already owns HTTP 422 mapping.

**Version decision: keep the target at ReqShield 3.2.** A major release is not required if this work remains additive/compatibility-preserving:

- keep existing mutable `Validator` configuration APIs available;
- keep static fragment APIs for 3.x compatibility, while documenting/deprecating them as legacy bootstrap helpers;
- make compiled execution a frozen snapshot without removing existing setters from ordinary validators;
- keep `ValidationException` constructor compatibility while removing automatic HTTP-status ownership from the validator runtime;
- keep existing result projection helpers compatible, adding caller-controlled status where useful rather than removing them;
- narrow database correlation IDs in documentation/static contracts to the integer behavior already enforced at runtime.

A ReqShield 4.0 should only be considered later if we choose to remove static fragment APIs, remove/rename transport-oriented result helpers, make all validators immutable, or otherwise break the established 3.x public surface.

### 1.2 Batch execution tracker

Implementation proceeds in bounded batches. Update this tracker in the same development pass as the code so the plan remains authoritative.

| Batch | Scope | Status |
| --- | --- | --- |
| 1 | Baseline + DBLayer 5.1 floor + integer correlation contract | **complete — PR run #43 green** |
| 2 | Production native DBLayer 5.1 provider + resolver lifetime + DB regression matrix | **implementation complete / QA pending** |
| 3 | Instance-owned freezeable `SchemaRegistry` + static-fragment compatibility boundary | **next** |
| 4 | Immutable `ValidatorProfile` + Foundation profile-parity semantics | open |
| 5 | Frozen/reentrant `CompiledValidator` + cache/state isolation | open |
| 6 | Transport-neutral exception cleanup + Pathwise 4.1 / Runwire trust-boundary closure | open |
| 7 | Documentation + benchmarks + PHP 8.4/8.5 stable/lowest QA + ReqShield 3.2 release gate | open |
| 8 | Foundation 26.7 migration: consume 3.2 and delete duplicate DB/schema/profile mechanics | open |

#### Batch 1 — baseline and correlation contract

- [X] Raise `require-dev["infocyph/dblayer"]` from `^5.0` to `^5.1`.
- [X] Keep DBLayer optional: no production `require` dependency introduced.
- [X] Make `DatabaseProvider` check correlation IDs and returned IDs explicitly integer in the public/static contract.
- [X] Generate dense integer correlation IDs inside `BatchExecutor` instead of inheriting caller batch keys.
- [X] Fail closed on unknown/non-integer provider IDs.
- [X] Fail closed on duplicate provider IDs.
- [X] Align mock/reference provider fixtures and direct DBLayer tests with integer correlation IDs.
- [X] Update database-rule documentation to DBLayer 5.1 reference semantics.
- [X] Normalize the ecosystem filesystem-trust boundary to **Pathwise 4.1**; ReqShield does not acquire a Pathwise dependency.
- [X] Full PR Security & Standards matrix passed on PHP 8.4/8.5 stable/lowest in PR run #43 after clearing PHPStan, skip-directive, reference-integrity and Rector failures.

**Batch 1 status:** COMPLETE — PR run #43 is green across clean install, PHP 8.4/8.5 QA/analysis, stable/lowest and benchmarks.

Current released ReqShield baseline:

- ReqShield: `3.1`
- PHP: `^8.4`
- DBLayer development/reference integration: `^5.1` on this development branch
- DBLayer current Foundation baseline: `^5.1`

ReqShield 3.1 already owns:

- rule parsing/compilation/execution;
- sanitization and casting;
- nested/wildcard validation;
- validation limits and fail-fast behavior;
- structured validation results/failures;
- schema composition and JSON-schema export;
- bounded validation-plan caching;
- logical batching of `exists` / `unique` rules;
- a mature DBLayer reference provider in the test suite.

Foundation currently owns a production `ReqShieldDatabaseProvider` even though the mechanism is generic ReqShield↔DBLayer integration. That duplication should be removed.

---

## 2. Ownership invariants

### ReqShield owns

- validation rules and rule semantics;
- database-validation rule payload semantics;
- logical batching and correlation IDs;
- generic validation schema registry mechanics;
- immutable/frozen schema topology mechanics;
- the optional native ReqShield↔DBLayer adapter;
- validation bounds and validation-engine failure models;
- framework-neutral validation-profile normalization/application;
- frozen compiled-validator execution semantics and reentrancy guarantees;
- transport-neutral validation exceptions/results, while optional serialization helpers may remain convenience APIs.

### DBLayer owns

- exact database connection/runtime lifecycle;
- driver capabilities;
- SQL/query building and execution;
- parameter/bind limits;
- physical query chunk sizing;
- connection pooling/leases;
- transaction state and DB runtime isolation.

### Foundation owns

- application/auth schema definitions;
- config defaults/overrides and merge order;
- which DB connection/profile is selected;
- execution-scope lifecycle through its DBLayer factory/runtime state;
- Webrick request/input adaptation;
- application/HTTP validation exception mapping;
- DI/module/capability selection.

### Hard boundaries

- ReqShield must not depend on Foundation or Webrick.
- ReqShield must not create or own a global DBLayer connection registry.
- The DBLayer adapter must not pin an execution-scoped `Connection` across requests/jobs/Fibers.
- Foundation must not retain a second implementation of generic ReqShield database batching once the native adapter exists.
- DB bind-limit maps must not be duplicated in ReqShield or Foundation; DBLayer 5.1 is authoritative.
- Foundation must not keep a second generic option-to-ReqShield-setter translation layer once ReqShield owns a validation profile.
- ReqShield validation execution must not assign an HTTP status code to thrown validation exceptions; Foundation/application transport mapping owns HTTP status.
- A "compiled" validator must not merely retain an externally mutable validator and call it through a closure.

### Process/runtime security boundary

Foundation is expected to use `pcntl`/`posix` for trusted runtime supervision, and a separate low-level process/runtime-security library may later centralize safe process execution and OS capability controls. That concern must **not** be folded into ReqShield.

ReqShield's responsibility is limited to validating the **shape and policy values** supplied to a privileged operation. It does not determine whether arbitrary PHP, shell text, uploaded content or a process is safe to execute.

Required boundary:

- [ ] Treat strings such as `exec`, `system`, `shell_exec`, `proc_open`, `pcntl_fork`, `pcntl_exec`, `pcntl_signal`, `posix_kill`, `posix_setuid`, etc. as ordinary data unless the application's schema says otherwise.
- [ ] Do not add a dangerous-function-name blacklist or sanitizer that rejects these substrings globally.
- [ ] Do not scan uploaded files, PHP source, templates or arbitrary text for dangerous-function names.
- [ ] Do not add shell quoting, shell escaping, executable selection, process spawning, signal control, UID/GID switching, sandboxing or runtime-profile management to ReqShield.
- [ ] Do not claim validation can make uploaded/dynamic PHP safe to execute.
- [ ] Where Foundation exposes a registered operation/capability identifier, validate it structurally with normal ReqShield rules such as required/string/enum/allowlist/bounds.
- [ ] User input must select a **registered application operation**, not an executable or raw shell command, when Foundation applies this pattern.
- [ ] Authorization for that operation remains Foundation/application policy; validation is not authorization.
- [ ] Filesystem/path containment remains Pathwise 4.1 responsibility.
- [ ] Process execution, argv construction, environment/cwd policy, signals, privilege changes, sandbox profiles and OS isolation belong to the dedicated process/runtime layer.

Conceptual safe boundary:

```text
untrusted input
    ↓
ReqShield
    └─ validates: operation = "image.thumbnail", width = 500
             ↓
Foundation/application authorization + operation registry
             ↓
process/runtime library
    └─ maps registered operation to known executable + argv + limits
             ↓
OS/runtime isolation
```

Do **not** introduce APIs such as `SafeShellCommand`, `ForbiddenPhpFunction`, `PhpCodeSafe`, or `DangerousFunctionRule` as part of this release. Generic validation primitives are sufficient for ReqShield's part of this boundary.

---

## 3. DBLayer 5.1 baseline

### 3.1 Raise the reference-integration floor

- [X] Raise `infocyph/dblayer` in `require-dev` from `^5.0` to `^5.1`.
- [X] Keep DBLayer out of normal `require`; ReqShield remains database-library agnostic.
- [X] Add Composer `suggest` text for consumers that want the optional native DBLayer 5.1 database-rule provider.
- [ ] Run the DB integration suite specifically against DBLayer 5.1 stable behavior.

### 3.2 Consume DBLayer 5.1 runtime semantics directly

Use DBLayer 5.1's instance-first APIs:

- exact `Connection` ownership;
- `Connection::safeBatchSize()`;
- `Connection::effectiveMaxBindParameters()` where assertions/tests need it;
- DBLayer query-builder/bound-parameter execution;
- no static `DB` facade dependency in production bridge code.

The adapter must treat the supplied connection as an execution-owned dependency, not process-global state.

---

## 4. Promote the DBLayer provider into production source

### 4.1 New production bridge

Promote the proven test reference implementation into production code, e.g.:

```text
src/Bridge/DBLayerDatabaseProvider.php
```

Namespace:

```php
Infocyph\ReqShield\Bridge\DBLayerDatabaseProvider
```

It implements:

```php
Infocyph\ReqShield\Contracts\DatabaseProvider
```

### 4.2 Connection ownership model

Do **not** copy the test fixture's long-lived direct-connection constructor unchanged.

Production bridge should support a lazy connection resolver:

```php
Closure(): Infocyph\DBLayer\Connection\Connection
```

Requirements:

- [X] Resolve the connection at the beginning of each `batchExists()` / `batchUnique()` call.
- [X] Resolve once per provider operation, then use that same connection for all physical chunks in that operation.
- [X] Never retain the resolved execution-scoped connection after the method returns.
- [X] Derive safe batch sizing from that exact connection.
- [X] Allow a direct `Connection` convenience constructor/factory only if its semantics are explicitly documented as caller-owned and safe for the caller's chosen lifetime.
- [X] Prefer a resolver-first API for framework/persistent-runtime integrations.

This lets Foundation pass a closure around its execution-scoped `DBLayerFactory::connection($name)` without ReqShield knowing Foundation internals.

### 4.3 Adapter behavior to preserve

Carry forward the already-proven reference-provider semantics:

- [X] `exists` batching grouped by column.
- [X] `unique` batching grouped by column + ignore + ID column + soft-delete policy.
- [X] deduplicate repeated candidate values before generating `WHERE IN` bindings;
- [X] preserve per-check correlation after deduplication;
- [X] query `NULL` separately where required by SQL semantics;
- [X] preserve zero-like values (`0`, `'0'`, `false`) without accidental truthiness filtering;
- [X] support custom `id_column`;
- [X] support `withTrashed()` / `withoutTrashed(custom_column)`;
- [X] use bound parameters for values and ignore IDs;
- [X] validate/quote identifiers using DBLayer-supported query construction rather than interpolating user-controlled identifiers;
- [X] physical chunk size must come from `Connection::safeBatchSize()`;
- [X] do not copy the test reference provider's hard-coded `MAX_BATCH_VALUES = 1_000` into production by default; if a provider-level ceiling is retained, make it explicit/configurable and justify it with validation bounds + benchmark evidence;
- [X] account for fixed ignore bindings when calculating unique-query chunk size;
- [X] keep an application/request ceiling for unusually large validation batches if needed, but never exceed DBLayer's effective bind ceiling.

### 4.4 Correct nullable ignore-column semantics

Preserve the correct unique-ignore condition:

```sql
(id_column != :ignore OR id_column IS NULL)
```

Do not use only:

```sql
id_column != :ignore
```

because SQL three-valued logic would incorrectly exclude rows whose custom ID column is `NULL`.

**Implemented:** the production-provider suite explicitly covers a nullable custom ID column under an ignore predicate and proves the NULL row still participates in uniqueness checking.

---

## 5. Tighten the DatabaseProvider correlation contract

ReqShield documentation and `BatchExecutor` already treat logical check IDs as distinct integers. The interface PHPDoc currently permits `int|string`, which does not match runtime enforcement.

### Changes

- [X] Make provider input check IDs explicitly integer correlation IDs.
- [X] Change provider return PHPDoc from `list<int|string>` to `list<int>`.
- [X] Align `DatabaseBatchRule` payload documentation with BatchExecutor-owned integer correlation IDs.
- [X] Keep `BatchExecutor` fail-closed behavior for unknown/malformed returned IDs and reject duplicate returned IDs.
- [X] Add contract tests proving string, unknown, duplicate/malformed correlation IDs cannot be accepted as valid provider results.
- [X] Update `docs/database-rules.rst` so public contract, static analysis and runtime behavior agree.

The integer-ID narrowing documents behavior ReqShield already enforced for valid provider results. Batch 1 additionally hardens the boundary by generating correlation IDs independently from caller array keys and rejecting duplicate provider IDs.

---

## 6. Instance-owned freezeable SchemaRegistry

### 6.1 Add generic schema registry mechanics

Introduce an instance-owned registry, e.g.:

```text
src/Schema/SchemaRegistry.php
```

Recommended responsibilities:

```php
define(string $name, ...)
extend(string $name, ...)
has(string $name): bool
get(string $name): ValidationSchema
all(): array
freeze(): void
isFrozen(): bool
```

Exact value type may use ReqShield's existing schema representation rather than introducing an unnecessary wrapper type.

### 6.2 Runtime requirements

- [ ] Registry state belongs to the registry instance, never static process-global storage.
- [ ] `freeze()` is idempotent.
- [ ] Any define/replace/extend/remove operation after freeze throws a dedicated immutable-topology exception.
- [ ] Reads after freeze remain allocation-light.
- [ ] Retrieval must not return mutable references capable of mutating frozen registry topology indirectly.
- [ ] No request/job-specific state may be stored in the registry.
- [ ] Registry can be created/frozen during application graph/bootstrap construction and safely shared for reads afterward.

### 6.3 Existing static fragments

Do not force a large compatibility rewrite in 3.2.

- [ ] Keep current static fragment helpers where required for compatibility.
- [ ] Document them as bootstrap/legacy convenience rather than the preferred persistent-runtime topology.
- [ ] Do not implement the new `SchemaRegistry` internally as another global/static store.
- [ ] New framework integrations should consume the instance registry.
- [ ] Mark/document static fragment registration as legacy bootstrap topology; do not use it in persistent Foundation runtime composition.
- [ ] Consider an `@deprecated` documentation annotation in 3.2, but do not remove the API until a future major.

### 6.4 Lightweight immutable `ValidatorProfile`

Foundation's current `ValidatorFactory` performs generic ReqShield mechanics that are not application policy: it normalizes aliases/messages/sanitizers/casts/locale packs/limits and manually maps profile keys onto ReqShield setter calls. That translation belongs in ReqShield.

Add a small framework-neutral immutable profile value, tentatively:

```text
src/Support/ValidatorProfile.php
```

Recommended shape:

```php
$profile = ValidatorProfile::fromArray($options);
$validator = $profile->apply(Validator::make($rules, $databaseProvider));
```

Exact names may differ, but the ownership must not.

Required profile semantics:

- [ ] final/immutable value object; no container, config-repository or Foundation dependency;
- [ ] normalize/apply ReqShield-native options: fail-fast behavior, aliases, messages, sanitizers, casts, locale/locale packs, nested mode, unknown-field policy, DTO mapping, throw-on-failure and validation limits;
- [ ] preserve current Foundation profile behavior during migration, including deterministic precedence for `strip_unknown`, `strict` and `allow_unknown`;
- [ ] accept ReqShield's existing `required` nested-mode compatibility alias as targeted mode;
- [ ] validate positive limit values once in the profile layer instead of repeating that parser in every framework bridge;
- [ ] support immutable overlay/merge so shared defaults can be normalized once and schema/request overrides applied without mutating the base profile;
- [ ] nested map options such as messages, aliases, sanitizers, casts, locale packs and limits must have explicit merge semantics;
- [ ] profile parsing must not resolve DB connections or perform validation I/O;
- [ ] reusable/frozen profiles must not retain request data;
- [ ] unknown profile keys should fail clearly or be handled by an explicitly documented forward-compatibility policy; do not silently turn configuration typos into security-policy drift.

Foundation still owns where its configuration comes from, merge/source order, and which profile applies to a named application schema. It should no longer own the generic meaning of each ReqShield profile option.

---

## 7. Validator runtime/isolation audit

### 7.1 `CompiledValidator` must become a real frozen execution boundary

The current `CompiledValidator` is `readonly` only at the wrapper level: it stores a closure that directly calls a mutable `Validator`. That is not a sufficient persistent-runtime contract, especially because validation callbacks receive the validator instance and validation mutates bounded plan/LRU caches.

ReqShield 3.2 should preserve mutable builder-style `Validator` APIs for compatibility while making compiled execution explicit and safe:

- [ ] `Validator::compile()` / `Validator::compile(...)` must produce an independent frozen execution snapshot rather than a thin closure over mutable configuration;
- [ ] do not rely on a shallow clone unless every nested mutable object has been audited;
- [ ] frozen execution rejects topology/configuration mutation through callbacks or retained references with a dedicated exception;
- [ ] the same compiled validator instance must be safely reusable sequentially and by interleaved Fibers;
- [ ] callbacks may observe validation context, but cannot mutate frozen validator topology while an execution is in progress;
- [ ] caller-owned mutable state captured by a callback remains the caller's responsibility and must be documented separately from ReqShield isolation;
- [ ] database-provider resolution stays lazy and execution-scoped under compiled reuse.

### 7.2 Mutable-validator audit

ReqShield validators are configuration-bearing mutable objects before/during setup. Persistent runtimes must not accidentally share request-specific mutations.

Audit and test:

- [ ] `when()` / conditional rules;
- [ ] callbacks and after-validation callbacks;
- [ ] locale/message/alias changes;
- [ ] sanitizers/casts;
- [ ] unknown-field policy;
- [ ] wildcard schema cache;
- [ ] validation-plan cache;
- [ ] database provider attachment;
- [ ] compiled-validator behavior.

Required guarantees:

- [ ] one validation call does not retain input values into the next call;
- [ ] wildcard/nested expansion caches contain only schema/plan information, never request data;
- [ ] validation callbacks cannot mutate frozen shared schema topology;
- [ ] non-DB validation executes with zero DB resolution/I/O;
- [ ] a lazy DB connection resolver is not invoked unless a DB rule actually reaches batched execution;
- [ ] interleaved Fiber validations using distinct provider/resolver state do not cross-contaminate.

### 7.3 Cache/state classification

Do not remove benign process caches merely because they are static. The audit found several bounded caches that may remain if tests prove they contain only reusable metadata:

- `Sanitizer::$pipelineCallables`: string-defined sanitizer pipeline metadata only;
- `SchemaCompiler::$resolvedBuiltinRuleClassCache`: built-in rule-class lookup metadata only;
- `Validator::$processPlanCache`: only pure/string-schema compiled plans;
- per-validator compiled/wildcard plan caches: schema/shape-derived plans only, never input values.

Requirements:

- [ ] bounded caches must remain bounded under adversarial schema/shape churn;
- [ ] wildcard cache keys may use request shape but must never retain scalar request values or object payloads;
- [ ] process caches must not become hidden application schema-registration stores;
- [ ] static fragment registration remains classified separately as mutable global topology, not as a harmless cache;
- [ ] cache/LRU mutation during Fiber interleaving must not alter validation correctness.

A shared compiled validator is expected to be safely reusable under these constraints; do not hide ownership/state resets in Foundation.

---

## 8. Database failure taxonomy

Preserve the distinction between a validation miss and infrastructure failure.

- [ ] `exists` miss / `unique` conflict remain ordinary validation failures.
- [ ] connection/query/driver failures become `DatabaseValidationException` with the original exception preserved as `previous`.
- [ ] malformed provider output remains a ReqShield database-validation infrastructure/contract failure.
- [ ] never convert DB outages into "field invalid" results.
- [ ] never leak raw SQL, credentials or sensitive bindings through public validation messages.

Foundation will map these exceptions into its application/HTTP policy; ReqShield must not own HTTP status codes.

### 8.1 Transport/status cleanup discovered by the audit

Current ReqShield contains one concrete ownership leak:

```php
new ValidationException('Validation failed', $errors, 422)
```

inside validator execution.

Correct it in 3.2:

- [ ] thrown validation exceptions from normal ReqShield execution use transport-neutral exception code semantics; do not automatically assign HTTP `422`;
- [ ] preserve the existing `ValidationException` constructor signature for compatibility;
- [ ] keep `ValidationResult::throw()` transport-neutral;
- [ ] optional JSON:API / Problem Details projection helpers may remain, but caller-controlled status/type must be possible and docs must describe them as presentation helpers, not runtime HTTP policy;
- [ ] where practical, make `toJsonApiErrors()` accept an optional caller status while preserving the existing no-argument call;
- [ ] Foundation `ValidationExceptionMapper` remains the place that chooses HTTP 422 for Foundation web requests;
- [ ] add a regression test that ReqShield throwing behavior itself does not imply Foundation/Webrick HTTP policy.

---

## 9. Tests

### 9.1 Production DBLayer provider tests

Move/expand the current DBLayer reference-provider coverage so it validates the production bridge itself.

Required matrix:

- [X] flat `exists` / `unique`;
- [X] nested database rules;
- [X] wildcard batching;
- [X] mixed tables/columns;
- [X] duplicate candidate values;
- [X] zero-like values;
- [X] `NULL` values;
- [X] ignore IDs (`0`, `'0'`, integer/string application IDs as values, while correlation IDs remain integers);
- [X] custom ID columns;
- [X] nullable custom ID column + ignore regression;
- [X] default and custom soft-delete columns;
- [X] DBLayer 5.1 derived safe batch boundaries;
- [X] fixed-binding-aware batch sizing;
- [X] constrained `security.max_params`;
- [X] more than one physical chunk;
- [X] resolver invoked once per provider operation;
- [X] resolver not invoked for non-DB validation;
- [X] DB failure propagation;
- [X] unknown/malformed correlation IDs;
- [X] SQLite deterministic integration coverage.

**Batch 2 implementation status:** complete. The test-only DBLayer provider has been replaced by the production `Infocyph\ReqShield\Bridge\DBLayerDatabaseProvider`; PR CI remains the batch closure gate.

### 9.2 SchemaRegistry tests

- [ ] define/get/has/all;
- [ ] extend/override semantics;
- [ ] duplicate-name behavior is explicit;
- [ ] freeze and post-freeze write rejection;
- [ ] repeated read stability;
- [ ] independent registries do not share state;
- [ ] interleaved Fiber reads remain isolated from other registries;
- [ ] frozen topology cannot be mutated through returned structures.

### 9.3 Runtime tests

- [ ] repeated validation using the same safe validator/compiled-validator path;
- [ ] sequential isolation;
- [ ] interleaved Fiber isolation;
- [ ] large wildcard input under configured limits;
- [ ] bounds failures occur before expensive DB work where applicable.

### 9.4 Process/capability boundary tests

These tests protect ReqShield from drifting into a false sandbox role:

- [ ] ordinary string fields can validly contain text such as `exec`, `system`, `pcntl_fork`, `posix_kill` when the schema permits ordinary strings;
- [ ] no global sanitizer silently removes or rewrites dangerous-function-like substrings;
- [ ] a schema-defined allowlist/enum can reject an unregistered operation identifier and accept a registered one;
- [ ] validation of an operation identifier does not execute, resolve or inspect an executable;
- [ ] authorization/process execution remains outside ReqShield test fixtures except for framework-neutral mocked application examples.

### 9.5 ValidatorProfile / compiled-runtime tests

- [ ] profile normalization and immutable overlay/merge;
- [ ] exact migration parity with Foundation's current option semantics;
- [ ] conflicting/invalid limit and unknown-field settings fail deterministically;
- [ ] profile construction/application performs no DB resolution;
- [ ] compiling creates a snapshot independent from later mutation of the source builder;
- [ ] post-compile mutation of the source `Validator` cannot alter the compiled validator;
- [ ] mutation attempted through a callback against a frozen compiled execution fails closed;
- [ ] the **same** compiled validator instance passes sequential reuse tests;
- [ ] the **same** compiled validator instance passes interleaved Fiber reuse tests;
- [ ] wildcard/conditional validation under shared compiled reuse retains no prior request values;
- [ ] bounded cache sizes remain bounded under schema/shape churn;
- [ ] transport-neutral thrown exception behavior is covered independently from Foundation HTTP mapping.

---

## 10. Static analysis and QA

Run the normal PHPForge/ReqShield gates on PHP 8.4 and 8.5 where configured:

- [ ] Composer validate;
- [ ] PHPUnit/Pest full suite;
- [ ] DBLayer integration suite with `^5.1`;
- [ ] PHPStan;
- [ ] Rector dry-run;
- [ ] coding-style checks;
- [ ] lowest-dependency lane;
- [ ] stable dependency lane.

No suppression should be added merely to hide a provider/schema ownership issue.

---

## 11. Performance acceptance

### 11.1 DB provider

Benchmark representative DB validation paths:

- direct DBLayer query baseline;
- ReqShield production DBLayer provider;
- duplicate-heavy wildcard batches;
- multiple physical chunks near bind ceilings.

Acceptance goals:

- [ ] logical batching remains one provider call per operation/table grouping as designed;
- [ ] duplicate values do not multiply bindings unnecessarily;
- [ ] safe sizing introduces no duplicated driver-limit logic;
- [ ] lazy connection resolution is negligible relative to DB work;
- [ ] no DB/provider setup occurs for non-DB validation.

### 11.2 Schema registry

- [ ] frozen registry lookup should remain effectively O(1) by name;
- [ ] normal validation should not mutate/rebuild registry topology;
- [ ] no deep clone of the whole registry per request merely for isolation.

### 11.3 Profile and compiled execution

Benchmark representative hot paths before/after the extraction:

- raw `Validator::make()` + direct setter configuration;
- reusable `ValidatorProfile` application;
- compiled/frozen validator repeated execution;
- wildcard compiled reuse across repeated same-shape inputs.

Acceptance:

- [ ] normalizing a shared base profile should happen once where callers reuse it;
- [ ] applying a pre-normalized profile should not perform repeated reflection or config parsing;
- [ ] compiled execution must not deep-clone the complete validator per validation call merely to obtain isolation;
- [ ] reentrancy safety must not introduce request-global locks or serialize independent validators;
- [ ] caches remain bounded and allocation-light.

Performance fixes must preserve correctness and isolation first.

---

## 12. Documentation

Update:

- [ ] `README.md` with optional native DBLayer integration example;
- [ ] `docs/database-rules.rst` with DBLayer 5.1 bridge usage and connection-resolver lifetime guidance;
- [ ] schema documentation with instance-owned registry/freeze pattern;
- [ ] validation-profile documentation with canonical option meanings, immutable overlay semantics and a framework-neutral example;
- [ ] compiled-validator documentation that distinguishes mutable configuration/build phase from frozen reusable execution phase;
- [ ] persistent-runtime guidance warning against process-global mutable schema registration;
- [ ] document that validation is not a process/PHP sandbox and dangerous-function-name filtering is intentionally out of scope;
- [ ] document that ReqShield exceptions are transport-neutral and HTTP status selection belongs to the application/framework;
- [ ] clarify that `Path`, `SafeFilename`, `SecureFile` and `UploadMeta` validate syntax/metadata only; Pathwise 4.1 owns canonical path containment, storage trust, malware/storage policy and filesystem authorization;
- [ ] document the recommended registered-operation pattern for applications that validate input for privileged process capabilities;
- [ ] installation/development docs to identify DBLayer 5.1 as a development/reference integration only;
- [ ] upgrade/release notes for 3.2.

Example framework-neutral provider usage should resemble:

```php
$provider = DBLayerDatabaseProvider::fromResolver(
    static fn(): Connection => $applicationDatabase->connection(),
);
```

The exact API can differ, but execution-owned resolution semantics must remain clear.

For privileged application operations, examples should validate structured intent rather than raw commands, e.g. an `operation` value such as `image.thumbnail` plus bounded parameters. ReqShield must not provide the executable mapping or execute the operation.

---

## 13. Foundation 26.7 migration after ReqShield 3.2

Once ReqShield 3.2 is released/consumable:

### Foundation dependency

- [ ] Raise Foundation's ReqShield floor from `^3.1` to `^3.2`.

### Remove duplicated mechanics

- [ ] Delete Foundation `src/Validation/ReqShieldDatabaseProvider.php`.
- [ ] Bind ReqShield's native DBLayer provider directly.
- [ ] Pass Foundation's DB connection selection as a lazy resolver around the current execution-owned DBLayer connection.
- [ ] Remove Foundation-specific physical batching, bind sizing, identifier normalization and SQL NULL/ignore handling now owned by the ReqShield bridge/DBLayer.
- [ ] Delete or collapse `ValidationGraphFactory::databaseProvider()` if it becomes only a pass-through constructor.
- [ ] Update Foundation DB-provider tests that currently use field strings as provider correlation IDs; native provider contract tests must use integer correlation IDs or exercise the provider through ReqShield validation.

### Schema topology

Foundation's current `ValidationSchemaRegistry` is already readonly and freezes its snapshot at application composition. The extraction is therefore about **generic ownership/deduplication**, not fixing an existing Foundation runtime leak.

- [ ] Move generic schema normalize/define/extend/freeze mechanics to ReqShield `SchemaRegistry`.
- [ ] Prefer deleting Foundation `ValidationSchemaRegistry.php` and binding the ReqShield registry directly after Foundation has layered its application schemas.
- [ ] If a Foundation compatibility facade must remain, it may only adapt Foundation config/schema names; it must not reimplement generic registry normalization/composition/freeze behavior.
- [ ] Keep Foundation's schema names, auth schema definitions, config source/default/override order and composition policy in Foundation.
- [ ] Freeze production schema topology at graph/bootstrap construction completion.
- [ ] Do not permit request execution to mutate shared schema registration.

### Validator profile

- [ ] Replace Foundation `ValidatorFactory`'s generic setter-by-setter ReqShield configuration with ReqShield `ValidatorProfile`.
- [ ] Remove Foundation-local normalization of aliases/messages/sanitizers/casts/locale packs/limits once the native profile owns those semantics.
- [ ] Foundation may retain a thin factory that selects named rules, selects/merges Foundation config defaults + schema/request overrides, chooses the optional DB provider, and hands the resulting native profile to ReqShield.
- [ ] Do not make Foundation `ValueNormalizer` authoritative for ReqShield profile semantics.
- [ ] Allow reusable base profiles to be normalized once during graph construction where practical.
- [ ] Foundation `compile()` should return ReqShield's frozen/reentrant compiled validator path, not a wrapper around a mutable validator.

### Keep in Foundation

- [ ] `AuthRequestSchemas` and other application/domain schema definitions;
- [ ] `FormRequest` / Webrick request adaptation;
- [ ] validation config source/default/override selection;
- [ ] `ValidationExceptionMapper` / HTTP response policy;
- [ ] validation service-provider/DI capability composition;
- [ ] DB connection/profile selection;
- [ ] authorization and selection of registered privileged operations/capabilities.

### Intentionally retained Foundation validation/policy code

The whole-codebase audit also reviewed Foundation's runtime/config/security/OAuth validators. Do **not** move these wholesale into ReqShield 3.2:

- [ ] `RuntimeConfigValidator` and `Config/Internal/Runtime*Validator` remain Foundation bootstrap/runtime policy;
- [ ] `ProductionSecurityValidator` remains Foundation deployment/security-topology policy;
- [ ] OAuth/OpenID configuration validators remain Foundation protocol/application configuration policy;
- [ ] tiny primitive checks may legitimately remain duplicated there because ReqShield is an optional Foundation capability and production/bootstrap config validation must not become dependent on installing the validation module;
- [ ] future use of ReqShield for optional application configuration schemas must not invert this dependency boundary.

These classes combine application topology, lower-library capability selection and security policy. They are not generic validation-engine mechanics even when individual checks resemble ReqShield rules.

### Keep outside ReqShield

- [ ] Pathwise 4.1 owns path/filesystem containment and upload/storage path safety.
- [ ] A dedicated low-level process/runtime library owns safe executable/argv handling, process lifecycle, signals, environment/cwd policy, privilege changes and sandbox integration.
- [ ] Foundation owns which process/runtime profile or registered operation is exposed to application code.
- [ ] OS/container/runtime configuration remains the final execution-security boundary for untrusted code.
- [ ] ReqShield rules named `Path`, `SafeFilename`, `SecureFile`, `UploadId` and `UploadMeta` remain syntactic/metadata validation; their success is never a Pathwise 4.1 containment/trust decision.

### Foundation acceptance

- [ ] non-DB schema validation performs no DB resolution;
- [ ] selected DB rules resolve the exact current execution connection;
- [ ] pooled execution never leaks a prior request/job connection into later validation;
- [ ] sequential and interleaved Fiber validation isolation passes;
- [ ] Foundation no longer contains generic ReqShield↔DBLayer SQL/batching mechanics;
- [ ] direct ReqShield vs Foundation bridge benchmark attribution is recorded;
- [ ] Foundation no longer contains the generic ReqShield profile-to-setter translation layer;
- [ ] Foundation no longer contains a package-local generic schema registry implementation unless only a compatibility facade remains;
- [ ] Foundation `ValidationExceptionMapper` still owns HTTP 422 mapping while ReqShield exceptions stay transport-neutral;
- [ ] Point 26.7 ownership statement matches the actual codebase and Foundation's ReqShield benchmark is renamed/versioned for the 3.2 integration.

---

## 14. Explicit non-goals

Do not add during this pass:

- a ReqShield DI container;
- a Foundation/Webrick dependency;
- HTTP exception/status mapping;
- application configuration loading;
- a second DB query builder;
- a second connection pool/registry;
- generic ORM/repository abstractions;
- automatic global DBLayer facade registration;
- driver bind-limit tables duplicated from DBLayer;
- dangerous PHP function blacklists;
- source-code/upload scanning for `exec`, `system`, `shell_exec`, `proc_open`, `pcntl_*`, `posix_*` or similar function names;
- shell command sanitization/escaping APIs;
- process spawning/execution APIs;
- process/signal/UID/GID/sandbox policy;
- executable allowlists or command registries owned by ReqShield;
- authorization logic;
- a framework/container/config-repository abstraction inside ReqShield;
- a mutable/process-global validation profile registry.

The audit now provides direct implementation evidence that a **small immutable `ValidatorProfile` is required in 3.2**: Foundation currently duplicates generic option normalization and setter application. Keep this value object narrow; do not turn it into a framework configuration subsystem.

---

## 15. Implementation order

1. **Batch 1 — COMPLETE:** DBLayer `^5.1` floor, integer correlation contract and release-gate cleanup are green in PR run #43.
2. **Batch 2 — implementation complete / QA pending:** native DBLayer 5.1 bridge, resolver-first ownership, DBLayer-derived chunking and the production regression matrix are implemented; PR CI is the closure gate.
3. **Batch 3 — next:** add the instance-owned freezeable `SchemaRegistry`; keep static fragments compatibility-only.
4. **Batch 4:** add the lightweight immutable `ValidatorProfile`, with merge/apply semantics matching current Foundation behavior.
5. **Batch 5:** rework `CompiledValidator` into a frozen execution snapshot; close same-instance sequential/Fiber reentrancy; classify/audit bounded caches.
6. **Batch 6:** remove automatic HTTP-422 exception-code ownership and lock/document the Pathwise 4.1 + Runwire trust boundaries with drift-prevention tests.
7. **Batch 7:** complete docs/benchmarks, run PHP 8.4/8.5 stable + lowest QA/static-analysis gates, and close the ReqShield 3.2 release gate.
8. **Batch 8:** return to Foundation 26.7, consume ReqShield 3.2, remove duplicate DB provider/schema/profile mechanics, run Foundation acceptance/performance gates, and update the Foundation tracker/benchmark naming only after the dependency is consumable.

---

## 16. Completion gate

ReqShield 3.2 is complete when:

- DBLayer 5.1 is the tested reference-integration floor without becoming a mandatory runtime dependency;
- the generic DBLayer database-rule provider is production-owned by ReqShield;
- the provider resolves execution-owned connections safely and does not retain scoped connections across executions;
- DBLayer remains authoritative for parameter limits and physical query sizing;
- `exists`/`unique` semantics correctly cover duplicate, NULL, zero-like, ignore/custom-ID and soft-delete cases;
- provider correlation IDs are consistently integer and malformed output fails closed;
- an instance-owned freezeable schema registry exists for persistent-runtime-safe topology;
- a small immutable `ValidatorProfile` owns generic ReqShield option normalization/application;
- Foundation no longer needs to understand the generic setter semantics behind that profile;
- compiled validators are independent frozen execution snapshots, not closures over mutable builders;
- the same compiled instance is safe under sequential and interleaved Fiber reuse;
- non-DB validation remains DB-cold;
- sequential/Fiber reuse does not leak mutable validation state;
- bounded process/instance caches retain only reusable metadata/schema plans and never request scalar/object data;
- ReqShield validation exceptions do not automatically encode HTTP 422; Foundation/application mapping owns transport status;
- ReqShield explicitly remains validation-only for process-related inputs: it validates structured intent/parameters but does not blacklist dangerous function names, authorize capabilities, execute processes or claim to sandbox code;
- QA/static analysis and representative performance gates are green;
- Foundation can delete its duplicate DB provider/schema-registry/profile-translation mechanics without moving application policy into ReqShield.

After that, Foundation Point 26.7 can close on top of ReqShield 3.2 rather than carrying framework-local substitutes for generic validation/database integration mechanics.

---

## 17. Runwire validation/authorization boundary

### 17.1 Final ownership chain

For privileged process operations:

```text
untrusted application input
        ↓
ReqShield
validates operation ID + structured arguments
        ↓
Foundation/application
checks authorization/capability and selects trusted operation
        ↓
Pathwise 4.1, where an artifact/path is involved
resolves data under intended filesystem/storage boundary
        ↓
Runwire
executes the trusted structured process definition
        ↓
OS/runtime sandbox boundary where required
```

ReqShield never becomes the process executor or security sandbox.

---

### 17.2 ReqShield owns data/intent validation only

ReqShield may validate generic structures such as:

```php
[
    'operation' => 'image.thumbnail',
    'file_id' => '...',
    'width' => 500,
]
```

using normal generic rules:

- required/optional;
- string/integer/boolean;
- enum/allowlist membership;
- length/range;
- nested/wildcard structures;
- custom application rules where appropriate.

ReqShield does not need to know that `image.thumbnail` ultimately maps to a Runwire process command.

---

### 17.3 No dangerous-function blacklist

Do not add built-in validation rules whose purpose is to reject strings because they contain names such as:

```text
exec
system
shell_exec
passthru
popen
proc_open
pcntl_exec
pcntl_fork
pcntl_signal
posix_kill
posix_setuid
```

A string containing one of those names is ordinary data unless another layer deliberately interprets it as executable code/command input.

Therefore these remain valid examples of ordinary data from ReqShield's point of view:

```text
"The PHP exec() function starts a program"
"pcntl_fork documentation"
"system status"
```

Do not weaken general validation merely because Foundation installs `pcntl`/`posix` or Runwire.

---

### 17.4 No shell escaping/sanitizer API

Do not add a ReqShield rule such as:

```text
safe_shell_command
shell_escape
safe_exec
safe_php_code
```

as a claimed security boundary.

Shell safety belongs to avoiding shell-string construction and using Runwire's structured executable + argv API.

ReqShield can validate an argument's domain constraints, for example a bounded branch name or numeric image width, but it cannot convert an arbitrary command string into a safe privileged operation.

---

### 17.5 Operation allowlisting stays generic

If ReqShield has or gains a generic enum/allowlist rule, Foundation may use it for configured operation identifiers.

Example concept:

```text
operation ∈ {image.thumbnail, pdf.inspect, git.status}
```

That rule remains a generic validation primitive.

Do not introduce Runwire-specific rule classes into ReqShield merely to express it.

Foundation remains responsible for ensuring that the validated identifier is authorized and mapped to a trusted operation definition.

---

### 17.6 Structured arguments, not command text

Foundation schemas should prefer intent-level fields:

```text
operation
artifact_id
width
height
format
```

rather than:

```text
command
shell
script
```

where privileged process execution is intended.

ReqShield validates the former as ordinary application data. Foundation resolves/authorizes it. Runwire converts the trusted registered operation to executable + argv.

ReqShield should not parse shell grammar or PHP source syntax as part of ordinary validation.

---

### 17.7 Path/file inputs

When a privileged operation targets an uploaded/stored file, ReqShield should normally validate an application-level artifact identifier or bounded logical path value rather than trying to establish filesystem trust itself.

Correct ownership:

```text
ReqShield: shape/domain of file_id/path parameter
Pathwise 4.1: canonical filesystem/storage containment
Foundation: authorization to use that artifact for this operation
Runwire: process invocation
```

Do not duplicate Pathwise 4.1 traversal/symlink/storage-root mechanics inside ReqShield process-operation schemas.

---

### 17.8 Uploaded PHP/source code

ReqShield is not a source-code malware scanner.

Do not scan upload/body contents for occurrences of dangerous PHP APIs and call that a sandbox.

If an application accepts source code as data, ReqShield may validate metadata and generic size/shape constraints. Pathwise 4.1 handles storage safety. Execution, if ever allowed, must go through Foundation authorization and a separately isolated Runwire/OS execution profile.

A forked Runwire child is not made safe by ReqShield content filtering.

---

### 17.9 No Runwire dependency

Do not add Runwire to ReqShield production Composer requirements.

ReqShield's normal non-process validation must remain usable in:

- FPM;
- CLI;
- serverless;
- persistent workers;
- applications that never install Runwire;
- applications that use a different process/runtime implementation.

Cross-library integration tests may be added at Foundation level instead of coupling ReqShield's own suite to Runwire.

---

### 17.10 Database validation remains independent

The Runwire decision does not alter the ReqShield 3.2 DBLayer 5.1 plan.

Keep:

- lazy DB connection resolution for optional database rules;
- batching;
- integer correlation IDs;
- physical parameter limit handling;
- correct Unique/Exists semantics;
- database validation optional/cold when unused.

Do not use Runwire workers/processes to parallelize validation as part of ReqShield 3.2.

---

### 17.11 Persistent-runtime isolation

Runwire makes persistent Foundation workers a native deployment mode, so ReqShield's existing isolation requirements become even more important.

Prove:

- compiled validator/schema state is safe across many requests in one Runwire worker;
- the **same frozen compiled validator instance** is safe when sequential requests and interleaved Fibers share it;
- mutator calls reached through validation callbacks cannot alter frozen compiled topology;
- per-validation result/error/data state is not retained globally;
- instance-owned/frozen schema registry cannot be mutated by an unrelated request;
- Fiber/interleaved validation retains no data from another execution;
- DB provider lazy resolver resolves the current Foundation execution connection rather than retaining a previous request's connection;
- non-DB schemas never accidentally resolve DB services merely because the worker is persistent.

These are ReqShield state-lifetime concerns, not Runwire integration APIs.

---

### 17.12 Foundation operation-schema example

A Foundation-owned schema may conceptually validate:

```php
[
    'operation' => ['required', 'string', /* allowlisted application operation */],
    'artifact_id' => ['required', 'string', 'max:...'],
    'width' => ['required', 'integer', 'min:1', 'max:4096'],
]
```

Then:

```text
ReqShield result
    ↓
Foundation authorization
    ↓
Pathwise 4.1 artifact lookup
    ↓
Foundation registered-operation mapping
    ↓
Runwire ProcessRunner
```

Do not place executable paths or raw shell command templates into ReqShield schema definitions.

---

### 17.13 Boundary tests

Retain/add tests proving:

- string values containing `exec(` are accepted when schema permits ordinary strings;
- strings containing `pcntl_fork`/`posix_kill` are not specially interpreted;
- enum/allowlist validation can reject an unknown operation ID without knowing Runwire;
- nested structured argument bounds work normally;
- no built-in process/shell sanitizer is invoked;
- schema compilation/cache behavior is identical whether Runwire is installed or absent;
- persistent sequential/Fiber validation does not retain operation/argument values between requests;
- non-DB operation validation does no DB I/O.

Foundation owns end-to-end tests proving unauthorized/unregistered operations never reach Runwire.

---

### 17.14 Documentation wording

Normalize final ReqShield 3.2 docs so the concrete ecosystem boundary reads:

```text
ReqShield   validates structured data/intent
Foundation  authorizes capability/operation
Pathwise 4.1    resolves filesystem/storage artifact where needed
Runwire     executes/supervises process/runtime mechanics
OS          supplies final hostile-code sandbox boundary
```

Replace provisional “future process runtime” / `Runwire` wording with **Runwire** where referring to the Infocyph implementation.

Do not describe Runwire as a ReqShield requirement.

---

### 17.15 Non-goals clarification

ReqShield 3.2 specifically does not add:

- Runwire dependency;
- process runner;
- shell parser;
- shell escaping facade;
- PHP source linter/sandbox;
- dangerous-function blacklist;
- executable registry;
- process authorization;
- UID/GID policy;
- seccomp/AppArmor/container policy;
- `pcntl`/`posix` wrappers;
- process-level parallel validation.

---

### 17.16 Runwire boundary completion gate

ReqShield 3.2 process/runtime-boundary acceptance additionally requires:

- [ ] Runwire is named as the process/runtime owner in ecosystem integration documentation;
- [ ] ReqShield has no production dependency on Runwire;
- [ ] ordinary strings are not rejected because they contain process/PHP function names;
- [ ] no shell/process sanitizer is marketed as a sandbox;
- [ ] generic enum/allowlist/structured validation is sufficient for Foundation operation schemas;
- [ ] persistent Runwire worker deployment does not cause schema/result/DB-provider state leakage;
- [ ] Foundation owns end-to-end authorization before Runwire invocation;
- [ ] Pathwise 4.1 remains filesystem trust owner where files are involved;
- [ ] ReqShield's path/upload rules are documented as syntax/metadata checks, not containment, malware, storage-trust or filesystem-authorization guarantees.

All DBLayer, SchemaRegistry, runtime-state, QA, benchmark and Foundation 26.7 criteria from earlier sections of this plan remain unchanged.
