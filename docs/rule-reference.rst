Complete Rule Reference
=======================

ReqShield supports 103 validation rules, covering a vast range of validation scenarios from basic type checks to complex database and conditional logic.

This page serves as a complete reference, categorized for easy lookup.

Basic Type Rules (9)
--------------------

required
~~~~~~~~
The field under validation must be present and not empty.

.. code-block:: php

    'email' => 'required'

filled
~~~~~~
The field under validation must be present and contain a non-empty value when it is present.

.. code-block:: php

    'bio' => 'filled'

string
~~~~~~
The field under validation must be a string.

.. code-block:: php

    'name' => 'string'

integer
~~~~~~~
The field under validation must be an integer.

.. code-block:: php

    'age' => 'integer'

numeric
~~~~~~~
The field under validation must be numeric (integer or float).

.. code-block:: php

    'price' => 'numeric'

boolean
~~~~~~~
The field under validation must be a boolean value (true, false, 1, 0, "1", "0").

.. code-block:: php

    'agreed' => 'boolean'

array
~~~~~
The field under validation must be an array.

.. code-block:: php

    'items' => 'array'

nullable
~~~~~~~~
The field under validation may be null.

.. code-block:: php

    'middle_name' => 'nullable|string'

present
~~~~~~~
The field under validation must be present in the input data but can be empty.

.. code-block:: php

    'field' => 'present'

Format Rules (10)
-----------------

email
~~~~~
The field under validation must be formatted as an email address.

.. code-block:: php

    'email' => 'email'

url
~~~
The field under validation must be a valid URL.

.. code-block:: php

    'website' => 'url'

active_url
~~~~~~~~~~
The field under validation must have a valid A or AAAA record according to the ``dns_get_record()`` PHP function.

.. code-block:: php

    'website' => 'active_url'

ip
~~
The field under validation must be an IP address. You can specify version (``v4`` or ``v6``) and scope (``public`` or ``private``).

.. code-block:: php

    'server_ip' => 'ip'              // Any IP
    'ipv4_addr' => 'ip:v4'           // IPv4 only
    'ipv6_addr' => 'ip:v6'           // IPv6 only
    'public_ip' => 'ip:v4,public'    // Public IPv4
    'private_ip' => 'ip:v4,private'  // Private IPv4

json
~~~~
The field under validation must be a valid JSON string.

.. code-block:: php

    'settings' => 'json'

uuid
~~~~
The field under validation must be a valid RFC 4122 universally unique identifier (UUID). You can specify version (1-5).

.. code-block:: php

    'id' => 'uuid'       // Any version
    'id' => 'uuid:4'     // Version 4 only

ulid
~~~~
The field under validation must be a valid Universally Unique Lexicographically Sortable Identifier (ULID).

.. code-block:: php

    'id' => 'ulid'

mac
~~~
The field under validation must be a valid MAC address.

.. code-block:: php

    'mac_address' => 'mac'

hex_color
~~~~~~~~~
The field under validation must be a valid hexadecimal color code.

.. code-block:: php

    'color' => 'hex_color'  // e.g., #FF5733 or #F57

timezone
~~~~~~~~
The field under validation must be a valid timezone identifier according to the ``timezone_identifiers_list()`` PHP function.

.. code-block:: php

    'timezone' => 'timezone'  // e.g., 'America/New_York'

String Rules (12)
-----------------

alpha
~~~~~
The field under validation must be entirely alphabetic characters.

.. code-block:: php

    'name' => 'alpha'

alpha_num
~~~~~~~~~
The field under validation must be entirely alpha-numeric characters.

.. code-block:: php

    'username' => 'alpha_num'

alpha_dash
~~~~~~~~~~
The field under validation may contain alpha-numeric characters, dashes, and underscores.

.. code-block:: php

    'username' => 'alpha_dash'

ascii
~~~~~
The field under validation must be entirely ASCII characters.

.. code-block:: php

    'code' => 'ascii'

lowercase
~~~~~~~~~
The field under validation must be lowercase.

.. code-block:: php

    'username' => 'lowercase'

uppercase
~~~~~~~~~
The field under validation must be uppercase.

.. code-block:: php

    'code' => 'uppercase'

starts_with
~~~~~~~~~~~
The field under validation must start with one of the given values.

.. code-block:: php

    'phone' => 'starts_with:+1,+44'  // Must start with +1 or +44

ends_with
~~~~~~~~~
The field under validation must end with one of the given values.

.. code-block:: php

    'domain' => 'ends_with:.com,.org,.net'

contains
~~~~~~~~
The field under validation must contain the given value.

.. code-block:: php

    'message' => 'contains:urgent'

doesnt_contain
~~~~~~~~~~~~~~
The field under validation must not contain any of the given values.

.. code-block:: php

    'username' => 'doesnt_contain:admin,root'

