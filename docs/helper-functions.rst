Namespaced Helper Functions
===========================

ReqShield does not define generic global functions. Import helpers explicitly
from the package namespace, or use ``Validator::make()`` directly.

.. code-block:: php

    use function Infocyph\ReqShield\fails;
    use function Infocyph\ReqShield\passes;
    use function Infocyph\ReqShield\sanitize;
    use function Infocyph\ReqShield\validate;
    use function Infocyph\ReqShield\validator;

    $validator = validator(['email' => 'required|email']);
    $result = validate(['email' => 'required|email'], $data);
    $email = sanitize('  TEST@example.com  ', ['trim', 'lowercase']);

    if (passes($rules, $data)) {
        // Valid input.
    }

    if (fails($rules, $data)) {
        // Invalid input.
    }

Database-backed schemas accept a ``DatabaseProvider`` as the optional final
argument to ``validator()``, ``validate()``, ``passes()``, and ``fails()``.
