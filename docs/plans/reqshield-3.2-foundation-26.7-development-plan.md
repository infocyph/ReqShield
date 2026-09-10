# ReqShield 3.2 — DBLayer 5.1 Integration & Foundation 26.7 Development Plan

**Status:** implementation plan  
**Target:** ReqShield 3.2  
**Branch:** `reqshield-3.2/foundation-26.7`  
**Foundation consumer:** `infocyph/Foundation` Point 26.7  
**Priority:** correctness → runtime ownership/isolation → security bounds → performance → ergonomics

> ReqShield remains the validation/sanitization/schema/rule engine. DBLayer remains the database runtime/query owner. Foundation remains the application composition, schema-selection, connection-selection, Webrick adaptation and HTTP error-mapping owner. This pass removes generic ReqShield↔DBLayer mechanics from Foundation without moving application policy into ReqShield.

---

## 1. Baseline

Current released ReqShield baseline:

- ReqShield: `3.1`
- PHP: `^8.4`
- DBLayer development/reference integration: currently `^5.0`
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
- validation bounds and validation-engine failure models.

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

---

## 3. DBLayer 5.1 baseline

### 3.1 Raise the reference-integration floor

- [ ] Raise `infocyph/dblayer` in `require-dev` from `^5.0` to `^5.1`.
- [ ] Keep DBLayer out of normal `require`; ReqShield remains database-library agnostic.
- [ ] Add/update Composer `suggest` text for consumers that want the native DBLayer database-rule provider.
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

- [ ] Resolve the connection at the beginning of each `batchExists()` / `batchUnique()` call.
- [ ] Resolve once per provider operation, then use that same connection for all physical chunks in that operation.
- [ ] Never retain the resolved execution-scoped connection after the method returns.
- [ ] Derive safe batch sizing from that exact connection.
- [ ] Allow a direct `Connection` convenience constructor/factory only if its semantics are explicitly documented as caller-owned and safe for the caller's chosen lifetime.
- [ ] Prefer a resolver-first API for framework/persistent-runtime integrations.

This lets Foundation pass a closure around its execution-scoped `DBLayerFactory::connection($name)` without ReqShield knowing Foundation internals.

### 4.3 Adapter behavior to preserve

Carry forward the already-proven reference-provider semantics:

- [ ] `exists` batching grouped by column.
- [ ] `unique` batching grouped by column + ignore + ID column + soft-delete policy.
- [ ] deduplicate repeated candidate values before generating `WHERE IN` bindings;
- [ ] preserve per-check correlation after deduplication;
- [ ] query `NULL` separately where required by SQL semantics;
- [ ] preserve zero-like values (`0`, `'0'`, `false`) without accidental truthiness filtering;
- [ ] support custom `id_column`;
- [ ] support `withTrashed()` / `withoutTrashed(custom_column)`;
- [ ] use bound parameters for values and ignore IDs;
- [ ] validate/quote identifiers using DBLayer-supported query construction rather than interpolating user-controlled identifiers;
- [ ] physical chunk size must come from `Connection::safeBatchSize()`;
- [ ] account for fixed ignore bindings when calculating unique-query chunk size;
- [ ] keep an application/request ceiling for unusually large validation batches if needed, but never exceed DBLayer's effective bind ceiling.

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

Add an explicit regression test for this behavior in the production-provider suite.

---

## 5. Tighten the DatabaseProvider correlation contract

ReqShield documentation and `BatchExecutor` already treat logical check IDs as distinct integers. The interface PHPDoc currently permits `int|string`, which does not match runtime enforcement.

### Changes

- [ ] Make provider input check IDs explicitly integer correlation IDs.
- [ ] Change provider return PHPDoc from `list<int|string>` to `list<int>`.
- [ ] Align `DatabaseBatchRule` payload PHPDoc/types with integer correlation IDs.
- [ ] Keep `BatchExecutor` fail-closed behavior for unknown/malformed returned IDs.
- [ ] Add contract tests proving string, unknown, duplicate/malformed correlation IDs cannot be accepted as valid provider results.
- [ ] Update `docs/database-rules.rst` so public contract, static analysis and runtime behavior agree.

This is a contract correction to the behavior ReqShield already enforces, not a new string-ID feature removal.

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

---

## 7. Validator runtime/isolation audit

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

If a shared compiled validator is not safely reusable under these constraints, make the safe ownership/lifetime explicit rather than hiding state resets in Foundation.

---

## 8. Database failure taxonomy

Preserve the distinction between a validation miss and infrastructure failure.

- [ ] `exists` miss / `unique` conflict remain ordinary validation failures.
- [ ] connection/query/driver failures become `DatabaseValidationException` with the original exception preserved as `previous`.
- [ ] malformed provider output remains a ReqShield database-validation infrastructure/contract failure.
- [ ] never convert DB outages into "field invalid" results.
- [ ] never leak raw SQL, credentials or sensitive bindings through public validation messages.

Foundation will map these exceptions into its application/HTTP policy; ReqShield must not own HTTP status codes.

---

## 9. Tests

### 9.1 Production DBLayer provider tests

Move/expand the current DBLayer reference-provider coverage so it validates the production bridge itself.

Required matrix:

