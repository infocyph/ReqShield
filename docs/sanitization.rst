Sanitization and Normalization
==============================

ReqShield sanitizers perform deterministic input normalization and filtering.
Schema pipelines are normalized, resolved, and cached before validation;
wildcard patterns are also prepared before the hot path.

.. code-block:: php

    $validator = Validator::make([
        'email' => [
            'rules' => 'required|email',
            'sanitize' => ['trim', 'lowercase'],
        ],
    ])->setSanitizers([
        'contacts.*.email' => ['trim', 'lowercase'],
    ]);

For direct use:

.. code-block:: php

    use Infocyph\ReqShield\Sanitizer;
    use function Infocyph\ReqShield\sanitize;

    $email = Sanitizer::email($value);
    $slug = Sanitizer::slug($title);
    $normalized = sanitize($value, ['trim', 'lowercase']);

The retained operations cover scalar normalization, case conversion, text
processing, phone/currency/filename/domain formats, alphanumeric filtering,
HTML encoding/decoding, ``stripTags``, SQL-LIKE escaping, base64/JSON, and array
normalization.

HTML and Escaping Utilities
---------------------------

``htmlEncode()``, ``htmlDecode()``, and ``stripTags()`` are utilities, not a
security boundary. ReqShield deliberately has no ``removeXss()``,
``removeSqlPatterns()``, or ``stripUnsafeTags()`` APIs because those names imply
guarantees a generic filter cannot provide.

Use prepared, parameterized queries for SQL security. Use contextual escaping
at the output sink for HTML, JavaScript, URL, and attribute contexts. Use a
dedicated HTML purifier when rich user-authored HTML is allowed. Authorization
and CSRF protection belong to the application or HTTP framework layer.

``escapeLike()`` escapes wildcard characters for a LIKE value; it does not make
an SQL statement safe. Bind its result as a query parameter.
