<?php

declare(strict_types=1);

namespace HelgeSverre\Toon\Tests\Spec;

use HelgeSverre\Toon\DecodeOptions;
use HelgeSverre\Toon\EncodeOptions;
use HelgeSverre\Toon\Exceptions\CountMismatchException;
use HelgeSverre\Toon\Exceptions\StrictModeException;
use HelgeSverre\Toon\Exceptions\SyntaxException;
use HelgeSverre\Toon\Toon;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * TOON Specification v4.0 / v4.1 compliance.
 *
 * Covers the forms and rules introduced or tightened in v4:
 * - §9.3 nested field groups in tabular headers
 * - §9.5 keyed tabular form
 * - §5.1 comment lines
 * - §4 the normative decoder number grammar
 * - §7.2 the "#" and leading-plus quoting triggers
 * - §9.1 canonical empty-array forms
 * - §12 byte-order mark, CRLF, and trailing-space handling
 * - §6 / §14.2 header syntax and position errors
 */
final class Version4ComplianceTest extends TestCase
{
    // =====================================================================
    // §9.3 Nested field groups
    // =====================================================================

    public function test_nested_uniform_column_becomes_a_nested_field_group(): void
    {
        $input = [
            'forecast' => [
                ['day' => 'Mon', 'temp' => ['min' => -2, 'max' => 4], 'condition' => 'snow'],
                ['day' => 'Tue', 'temp' => ['min' => 1, 'max' => 7], 'condition' => 'cloudy'],
            ],
        ];

        $expected = "forecast[2]{day,temp{min,max},condition}:\n  Mon,-2,4,snow\n  Tue,1,7,cloudy";

        $this->assertSame($expected, Toon::encode($input));
        $this->assertSame($input, Toon::decode($expected));
    }

    public function test_nested_field_groups_nest_arbitrarily_deep(): void
    {
        $input = ['r' => [['a' => ['b' => ['c' => 1]]], ['a' => ['b' => ['c' => 2]]]]];

        $expected = "r[2]{a{b{c}}}:\n  1\n  2";

        $this->assertSame($expected, Toon::encode($input));
        $this->assertSame($input, Toon::decode($expected));
    }

    public function test_column_mixing_null_and_object_disqualifies_tabular_form(): void
    {
        // §9.3: a column that is neither uniform-primitive nor nested-uniform
        // disqualifies the whole array, which then encodes per §9.4.
        $input = ['r' => [['a' => null], ['a' => ['b' => 1]]]];

        $encoded = Toon::encode($input);

        $this->assertSame("r[2]:\n  - a: null\n  - a:\n      b: 1", $encoded);
        $this->assertSame($input, Toon::decode($encoded));
    }

    public function test_leaf_cells_map_depth_first_in_header_order(): void
    {
        $this->assertSame(
            ['k' => [['x' => 1, 'n' => ['a' => 2, 'b' => 3], 'y' => 4]]],
            Toon::decode("k[1]{x,n{a,b},y}:\n  1,2,3,4")
        );
    }

    public function test_row_width_counts_leaf_fields_not_field_entries(): void
    {
        $this->expectException(CountMismatchException::class);
        $this->expectExceptionMessage('expected 3 values, got 2');

        Toon::decode("k[1]{x,n{a,b}}:\n  1,2");
    }

    // =====================================================================
    // §9.5 Keyed tabular form
    // =====================================================================

    public function test_object_of_uniform_objects_uses_keyed_tabular_form(): void
    {
        $input = [
            'stations' => [
                'tempelhof' => ['lat' => 52.47, 'lon' => 13.4, 'active' => true],
                'tegel' => ['lat' => 52.55, 'lon' => 13.29, 'active' => false],
            ],
        ];

        $expected = "stations[2:]{lat,lon,active}:\n  tempelhof: 52.47,13.4,true\n  tegel: 52.55,13.29,false";

        $this->assertSame($expected, Toon::encode($input));
        $this->assertSame($input, Toon::decode($expected));
    }

    public function test_keyed_tabular_form_applies_at_the_document_root(): void
    {
        $input = ['a' => ['x' => 1, 'y' => 2], 'b' => ['x' => 3, 'y' => 4]];

        $expected = "[2:]{x,y}:\n  a: 1,2\n  b: 3,4";

        $this->assertSame($expected, Toon::encode($input));
        $this->assertSame($input, Toon::decode($expected));
    }

