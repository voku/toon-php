# TOON Specification Compliance Report

**Library:** toon-php
**Version:** 4.0.0
**Spec Version:** TOON Specification v4.1
**Status:** ✅ CONFORMANT

---

## v4.0–v4.1 Compliance Notes

Aligns with **TOON Specification v4.1**.

### Encoder

- **§9.3 Nested field groups**: a column whose values are uniform non-empty objects is emitted as a nested field group (`items[2]{id,dims{w,h}}:`), keeping the array tabular. Groups nest without a depth cap; row cells are the primitive leaf values in depth-first, pre-order header order.
- **§9.3 Tabular form is mandatory**: wherever detection succeeds and the position permits a fields-bearing header, tabular form is used. Arrays containing an empty object, or with a column that is neither uniform-primitive nor nested-uniform, fall back to list form (§9.4).
- **§9.5 Keyed tabular form**: an object with at least two entries whose values are uniform non-empty objects is emitted as `key[N:]{fields}:` with one `entrykey: cells` row per entry. It applies in object-field position and at the document root, and its header may carry nested field groups. Array elements are anonymous and never use the keyed form.
- **§9.1 Empty arrays**: `key: []` in object-field position and `[]` at the root. The legacy `key[0]:` / `[0]:` header forms are no longer emitted. Inner list-item arrays keep `- [0]:` per §9.2.
- **§10 Depth model**: a scope opened by the field carried on a list-item hyphen line has its content at depth +2, so it can never be confused with a sibling field at depth +1.
- **§11.1 Delimiter declaration**: every header — inline, list, tabular and keyed — declares the document delimiter.
- **§7.2 Quoting**: strings that equal or start with `#` are quoted, so encoder output never contains a line that reads as a comment (§5.1). The numeric-like trigger covers leading-plus forms, so `"+1"` is emitted quoted.
- **§3 Host strings**: a PHP string that is not well-formed UTF-8 — which is how an unpaired surrogate reaches the encoder — is rejected with an `InvalidArgumentException` rather than emitted or silently substituted.

### Decoder

- **§5.1 Comment lines**: a line whose first character after zero or more spaces is `#` is removed in a lexical pre-pass, in strict and non-strict mode alike. Comment removal never creates or terminates a scope, and a comment is never counted as a row, item, entry, or blank line. A tab in the leading whitespace disqualifies the line.
- **§6 Headers**: nested field groups and keyed headers (`[N:<delim?>]`) are parsed recursively, with brace matching that ignores braces inside quoted names. Malformed headers — a missing length (`key[]:`), leading-zero lengths (`[03]`), a keyed header without a field list, an empty field list at any nesting level, unmatched braces, a delimiter mismatch, whitespace between a key and its bracket segment, content between the bracket segment and the colon, or content after a fields-bearing header's colon — are strict-mode errors and fall through to key-value parsing in non-strict mode.
- **§6 / §14.2 Keyless header positions**: a keyless non-keyed header is valid only as the document's root header or as a list item; a keyless fields-bearing header only at the root. Elsewhere it is a strict-mode error.
- **§4 Number grammar**: an unquoted token decodes as a number only when it matches `/^-?[0-9]+(?:\.[0-9]+)?(?:e[+-]?[0-9]+)?$/i` without forbidden leading zeros. `.5`, `1.`, `+5`, `Infinity`, `NaN`, `0x10` and `1_000` decode as strings; the decision is never delegated to PHP's wider `is_numeric()`.
- **§4 / §14.2 Ill-formed UTF-8**: strict mode errors on input that is not well-formed UTF-8 instead of substituting U+FFFD.
- **§7.4 Quoted-token boundary**: a token whose first character is `"` must end at its closing quote; any character after it errors, in strict and non-strict mode alike.
- **§7.4 Unquoted key tokens**: any token before the first unquoted colon is accepted as a literal key, so `foo-bar: 1`, `2key`, and `5]: x` are valid input.
- **§9.3 Row disambiguation**: at row depth, a line with no unquoted colon is a row; when both appear, the delimiter before the colon makes it a row and the colon before the delimiter ends the rows.
- **§9.5 Entry rows**: every line at entry depth containing an unquoted colon is an entry row; a keyed scope ends only when the depth decreases or at end of input. A line at entry depth without an unquoted colon is a strict-mode error.
- **§14.1 Non-strict tolerance**: a declared `[N]` never terminates or truncates a scope. On a width mismatch the field walk applies unchanged — a leaf field with no remaining cell is absent, and surplus cells contribute nothing.
- **§14.2 Indentation**: a depth jump of more than one level, and a line deeper than its enclosing scope's content depth whose preceding line did not open a scope, are strict-mode errors; non-strict skips the latter.
- **§5 / §14.2 Trailing content**: any non-comment, non-blank line following a completed root array, keyed tabular root object, or root `[]` is a strict-mode error.
- **§12 Blank lines**: a header's span runs from its first item, row, or entry line through the last line of its content. A blank line inside the span is a strict-mode error; blank lines between a header and its first row, and after a scope's content, are ignored in both modes.
- **§12 Byte-order mark, CRLF, trailing spaces**: a single leading U+FEFF is removed before any processing; a trailing CR is excluded from each line's content; trailing spaces are stripped before line classification.
- **§15 Prototype-key safety**: PHP arrays have no prototype chain, so `__proto__`, `constructor` and `prototype` decode as ordinary own entries with no special handling.
- **§8 Dotted keys**: single literal keys. Key folding and path expansion were removed in v4.0 and were never implemented here.

