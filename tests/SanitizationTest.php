<?php

use Infocyph\ReqShield\Sanitizer;

// --- Example 6, 20 ---

test('basic type sanitizers work', function () {
    expect(Sanitizer::string('  <b>text</b>  '))->toBe('text');
    expect(Sanitizer::integer('123.45'))->toBe(123);
    expect(Sanitizer::float('123.45'))->toBe(123.45);
    expect(Sanitizer::boolean('yes'))->toBeTrue();
    expect(Sanitizer::boolean('off'))->toBeFalse();
});

test('case conversion sanitizers work', function () {
    expect(Sanitizer::lowercase('HELLO'))->toBe('hello');
    expect(Sanitizer::uppercase('hello'))->toBe('hello');
    expect(Sanitizer::camelCase('hello world'))->toBe('helloWorld');
    expect(Sanitizer::pascalCase('hello world'))->toBe('HelloWorld');
    expect(Sanitizer::snakeCase('Hello World'))->toBe('hello_world');
    expect(Sanitizer::kebabCase('Hello World'))->toBe('hello-world');
});

test('text processing sanitizers work', function () {
    expect(Sanitizer::trim('  hello  '))->toBe('hello');
    expect(Sanitizer::slug('Hello World!'))->toBe('hello-world');
    expect(Sanitizer::truncate('Long text here', 10))->toBe('Long text…');
});

test('special format sanitizers work', function () {
    expect(Sanitizer::phone('+1 (555) 123-4567'))->toBe('15551234567');
    expect(Sanitizer::currency('$1,234.56'))->toBe('1234.56');
    expect(Sanitizer::filename('../../../etc/passwd'))->toBe('etc-passwd');
    expect(Sanitizer::domain('https://www.example.com/path'))->toBe('example.com');
    expect(Sanitizer::htmlEncode('<script>xss</script>'))->toBe('&lt;script&gt;xss&lt;/script&gt;');
    expect(Sanitizer::jsonDecode('{"name":"John"}'))->toEqual(['name' => 'John']);
});
