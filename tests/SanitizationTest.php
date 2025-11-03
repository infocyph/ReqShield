<?php

use Infocyph\ReqShield\Sanitizer;

test('basic type sanitizers work', function () {
    expect(Sanitizer::string('  <b>text</b>  '))
      ->toBe('text')
      ->and(Sanitizer::integer('123.45'))->toBe(123)
      ->and(Sanitizer::float('123.45'))->toBe(123.45)
      ->and(Sanitizer::boolean('yes'))->toBeTrue()
      ->and(Sanitizer::boolean('off'))->toBeFalse()
      ->and(Sanitizer::email(' TEST@example.com  '))->toBe('TEST@example.com');
    // Test the actual behavior: FILTER_SANITIZE_EMAIL does not lowercase.
});

test('case conversion sanitizers work', function () {
    expect(Sanitizer::lowercase('HELLO'))
      ->toBe('hello')
      ->and(Sanitizer::uppercase('hello'))->toBe('HELLO')
      ->and(Sanitizer::camelCase('hello world'))->toBe('helloWorld')
      ->and(Sanitizer::pascalCase('hello world'))->toBe('HelloWorld')
      ->and(Sanitizer::snakeCase('Hello World'))->toBe('hello_world')
      ->and(Sanitizer::kebabCase('Hello World'))->toBe('hello-world')
      ->and(Sanitizer::sentenceCase('hello world. new sentence.'))->toBe(
          'Hello world. new sentence.'
      )
      ->and(Sanitizer::titleCase('hello world'))->toBe('Hello World');
});

test('text processing sanitizers work', function () {
    expect(Sanitizer::trim('  hello  '))
      ->toBe('hello')
      ->and(Sanitizer::slug('Hello World!'))->toBe('hello-world')
      ->and(Sanitizer::truncate('Long text here', 10))->toBe('Long text ...')
      ->and(Sanitizer::truncateWords('Hello world, this is a test.', 3, '...'))
      ->toBe('Hello world, this...')
      ->and(Sanitizer::normalizeWhitespace("hello \n world \t test"))->toBe(
          'hello world test'
      )
      ->and(Sanitizer::removeLineBreaks("hello\r\nworld"))->toBe('hello world');
});

test('special format sanitizers work', function () {
    expect(Sanitizer::phone('+1 (555) 123-4567'))
      ->toBe('+15551234567')
      ->and(Sanitizer::currency('$1,234.56', 'USD'))->toBe(1234.56)
      ->and(Sanitizer::currency('-$500.75'))->toBe(-500.75)
      ->and(Sanitizer::currency('1.234,56€', 'EUR'))->toBe(1234.56)
      ->and(Sanitizer::currency('-500,75', 'EUR'))->toBe(-500.75)
      ->and(Sanitizer::currency(1234.56))->toBe(1234.56)
      ->and(Sanitizer::filename('../../../etc/passwd'))->toBe('passwd')
      ->and(Sanitizer::domain('https://www.example.com/path'))->toBe(
          'www.example.com'
      )
      ->and(Sanitizer::htmlEncode('<script>xss</script>'))->toBe(
          '&lt;script&gt;xss&lt;/script&gt;'
      );

});

test('alphanumeric filters work', function () {
    $value = 'Hello World! 123_-.';
    expect(Sanitizer::alpha($value))
      ->toBe('HelloWorld')
      ->and(Sanitizer::alphanumeric($value))->toBe('HelloWorld123')
      ->and(Sanitizer::alphaDash($value))->toBe('HelloWorld123_-')
      ->and(Sanitizer::alphanumericSpace($value))->toBe('Hello World 123')
      ->and(Sanitizer::numeric('abc123def456'))->toBe('123456');
});

test('encoding and decoding work', function () {
    $encoded = 'SGVsbG8gV29ybGQ=';
    $decoded = 'Hello World';
    expect(Sanitizer::base64Encode($decoded))
      ->toBe($encoded)
      ->and(Sanitizer::base64Decode($encoded))->toBe($decoded);

    $htmlEncoded = '&lt;p&gt;Test&lt;/p&gt;';
    $htmlDecoded = '<p>Test</p>';
    expect(Sanitizer::htmlDecode($htmlEncoded))->toBe($htmlDecoded);
});

test('json sanitizers work', function () {
    $array = ['name' => 'John', 'age' => 30];
    $json = '{"name":"John","age":30}';
    expect(Sanitizer::jsonEncode($array))
      ->toBe($json)
      ->and(Sanitizer::jsonDecode($json))->toEqual($array)
      ->and(Sanitizer::jsonDecode('invalid json'))->toEqual([]);
});

test('array and batch operations work', function () {
    $dirtyArray = ['  key1  ', '<b>key2</b>', 123];
    $cleanArray = ['key1', 'key2', '123'];
    expect(Sanitizer::array($dirtyArray))->toEqual($cleanArray);

    $batchInput = [' HELLO ', ' WORLD '];
    $batchOutput = [' hello ', ' world '];
    expect(Sanitizer::batch($batchInput, 'lowercase'))->toEqual($batchOutput);

    $applied = Sanitizer::apply('  <b>HELLO</b>  ', ['string', 'lowercase']);
    expect($applied)->toBe('hello');
});

test('security sanitizers work', function () {
    expect(Sanitizer::escapeLike('50% off! _wildcard_'))
      ->toBe('50\% off! \_wildcard\_')
      ->and(Sanitizer::removeSqlPatterns('SELECT * FROM users; -- comment'))
      ->toBe('* FROM users;')
      ->and(
          Sanitizer::removeXss(
              '<script>alert(1)</script><p onclick="danger">hi</p>'
          )
      )
      ->toBe('<p>hi</p>');

});

test('html tag stripping works', function () {
    $html = '<b>Hello</b> <p>World</p> <i>Test</i>';
    expect(Sanitizer::stripTags($html, '<b><i>'))
      ->toBe('<b>Hello</b> World <i>Test</i>')
      ->and(
          Sanitizer::stripUnsafeTags(
              '<script>xss</script><p>safe</p><strong>bold</strong>'
          )
      )
      ->toBe('<p>safe</p><strong>bold</strong>')
      ->and(Sanitizer::stripWhitespace("Hello \n World!"))->toBe('HelloWorld!');
});

test('utility sanitizers work', function () {
    expect(Sanitizer::url('https://example.com/ test path'))
      ->toBe('https://example.com/testpath')
      ->and(Sanitizer::formatCurrency(1234.56, 'USD'))->toBe('$1,234.56')
      ->and(Sanitizer::formatCurrency(1234.56, 'EUR'))->toBe('€1.234,56');
});