### Options

- **§13 `indentSize`**: `EncodeOptions` and `DecodeOptions` accept `indentSize`. The former `indent` name remains as a deprecated alias on both the constructor and the properties.

### PHP-specific notes

- **Empty arrays vs empty objects**: PHP represents both `[]` and `{}` as an empty array. The library reads an empty PHP array as an empty **array**, so `encode([])` emits `[]` and `encode(['k' => []])` emits `k: []`. Both round-trip.
- **Numeric out-of-range policy (§4)**: an integer token outside PHP's integer domain decodes to the nearest float. Numeric keys are quoted on output because PHP coerces numeric string keys to integers.

---

## v3.1–v3.3 Compliance Notes

Aligns with **TOON Specification v3.3**. Changes since v3.0:

- **§7.1 Unicode escapes**: encoder emits C0 control characters (U+0000–U+001F except `\n`, `\r`, `\t`) as `\uXXXX`; decoder accepts `\uXXXX` (case-insensitive hex), rejecting lone surrogates and escapes with fewer than four hex digits. Control characters are preserved as data, never stripped (§15).
- **§9.1 Empty arrays**: decoder accepts the canonical `[]` and `key: []` forms in addition to the legacy `[0]:` / `key[0]:` forms.
- **§2 Numbers**: canonical decimal for `n = 0` or `1e-6 ≤ |n| < 1e21`; exponent notation (lowercase `e`, explicit sign) outside that range. Numbers are never quoted as strings.
- **§6 / §14.2 Strict headers**: leading-zero and malformed bracket lengths (`[03]`, `[-1]`) are rejected in strict mode; non-strict treats them as literal keys.
- **§6 / §14.2 Header delimiter mismatch**: a header whose bracket delimiter differs from its field-list delimiter (e.g. `rows[2|]{a,b}:`) is not a valid header. Strict mode reports a header syntax error on the header line, independent of row width/count checks; non-strict falls through to key-value parsing.
- **§8 / §14.4 Duplicate keys**: strict mode errors on duplicate sibling keys; non-strict applies last-write-wins in document order.

> Superseded by v4.1: `key: []` and `[]` are now the only forms the encoder emits (§9.1).

---

## v2.0 Compliance Notes

This release aligns with **TOON Specification v2.0**, which removes the optional `#` length marker prefix:

- **Encoder**: Always emits `[N]` format (e.g., `[3]: a,b,c`)
- **Decoder**: Rejects `[#N]` format with `SyntaxException`
- **Breaking Change**: Removed `lengthMarker` parameter and related methods from `EncodeOptions`

### Control Character Handling (§7.1, updated in v3.1)

Per TOON Spec §7.1, backslash, double quote, and three control characters use short escapes:

- `\n` (newline, 0x0A), `\r` (carriage return, 0x0D), `\t` (tab, 0x09)

**Implementation Policy**: All other C0 control characters (0x00-0x08, 0x0B, 0x0C, 0x0E-0x1F) are emitted as `\uXXXX` (lowercase hex) and preserved as data, never stripped (§15). On decode, `\uXXXX` is accepted (case-insensitive hex) and lone surrogates are rejected.

**Test Coverage**: `PrimitivesTest.php` and `Version31To33ComplianceTest.php` verify `\uXXXX` escaping on encode, decoding, and round-trip preservation of control characters.

---

## Encoder Conformance Checklist (§13.1)

Conforming encoders MUST:

- [x] **Produce UTF-8 output with LF (U+000A) line endings (§5)**
  - ✅ Verified in `Encoders.php`: Uses PHP string concatenation with `\n`
  - ✅ Test coverage: All encoding tests verify output format

