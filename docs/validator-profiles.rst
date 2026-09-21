Validator Profiles
==================

``Infocyph\ReqShield\Support\ValidatorProfile`` is a small immutable value
object for normalizing and applying ReqShield-native validator options. It is
framework-neutral: configuration source order, named application schemas, DI,
HTTP mapping and database-connection selection remain caller responsibilities.

Basic Use
---------

.. code-block:: php

    use Infocyph\ReqShield\Support\ValidatorProfile;
    use Infocyph\ReqShield\Validator;

    $profile = ValidatorProfile::fromArray([
        'fail_fast' => true,
        'strip_unknown' => true,
        'sanitizers' => [
            'email' => ['trim', 'lowercase'],
        ],
        'casts' => [
            'age' => 'integer',
        ],
        'limits' => [
            'max_fields' => 100,
        ],
    ]);

    $validator = $profile->apply(Validator::make($rules, $databaseProvider));

Profiles are sparse. Only options supplied by the caller are stored, so a base
profile can be normalized once and overlaid with schema/request-specific
options without accidentally restoring unrelated defaults.

Immutable Overlay
-----------------

.. code-block:: php

    $base = ValidatorProfile::fromArray([
        'messages' => ['email.required' => 'Email is required.'],
        'limits' => ['max_depth' => 64, 'max_fields' => 500],
    ]);

    $derived = $base->overlay([
        'messages' => ['email.email' => 'Email is invalid.'],
        'limits' => ['max_fields' => 100],
    ]);

Map options ``aliases``, ``casts``, ``limits``, ``locale_packs``, ``messages``
and ``sanitizers`` merge by key. Scalar options replace the base value.

Supported Options
-----------------

* ``fail_fast`` and ``stop_on_first_error``
* ``aliases`` and ``messages``
* ``sanitizers`` and ``casts``
* ``locale`` and ``locale_packs``
* ``nested`` and ``nested_mode``
* ``allow_unknown``, ``strict`` and ``strip_unknown``
* ``dto``
* ``throw_on_failure``
* ``limits.max_depth``
* ``limits.max_fields``
* ``limits.max_wildcard_expansions``
* ``limits.max_flattened_paths``

``nested_mode=required`` is normalized to ReqShield's ``targeted`` mode for
Foundation compatibility. Unknown top-level options and unknown limit keys
fail explicitly with ``InvalidValidatorProfileException``.

Unknown-Field Precedence
------------------------

When several unknown-field controls are present, application order is
deterministic:

1. ``strip_unknown=true`` wins;
2. otherwise ``strict=true`` wins;
3. otherwise an explicit ``allow_unknown`` value is applied.

This preserves the existing Foundation adapter behavior while moving the
generic option semantics into ReqShield.

Runtime Boundary
----------------

Profile construction and application perform no database I/O and retain no
request data. A profile configures a validator; database rules still use the
validator's caller-supplied ``DatabaseProvider`` only when those rules execute.

Callables supplied as sanitizer configuration remain caller-owned objects.
ReqShield does not freeze or serialize external callable state.
