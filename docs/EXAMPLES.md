# TOON Encoding Examples

Comprehensive examples of TOON encoding for different data structures.

## Nested Objects

```php
echo Toon::encode([
    'user' => [
        'id' => 123,
        'email' => 'ada@example.com',
        'metadata' => [
            'active' => true,
            'score' => 9.5
        ]
    ]
]);
```

Output:

```
user:
  id: 123
  email: ada@example.com
  metadata:
    active: true
    score: 9.5
```

## Primitive Arrays

```php
echo Toon::encode([
    'tags' => ['reading', 'gaming', 'coding']
]);
```

Output:

```
tags[3]: reading,gaming,coding
```

## Tabular Arrays (Uniform Objects)

When all objects in an array have the same keys with primitive values, TOON uses an efficient tabular format:

```php
echo Toon::encode([
    'items' => [
        ['sku' => 'A1', 'qty' => 2, 'price' => 9.99],
        ['sku' => 'B2', 'qty' => 1, 'price' => 14.5]
    ]
]);
```

Output:

```
items[2]{sku,qty,price}:
  A1,2,9.99
  B2,1,14.5
```

## Non-uniform Object Arrays

When objects have different keys, TOON falls back to list format:

```php
echo Toon::encode([
    'items' => [
        ['id' => 1, 'name' => 'First'],
        ['id' => 2, 'name' => 'Second', 'extra' => true]
    ]
]);
```

Output:

```
items[2]:
  - id: 1
    name: First
  - id: 2
    name: Second
    extra: true
```

## Nested Field Groups

A column whose values are uniform objects keeps the array tabular; the header declares the nesting and the rows stay flat:

```php
echo Toon::encode([
    'items' => [
        ['sku' => 'A1', 'dims' => ['w' => 10, 'h' => 4]],
        ['sku' => 'B2', 'dims' => ['w' => 7, 'h' => 9]]
    ]
]);
```

Output:

```
items[2]{sku,dims{w,h}}:
  A1,10,4
  B2,7,9
```

## Keyed Tabular Objects

An object with at least two entries whose values share one uniform shape collapses into a table whose rows carry their own keys:

```php
echo Toon::encode([
    'stations' => [
        'tempelhof' => ['lat' => 52.47, 'active' => true],
        'tegel' => ['lat' => 52.55, 'active' => false]
    ]
]);
```

Output:

```
stations[2:]{lat,active}:
  tempelhof: 52.47,true
  tegel: 52.55,false
```

## Array of Arrays

```php
echo Toon::encode([
    'pairs' => [['a', 'b'], ['c', 'd']]
]);
```

Output:

```
pairs[2]:
  - [2]: a,b
  - [2]: c,d
```

## Empty Arrays

```php
echo Toon::encode(['items' => []]);
echo Toon::encode([]);
```

Output:

```
items: []
[]
```

PHP represents both `[]` and `{}` as an empty array, so an empty PHP array is always encoded as an empty array.

## Comments

Comments are decode-only: a line whose first non-space character is `#` is removed before any other parsing, so it never terminates a scope and never counts toward a declared length.

```php
Toon::decode(<<<TOON
# Weekly export
forecast[2]{day,condition}:
  # Monday was revised
  Mon,snow
  Tue,cloudy
TOON);
// ['forecast' => [['day' => 'Mon', 'condition' => 'snow'], ['day' => 'Tue', 'condition' => 'cloudy']]]
```

## Configuration Options

Customize encoding behavior with `EncodeOptions`:

```php
use HelgeSverre\Toon\EncodeOptions;

// Custom indentation (default: 2)
$options = new EncodeOptions(indentSize: 4);
echo Toon::encode(['a' => ['b' => 'c']], $options);
// a:
//     b: c

// Tab delimiter instead of comma (default: ',')
$options = new EncodeOptions(delimiter: "\t");
echo Toon::encode(['tags' => ['a', 'b', 'c']], $options);
// tags[3	]: a	b	c

// Pipe delimiter
$options = new EncodeOptions(delimiter: '|');
echo Toon::encode(['tags' => ['a', 'b', 'c']], $options);
// tags[3|]: a|b|c
```

### Preset Configurations

```php
use HelgeSverre\Toon\EncodeOptions;

// Maximum compactness (production)
$compact = EncodeOptions::compact();

// Human-readable (debugging)
$readable = EncodeOptions::readable();

// Tab-delimited (spreadsheets)
$tabular = EncodeOptions::tabular();
```