- [ ] flat `exists` / `unique`;
- [ ] nested database rules;
- [ ] wildcard batching;
- [ ] mixed tables/columns;
- [ ] duplicate candidate values;
- [ ] zero-like values;
- [ ] `NULL` values;
- [ ] ignore IDs (`0`, `'0'`, integer/string application IDs as values, while correlation IDs remain integers);
- [ ] custom ID columns;
- [ ] nullable custom ID column + ignore regression;
- [ ] default and custom soft-delete columns;
- [ ] DBLayer 5.1 derived safe batch boundaries;
- [ ] fixed-binding-aware batch sizing;
- [ ] constrained `security.max_params`;
- [ ] more than one physical chunk;
- [ ] resolver invoked once per provider operation;
- [ ] resolver not invoked for non-DB validation;
- [ ] DB failure propagation;
- [ ] unknown/malformed correlation IDs;
- [ ] SQLite deterministic integration coverage.

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

Performance fixes must preserve correctness and isolation first.

---

## 12. Documentation

Update:

- [ ] `README.md` with optional native DBLayer integration example;
- [ ] `docs/database-rules.rst` with DBLayer 5.1 bridge usage and connection-resolver lifetime guidance;
- [ ] schema documentation with instance-owned registry/freeze pattern;
- [ ] persistent-runtime guidance warning against process-global mutable schema registration;
- [ ] installation/development docs to identify DBLayer 5.1 as a development/reference integration only;
- [ ] upgrade/release notes for 3.2.

Example framework-neutral provider usage should resemble:

```php
$provider = DBLayerDatabaseProvider::fromResolver(
    static fn(): Connection => $applicationDatabase->connection(),
);
```

The exact API can differ, but execution-owned resolution semantics must remain clear.

---

## 13. Foundation 26.7 migration after ReqShield 3.2

Once ReqShield 3.2 is released/consumable:

### Foundation dependency

- [ ] Raise Foundation's ReqShield floor from `^3.1` to `^3.2`.

### Remove duplicated mechanics

- [ ] Delete Foundation `src/Validation/ReqShieldDatabaseProvider.php`.
- [ ] Replace it with ReqShield's native DBLayer provider.
- [ ] Pass Foundation's DB connection selection as a lazy resolver around the current execution-owned DBLayer connection.
- [ ] Remove Foundation-specific physical batching, bind sizing, identifier normalization and SQL NULL/ignore handling now owned by the ReqShield bridge/DBLayer.

### Schema topology

- [ ] Replace Foundation's generic mutable registry mechanics with ReqShield's instance-owned `SchemaRegistry` where applicable.
- [ ] Keep Foundation's schema names, auth schema definitions, config defaults/overrides and composition policy in Foundation.
- [ ] Freeze production schema topology at graph/bootstrap construction completion.
- [ ] Do not permit request execution to mutate shared schema registration.

### Keep in Foundation

- [ ] `AuthRequestSchemas` and other application/domain schema definitions;
- [ ] `FormRequest` / Webrick request adaptation;
- [ ] validation config source/default/override selection;
- [ ] `ValidationExceptionMapper` / HTTP response policy;
- [ ] validation service-provider/DI capability composition;
- [ ] DB connection/profile selection.

### Foundation acceptance

- [ ] non-DB schema validation performs no DB resolution;
- [ ] selected DB rules resolve the exact current execution connection;
- [ ] pooled execution never leaks a prior request/job connection into later validation;
- [ ] sequential and interleaved Fiber validation isolation passes;
- [ ] Foundation no longer contains generic ReqShield↔DBLayer SQL/batching mechanics;
- [ ] direct ReqShield vs Foundation bridge benchmark attribution is recorded;
- [ ] Point 26.7 ownership statement matches the actual codebase.

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
- a large `ValidationProfile` abstraction unless implementation evidence shows it materially removes duplicated generic ReqShield mechanics.

Foundation's current setter-based `ValidatorFactory` may remain application adaptation in this pass. A future immutable ReqShield profile object is optional ergonomic follow-up, not a 3.2 completion blocker.

---

## 15. Implementation order

1. Raise DBLayer dev/reference floor to `^5.1` and confirm baseline QA.
2. Correct the integer correlation-ID public/static contract.
3. Promote/refactor the DBLayer reference provider into production source with resolver-first connection ownership.
4. Port the existing DB integration tests to the production provider and add nullable-ignore/runtime-lifetime regressions.
5. Add the instance-owned freezeable `SchemaRegistry` and its isolation tests.
6. Audit validator/compiled-validator persistent-runtime state and close any discovered leaks.
7. Complete docs and benchmarks.
8. Run PHP 8.4/8.5 stable + lowest QA/static-analysis gates.
9. Release ReqShield 3.2.
10. Return to Foundation 26.7, consume 3.2, remove duplicated bridge/registry mechanics, and run Foundation acceptance/performance gates.

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
- non-DB validation remains DB-cold;
- sequential/Fiber reuse does not leak mutable validation state;
- QA/static analysis and representative performance gates are green;
- Foundation can delete its duplicate DB provider/schema-registry mechanics without moving application policy into ReqShield.

After that, Foundation Point 26.7 can close on top of ReqShield 3.2 rather than carrying framework-local substitutes for generic validation/database integration mechanics.
