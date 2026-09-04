<?php

declare(strict_types=1);

namespace HelgeSverre\Toon\Tests\Spec;

use HelgeSverre\Toon\DecodeOptions;
use HelgeSverre\Toon\Exceptions\DecodeException;
use HelgeSverre\Toon\Toon;
use PHPUnit\Framework\TestCase;

/**
 * Production-path coverage for array-length and tabular field-list parsing.
 *
 * These rules were previously exercised only through internal helper methods
 * (`DelimiterParser::extractLength` / `extractFields`) that no production code
 * consumed. The helpers were removed as dead code; the behaviour they enforced
 * is a real requirement of the decoder, so it is asserted here against the
 * public `Toon::decode()` path (which parses headers inline in `HeaderParser`).
 */
final class MalformedHeaderDecodeTest extends TestCase
{
    /**
     * @dataProvider malformedHeaders
     */
    public function test_strict_decode_rejects_malformed_header(string $toon): void
    {
        $this->expectException(DecodeException::class);
        Toon::decode($toon, new DecodeOptions(strict: true));
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function malformedHeaders(): iterable
    {
        // Array length section (formerly DelimiterParser::extractLength).
        yield 'negative length' => ['n[-5]: a,b'];
        yield 'negative length -1' => ['n[-1]: a'];
        // §7.4: "5]" is an ordinary literal key, not a malformed header, so it is
        // no longer listed here (see the acceptance test below).
        yield 'non-numeric length' => ['n[abc]: x'];
        yield 'leading-zero length' => ['n[03]: a,b,c'];

        // Tabular field list (formerly DelimiterParser::extractFields).
        yield 'missing closing brace' => ["u[1]{id,name:\n  1,2"];
        yield 'empty field list with data' => ["u[1]{}:\n  x"];
    }

    /**
     * Happy path for the field list: quoted names and surrounding whitespace are
     * handled (formerly DelimiterParser::extractFields' passing cases).
     */
    public function test_decode_parses_quoted_and_trimmed_tabular_fields(): void
    {
        $toon = "u[1]{ \"first name\" , last }:\n  Ada,Lovelace";

        $this->assertSame(
            ['u' => [['first name' => 'Ada', 'last' => 'Lovelace']]],
            Toon::decode($toon)
        );
    }

    /**
     * §6/§14.2: a field entry is a key, and `unquoted-key` matches at least one
     * character, so an empty field entry is a malformed field list.
     */
    public function test_strict_decode_rejects_empty_field_entry(): void
    {
        $this->expectException(DecodeException::class);

        Toon::decode("u[1]{id,,name}:\n  1,2,3");
    }

    public function test_decode_accepts_stray_close_bracket_as_literal_key(): void
    {
        // §7.4: an unquoted key token is everything before the first unquoted
        // colon, accepted as a literal key in strict and non-strict mode alike.
        $this->assertSame(['5]' => 'x'], Toon::decode('5]: x'));
    }
}