    public function test_keyed_header_carries_nested_field_groups(): void
    {
        $input = [
            's' => [
                't' => ['c' => ['lat' => 1, 'lon' => 2], 'on' => true],
                'u' => ['c' => ['lat' => 3, 'lon' => 4], 'on' => false],
            ],
        ];

        $expected = "s[2:]{c{lat,lon},on}:\n  t: 1,2,true\n  u: 3,4,false";

        $this->assertSame($expected, Toon::encode($input));
        $this->assertSame($input, Toon::decode($expected));
    }

    public function test_single_entry_object_stays_in_nested_form(): void
    {
        // §9.5: keyed tabular detection requires at least two entries.
        $this->assertSame("s:\n  t:\n    a: 1\n    b: 2", Toon::encode(['s' => ['t' => ['a' => 1, 'b' => 2]]]));
    }

    public function test_object_with_non_uniform_column_stays_in_nested_form(): void
    {
        $input = ['s' => ['a' => ['x' => 1], 'b' => ['x' => [1, 2]]]];

        $this->assertSame("s:\n  a:\n    x: 1\n  b:\n    x[2]: 1,2", Toon::encode($input));
    }

    public function test_keyed_tabular_object_as_first_field_of_a_list_item(): void
    {
        // §10: the header sits on the hyphen line and entry rows at depth +2,
        // while the object's remaining fields stay at depth +1.
        $input = ['items' => [
            ['stations' => ['a' => ['x' => 1], 'b' => ['x' => 2]], 'note' => 'first'],
            ['other' => 1],
        ]];

        $expected = "items[2]:\n  - stations[2:]{x}:\n      a: 1\n      b: 2\n    note: first\n  - other: 1";

        $this->assertSame($expected, Toon::encode($input));
        $this->assertSame($input, Toon::decode($expected));
    }

    public function test_keyed_entry_keys_are_quoted_when_required(): void
    {
        $input = ['s' => ['my-key' => ['x' => 1], 'other key' => ['x' => 2]]];

        $expected = "s[2:]{x}:\n  \"my-key\": 1\n  \"other key\": 2";

        $this->assertSame($expected, Toon::encode($input));
        $this->assertSame($input, Toon::decode($expected));
    }

    public function test_keyed_header_declares_the_document_delimiter(): void
    {
        $input = ['s' => ['a' => ['x' => 1, 'y' => 2], 'b' => ['x' => 3, 'y' => 4]]];

        $expected = "s[2:|]{x|y}:\n  a: 1|2\n  b: 3|4";

        $this->assertSame($expected, Toon::encode($input, new EncodeOptions(delimiter: '|')));
        $this->assertSame($input, Toon::decode($expected));
    }

    public function test_keyed_scope_does_not_end_on_a_colon_before_delimiter_line(): void
    {
        // §9.5: every line at entry depth containing an unquoted colon is an entry
        // row; the §9.3 colon-before-delimiter rule does not apply here.
        $this->assertSame(
            ['k' => ['x' => ['a' => 1, 'b' => 2], 'y' => ['a' => 3, 'b' => 4]]],
            Toon::decode("k[2:]{a,b}:\n  x: 1,2\n  y: 3,4")
        );
    }

    public function test_keyed_header_accepts_a_declared_entry_count_of_zero(): void
    {
        $this->assertSame(['k' => []], Toon::decode('k[0:]{f}:'));
    }

    public function test_keyed_entry_row_without_cells_is_a_width_error(): void
    {
        // §9.5: a bare "entrykey:" has zero cells, and a field list always declares
        // at least one leaf field.
        $this->expectException(CountMismatchException::class);

        Toon::decode("k[1:]{a}:\n  x:");
    }

    public function test_strict_rejects_line_at_entry_depth_without_a_colon(): void
    {
        $this->expectException(StrictModeException::class);
        $this->expectExceptionMessage('no unquoted colon');

        Toon::decode("k[2:]{a}:\n  x: 1\n  nope");
    }

    public function test_strict_rejects_keyed_header_without_a_field_list(): void
    {
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage('Keyed header requires a field list');

        Toon::decode("k[2:]:\n  a: 1");
    }

    // =====================================================================
    // §5.1 Comment lines
    // =====================================================================

    public function test_comment_lines_are_removed_before_all_other_processing(): void
    {
        $toon = "# Weekly export\nforecast[2]{day,condition}:\n  # Monday was revised\n  Mon,snow\n  Tue,cloudy";

        $this->assertSame(
            ['forecast' => [
                ['day' => 'Mon', 'condition' => 'snow'],
                ['day' => 'Tue', 'condition' => 'cloudy'],
            ]],
            Toon::decode($toon)
        );
    }