- [x] **Use consistent indentation (default 2 spaces, no tabs) (§12)**
  - ✅ Implemented in `LineWriter.php`: `str_repeat(Constants::SPACE, $options->indent)`
  - ✅ Default: 2 spaces (EncodeOptions::default())
  - ✅ Test coverage: `EdgeCasesTest.php`, `FormatInvariantsTest.php`

- [x] **Escape \\, ", \n, \r, \t in quoted strings; reject other escapes (§7.1)**
  - ✅ Implemented in `Primitives.php:escapeString()`
  - ✅ Only escapes: `\\`, `\"`, `\n`, `\r`, `\t`
  - ✅ Rejects strings with unsupported control characters (0x00-0x08, 0x0B, 0x0C, 0x0E-0x1F)
  - ✅ Test coverage: `PrimitivesTest.php` (escape tests + 5 control character rejection tests)

- [x] **Quote strings containing active delimiter, colon, or structural characters (§7.2)**
  - ✅ Implemented in `Primitives.php:needsQuoting()`
  - ✅ Quotes strings with: delimiter, colon, structural chars
  - ✅ Test coverage: `PrimitivesTest.php`, `DelimitersTest.php`

- [x] **Emit array lengths [N] matching actual item count (§6, §9)**
  - ✅ Implemented in `Encoders.php`: Uses `count()` for all array headers
  - ✅ Inline arrays: `[count($array)]:`
  - ✅ List arrays: `[count($array)]:`
  - ✅ Tabular arrays: `[count($array)]`
  - ✅ Test coverage: All array tests verify length accuracy

- [x] **Preserve object key order as encountered (§2)**
  - ✅ PHP arrays maintain insertion order
  - ✅ `Encoders::encodeObject()` iterates keys in order
  - ✅ Test coverage: `ArraysTest.php`, `TabularArraysTest.php`

- [x] **Canonical number form (§2)**
  - ✅ Implemented in `Primitives.php:encodePrimitive()`
  - ✅ Canonical decimal for `n = 0` or `1e-6 ≤ |n| < 1e21`; exponent notation (lowercase `e`, explicit sign) outside that range
  - ✅ Uses locale-independent `number_format()` with explicit '.' decimal separator
  - ✅ Test coverage: `NormalizationTest.php`, `PrimitivesTest.php`

- [x] **Convert -0 to 0 (§2)**
  - ✅ Implemented in `Normalize.php:normalizeNumber()`
  - ✅ Explicitly checks for `-0.0` and converts to `0`
  - ✅ Test coverage: `NormalizationTest.php`

- [x] **Convert NaN/±Infinity to null (§3)**
  - ✅ Implemented in `Normalize.php:normalizeNumber()`
  - ✅ `is_nan()`, `is_infinite()` → `null`
  - ✅ Test coverage: `NormalizationTest.php`

- [x] **Emit no trailing spaces or trailing newline (§12)**
  - ✅ Implemented in `LineWriter.php:toString()`
  - ✅ Returns trimmed output, no trailing newline
  - ✅ Test coverage: `FormatInvariantsTest.php`

**Encoder Conformance: ✅ 10/10 - FULLY CONFORMANT**

---

## Decoder Conformance Checklist (§13.2)

Conforming decoders MUST:

- [x] **Parse array headers per §6 (length, delimiter, optional fields)**
  - ✅ Implemented in `Decoder/HeaderParser.php`
  - ✅ Parses: `[N]:`, `[N|]:`, `[N]{fields}:`
  - ✅ **Rejects** `[#N]` format (removed in v2.0) with clear error message
  - ✅ Test coverage: Comprehensive header parsing tests + v2.0 breaking change tests

- [x] **Split inline arrays and tabular rows using active delimiter only (§11)**
  - ✅ Implemented in `Decoder/DelimiterParser.php:split()`
  - ✅ Respects quoted strings, only splits on unquoted delimiter
  - ✅ Test coverage: `DelimitersTest.php`

- [x] **Unescape quoted strings with only valid escapes (§7.1)**
  - ✅ Implemented in `Decoder/ValueParser.php:unescapeString()`
  - ✅ Only unescapes: `\\`, `\"`, `\n`, `\r`, `\t`
  - ✅ Throws on invalid escape sequences
  - ✅ Test coverage: `PrimitivesTest.php`

- [x] **Type unquoted primitives: true/false/null → booleans/null, numeric → number, else → string (§4)**
  - ✅ Implemented in `Decoder/ValueParser.php:parseValue()`
  - ✅ "true"/"false" → boolean
  - ✅ "null" → null
  - ✅ Numeric strings → int/float
  - ✅ Everything else → string
  - ✅ Test coverage: `PrimitivesTest.php`

