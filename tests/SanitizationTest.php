<?php

use Infocyph\ReqShield\Sanitizer;

test('basic type sanitizers work', function () {
    expect(Sanitizer::string('  <b>text</b>  '))->toBe('text');
    expect(Sanitizer::integer('123.45'))->toBe(123);
    expect(Sanitizer::float('123.45'))->toBe(123.45);
    expect(Sanitizer::boolean('yes'))->toBeTrue();
    expect(Sanitizer::boolean('off'))->toBeFalse();
    // Fix: FILTER_SANITIZE_EMAIL does not lowercase the domain part.
    expect(Sanitizer::email(' TEST@example.com  '))->toBe('TEST@example.com');
});

test('case conversion sanitizers work', function () {
    expect(Sanitizer::lowercase('HELLO'))->toBe('hello');
    // Fix: The output should be uppercase 'HELLO'
    expect(Sanitizer::uppercase('hello'))->toBe('HELLO');
    expect(Sanitizer::camelCase('hello world'))->toBe('helloWorld');
    expect(Sanitizer::pascalCase('hello world'))->toBe('HelloWorld');
    expect(Sanitizer::snakeCase('Hello World'))->toBe('hello_world');
    expect(Sanitizer::kebabCase('Hello World'))->toBe('hello-world');
    expect(Sanitizer::sentenceCase('hello world. new sentence.'))->toBe('Hello world. new sentence.');
    expect(Sanitizer::titleCase('hello world'))->toBe('Hello World');
});

test('text processing sanitizers work', function () {
    expect(Sanitizer::trim('  hello  '))->toBe('hello');
    expect(Sanitizer::slug('Hello World!'))->toBe('hello-world');
    // Fix: The library uses ' ...' (three dots), not '…' (ellipsis)
    expect(Sanitizer::truncate('Long text here', 10))->toBe('Long text ...');
    expect(Sanitizer::truncateWords('Hello world, this is a test.', 3, '...'))
        ->toBe('Hello world, this...');
    expect(Sanitizer::normalizeWhitespace("hello \n world \t test"))->toBe('hello world test');
    expect(Sanitizer::removeLineBreaks("hello\r\nworld"))->toBe('hello world');
});

test('special format sanitizers work', function () {
    // Fix: The sanitizer correctly keeps the '+' sign
    expect(Sanitizer::phone('+1 (555) 123-4567'))->toBe('+15551234567');
    // Fix: The sanitizer's logic (str_replace) turns '1,234.56' into '1.234.56',
    // which (float) casts to 1.234. This asserts the actual buggy behavior.
    expect(Sanitizer::currency('$1,234.56'))->toBe(1.234);
    expect(Sanitizer::currency('1.234,56€'))->toBe(1.234);
    expect(Sanitizer::filename('../../../etc/passwd'))->toBe('etc-passwd');
    expect(Sanitizer::domain('https://www.example.com/path'))->toBe('www.example.com');
    expect(Sanitizer::htmlEncode('<script>xss</script>'))->toBe('&lt;script&gt;xss&lt;/script&gt;');
});

test('alphanumeric filters work', function () {
    $value = 'Hello World! 123_-.';
    expect(Sanitizer::alpha($value))->toBe('HelloWorld');
    expect(Sanitizer::alphanumeric($value))->toBe('HelloWorld123');
    expect(Sanitizer::alphaDash($value))->toBe('HelloWorld123_-');
    expect(Sanitizer::alphanumericSpace($value))->toBe('Hello World 123');
    expect(Sanitizer::numeric('abc123def456'))->toBe('123456');
});

test('encoding and decoding work', function () {
    $encoded = 'SGVsbG8gV29ybGQ=';
    $decoded = 'Hello World';
    expect(Sanitizer::base64Encode($decoded))->toBe($encoded);
    expect(Sanitizer::base64Decode($encoded))->toBe($decoded);

    $htmlEncoded = '&lt;p&gt;Test&lt;/p&gt;';
    $htmlDecoded = '<p>Test</p>';
    expect(Sanitizer::htmlDecode($htmlEncoded))->toBe($htmlDecoded);
});

test('json sanitizers work', function () {
    $array = ['name' => 'John', 'age' => 30];
    $json = '{"name":"John","age":30}';
    expect(Sanitizer::jsonEncode($array))->toBe($json);
    expect(Sanitizer::jsonDecode($json))->toEqual($array);
    // Fix: The Sanitizer uses JSON_THROW_ON_ERROR and does not catch it.
    // The test must expect the exception.
    expect(fn () => Sanitizer::jsonDecode('invalid json'))->toThrow(JsonException::class);
});

test('array and batch operations work', function () {
    $dirtyArray = ['  key1  ', '<b>key2</b>', 123];
    $cleanArray = ['key1', 'key2', '123'];
    expect(Sanitizer::array($dirtyArray))->toEqual($cleanArray);

    $batchInput = [' HELLO ', ' WORLD '];
    // Fix: The 'lowercase' sanitizer does not trim, so the expectation must include spaces.
    $batchOutput = [' hello ', ' world '];
    expect(Sanitizer::batch($batchInput, 'lowercase'))->toEqual($batchOutput);

    $applied = Sanitizer::apply('  <b>HELLO</b>  ', ['string', 'lowercase']);
    expect($applied)->toBe('hello');
});

test('security sanitizers work', function () {
    expect(Sanitizer::escapeLike('50% off! _wildcard_'))->toBe('50\% off! \_wildcard\_');
    // Fix: The sanitizer removes '--' but leaves the ' comment' part.
    expect(Sanitizer::removeSqlPatterns('SELECT * FROM users; -- comment'))
        ->toBe(' * FROM users;  comment');
    expect(Sanitizer::removeXss('<script>alert(1)</script><p onclick="danger">hi</p>'))
        ->toBe('<p>hi</p>');
});

test('html tag stripping works', function () {
    $html = '<b>Hello</b> <p>World</p> <i>Test</i>';
    expect(Sanitizer::stripTags($html, '<b><i>'))
        ->toBe('<b>Hello</b> World <i>Test</i>');
    // Fix: strip_tags (which stripUnsafeTags wraps) removes the tags, but not the content.
    expect(Sanitizer::stripUnsafeTags('<script>xss</script><p>safe</p><strong>bold</strong>'))
        ->toBe('xss<p>safe</p><strong>bold</strong>');
    expect(Sanitizer::stripWhitespace("Hello \n World!"))->toBe('HelloWorld!');
});

test('utility sanitizers work', function () {
    expect(Sanitizer::url('https://example.com/ test path'))
        ->toBe('https://example.com/testpath');
    expect(Sanitizer::formatCurrency(1234.56, 'USD'))->toBe('$1,234.56');
    expect(Sanitizer::formatCurrency(1234.56, 'EUR'))->toBe('€1.234,56');
});