    public function test_a_comment_is_not_counted_as_a_blank_line_inside_an_array(): void
    {
        $this->assertSame(['a', 'b'], Toon::decode("[2]:\n  - a\n  # c\n  - b"));
    }

    public function test_a_comment_does_not_terminate_a_keyed_scope(): void
    {
        $this->assertSame(
            ['k' => ['x' => ['a' => 1], 'y' => ['a' => 2]]],
            Toon::decode("k[2:]{a}:\n  x: 1\n# c\n  y: 2")
        );
    }

    public function test_a_document_of_only_comments_decodes_to_an_empty_object(): void
    {
        $this->assertSame([], Toon::decode("# a\n# b"));
    }

    public function test_a_tab_before_the_hash_does_not_make_a_comment_line(): void
    {
        // §5.1: only spaces may precede the "#".
        $this->expectException(StrictModeException::class);

        Toon::decode("a: 1\n\t# x");
    }

    public function test_encoder_quotes_hash_leading_strings_so_output_never_reads_as_a_comment(): void
    {
        $this->assertSame("a: \"#tag\"\nb: \"#\"\nc: mid#dle", Toon::encode(['a' => '#tag', 'b' => '#', 'c' => 'mid#dle']));
        $this->assertSame(['a' => '#tag', 'b' => '#', 'c' => 'mid#dle'], Toon::decode(Toon::encode(['a' => '#tag', 'b' => '#', 'c' => 'mid#dle'])));
    }

    // =====================================================================
    // §4 Number grammar
    // =====================================================================

