Handling the Validation Result
==============================

``validate()`` returns a final, readonly ``ValidationResult``. Validation is
complete when the result is created; it cannot be mutated or merged later.

.. code-block:: php

    $result->passes();
    $result->fails();

    $result->validated();
    $result->typed();
    $result->only(['email', 'name']);
    $result->except(['password']);
    $result->safe(['optional_field']);
    $result->has('email');
    $result->get('email');

Errors and Failure Metadata
---------------------------

.. code-block:: php

    $result->errors();
    $result->errorsFor('email');
    $result->first('email');
    $result->firstError();
    $result->failures();
    $result->failuresFor('email');

Failure entries contain ``field``, ``rule``, ``message``, and ``value``.
API formatters include ``toFlatErrors()``, ``toApiErrors()``,
``toJsonApiErrors()``, and ``toProblemJson()``.

Typed Input and DTOs
--------------------

.. code-block:: php

    $input = $result->input();
    $age = $input->int('age');
    $status = $input->enum('status', OrderStatus::class);
    $dto = $result->toDTO();

Invalid fixed cast definitions or incompatible values throw ``CastException``;
they are not silently changed to ``0`` or ``0.0``. Generic collection
transformations belong in normal PHP after reading ``validated()`` or
``typed()``.

Use ``$result->throw()`` to raise ``ValidationException`` for an invalid request.
