# ReqShield 3.2 — Runwire Process/Runtime Boundary Addendum

## Status

Parent plan: `docs/plans/reqshield-3.2-foundation-26.7-development-plan.md`

Runwire plan: `infocyph/Runwire` → `docs/plans/runwire-1.0-foundation-3-launch-plan.md`

This addendum replaces the parent plan's provisional references to a separate/future process-runtime library with the concrete Infocyph library **Runwire 1.0**.

It does **not** add a ReqShield → Runwire dependency. ReqShield remains a framework-agnostic validation/sanitization/schema library.

---

# 1. Final ownership chain

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

# 2. ReqShield owns data/intent validation only

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

# 3. No dangerous-function blacklist

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

# 4. No shell escaping/sanitizer API

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

# 5. Operation allowlisting stays generic

If ReqShield has or gains a generic enum/allowlist rule, Foundation may use it for configured operation identifiers.

Example concept:

```text
operation ∈ {image.thumbnail, pdf.inspect, git.status}
```

That rule remains a generic validation primitive.

Do not introduce Runwire-specific rule classes into ReqShield merely to express it.

Foundation remains responsible for ensuring that the validated identifier is authorized and mapped to a trusted operation definition.

---

# 6. Structured arguments, not command text

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

# 7. Path/file inputs

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

# 8. Uploaded PHP/source code

ReqShield is not a source-code malware scanner.

Do not scan upload/body contents for occurrences of dangerous PHP APIs and call that a sandbox.

If an application accepts source code as data, ReqShield may validate metadata and generic size/shape constraints. Pathwise handles storage safety. Execution, if ever allowed, must go through Foundation authorization and a separately isolated Runwire/OS execution profile.

A forked Runwire child is not made safe by ReqShield content filtering.

---

# 9. No Runwire dependency

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

# 10. Database validation remains independent

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

# 11. Persistent-runtime isolation

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

# 12. Foundation operation-schema example

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

# 13. Boundary tests

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

# 14. Documentation wording

Normalize final ReqShield 3.2 docs so the concrete ecosystem boundary reads:

```text
ReqShield   validates structured data/intent
Foundation  authorizes capability/operation
Pathwise    resolves filesystem/storage artifact where needed
Runwire     executes/supervises process/runtime mechanics
OS          supplies final hostile-code sandbox boundary
```

Replace provisional “future process runtime” / `ProcessGuard` wording with **Runwire** where referring to the Infocyph implementation.

Do not describe Runwire as a ReqShield requirement.

---

# 15. Non-goals clarification

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

# 16. Completion gate addendum

ReqShield 3.2 process/runtime-boundary acceptance additionally requires:

- [ ] Runwire is named as the process/runtime owner in ecosystem integration documentation;
- [ ] ReqShield has no production dependency on Runwire;
- [ ] ordinary strings are not rejected because they contain process/PHP function names;
- [ ] no shell/process sanitizer is marketed as a sandbox;
- [ ] generic enum/allowlist/structured validation is sufficient for Foundation operation schemas;
- [ ] persistent Runwire worker deployment does not cause schema/result/DB-provider state leakage;
- [ ] Foundation owns end-to-end authorization before Runwire invocation;
- [ ] Pathwise remains filesystem trust owner where files are involved.

All DBLayer, SchemaRegistry, runtime-state, QA, benchmark and Foundation 26.7 criteria from the parent plan remain unchanged.