- [x] **Enforce strict-mode rules when strict=true (§14)**
  - ✅ Implemented in `Decoder/StrictValidator.php`
  - ✅ Default: `strict=true`
  - ✅ Validates: array counts, row widths, blank lines, colon presence
  - ✅ Test coverage: Comprehensive strict mode tests

- [x] **Preserve array order and object key order (§2)**
  - ✅ PHP arrays maintain insertion order
  - ✅ Parser preserves order during decoding
  - ✅ Test coverage: Round-trip tests verify order preservation

**Decoder Conformance: ✅ 7/7 - FULLY CONFORMANT**

---

## Strict Mode Requirements (§14)

When strict mode is enabled (default), decoders MUST error on:

### 14.1 Array Count and Width Mismatches

- [x] **Inline primitive arrays: decoded value count ≠ declared N**
  - ✅ `StrictValidator::validateArrayCount()` - line 68
  - ✅ Test: `EdgeCasesTest.php`

- [x] **List arrays: number of list items ≠ declared N**
  - ✅ `Parser::parseListArrayFromHeader()` - validates count
  - ✅ Test: List array tests

- [x] **Tabular arrays: number of rows ≠ declared N**
  - ✅ `Parser::parseTabularArrayFromHeader()` - validates count
  - ✅ Test: `TabularArraysTest.php`

- [x] **Tabular row width mismatches: any row's value count ≠ field count**
  - ✅ `StrictValidator::validateTabularRowWidth()` - line 86
  - ✅ Test: `TabularArraysTest.php`

### 14.2 Syntax Errors

- [x] **Missing colon in key context**
  - ✅ `StrictValidator::validateColonPresent()` - line 17
  - ✅ Test: Syntax error tests

- [x] **Invalid escape sequences or unterminated strings in quoted tokens**
  - ✅ `ValueParser::unescapeString()` - throws on invalid escapes
  - ✅ Test: `EdgeCasesTest.php`

- [x] **Delimiter mismatch (detected via width/count checks and header scope)**
  - ✅ Implicit via width/count validation
  - ✅ Test: `DelimitersTest.php`

### 14.3 Indentation Errors

- [x] **Leading spaces not a multiple of indentSize**
  - ✅ `Tokenizer::tokenize()` - validates indent alignment
  - ✅ Test: Indentation tests

- [x] **Any tab used in indentation (tabs allowed in quoted strings and as HTAB delimiter)**
  - ✅ `Tokenizer::tokenize()` - checks for tabs in indentation
  - ✅ Test: Indentation tests

### 14.4 Structural Errors

- [x] **Blank lines inside arrays/tabular rows**
  - ✅ `StrictValidator::validateNoBlankLinesInArray()` - line 99
  - ✅ Test: Array tests

- [x] **Empty input (document with no non-empty lines after ignoring trailing newline(s))**
  - ✅ `StrictValidator::validateNotEmpty()` - line 30
  - ✅ Test: `EdgeCasesTest.php`

**Strict Mode Conformance: ✅ 11/11 - FULLY CONFORMANT**

---

## Architecture Verification

### Instance-Based Pattern (Post-Refactoring)

Both Encoder and Decoder now use consistent instance-based architecture:

**Encoder:**

- Constructor: stores `EncodeOptions` and `LineWriter` as readonly properties
- No parameter threading through methods
- Clean, maintainable code structure

**Decoder:**

- Constructor: stores `DecodeOptions` as readonly property
- No parameter threading through methods
- Matches Encoder pattern

### Static Utilities

Both use static utility classes appropriately:

- `Primitives` - Pure functions for encoding primitives
- `ValueParser` - Pure functions for parsing values
- `DelimiterParser` - Pure functions for delimiter handling
- `HeaderParser` - Pure functions for header parsing
- `StrictValidator` - Pure validation functions
- `Normalize` - Pure normalization functions

---

## Conclusion

**Status: ✅ CONFORMANT WITH TOON SPECIFICATION v4.1**

The toon-php library implements the MUST requirements for:

- Encoder conformance (§13.1)
- Decoder conformance (§13.2)
- Strict mode validation (§14)

---

## References

- TOON Specification v4.1: `/docs/SPEC.md`
- Encoder Implementation: `/src/Encoders.php`, `/src/Toon.php`
- Decoder Implementation: `/src/Decoder/` directory
- Test Suite: `/tests/` directory
- Benchmarks: `/benchmarks/results/`