doesnt_start_with
~~~~~~~~~~~~~~~~~
The field under validation must not start with any of the given values.

.. code-block:: php

    'username' => 'doesnt_start_with:admin,test'

doesnt_end_with
~~~~~~~~~~~~~~~
The field under validation must not end with any of the given values.

.. code-block:: php

    'email' => 'doesnt_end_with:temp.com,trash.com'

Numeric Rules (14)
------------------

min
~~~
The field under validation must have a minimum value. For strings, it validates the length. For arrays, it validates the count. For numerics, it validates the value.

.. code-block:: php

    'age' => 'min:18'           // Minimum value 18
    'name' => 'string|min:3'    // Minimum 3 characters
    'items' => 'array|min:2'    // Minimum 2 items

max
~~~
The field under validation must not exceed the maximum value.

.. code-block:: php

    'age' => 'max:120'          // Maximum value 120
    'name' => 'string|max:255'  // Maximum 255 characters
    'items' => 'array|max:10'   // Maximum 10 items

between
~~~~~~~
The field under validation must have a size between the given min and max.

.. code-block:: php

    'age' => 'between:18,65'
    'name' => 'string|between:3,50'

size
~~~~
The field under validation must have a size matching the given value.

.. code-block:: php

    'pin' => 'size:4'           // Exactly 4 digits
    'name' => 'string|size:10'  // Exactly 10 characters

digits
~~~~~~
The field under validation must be numeric and must have an exact length of value.

.. code-block:: php

    'pin' => 'digits:4'         // Must be exactly 4 digits

digits_between
~~~~~~~~~~~~~~
The field under validation must have a length between the given min and max.

.. code-block:: php

    'phone' => 'digits_between:10,15'

min_digits
~~~~~~~~~~
The field under validation must have a minimum number of digits.

.. code-block:: php

    'phone' => 'min_digits:10'

max_digits
~~~~~~~~~~
The field under validation must have a maximum number of digits.

.. code-block:: php

    'phone' => 'max_digits:15'

decimal
~~~~~~~
The field under validation must be numeric and may contain the specified number of decimal places.

.. code-block:: php

    'price' => 'decimal:2'      // e.g., 19.99
    'rate' => 'decimal:0,4'     // 0 to 4 decimal places

multiple_of
~~~~~~~~~~~
The field under validation must be a multiple of the given value.

.. code-block:: php

    'quantity' => 'multiple_of:5'  // Must be 5, 10, 15, etc.

gt (Greater Than)
~~~~~~~~~~~~~~~~~
The field under validation must be greater than the given field.

.. code-block:: php

    'end_date' => 'gt:start_date'
    'max_price' => 'gt:min_price'

gte (Greater Than or Equal)
~~~~~~~~~~~~~~~~~~~~~~~~~~~~
The field under validation must be greater than or equal to the given field.

.. code-block:: php

    'end_date' => 'gte:start_date'

lt (Less Than)
~~~~~~~~~~~~~~
The field under validation must be less than the given field.

.. code-block:: php

    'min_price' => 'lt:max_price'

lte (Less Than or Equal)
~~~~~~~~~~~~~~~~~~~~~~~~~
The field under validation must be less than or equal to the given field.

.. code-block:: php

    'discount' => 'lte:price'

Date/Time Rules (7)
-------------------

date
~~~~
The field under validation must be a valid date according to the ``strtotime()`` PHP function.

.. code-block:: php

    'birthday' => 'date'

date_format
~~~~~~~~~~~
The field under validation must match the given date format.

.. code-block:: php

    'date' => 'date_format:Y-m-d'       // e.g., 2025-01-15
    'time' => 'date_format:H:i:s'       // e.g., 14:30:00

date_equals
~~~~~~~~~~~
The field under validation must be equal to the given date.

.. code-block:: php

    'scheduled_date' => 'date_equals:2025-01-01'

before
~~~~~~
The field under validation must be a date before the given date.

.. code-block:: php

    'start_date' => 'before:2025-12-31'
    'start_date' => 'before:end_date'  // Compare with another field

before_or_equal
~~~~~~~~~~~~~~~
The field under validation must be a date before or equal to the given date.

.. code-block:: php

    'start_date' => 'before_or_equal:2025-12-31'

after
~~~~~
The field under validation must be a date after the given date.

.. code-block:: php

    'end_date' => 'after:2025-01-01'
    'end_date' => 'after:start_date'   // Compare with another field

after_or_equal
~~~~~~~~~~~~~~
The field under validation must be a date after or equal to the given date.

.. code-block:: php

    'end_date' => 'after_or_equal:start_date'

Conditional Rules (27)
----------------------

required_if
~~~~~~~~~~~
The field under validation must be present and not empty if another field equals a certain value.

