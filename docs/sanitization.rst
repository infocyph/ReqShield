Sanitization
============

ReqShield includes a powerful ``Sanitizer`` utility class with over 50 methods for cleaning and normalizing data.

It's highly recommended to **sanitize your data *before*** validation.

Using the ``Sanitizer`` Class
----------------------------

All methods are static and can be called directly.

.. code-block:: php

    use Infocyph\ReqShield\Sanitizer;

    $rawInput = [
        'email' => '  TEST@EXAMPLE.COM  ',
        'username' => '  john_doe!  ',
        'age' => '25.5',
        'terms' => 'on',
        'bio' => '<script>alert("xss")</script> safe text',
    ];

    // Sanitize each piece of data
    $cleanInput = [
        'email' => Sanitizer::email($rawInput['email']),           // 'TEST@EXAMPLE.COM'
        'username' => Sanitizer::alphaDash($rawInput['username']), // 'john_doe'
        'age' => Sanitizer::integer($rawInput['age']),             // 25
        'terms' => Sanitizer::boolean($rawInput['terms']),         // true
        'bio' => Sanitizer::string($rawInput['bio']),              // 'safe text'
    ];

    // Now, validate the clean input
    $result = $validator->validate($cleanInput);

Helper Function
---------------

You can also use the global ``sanitize()`` helper function.

.. code-block:: php

    // Single sanitizer
    $cleanEmail = sanitize('  TEST@ex.com  ', 'email');

    // Chain multiple sanitizers
    $cleanBio = sanitize('  <b>BIO</b>  ', ['string', 'lowercase']); // 'bio'

Available Sanitizers
--------------------

Here is a partial list of the most common sanitizers.

Basic Types
~~~~~~~~~~~
* ``string($value)``: Strips tags and trims whitespace.
* ``integer($value)``: Converts to an integer.
* ``float($value)``: Converts to a float.
* ``boolean($value)``: Converts 'yes', 'on', '1', true to ``true``.
* ``email($value)``: Strips characters not allowed in emails.
* ``url($value)``: Strips characters not allowed in URLs.

Case Conversions
~~~~~~~~~~~~~~~~
* ``lowercase($value)``
* ``uppercase($value)``
* ``camelCase($value)``
* ``pascalCase($value)``
* ``snakeCase($value)``
* ``kebabCase($value)``
* ``titleCase($value)``

Text Processing
~~~~~~~~~~~~~~~
* ``trim($value)``: Trims whitespace.
* ``slug($value)``: Converts a string to a URL-friendly slug.
* ``truncate($value, $length)``: Trims string to a length.
* ``normalizeWhitespace($value)``: Collapses multiple spaces.

Special Formats
~~~~~~~~~~~~~~~
* ``phone($value)``: Keeps only digits and the ``+`` sign.
* ``currency($value)``: Removes currency symbols and commas.
* ``filename($value)``: Removes path traversal and unsafe characters.
* ``domain($value)``: Removes protocol and paths.

Security & HTML
~~~~~~~~~~~~~~~
* ``htmlEncode($value)``: Escapes HTML entities (for displaying user content).
* ``htmlDecode($value)``: Decodes HTML entities.
* ``stripTags($value, $allowed)``: Removes HTML tags, optionally allowing some.
* ``removeXss($value)``: Removes ``<script>`` tags and ``onclick`` attributes.
