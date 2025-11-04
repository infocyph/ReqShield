Sanitization
============

ReqShield includes a powerful ``Sanitizer`` utility class with over 50 methods for cleaning and normalizing data.

It's highly recommended to **sanitize your data *before*** validation to ensure clean, consistent input.

Using the ``Sanitizer`` Class
------------------------------

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

You can also use the global ``sanitize()`` helper function for quick sanitization.

.. code-block:: php

    // Single sanitizer
    $cleanEmail = sanitize('  TEST@ex.com  ', 'email');

    // Chain multiple sanitizers
    $cleanBio = sanitize('  <b>BIO</b>  ', ['string', 'lowercase']); // 'bio'

Sanitizer Options
-----------------

Some sanitizers accept additional parameters for customization:

.. code-block:: php

    // Truncate with custom length and suffix
    Sanitizer::truncate('Long text here', 10);              // 'Long text ...'
    Sanitizer::truncate('Long text here', 10, '---');       // 'Long text ---'

    // Truncate words with custom word count and suffix
    Sanitizer::truncateWords('Hello world test', 2);        // 'Hello world...'
    Sanitizer::truncateWords('Hello world test', 2, '>>>'); // 'Hello world>>>'

    // Currency with different formats
    Sanitizer::currency('$1,234.56', 'USD');                // 1234.56
    Sanitizer::currency('1.234,56€', 'EUR');                // 1234.56

    // Format currency for display
    Sanitizer::formatCurrency(1234.56, 'USD');              // '$1,234.56'
    Sanitizer::formatCurrency(1234.56, 'EUR');              // '€1.234,56'

    // Strip tags with allowed tags
    Sanitizer::stripTags('<b>Hello</b> <p>World</p>', '<b>'); // '<b>Hello</b> World'

    // Batch operations
    Sanitizer::batch(['  HELLO  ', '  WORLD  '], 'lowercase'); // ['  hello  ', '  world  ']

    // Apply multiple sanitizers in sequence
    Sanitizer::apply('  <b>HELLO</b>  ', ['string', 'lowercase']); // 'hello'

Complete Sanitizer Reference
-----------------------------

Basic Type Sanitizers
~~~~~~~~~~~~~~~~~~~~~

string($value)
^^^^^^^^^^^^^^
Strips HTML tags and trims whitespace.

.. code-block:: php

    Sanitizer::string('  <b>text</b>  '); // 'text'

integer($value)
^^^^^^^^^^^^^^^
Converts value to an integer.

.. code-block:: php

    Sanitizer::integer('123.45');  // 123
    Sanitizer::integer('abc123');  // 0

float($value)
^^^^^^^^^^^^^
Converts value to a float.

.. code-block:: php

    Sanitizer::float('123.45');    // 123.45
    Sanitizer::float('12.3.4');    // 12.3

boolean($value)
^^^^^^^^^^^^^^^
Converts value to boolean. Accepts 'yes', 'on', '1', true, 1 as ``true``. Accepts 'no', 'off', '0', false, 0 as ``false``.

.. code-block:: php

    Sanitizer::boolean('yes');     // true
    Sanitizer::boolean('on');      // true
    Sanitizer::boolean('off');     // false
    Sanitizer::boolean('no');      // false

email($value)
^^^^^^^^^^^^^
Removes all characters except letters, digits and ``!#$%&'*+-=?^_`{|}~@.[]``.

.. code-block:: php

    Sanitizer::email(' TEST@example.com '); // 'TEST@example.com'

url($value)
^^^^^^^^^^^
Removes all characters except valid URL characters.

.. code-block:: php

    Sanitizer::url('https://example.com/ test path'); // 'https://example.com/testpath'

Case Conversion Sanitizers
~~~~~~~~~~~~~~~~~~~~~~~~~~~

lowercase($value)
^^^^^^^^^^^^^^^^^
Converts string to lowercase.

.. code-block:: php

    Sanitizer::lowercase('HELLO'); // 'hello'

uppercase($value)
^^^^^^^^^^^^^^^^^
Converts string to uppercase.

.. code-block:: php

    Sanitizer::uppercase('hello'); // 'HELLO'

camelCase($value)
^^^^^^^^^^^^^^^^^
Converts string to camelCase.

.. code-block:: php

    Sanitizer::camelCase('hello world');      // 'helloWorld'
    Sanitizer::camelCase('foo_bar_baz');      // 'fooBarBaz'

pascalCase($value)
^^^^^^^^^^^^^^^^^^
Converts string to PascalCase.

