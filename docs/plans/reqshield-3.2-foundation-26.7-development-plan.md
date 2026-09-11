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
- [ ] Filesystem/path containment remains Pathwise responsibility.
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

### 9.4 Process/capability boundary tests

These tests protect ReqShield from drifting into a false sandbox role:

- [ ] ordinary string fields can validly contain text such as `exec`, `system`, `pcntl_fork`, `posix_kill` when the schema permits ordinary strings;
- [ ] no global sanitizer silently removes or rewrites dangerous-function-like substrings;
- [ ] a schema-defined allowlist/enum can reject an unregistered operation identifier and accept a registered one;
- [ ] validation of an operation identifier does not execute, resolve or inspect an executable;
- [ ] authorization/process execution remains outside ReqShield test fixtures except for framework-neutral mocked application examples.

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
- [ ] document that validation is not a process/PHP sandbox and dangerous-function-name filtering is intentionally out of scope;
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
- [ ] DB connection/profile selection;
- [ ] authorization and selection of registered privileged operations/capabilities.

### Keep outside ReqShield

- [ ] Pathwise owns path/filesystem containment and upload/storage path safety.
- [ ] A dedicated low-level process/runtime library owns safe executable/argv handling, process lifecycle, signals, environment/cwd policy, privilege changes and sandbox integration.
- [ ] Foundation owns which process/runtime profile or registered operation is exposed to application code.
- [ ] OS/container/runtime configuration remains the final execution-security boundary for untrusted code.

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
- dangerous PHP function blacklists;
- source-code/upload scanning for `exec`, `system`, `shell_exec`, `proc_open`, `pcntl_*`, `posix_*` or similar function names;
- shell command sanitization/escaping APIs;
- process spawning/execution APIs;
- process/signal/UID/GID/sandbox policy;
- executable allowlists or command registries owned by ReqShield;
- authorization logic;
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
7. Lock/document the process/runtime-security boundary and add drift-prevention tests showing ReqShield validates structured intent rather than dangerous-function substrings.
8. Complete docs and benchmarks.
9. Run PHP 8.4/8.5 stable + lowest QA/static-analysis gates.
10. Release ReqShield 3.2.
11. Return to Foundation 26.7, consume 3.2, remove duplicated bridge/registry mechanics, and run Foundation acceptance/performance gates.

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
- ReqShield explicitly remains validation-only for process-related inputs: it validates structured intent/parameters but does not blacklist dangerous function names, authorize capabilities, execute processes or claim to sandbox code;
- QA/static analysis and representative performance gates are green;
- Foundation can delete its duplicate DB provider/schema-registry mechanics without moving application policy into ReqShield.

After that, Foundation Point 26.7 can close on top of ReqShield 3.2 rather than carrying framework-local substitutes for generic validation/database integration mechanics.

---

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
Pathwise, where an artifact/path is involved
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
Pathwise: canonical filesystem/storage containment
Foundation: authorization to use that artifact for this operation
Runwire: process invocation
```

Do not duplicate Pathwise traversal/symlink/storage-root mechanics inside ReqShield process-operation schemas.

---

### 17.8 Uploaded PHP/source code

ReqShield is not a source-code malware scanner.

Do not scan upload/body contents for occurrences of dangerous PHP APIs and call that a sandbox.

If an application accepts source code as data, ReqShield may validate metadata and generic size/shape constraints. Pathwise handles storage safety. Execution, if ever allowed, must go through Foundation authorization and a separately isolated Runwire/OS execution profile.

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
Pathwise artifact lookup
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
Pathwise    resolves filesystem/storage artifact where needed
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
- [ ] Pathwise remains filesystem trust owner where files are involved.

All DBLayer, SchemaRegistry, runtime-state, QA, benchmark and Foundation 26.7 criteria from earlier sections of this plan remain unchanged.
