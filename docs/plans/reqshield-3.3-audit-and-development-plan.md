# ReqShield audit and development plan

Audit date: 2026-10-08 (Asia/Dhaka). Audited revision: `dcee175363e678f463fbad9224dcd83e5ad10338`, tagged `3.2`.

**Changes are required.** Existing quality checks pass, but focused adversarial probes reproduce validation bypasses, unsafe output, unintended network access, and database comparison defects. This section preserves the original audit findings and implementation plan; subsequent PR #17 commits now include remediation, Runwire integration and package updates. Refer to the completion tracker for current status.

This plan follows [PHPForge engineering principles](https://github.com/infocyph/PHPForge/blob/main/resources/engineering-principles.md) and its [agent workflow](https://github.com/infocyph/PHPForge/blob/main/resources/AGENTS.md): preserve security and contracts, change the existing owner, justify new types, separate required fixes from optional improvements, and measure representative successful request throughput before accepting hot-path changes.

## Agreed release target

Ship **one 3.3.0 release** containing all required security and correctness repairs (R01–R11), optional passed-instance Runwire 2.1.1 integration, DBLayer 6.0 support, and the complete compatible package/tooling refresh in batches A–E. This follows the maintainer's preference for a single release; the batches are implementation checkpoints, not separate release milestones.

Preserve public validator signatures, named parameters, flat result keys and custom database provider contracts. **Explicit maintainer decision (2026-10-09): no compatibility or fallback support for DBLayer 5.x or older ArrayKit versions.** The optional native DBLayer bridge supports DBLayer >=6.0 and the compatible ArrayKit >=5.3 dependency line; Composer must reject legacy installed versions. Document newly rejected ambiguous or unsafe inputs. A major release is unnecessary for the planned additive APIs and repairs. If implementation reveals an unavoidable breaking contract change, revise the version decision explicitly rather than silently including it in a minor release.

Use one final acceptance gate on the same candidate commit and resolved package set. Do not publish an intermediate patch or partial feature release. As of the audited `055b22b5` code candidate, the full hosted acceptance suite passes, including correct-HTTP FPM RPM and a 300-second Runwire soak. The PR stays draft, unmerged, and untagged; application-specific deployment checks and post-documentation final-head checks remain distinct from the hosted certification.

## Audit scope and verified evidence

Reviewed the validation pipeline, nested/wildcard traversal and caching, conditional dependencies, unknown-field policies, sanitization and casts, result/DTO boundaries, rule snapshots and frozen topology, schema export, upload/MIME/image rules, regex/DNS rules, database provider contracts and SQL batching, helper/bootstrap behavior, Composer metadata, benchmarks, documentation, and CI. Automated detectors scanned 238 PHP files. There are 190 production source files; independent public rules and provider/security boundaries justify many small files, so file count alone is not a defect.

| Check | Current result |
| --- | --- |
| PHPForge doctor, configuration discovery, active configuration | Healthy; PHP 8.4/8.5 CI matrix; active complexity limits class 80/function 12 |
| `composer validate --strict`, platform check | Passed on the audit host |
| `composer ic:tests:details` | Passed all configured checks on PHP 8.5.4; Pest: 306 tests, 3,841 assertions |
| Final `composer ic:release:guard` | Passed on the audited revision; abandonment reported as non-blocking; the newly reproduced defects still block the proposed release |
| Live `composer audit --locked --format=json` | Zero advisories; one abandoned development package, `doctrine/annotations`, required by PHPBench 1.7.0; default Composer exits 1 for abandonment |
| Isolated consumer with released DBLayer 6.0 and Runwire 2.1.1 | Existing suite passed: 306 tests, 3,841 assertions, zero failures/errors/skips on PHP 8.5.4 and PHP 8.4.26 |
| Live audit of isolated runtime dependencies | Zero advisories and zero abandoned packages |
| Production-only authoritative Composer consumer | Passed on PHP 8.4.26 and PHP 8.5.11 with native required extensions, without DBLayer or Runwire |
| Actual PostgreSQL 18 and MySQL 9.7 probes | Reproduced R04 using released DBLayer 6.0 |
| Localhost HTTP image probe | Reproduced R05; the rule issued an HTTP GET and returned a passing result |
| Passed Runwire context through an already-bound DBLayer connection | Query succeeds; DBLayer restores its binding and leaves the host request open; cancellation becomes a ReqShield database exception |

The isolated packages came from published Composer archives, not sibling development checkouts. Their source references match the live peeled release tags: DBLayer 6.0 `85b9a6373628692f1bf74ba20a31649fad767f2a`; Runwire 2.1.1 `745b1c2bd7caa56aa5abf6d742c1aa8318fec494`.

The suite's green result does not cover the new probes below. MariaDB/MSSQL, lowest-dependency combinations, final hosted CI, sustained host RPM, and persistent-worker soak acceptance remain unverified. Existing PHPBench timing coverage is useful supporting evidence; it is not an end-to-end RPM certificate. No advisory result guarantees the absence of application vulnerabilities.

## Required findings

Severity describes the demonstrated library behavior and exposure conditions, not a CVSS assessment.

| ID | Priority | Reproduced behavior | Existing owner |
| --- | --- | --- | --- |
| R01 | High, security | Different input shapes produce identical shape signatures; a warm wildcard validator accepts a non-integer that a fresh validator rejects | `Support/NestedValidator.php:418`, `Concerns/HasValidatorInternals.php:739` |
| R02 | High, security | `stripUnknown()` removes flattened children but leaves them inside a validated parent array; `exclude` and failed children also remain reachable through parent values in `validated()`, `typed()`, and derived output | `Concerns/HasValidatorRequestFeatures.php:133`, `Validator.php:735`, result construction |
| R03 | High, security | A flat dotted key shadows a conflicting nested value; validation passes while the parent array still contains the invalid value | `Support/NestedValidator.php:106`, `Services/SanitizerMapApplier.php`, input preparation |
| R04 | High, correctness/availability | Batched predicates lose the original database parameter typing; PostgreSQL rejects integer comparisons, and MySQL changes comparison results when mixed candidates share a derived column | `Bridge/DBLayerDatabaseProvider.php:252` and `:295` |
| R05 | High, security | `image`/`dimensions` pass an untrusted `tmp_name` directly to `getimagesize()`, which can fetch URLs; empty/NUL paths throw `ValueError`; failed-upload image metadata is accepted | `Rules/AbstractImageFileRule.php:10`, upload source helpers |
| R06 | High for conditional bypass; medium for `distinct` | Associative wildcard keys do not bind conditional dependencies, so a required field can be skipped; valid distinct associative entries are rejected | `Support/NestedValidator.php:309`, `Rules/Distinct.php` |
| R07 | Medium, data integrity | Integer casting accepts an integral out-of-range float and produces a wrapped integer with a PHP warning instead of `CastException` | `Support/InputCaster.php:85` and its consumers |
| R08 | Medium, resource limits | Limits apply before sanitization only; the built-in JSON decoder expands one input string into an array exceeding `maxFields` without rejection | `Validator.php:795`, input preparation and `Services/SanitizerMapApplier.php` |
| R09 | Medium, public feature compatibility | `Validator::compile(['file' => new SecureFile()])` throws because the built-in composite's clone shares its nested rule instances | `Rules/SecureFile.php`, `Support/RuleDefinitionSnapshot.php:44` |
| R10 | Medium, schema correctness | Exporting `items.* => required|integer` drops the integer leaf schema and generates object items | `Services/JsonSchemaNodeBuilder.php:85` |
| R11 | Medium, strict-policy correctness | `strict()` silently ignores unregistered integer top-level keys while rejecting unregistered string keys | `Concerns/HasValidatorRequestFeatures.php:176` |

### Reproduction details

R01 uses an encoding collision, not a brute-force hash collision. Shape serialization emits delimiter-bearing keys without length framing. These inputs have the same current signature:

```php
$first = ['items' => ['a' => 1, 'b' => 2]];
$second = ['items' => ['a;s;k:b' => 'INVALID']];
$validator = Validator::make(['items' => 'required|array', 'items.*' => 'integer']);
$validator->validate($first);       // populates the wildcard plan cache
$validator->validate($second);     // incorrectly passes
```

R02: `['user' => 'array', 'user.name' => 'required|string']` with `stripUnknown()` and input `['user' => ['name' => 'ok', 'is_admin' => true]]` passes and returns `is_admin` inside the validated `user`. Adding `user.secret => exclude` similarly retains a supplied secret in that parent. An invalid `user.age` disappears from the direct flat key but remains inside `user`.

R03: `['user' => 'array', 'user.age' => 'integer']` accepts `['user' => ['age' => 'INVALID'], 'user.age' => 1]`, then exposes both conflicting representations.

R04 compares separate one-candidate calls with one two-candidate call on a table containing `(id = 1, token = '001')`:

| Database / column / candidates | Individual failed IDs | Batch result |
| --- | --- | --- |
| PostgreSQL / integer `id` / `[1, 2]` | `[1]` | SQLSTATE 42883: `integer = text` |
| MySQL / varchar `token` / `[1, 'unused']` | `[1]` | Incorrectly fails `[0, 1]` |

The derived candidate `UNION ALL` chooses a common SQL type before the predicate executes. SQL-native `EXISTS` alone does not preserve the original bound-value comparison semantics.

R05 was tested against an audit-owned localhost image server. `['upload' => 'image']` accepted `['upload' => ['tmp_name' => 'http://127.0.0.1:<port>/private-image']]` and fetched the resource. This is an SSRF surface when applications validate attacker-supplied upload-shaped arrays. PHP explicitly documents remote URL support for [getimagesize](https://www.php.net/manual/en/function.getimagesize.php); its documentation also cautions against using it alone as proof of valid image content. Filesystem containment remains the host/Pathwise responsibility, but ReqShield must prevent an image validation rule from opening arbitrary network URLs.

R06: `items.*.value => required_if:items.*.enabled,1` correctly rejects a missing value with `['items' => [['enabled' => '1']]]`, but accepts `['items' => ['alice' => ['enabled' => '1']]]`. Two different values under `items.alice.code` and `items.bob.code` fail `distinct` despite being unique.

R07: a `numeric` field with an integer cast accepts `1.0e30`, returns `5076964154930102272` on the audited 64-bit host, and emits a representability warning. The exact wrapped number is architecture dependent; rejection must be portable.

R08: `payload.* => integer`, `limits(maxFields: 3)`, and `setSanitizers(['payload' => ['jsonDecode']])` accept a JSON string encoding twenty array entries and return all twenty validated paths.

R09 uses the concrete `Rules\SecureFile` object; the string token can be recompiled successfully. R10's exported item schema is currently `type: object, properties: []`, rather than `type: integer`. R11 can be reproduced with `['name' => 'ok', 0 => 'unregistered']` against a strict name-only schema.

## Batch A — input identity, wildcard rules, and resource bounds

Resolve R01, R03, R06, R08, and R11 before adding cooperative yields.

- Encode shape keys with unambiguous length/type framing and explicit structural markers. A cache lookup that decides which input fields get validated must use a collision-resistant digest such as SHA-256, or verify the complete canonical shape before reuse. Changing the digest alone does not repair the delimiter collision. Preserve bounded, value-free caches and avoid storing request payloads.
- Reject conflicting dotted/nested representations before sanitizers, conditional callbacks, or validation. Preserve unambiguous existing flat-only and nested-only inputs; document the policy for identical duplicate representations and literal dots in associative keys.
- Track wildcard captures from the original schema's wildcard positions. Do not infer captures from every numeric segment in the expanded path. Bind associative keys, multiple wildcard levels, and fixed numeric segments correctly. Test all cross-field rule families that consume dependencies.
- Give `distinct` the correct expansion group, including associative keys. Its current per-field scan also scales quadratically: the local 250/500/1,000/2,000-row diagnostic took approximately 8/24/78/250 ms. These are single-run diagnostics, not release-performance claims. Prefer one execution-local count/index per actual distinct group if representative measurements justify it; preserve comparison semantics and never cache values across requests.
- Recheck effective input limits after structure-changing sanitizers and before recursive wildcard sanitizer traversal, shape hashing, expansion, or flattening. Use the existing limit owner; do not add a chain of limit wrappers. Test built-in JSON decoding, a shape-expanding custom callback, deep/cyclic results, and output at/above each boundary.
- Include integer root keys in strict/strip-unknown decisions and consistent failure paths. Avoid silently discarding them from policy checks.

Acceptance: every bypass reproduction fails safely; warm/fresh, flat/nested, targeted/all, mutable/compiled, and sequential/Fiber executions agree. Unambiguous supported inputs keep their existing result shape. Relevant configured limits stop work before recursive expansion.

## Batch B — safe result projection and uploads

Resolve R02 and R05 inside the current owners.

- Propagate stripped, excluded, and failed descendant paths into validated parent arrays. Keep the established flat result-key contract and preserve otherwise permitted parent contents. Ensure `typed()`, `safe()`, selection helpers, `input()`, and DTO mapping cannot recover removed values through an ancestor. Test parent/child rule order, wildcards, after-callback failures, database failures, and both unknown-field modes.
- Guard every image/dimension I/O entry independently. A preceding `file` error does not guarantee later rules are skipped in collect-all mode. Reject remote/non-file wrapper paths, empty/NUL paths, and failed upload statuses before I/O. Convert malformed source inputs into validation failures.
- Preserve genuine local array uploads and host-provided PSR-7 uploaded-file streams. Reading a supported stream must be bounded and preserve its cursor; do not blindly reopen its metadata URI as a network resource or reject all memory streams. Verify behavior against an independent PSR-7 implementation in development, without adding a concrete implementation to runtime dependencies.
- Keep containment, quarantine, malware scanning, storage publication, and HTTP policy with their existing host owners. The repair adds an I/O source boundary, not a new filesystem trust framework.

Acceptance: unknown/excluded/failed descendants are unreachable from all safe output paths; malformed image sources fail; neither `image` nor `dimensions` issues an HTTP request, including under collect-all validation. Valid local/PSR-7 cases remain supported and cursor-safe.

## Batch C — database semantics, casts, and existing public features

Resolve R04, R07, R09, and R10; complete the core repairs for the unified 3.3.0 release.

- Replace the shared typed candidate column with a bounded SQL projection that compares each original bound candidate inside its own database predicate. A candidate implementation is `UNION ALL` of candidate-index projections guarded by independently bound `EXISTS` queries. This retains SQL collation/coercion behavior and avoids PHP value re-matching.
- Account for every binding in `safeBatchSize()`, including repeated ignore predicates if the selected SQL shape repeats them. Keep identifier validation, typed deduplication, null behavior, qualified columns, the 128-candidate width limit, and query-builder-only fallback under restricted raw SQL policies. Revise query-count expectations only when actual binding costs change, with documented evidence.
- Run direct-versus-batched parity for exists and unique on SQLite, PostgreSQL, MySQL/MariaDB, and MSSQL when supported. Include text/numeric mixtures, boolean/null values, collation, ignored IDs including zero, soft deletes, qualified identifiers, restricted SQL policies, and parameter ceilings. Measure indexed query plans on representative data.
- Reject out-of-range integral floats before integer conversion. Preserve `tryInteger()`'s null-on-invalid contract and normal cast exceptions; test positive/negative boundaries, infinities, NaN, fractional values, and integer strings under PHP 8.4/8.5 with `E_ALL`.
- Implement correct cloning for `SecureFile`'s owned rule objects, rather than weakening snapshot isolation. Test object-based registry/compiled forms and unchanged string forms.
- Apply terminal wildcard property schemas to the array item node. Verify scalar, nullable, object, nested, and multi-wildcard exports and preserve constraints/required placement.

Acceptance: SQL batch answers match original single-candidate predicates on each supported engine; no PHP equality fallback or unsafe identifier acceptance; affected regressions and all required PHPForge checks pass. No warning accompanies valid/invalid integer boundary handling.

## Batch D — optional passed-instance Runwire 2.1.1 support

Runwire can benefit this library through cancellation/deadlines, cooperative fairness for large validation jobs, and forwarding the active execution context to DBLayer 6.0. It supplies no DNS-resolution capability in its runtime capability enum; wrapping `checkdnsrr()` in a coroutine does not make it nonblocking. Do not claim accelerated ordinary scalar validation or asynchronous PDO/image/DNS I/O without evidence.

### Public boundary and dependency policy

- Add an explicit `validateWithRunwire(array $data, RuntimeContext $runtime, ?RequestContext $request = null, ?CoroutineScope $scope = null): ValidationResult` entry point on `Validator` and `CompiledValidator`. This is an optional interoperability boundary; preserve existing `validate(array $data)`, subclass signatures, helper functions, profiles, and `Contracts\Rule`/`Contracts\DatabaseProvider` unchanged.
- Keep all execution binding/checkpoint state method-local. Both entry points use one cohesive execution implementation; do not clone an entire compiled validator per request. Do not save the request/scope in a reusable profile, registry, static cache, or worker-wide compiled validator.
- Test against `infocyph/runwire: 2.1.1` in `require-dev`, with a runtime `suggest` entry. Use no hard runtime dependency. Consumers without Runwire use the existing `validate()` path and incur no runtime discovery/bootstrap work.
- For the existing final DBLayer bridge, optional trailing execution parameters on its two concrete batch methods can forward the same objects while still implementing the unchanged two-method provider interface. Keep the execution-specific provider selection in the existing executor; other providers continue their normal contract and receive host binding through their own caller-owned integration.

Proposed call flow, to implement and test:

```php
// Framework -> ReqShield
$result = $compiled->validateWithRunwire($data, $runtime, $request, $scope);

// Framework -> another library -> ReqShield
// The intermediary forwards the same objects, without constructing a new runtime.
$result = $compiled->validateWithRunwire($data, $runtime, $request, $scope);
```

### Lifecycle, capabilities, and cancellation

- Validate runtime PID and request/runtime object identity; reject completed requests and closed scopes before work and after any yield. Use a public scope guard that does not modify host task-local state. Do not require callers to fabricate worker slots or generations.
- Check both request and scope cancellation/deadline tokens. Neither source may replace or loosen another; downstream query budgets use the earliest effective deadline and compose with existing DBLayer query guards.
- Check before validation/normalization, external I/O and callbacks, at bounded intervals during large traversal/validation, and before returning read-only results. An initial target is every 256 work items, subject to measurement. Yield only through the passed scope when `RUNWIRE_COROUTINES` is available. Missing scope/capability keeps the synchronous path while retaining supplied cancellation guards.
- Propagate terminal cancellation as cancellation, rather than field validation errors, normal fallback, retries, or a generic database outage. The current `BatchExecutor` catches all provider exceptions, so its exception boundary needs explicit handling for the new bound path. Preserve ordinary database exception sanitization and previous exceptions.
- At the resolved DBLayer 6.0 `Connection` boundary, wrap the complete logical provider operation in `Connection::withRunwire($runtime, $callback, $request, $scope)`. Resolve once per operation, use that same connection for its chunks, restore binding in `finally`, and never lease/release a caller-owned connection.
- Preserve host-owned existing bindings and reject conflicting nested ownership. Do not change bindings while another Fiber is using the same connection; the host must supply correctly scoped connections. Test direct forwarding and intermediary forwarding with object identity assertions.
- Never create/start/stop `CoroutineRuntime`, run an event loop, spawn workers, alter process hooks, complete/cancel host requests, close host scopes, or reset host runtimes. Do not introduce parallel rule execution that changes fail-fast order or shares unsafe connections/custom rule state.
- Arbitrary callback or synchronous I/O work cannot be forcibly interrupted. Document cooperative checkpoint limits and avoid claiming transaction rollback or reversal of a callback's committed side effects.

Acceptance matrix: no Runwire installed; installed/unbound; bound/no capabilities; runtime-only; active request; active scope; both tokens; cancelled/expired tokens; completed request; closed scope; wrong runtime/PID; nested compatible/conflicting bindings; exception cleanup; two interleaved requests; actual persistent host and PHP-FPM. Host lifecycle objects remain usable after validation. The generic `DatabaseProvider` contract remains unchanged, but DBLayer 5.x is not supported and no compatibility fallback is provided.

## Batch E — all package upgrades, documentation, and the single release gate

- Require DBLayer `^6.0` in development and reject installed DBLayer `<6.0` at Composer resolution; do **not** add DBLayer 5.x compatibility jobs or fallback detection. The production DBLayer bridge remains opt-in because ReqShield supports validation without database rules.
- Refresh `infocyph/phpforge` to the current supported `dev-main` revision and update all compatible direct and transitive development packages together. Review upstream migration requirements, Composer conflicts, advisories, and abandonment; make necessary first-party configuration or compatibility repairs without weakening quality checks. Enforce the maintainer's explicit DBLayer 6 / ArrayKit 5.3 minimum support decision in the root package constraints; do not edit vendor code.
- Keep the Runwire development reference exactly `2.1.1`. Exercise the full optional dependency chain, including the ArrayKit and CacheLayer versions selected by DBLayer 6.0, without adding redundant direct runtime requirements. Recheck current upstream releases when implementing the refresh and record the final resolved versions and source references.
- DBLayer 6.0 remains optional **to install**; once installed, the native bridge requires DBLayer 6's `Connection::withRunwire()` directly. No DBLayer 5 fallback path or old ArrayKit compatibility code is accepted. Reject DBLayer `<6.0` and ArrayKit `<5.3` through Composer `conflict` without adding redundant mandatory production dependencies.
- Update README, installation, database rules, compiled validators, security boundaries, benchmark documentation, and a 3.3 upgrade guide together. Add one complete runnable example showing a host-owned context passed directly and through an intermediary, with a resolver returning the current host-owned connection. Mark new API examples as proposed until implemented.
- Investigate the abandoned PHPBench annotation dependency with the upstream-supported package/tooling path. It is development-only and PHPForge currently reports abandonment as non-blocking; do not suppress advisories or modify vendor files to produce a clean-looking audit.
- Keep structural work narrow. Public rule classes and extension/provider boundaries stay separate. Review duplicate built-in rule maps and unused legacy message/schema helpers in `HasValidatorRuntime` as optional follow-up work; protected subclass compatibility must be checked before removal. No new production type is currently justified by the plan.

### Required verification sequence

1. Add regressions for R01–R11 before fixing the affected behavior. Run narrow checks during each batch and preserve existing assertion strength.
2. For implementation batches, run `composer ic:process` sequentially, review its diff, then `composer ic:tests:details` and the final `composer ic:release:guard`. Keep every detector, supported scope, analysis level, complexity limit, and skip policy active. Do not absorb failures into a baseline or exemption.
3. Test supported PHP 8.4/8.5 and the next intended PHP upgrade target with `E_ALL`; test stable and lowest dependency combinations. Include the intended DB engine matrix and real PSR-7 compatibility.
4. Repeat clean production-only authoritative installs, both without optional packages and with the released optional dependencies. Validate documented examples and build Sphinx with warnings as errors.
5. Establish a stable end-to-end host baseline before hot-path changes. Use representative flat/nested/wildcard, valid/invalid, sanitization/casting, compiled reuse, distinct, and indexed database workloads; include ordinary FPM and a real host-owned Runwire execution path. Record cold and warm behavior separately.
6. Compare repeated median successful RPM at several bounded concurrency levels, with latency percentiles, errors/timeouts, CPU, continuous live process-tree RSS, query/binding counts, pool occupancy, and environment metadata. Enforce PHPForge's default 2% regression budget only on matching stable environments and semantically equivalent valid workloads. Never count bypassed validation as successful throughput or use a microbenchmark as host-RPM proof.
7. Run a bounded persistent-worker soak with request/task isolation, cancellation, tenant switching, cache churn, worker replacement, and failure recovery. A 300-second release run is a reasonable initial window; extend only when workload evidence warrants it. Require zero unexpected errors, no retained execution bindings, and bounded memory/resource growth.
8. Confirm configured hosted CI and release evidence on the exact final commit after all batches and package upgrades. Record SHA, resolved package versions/source references, test totals, host versions, and measurement artifacts. Revalidate the combined dependency set with a clean install and live advisory audit. A green suite cannot close an untested reproduction; a local check cannot certify an unrun hosted/performance gate. Publish/tag the single 3.3.0 release only after all required findings and gates are closed.

Rollback criteria: reopen the release gate for any reproduced bypass, source-induced network access, leaked request binding, changed SQL predicate result, unsafe output, warning/deprecation on supported valid paths, sustained RPM regression beyond the accepted budget, or breached latency/memory/downstream capacity limits.

## Completion tracking

| Batch | Deliverable | Status |
| --- | --- | --- |
| A | Input/cache/wildcard/limit repairs with regressions | **Batch QA passed** at `1c1e18e` (GitHub Actions [37862931807](https://github.com/infocyph/ReqShield/actions/runs/37862931807)): PHP 8.4/8.5, stable/lowest QA, static analysis and configured benchmarks green; final end-to-end host RPM and expanded cross-field performance remain release gates |
| B | Safe result projection and upload source boundary | **Batch QA passed** at `790f8a9`: [GitHub Actions 37863850902](https://github.com/infocyph/ReqShield/actions/runs/37863850902); PHP 8.4/8.5 stable/lowest QA, static analysis, and benchmarks green. Independent third-party PSR-7 and production-host probes remain final acceptance evidence |
| C | SQL/cast/snapshot/export repairs for 3.3.0 | **Batch QA passed** at `9135b8d`: [GitHub Actions 37864826678](https://github.com/infocyph/ReqShield/actions/runs/37864826678); PHP 8.4/8.5 stable/lowest QA, analyzers and configured benchmarks green. Cross-engine parity and production-host performance remain Batch E release evidence |
| D | Optional per-execution Runwire support | **Batch QA passed** at `8223ff9`: [GitHub Actions 37866171652](https://github.com/infocyph/ReqShield/actions/runs/37866171652). PHP 8.4/8.5 stable/lowest QA, analyzer, clean install and benchmark jobs green; actual persistent host soak remains release evidence |
| E | DBLayer 6+ / ArrayKit 5.3+ support floor, docs, and unified 3.3.0 acceptance | **Hosted release QA passed at `055b22b5`**: [PHPForge matrix](https://github.com/infocyph/ReqShield/actions/runs/37875840442), [release guard](https://github.com/infocyph/ReqShield/actions/runs/37875839892), [documentation](https://github.com/infocyph/ReqShield/actions/runs/37875839891), [MySQL 8.4/9.7 + PostgreSQL parity](https://github.com/infocyph/ReqShield/actions/runs/37875839909), [300-second Runwire host soak](https://github.com/infocyph/ReqShield/actions/runs/37875839938), and [correct-HTTP matched PHP-FPM throughput against exact 3.2](https://github.com/infocyph/ReqShield/actions/runs/37875839887) with stable successful RPM changes C1 −1.88%, C4 −1.80%, C8 −1.10% (all within unchanged 2% regression limit). DBLayer 5 / ArrayKit legacy compatibility remains intentionally removed. Deployment-specific real application/driver collation/resource evidence and exact final SHA revalidation after this documentation update remain release-review items; do not merge or tag |

The audit is historical evidence. Implementation batches A–D passed their hosted QA checkpoints, and Batch E's automated candidate gate passed at `055b22b5` (including steady correct-host RPM within the 2% allowance). The package-level implementation tracker is complete; separately record deployment-specific acceptance evidence and revalidate the final commit SHA after documentation updates. Keep PR #17 draft and leave merging, tagging, and publishing to the maintainer.