.. code-block:: php

    Sanitizer::pascalCase('hello world');     // 'HelloWorld'
    Sanitizer::pascalCase('foo_bar_baz');     // 'FooBarBaz'

snakeCase($value)
^^^^^^^^^^^^^^^^^
Converts string to snake_case.

.. code-block:: php

    Sanitizer::snakeCase('Hello World');      // 'hello_world'
    Sanitizer::snakeCase('fooBarBaz');        // 'foo_bar_baz'

kebabCase($value)
^^^^^^^^^^^^^^^^^
Converts string to kebab-case.

.. code-block:: php

    Sanitizer::kebabCase('Hello World');      // 'hello-world'
    Sanitizer::kebabCase('fooBarBaz');        // 'foo-bar-baz'

sentenceCase($value)
^^^^^^^^^^^^^^^^^^^^
Capitalizes the first letter of the first sentence only.

.. code-block:: php

    Sanitizer::sentenceCase('hello world. new sentence.'); // 'Hello world. new sentence.'

titleCase($value)
^^^^^^^^^^^^^^^^^
Capitalizes the first letter of each word.

.. code-block:: php

    Sanitizer::titleCase('hello world');      // 'Hello World'

Text Processing Sanitizers
~~~~~~~~~~~~~~~~~~~~~~~~~~~

trim($value)
^^^^^^^^^^^^
Removes whitespace from the beginning and end of a string.

.. code-block:: php

    Sanitizer::trim('  hello  ');  // 'hello'

slug($value)
^^^^^^^^^^^^
Converts a string to a URL-friendly slug.

.. code-block:: php

    Sanitizer::slug('Hello World!');          // 'hello-world'
    Sanitizer::slug('PHP & JavaScript');      // 'php-javascript'

truncate($value, $length, $suffix = '...')
^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^
Truncates a string to the specified length.

.. code-block:: php

    Sanitizer::truncate('Long text here', 10);        // 'Long text ...'
    Sanitizer::truncate('Long text here', 10, '---'); // 'Long text ---'

truncateWords($value, $words, $suffix = '...')
^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^
Truncates a string to the specified number of words.

.. code-block:: php

    Sanitizer::truncateWords('Hello world, this is a test.', 3);        // 'Hello world, this...'
    Sanitizer::truncateWords('Hello world, this is a test.', 3, '>>>'); // 'Hello world, this>>>'

normalizeWhitespace($value)
^^^^^^^^^^^^^^^^^^^^^^^^^^^
Collapses multiple spaces into single spaces.

.. code-block:: php

    Sanitizer::normalizeWhitespace("hello \n world \t test"); // 'hello world test'

removeLineBreaks($value)
^^^^^^^^^^^^^^^^^^^^^^^^
Removes line breaks and replaces them with spaces.

.. code-block:: php

    Sanitizer::removeLineBreaks("hello\r\nworld"); // 'hello world'

stripWhitespace($value)
^^^^^^^^^^^^^^^^^^^^^^^
Removes all whitespace characters from string.

.. code-block:: php

    Sanitizer::stripWhitespace("Hello \n World!"); // 'HelloWorld!'

Special Format Sanitizers
~~~~~~~~~~~~~~~~~~~~~~~~~~

phone($value)
^^^^^^^^^^^^^
Keeps only digits and the ``+`` sign (for international format).

.. code-block:: php

    Sanitizer::phone('+1 (555) 123-4567'); // '+15551234567'
    Sanitizer::phone('555.123.4567');      // '5551234567'

currency($value, $format = 'USD')
^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^
Removes currency symbols and formatting, returns numeric value. Supports USD and EUR formats.

.. code-block:: php

    Sanitizer::currency('$1,234.56', 'USD');   // 1234.56
    Sanitizer::currency('-$500.75');           // -500.75
    Sanitizer::currency('1.234,56€', 'EUR');   // 1234.56
    Sanitizer::currency('-500,75', 'EUR');     // -500.75
    Sanitizer::currency(1234.56);              // 1234.56

formatCurrency($value, $currency = 'USD')
^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^
Formats a numeric value as currency with proper symbols and formatting.

.. code-block:: php

    Sanitizer::formatCurrency(1234.56, 'USD'); // '$1,234.56'
    Sanitizer::formatCurrency(1234.56, 'EUR'); // '€1.234,56'

filename($value)
^^^^^^^^^^^^^^^^
Removes path traversal attempts and unsafe characters from filenames.

.. code-block:: php

    Sanitizer::filename('../../../etc/passwd');      // 'passwd'
    Sanitizer::filename('my file!@#.txt');           // 'myfile.txt'