.. code-block:: php

    'reason' => 'required_if:status,rejected'

required_unless
~~~~~~~~~~~~~~~
The field under validation must be present and not empty unless another field equals a certain value.

.. code-block:: php

    'phone' => 'required_unless:contact_method,email'

required_with
~~~~~~~~~~~~~
The field under validation must be present and not empty only if any of the other specified fields are present.

.. code-block:: php

    'city' => 'required_with:state,country'

required_with_all
~~~~~~~~~~~~~~~~~
The field under validation must be present and not empty only if all of the other specified fields are present.

.. code-block:: php

    'apartment' => 'required_with_all:street,city,state'

required_without
~~~~~~~~~~~~~~~~
The field under validation must be present and not empty only when any of the other specified fields are not present.

.. code-block:: php

    'email' => 'required_without:phone,address'

required_without_all
~~~~~~~~~~~~~~~~~~~~
The field under validation must be present and not empty only when all of the other specified fields are not present.

.. code-block:: php

    'alternative_contact' => 'required_without_all:phone,email,fax'

required_array_keys
~~~~~~~~~~~~~~~~~~~
The field under validation must be an array and must contain all of the specified keys.

.. code-block:: php

    'address' => 'required_array_keys:street,city,zip'

required_if_accepted
~~~~~~~~~~~~~~~~~~~~
The field under validation must be present and not empty if another field is accepted.

.. code-block:: php

    'terms_date' => 'required_if_accepted:terms'

required_if_declined
~~~~~~~~~~~~~~~~~~~~
The field under validation must be present and not empty if another field is declined.

.. code-block:: php

    'reason' => 'required_if_declined:consent'

present_if
~~~~~~~~~~
The field under validation must be present (but can be empty) if another field equals a certain value.

.. code-block:: php

    'notes' => 'present_if:has_notes,yes'

present_unless
~~~~~~~~~~~~~~
The field under validation must be present unless another field equals a certain value.

.. code-block:: php

    'alternative' => 'present_unless:primary,available'

present_with
~~~~~~~~~~~~
The field under validation must be present if any of the other specified fields are present.

.. code-block:: php

    'shipping_address' => 'present_with:shipping_method'

present_with_all
~~~~~~~~~~~~~~~~
The field under validation must be present if all of the other specified fields are present.

.. code-block:: php

    'full_address' => 'present_with_all:street,city,state,zip'

missing
~~~~~~~
The field under validation must not be present in the input data.

.. code-block:: php

    'internal_id' => 'missing'

missing_if
~~~~~~~~~~
The field under validation must not be present if another field equals a certain value.

.. code-block:: php

    'discount_code' => 'missing_if:membership_level,premium'

missing_unless
~~~~~~~~~~~~~~
The field under validation must not be present unless another field equals a certain value.

.. code-block:: php

    'promo_code' => 'missing_unless:source,partner'

prohibited
~~~~~~~~~~
The field under validation must not be present or must be empty.

.. code-block:: php

    'admin_override' => 'prohibited'

prohibited_if
~~~~~~~~~~~~~
The field under validation must not be present or must be empty if another field equals a certain value.

.. code-block:: php

    'manual_adjustment' => 'prohibited_if:auto_calculate,true'

prohibited_unless
~~~~~~~~~~~~~~~~~
The field under validation must not be present or must be empty unless another field equals a certain value.

.. code-block:: php

    'override' => 'prohibited_unless:role,admin'

prohibits
~~~~~~~~~
If the field under validation is present and not empty, the specified fields must not be present.

.. code-block:: php

    'card_payment' => 'prohibits:cash,check'

exclude
~~~~~~~
The field under validation will be excluded from the validated data.

.. code-block:: php

    'internal_note' => 'exclude'

exclude_if
~~~~~~~~~~
The field under validation will be excluded from the validated data if another field equals a certain value.

.. code-block:: php

    'optional_field' => 'exclude_if:include_optional,false'

exclude_unless
~~~~~~~~~~~~~~
The field under validation will be excluded unless another field equals a certain value.

.. code-block:: php

    'admin_field' => 'exclude_unless:role,admin'

exclude_with
~~~~~~~~~~~~
The field under validation will be excluded if another field is present.

.. code-block:: php

    'temp_id' => 'exclude_with:permanent_id'

exclude_without
~~~~~~~~~~~~~~~
The field under validation will be excluded if another field is not present.

.. code-block:: php

    'secondary_email' => 'exclude_without:primary_email'

Database Rules (2)
------------------

unique
~~~~~~
The field under validation must not exist within the given database table.

.. code-block:: php

    'email' => 'unique:users,email'
    'username' => 'unique:users,username,5'  // Ignore ID 5 (for updates)

**Note:** Requires a ``DatabaseProvider`` implementation. See :doc:`custom-rules` for more details.