    /**
     * @dataProvider nonNumericTokens
     */
    public function test_tokens_outside_the_number_grammar_decode_as_strings(string $token): void
    {
        $this->assertSame(['k' => $token], Toon::decode("k: {$token}"));
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function nonNumericTokens(): array
    {
        return [
            'leading dot' => ['.5'],
            'trailing dot' => ['1.'],
            'leading plus' => ['+5'],
            'infinity' => ['Infinity'],
            'nan' => ['NaN'],
            'hex' => ['0x10'],
            'underscores' => ['1_000'],
            'leading zero' => ['05'],
        ];
    }

    public function test_valid_number_forms_still_decode_as_numbers(): void
    {
        $this->assertSame(42, Toon::decode('k: 42')['k']);
        $this->assertSame(0.5, Toon::decode('k: 0.5')['k']);
        $this->assertSame(1.5, Toon::decode('k: 1.5000')['k']);
        $this->assertSame(-1000.0, Toon::decode('k: -1E+03')['k']);
        $this->assertSame(0, Toon::decode('k: -0')['k']);
    }

    public function test_encoder_quotes_leading_plus_numeric_lookalikes(): void
    {
        $this->assertSame('a: "+1"', Toon::encode(['a' => '+1']));
    }

    // =====================================================================
    // §7.4 Quoted-token boundary
    // =====================================================================

    public function test_a_token_starting_with_a_quote_must_end_at_its_closing_quote(): void
    {
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage('Unexpected content after closing quote');

        Toon::decode('k: "a"b');
    }

    public function test_the_quoted_token_boundary_is_enforced_in_non_strict_mode_too(): void
    {
        $this->expectException(SyntaxException::class);

        Toon::decode('k: "a"b', DecodeOptions::lenient());
    }

    // =====================================================================
    // §9.1 Empty arrays
    // =====================================================================

    public function test_empty_arrays_use_the_canonical_forms(): void
    {
        $this->assertSame('items: []', Toon::encode(['items' => []]));
        $this->assertSame('[]', Toon::encode([]));
    }

    public function test_legacy_empty_array_forms_are_still_accepted(): void
    {
        $this->assertSame(['items' => []], Toon::decode('items[0]:'));
        $this->assertSame([], Toon::decode('[0]:'));
        $this->assertSame(['items' => []], Toon::decode('items: []'));
        $this->assertSame([], Toon::decode('[]'));
        $this->assertSame([[]], Toon::decode("[1]:\n  - []"));
    }

    public function test_an_empty_array_literal_inside_a_row_is_the_string_bracket_pair(): void
    {
        $this->assertSame(['k' => [['a' => '[]']]], Toon::decode("k[1]{a}:\n  []"));
    }

    // =====================================================================
    // §12 Byte-order mark, CRLF, trailing spaces
    // =====================================================================

    public function test_a_leading_byte_order_mark_is_removed(): void
    {
        $this->assertSame(['a' => 1, 'b' => 2], Toon::decode("\xEF\xBB\xBFa: 1\nb: 2"));
    }

    public function test_crlf_input_is_accepted(): void
    {
        $this->assertSame(['a' => 1, 'b' => 2], Toon::decode("a: 1\r\nb: 2"));
    }

    public function test_trailing_spaces_are_stripped_before_line_classification(): void
    {
        // §12: a line whose content is "-" followed only by spaces is the bare
        // marker for an empty-object list item.
        $this->assertSame(['a', []], Toon::decode("[2]:\n  - a   \n  -   "));
    }

    public function test_token_trimming_is_exactly_u0020(): void
    {
        // §12: any other whitespace - notably HTAB outside its delimiter role -
        // is part of the token.
        $this->assertSame(['k' => "\tvalue"], Toon::decode("k: \tvalue"));
        $this->assertSame(['k' => ['a', "\tb"]], Toon::decode("k[2]: a, \tb"));
    }

    public function test_strict_mode_rejects_ill_formed_utf8(): void
    {
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage('Ill-formed UTF-8');

        Toon::decode("a: \xC3\x28");
    }

    public function test_encoder_rejects_host_strings_that_are_not_valid_unicode(): void
    {
        // §3: a host string containing an unpaired surrogate is not representable.
        $this->expectException(InvalidArgumentException::class);

        Toon::encode(['a' => "\xED\xA0\x80"]);
    }

    // =====================================================================
    // §6 / §14.2 Header syntax and position errors
    // =====================================================================

    public function test_strict_rejects_a_bracket_segment_without_a_length(): void
    {
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage('missing length');

        Toon::decode('key[]:');
    }

    public function test_strict_rejects_an_empty_field_list(): void
    {
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage('empty field list');

        Toon::decode("k[1]{}:\n  1");
    }

    public function test_strict_rejects_a_field_name_repeated_within_one_field_list(): void
    {
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage('Duplicate field name');

        Toon::decode("k[1]{a,a}:\n  1,2");
    }

    public function test_names_repeated_at_different_nesting_levels_are_not_duplicates(): void
    {
        $this->assertSame(
            ['k' => [['x' => 1, 'n' => ['x' => 2]]]],
            Toon::decode("k[1]{x,n{x}}:\n  1,2")
        );
    }

    public function test_strict_rejects_content_after_a_fields_bearing_headers_colon(): void
    {
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage('Content after a fields-bearing header');

        Toon::decode('k[1]{a}: 1');
    }

    public function test_strict_rejects_whitespace_between_a_key_and_its_bracket_segment(): void
    {
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage('Whitespace between key and bracket segment');

        Toon::decode('foo [2]: 1,2');
    }

    public function test_strict_rejects_trailing_content_after_a_completed_root_form(): void
    {
        $this->expectException(StrictModeException::class);
        $this->expectExceptionMessage('Trailing content');

        Toon::decode("[1]: a\nb: 2");
    }

    public function test_strict_rejects_an_indentation_depth_jump(): void
    {
        $this->expectException(StrictModeException::class);
        $this->expectExceptionMessage('depth jump');

        Toon::decode("a:\n    b: 1");
    }

    public function test_dotted_keys_are_single_literal_keys(): void
    {
        // §8: key folding is gone; a dotted key carries no structure.
        $this->assertSame(
            ['data.meta.items' => [['id' => 1, 'name' => 'a'], ['id' => 2, 'name' => 'b']]],
            Toon::decode("data.meta.items[2]{id,name}:\n  1,a\n  2,b")
        );
    }

    // =====================================================================
    // §14.1 Non-strict count and width tolerance
    // =====================================================================

    public function test_non_strict_mode_decodes_every_row_a_scope_contains(): void
    {
        // §14.1: a declared [N] never terminates or truncates a scope.
        $this->assertSame(
            ['k' => [['a' => 1], ['a' => 2]]],
            Toon::decode("k[1]{a}:\n  1\n  2", DecodeOptions::lenient())
        );
    }

    // =====================================================================
    // §15 Prototype-key safety
    // =====================================================================

    public function test_prototype_keys_are_ordinary_own_entries(): void
    {
        $this->assertSame(
            ['__proto__' => 1, 'constructor' => 2, 'prototype' => 3],
            Toon::decode("__proto__: 1\nconstructor: 2\nprototype: 3")
        );
    }

    // =====================================================================
    // §11.1 Every header declares the document delimiter
    // =====================================================================

    public function test_list_form_headers_declare_the_document_delimiter(): void
    {
        $this->assertSame(
            "a[2|]:\n  - [1|]: 1\n  - [2|]: 2|3",
            Toon::encode(['a' => [[1], [2, 3]]], new EncodeOptions(delimiter: '|'))
        );
    }
}