domain($value)
^^^^^^^^^^^^^^
Extracts domain from URL (removes protocol and paths).

.. code-block:: php

    Sanitizer::domain('https://www.example.com/path'); // 'www.example.com'
    Sanitizer::domain('http://subdomain.example.org'); // 'subdomain.example.org'

Alphanumeric Filter Sanitizers
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

alpha($value)
^^^^^^^^^^^^^
Keeps only alphabetic characters.

.. code-block:: php

    Sanitizer::alpha('Hello World! 123_-.');  // 'HelloWorld'

alphanumeric($value)
^^^^^^^^^^^^^^^^^^^^
Keeps only alphanumeric characters.

.. code-block:: php

    Sanitizer::alphanumeric('Hello World! 123_-.'); // 'HelloWorld123'

alphaDash($value)
^^^^^^^^^^^^^^^^^
Keeps only alphanumeric characters, dashes, and underscores.

.. code-block:: php

    Sanitizer::alphaDash('Hello World! 123_-.');    // 'HelloWorld123_-'

alphanumericSpace($value)
^^^^^^^^^^^^^^^^^^^^^^^^^
Keeps only alphanumeric characters and spaces.

.. code-block:: php

    Sanitizer::alphanumericSpace('Hello World! 123_-.'); // 'Hello World 123'

numeric($value)
^^^^^^^^^^^^^^^
Keeps only numeric characters.

.. code-block:: php

    Sanitizer::numeric('abc123def456');  // '123456'

Security & HTML Sanitizers
~~~~~~~~~~~~~~~~~~~~~~~~~~~

htmlEncode($value)
^^^^^^^^^^^^^^^^^^
Converts special characters to HTML entities (for safe display of user content).

.. code-block:: php

    Sanitizer::htmlEncode('<script>xss</script>'); // '&lt;script&gt;xss&lt;/script&gt;'
    Sanitizer::htmlEncode('"quoted"');             // '&quot;quoted&quot;'

htmlDecode($value)
^^^^^^^^^^^^^^^^^^
Converts HTML entities back to their characters.

.. code-block:: php

    Sanitizer::htmlDecode('&lt;p&gt;Test&lt;/p&gt;'); // '<p>Test</p>'

stripTags($value, $allowedTags = '')
^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^
Removes HTML tags, optionally allowing specific tags.

.. code-block:: php

    Sanitizer::stripTags('<b>Hello</b> <p>World</p>');        // 'Hello World'
    Sanitizer::stripTags('<b>Hello</b> <p>World</p>', '<b>'); // '<b>Hello</b> World'

stripUnsafeTags($value)
^^^^^^^^^^^^^^^^^^^^^^^
Removes potentially dangerous HTML tags (script, iframe, object, embed, style).

.. code-block:: php

    Sanitizer::stripUnsafeTags('<script>xss</script><p>safe</p>'); // '<p>safe</p>'

removeXss($value)
^^^^^^^^^^^^^^^^^
Removes common XSS attack vectors (script tags, event handlers).

.. code-block:: php

    Sanitizer::removeXss('<script>alert(1)</script><p onclick="danger">hi</p>'); // '<p>hi</p>'

escapeLike($value)
^^^^^^^^^^^^^^^^^^
Escapes special characters for SQL LIKE queries.

.. code-block:: php

    Sanitizer::escapeLike('50% off! _wildcard_'); // '50\% off! \_wildcard\_'

removeSqlPatterns($value)
^^^^^^^^^^^^^^^^^^^^^^^^^
Removes common SQL injection patterns (not a substitute for prepared statements!).

.. code-block:: php

    Sanitizer::removeSqlPatterns('SELECT * FROM users; -- comment'); // '* FROM users;'

Encoding Sanitizers
~~~~~~~~~~~~~~~~~~~

base64Encode($value)
^^^^^^^^^^^^^^^^^^^^
Encodes value to base64.

.. code-block:: php

    Sanitizer::base64Encode('Hello World'); // 'SGVsbG8gV29ybGQ='

base64Decode($value)
^^^^^^^^^^^^^^^^^^^^
Decodes base64 value.

.. code-block:: php

    Sanitizer::base64Decode('SGVsbG8gV29ybGQ='); // 'Hello World'

jsonEncode($value)
^^^^^^^^^^^^^^^^^^
Encodes value to JSON string.

.. code-block:: php

    Sanitizer::jsonEncode(['name' => 'John', 'age' => 30]); // '{"name":"John","age":30}'