exists
~~~~~~
The field under validation must exist within the given database table.

.. code-block:: php

    'category_id' => 'exists:categories,id'
    'user_id' => 'exists:users,id'

**Note:** Requires a ``DatabaseProvider`` implementation. See :doc:`custom-rules` for more details.

File Rules (6)
--------------

file
~~~~
The field under validation must be a successfully uploaded file (checks ``is_uploaded_file()``).

.. code-block:: php

    'document' => 'file'

image
~~~~~
The field under validation must be an image (jpeg, png, bmp, gif, svg, or webp).

.. code-block:: php

    'avatar' => 'image'

mimes
~~~~~
The file under validation must have a MIME type corresponding to one of the listed extensions.

.. code-block:: php

    'document' => 'mimes:pdf,doc,docx'

mimetypes
~~~~~~~~~
The file under validation must match one of the given MIME types.

.. code-block:: php

    'document' => 'mimetypes:application/pdf,application/msword'

extensions
~~~~~~~~~~
The file under validation must have one of the extensions listed.

.. code-block:: php

    'spreadsheet' => 'extensions:xls,xlsx,csv'

dimensions
~~~~~~~~~~
The file under validation must be an image meeting the dimension constraints.

.. code-block:: php

    'avatar' => 'dimensions:min_width=100,min_height=100,max_width=1000,max_height=1000'
    'banner' => 'dimensions:ratio=3/1'  // Aspect ratio
    'logo' => 'dimensions:width=200,height=200'  // Exact dimensions

Array Rules (5)
---------------

in
~~
The field under validation must be included in the given list of values.

.. code-block:: php

    'status' => 'in:pending,active,completed'
    'role' => 'in:admin,user,guest'

not_in
~~~~~~
The field under validation must not be included in the given list of values.

.. code-block:: php

    'username' => 'not_in:admin,root,system'

in_array
~~~~~~~~
The field under validation must exist in the values of another field (which must be an array).

.. code-block:: php

    'selected_item' => 'in_array:available_items'

distinct
~~~~~~~~
When working with arrays, the field under validation must not have any duplicate values.

.. code-block:: php

    'tags' => 'array|distinct'
    'emails.*' => 'distinct'  // Each email must be unique

is_list
~~~~~~~
The field under validation must be a list (sequential, zero-indexed array).

.. code-block:: php

    'items' => 'is_list'  // [0 => 'a', 1 => 'b'] passes, ['x' => 'a'] fails

Comparison Rules (3)
--------------------

same
~~~~
The field under validation must match the given field.

.. code-block:: php

    'password_confirmation' => 'same:password'
    'email_confirmation' => 'same:email'

different
~~~~~~~~~
The field under validation must have a different value than the given field.

.. code-block:: php

    'new_password' => 'different:old_password'

confirmed
~~~~~~~~~
The field under validation must have a matching field of ``{field}_confirmation``.

.. code-block:: php

    'password' => 'confirmed'  // Looks for 'password_confirmation'

Pattern Rules (2)
-----------------

regex
~~~~~
The field under validation must match the given regular expression.

.. code-block:: php

    'postal_code' => 'regex:/^\d{5}(-\d{4})?$/'  // US ZIP code
    'color' => 'regex:/^#[0-9A-F]{6}$/i'         // Hex color

**Note:** Use ``#`` as delimiter in the pattern.

not_regex
~~~~~~~~~
The field under validation must not match the given regular expression.

.. code-block:: php

    'username' => 'not_regex:/[^a-zA-Z0-9_]/'  // No special chars

Additional Rules (6)
--------------------

accepted
~~~~~~~~
The field under validation must be "yes", "on", 1, or true. Useful for validating terms of service acceptance.

.. code-block:: php

    'terms' => 'accepted'

accepted_if
~~~~~~~~~~~
The field under validation must be accepted if another field equals a certain value.

.. code-block:: php

    'terms' => 'accepted_if:account_type,business'

declined
~~~~~~~~
The field under validation must be "no", "off", 0, or false.

.. code-block:: php

    'marketing_emails' => 'declined'

declined_if
~~~~~~~~~~~
The field under validation must be declined if another field equals a certain value.

.. code-block:: php

    'newsletter' => 'declined_if:email_preference,minimal'

bail
~~~~
Stop validating this field after the first validation failure.

.. code-block:: php

    'email' => 'bail|required|email|max:255'

**Note:** ReqShield is fail-fast by default, so ``bail`` is implicit unless you change the behavior.

callback
~~~~~~~~
Use a custom callback function for validation. See :doc:`custom-rules` for detailed usage.

.. code-block:: php

    use Infocyph\ReqShield\Rules\Callback;

    'code' => [
        new Callback(
            callback: fn($value) => $value % 2 === 0,
            message: 'The code must be an even number'
        )
    ]
