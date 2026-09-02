<?php

declare(strict_types=1);

namespace HelgeSverre\Toon\Decoder;

use HelgeSverre\Toon\Constants;
use HelgeSverre\Toon\DecodeOptions;
use HelgeSverre\Toon\Exceptions\SyntaxException;

/**
 * Tokenizes TOON input into lines with metadata.
 *
 * Handles line splitting, indentation depth computation, and validation.
 */
final class Tokenizer
{
    /** UTF-8 encoding of U+FEFF (§12). */
    private const BYTE_ORDER_MARK = "\xEF\xBB\xBF";

    /**
     * Tokenize TOON input into lines with metadata.
     *
     * @param  string  $input  TOON input string
     * @param  DecodeOptions  $options  Decode options
     * @return array<int, array{content: string, depth: int, line: int, indent: int, blank: bool}> Array of line metadata
     */
    public static function tokenize(string $input, DecodeOptions $options): array
    {
        // §12: a single leading U+FEFF is a byte-order mark, not content.
        if (str_starts_with($input, self::BYTE_ORDER_MARK)) {
            $input = substr($input, strlen(self::BYTE_ORDER_MARK));
        }

        // §4/§14.2: byte input MUST decode as UTF-8; strict mode errors on
        // ill-formed sequences rather than substituting U+FFFD.
        if ($options->strict && $input !== '' && ! mb_check_encoding($input, 'UTF-8')) {
            throw new SyntaxException('Ill-formed UTF-8 in input', 0);
        }

        // Preprocess input (handle trailing newlines)
        $input = self::preprocessInput($input);

        // Split into lines
        $rawLines = explode("\n", $input);

        $lines = [];
        $lineNumber = 0;

        foreach ($rawLines as $line) {
            $lineNumber++;

            // §12: a trailing CR belongs to the line terminator (CRLF input), and
            // trailing spaces are not part of the line's content. Both are removed
            // before line classification.
            if (str_ends_with($line, "\r")) {
                $line = substr($line, 0, -1);
            }
            $line = rtrim($line, ' ');

            // §5.1: a line whose first character after zero or more leading spaces
            // is "#" is a comment line, removed in a lexical pre-pass in strict and
            // non-strict mode alike. Only spaces may precede the "#"; a tab in the
            // leading whitespace disqualifies the line. Removal never creates or
            // terminates a scope, and a comment is never counted as a row, item,
            // entry, or blank line, so the line is dropped entirely.
            if (str_starts_with(ltrim($line, ' '), Constants::COMMENT_MARKER)) {
                continue;
            }

            // Track blank lines (needed for strict mode validation in arrays)
            if (trim($line) === '') {
                $lines[] = [
                    'content' => '',
                    'depth' => 0,
                    'line' => $lineNumber,
                    'indent' => 0,
                    'blank' => true,
                ];

                continue;
            }

            // Compute indentation
            $indent = self::getIndentation($line);

            // Validate indentation in strict mode
            if ($options->strict) {
                StrictValidator::validateNoTabIndentation($line, $lineNumber);
                StrictValidator::validateIndentationMultiple($indent, $options->indentSize, $lineNumber, $line);
            } else {
                // In lenient mode, check for tabs but don't error (per §12.10)
                // Current implementation: reject tabs in both modes (implementation-defined)
                // Comment this out to allow tabs in lenient mode
                // StrictValidator::validateNoTabIndentation($line, $lineNumber);
            }

            // Compute depth
            $depth = self::computeDepth($indent, $options->indentSize, $options->strict);

            $lines[] = [
                'content' => $line,
                'depth' => $depth,
                'line' => $lineNumber,
                'indent' => $indent,
                'blank' => false,
            ];
        }

        return $lines;
    }

    /**
     * Preprocess input string.
     *
     * Handles trailing newlines per spec §12.15 (decoders SHOULD accept trailing newline).
     *
     * @param  string  $input  Raw input
     * @return string Preprocessed input
     */
    private static function preprocessInput(string $input): string
    {
        // Accept optional trailing newline (spec §12.15)
        return rtrim($input, "\n");
    }

    /**
     * Get the number of leading spaces in a line.
     *
     * @param  string  $line  Line to analyze
     * @return int Number of leading spaces
     */
    private static function getIndentation(string $line): int
    {
        // Count leading spaces
        $trimmed = ltrim($line, ' ');

        return strlen($line) - strlen($trimmed);
    }

    /**
     * Compute nesting depth from indentation.
     *
     * In strict mode: depth = indent / indentSize (must be exact multiple)
     * In non-strict mode: depth = floor(indent / indentSize)
     *
     * @param  int  $indent  Number of leading spaces
     * @param  int  $indentSize  Expected spaces per level
     * @param  bool  $strict  Strict mode flag
     * @return int Nesting depth (0 = root level)
     */
    private static function computeDepth(int $indent, int $indentSize, bool $strict): int
    {
        // Handle zero indent size (compact mode)
        if ($indentSize === 0) {
            return 0;
        }

        if ($strict) {
            // Strict mode: exact multiple required
            return (int) ($indent / $indentSize);
        }

        // Non-strict mode: floor division
        return (int) floor($indent / $indentSize);
    }
}
