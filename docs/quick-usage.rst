Quick Usage
===========

This page shows copy-paste style flows you can use in real request handling.

API Signup Example
------------------

.. code-block:: php

    <?php
    use Infocyph\ReqShield\Validator;

    $validator = Validator::make([
        'name' => [
            'rules' => 'required|string|min:2|max:80',
            'sanitize' => ['trim', 'titleCase'],
            'alias' => 'Full Name',
        ],
        'email' => [
            'rules' => 'required|email|max:255',
            'sanitize' => ['trim', 'lowercase'],
            'alias' => 'Email Address',
        ],
        'password' => 'required|string|min:8|confirmed',
        'age' => 'required|integer|min:18',
    ])->setCasts([
        'age' => 'integer',
    ])->setCustomMessages([
        'email.required' => ':field is required.',
        'password.min' => ':field must be at least :min characters.',
    ]);

    $result = $validator->validate($_POST);

    if ($result->fails()) {
        http_response_code(422);
        echo json_encode([
            'errors' => $result->errors(),
            'failures' => $result->failures(),
        ]);
        return;
    }

    $payload = $result->typed();
    // Persist user with $payload

Checkout Contacts Example (Nested + Wildcards)
----------------------------------------------

.. code-block:: php

    <?php
    use Infocyph\ReqShield\Validator;

    $validator = Validator::make([
        'contacts.*.name' => 'required|string|min:2',
        'contacts.*.email' => 'required|email',
        'contacts.*.phone' => 'nullable|string|min:8',
    ])->enableNestedValidation(false) // false => flatten only required paths
      ->setFieldAliases([
          'contacts.*.email' => 'Contact Email',
      ]);

    $result = $validator->validate([
        'contacts' => [
            ['name' => 'John', 'email' => 'bad-email'],
        ],
    ]);

    // Error key is contacts.0.email, alias is "Contact Email"
    print_r($result->errors());

Update Flow Example (Conditional + Fragment)
--------------------------------------------

.. code-block:: php

    <?php
    use Infocyph\ReqShield\Validator;

    Validator::defineFragment('address', [
        'line1' => 'required|string|max:120',
        'zip' => 'required|digits:5',
    ]);

    $validator = Validator::make([
        'type' => 'required|in:personal,business',
        'vat' => 'string',
    ])->useFragment('address', 'billing')
      ->sometimes('vat', 'required', fn (array $data): bool => ($data['type'] ?? null) === 'business')
      ->when(
          fn (array $data): bool => ($data['type'] ?? null) === 'business',
          fn (): array => ['billing.company_name' => 'required|string|max:120'],
      )
      ->enableNestedValidation();

    $result = $validator->validate($input);

    if ($result->passes()) {
        $safeData = $result->validated();
    }