jsonDecode($value)
^^^^^^^^^^^^^^^^^^
Decodes JSON string to array. Returns empty array on invalid JSON.

.. code-block:: php

    Sanitizer::jsonDecode('{"name":"John","age":30}'); // ['name' => 'John', 'age' => 30]
    Sanitizer::jsonDecode('invalid json');             // []

Array Sanitizers
~~~~~~~~~~~~~~~~

array($value)
^^^^^^^^^^^^^
Sanitizes all values in an array using the ``string()`` sanitizer.

.. code-block:: php

    Sanitizer::array(['  key1  ', '<b>key2</b>', 123]); // ['key1', 'key2', '123']

batch($array, $sanitizer)
^^^^^^^^^^^^^^^^^^^^^^^^^
Applies a single sanitizer to all elements in an array.

.. code-block:: php

    Sanitizer::batch([' HELLO ', ' WORLD '], 'lowercase'); // [' hello ', ' world ']
    Sanitizer::batch(['test@EX.com', 'foo@BAR.com'], 'email'); // ['test@EX.com', 'foo@BAR.com']

apply($value, array $sanitizers)
^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^
Applies multiple sanitizers to a value in sequence.

.. code-block:: php

    Sanitizer::apply('  <b>HELLO</b>  ', ['string', 'lowercase']); // 'hello'
    Sanitizer::apply('  TEST@EX.COM  ', ['email', 'lowercase']); // 'test@ex.com'

Best Practices
--------------

1. **Sanitize Before Validation**
   Always sanitize user input before passing it to the validator. This ensures consistent data format and reduces validation errors.

   .. code-block:: php

       $cleanData = [
           'email' => Sanitizer::email($input['email']),
           'name' => Sanitizer::string($input['name']),
           'age' => Sanitizer::integer($input['age']),
       ];
       $result = $validator->validate($cleanData);

2. **Chain Sanitizers**
   Use ``apply()`` or the ``sanitize()`` helper to chain multiple sanitizers.

   .. code-block:: php

       $clean = Sanitizer::apply($input, ['string', 'lowercase', 'trim']);
       // Or use helper:
       $clean = sanitize($input, ['string', 'lowercase', 'trim']);

3. **Use Type-Specific Sanitizers**
   Choose the appropriate sanitizer for your data type.

   .. code-block:: php

       $data = [
           'email' => Sanitizer::email($input['email']),         // Not just string()
           'phone' => Sanitizer::phone($input['phone']),         // Removes formatting
           'bio' => Sanitizer::stripUnsafeTags($input['bio']),   // Keeps safe HTML
       ];

4. **Batch Processing**
   For arrays of similar data, use ``batch()`` for efficiency.

   .. code-block:: php

       $emails = Sanitizer::batch($input['emails'], 'email');
       $names = Sanitizer::batch($input['names'], 'string');

5. **Security First**
   Always sanitize user-generated content that will be displayed in HTML.

   .. code-block:: php

       // For display in HTML
       $safe = Sanitizer::htmlEncode($userInput);
       
       // For database (use prepared statements!)
       $clean = Sanitizer::string($userInput);

6. **Don't Rely on Sanitization for Security**
   Sanitization is NOT a replacement for:
   
   - Prepared statements for SQL queries
   - Proper authentication and authorization
   - CSRF protection
   - Content Security Policy (CSP)

   It's one layer of defense, not the only layer.

Common Patterns
---------------

Email Cleaning
~~~~~~~~~~~~~~

.. code-block:: php

    $cleanEmail = sanitize($input['email'], ['email', 'lowercase', 'trim']);

Phone Number Normalization
~~~~~~~~~~~~~~~~~~~~~~~~~~~

.. code-block:: php

    $cleanPhone = Sanitizer::phone($input['phone']); // +15551234567

Safe HTML Content
~~~~~~~~~~~~~~~~~

.. code-block:: php

    $safeBio = Sanitizer::stripUnsafeTags($input['bio']);
    // Or for plain text:
    $plainBio = Sanitizer::string($input['bio']);

User-Friendly Slugs
~~~~~~~~~~~~~~~~~~~

.. code-block:: php

    $slug = Sanitizer::slug($input['title']); // 'my-awesome-post'

Currency Processing
~~~~~~~~~~~~~~~~~~~

.. code-block:: php

    // Input: '$1,234.56'
    $amount = Sanitizer::currency($input['price'], 'USD'); // 1234.56
    
    // Display: '$1,234.56'
    $display = Sanitizer::formatCurrency($amount, 'USD');
