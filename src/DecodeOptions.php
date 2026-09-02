<?php

declare(strict_types=1);

namespace HelgeSverre\Toon;

use InvalidArgumentException;

final class DecodeOptions
{
    /** Expected number of spaces per indentation level (§13 `indentSize`). */
    public readonly int $indentSize;

    /**
     * Deprecated alias of {@see self::$indentSize}, kept for backward compatibility.
     *
     * @deprecated Use $indentSize; the spec renamed the option in v3.3 (§13).
     */
    public readonly int $indent;

    /**
     * Create new decoding options.
     *
     * @param  int  $indentSize  Expected number of spaces per indentation level (default: 2, minimum: 1)
     * @param  bool  $strict  Enable strict mode validation (default: true)
     * @param  int|null  $indent  Deprecated alias of $indentSize; when given it wins
     *
     * @throws InvalidArgumentException If the indent size is less than 1
     */
    public function __construct(
        int $indentSize = 2,
        public readonly bool $strict = true,
        ?int $indent = null,
    ) {
        $resolved = $indent ?? $indentSize;

        // §12: depth is measured in indentSize-space units; 0 makes every line
        // depth 0 and cannot recover nesting, so it is not a valid setting.
        if ($resolved < 1) {
            throw new InvalidArgumentException('Indent size must be a positive integer (at least 1)');
        }

        $this->indentSize = $resolved;
        $this->indent = $resolved;
    }

    /**
     * Create options with default values.
     *
     * @return self Default options (indentSize: 2, strict: true)
     */
    public static function default(): self
    {
        return new self;
    }

    /**
     * Create options with lenient validation.
     *
     * Disables strict mode for more forgiving parsing:
     * - Allows count mismatches
     * - Allows irregular indentation (floor division)
     * - Allows blank lines in arrays
     * - Allows tabs in indentation
     * - Allows empty input
     *
     * Ideal for hand-written TOON or exploratory parsing.
     *
     * @return self Lenient options (indentSize: 2, strict: false)
     */
    public static function lenient(): self
    {
        return new self(
            indentSize: 2,
            strict: false
        );
    }

    /**
     * Create a copy with different indentation.
     *
     * @param  int  $indent  Expected number of spaces per indentation level
     * @return self New instance with updated indent size
     *
     * @deprecated Use withIndentSize(); the spec renamed the option in v3.3 (§13).
     */
    public function withIndent(int $indent): self
    {
        return new self($indent, $this->strict);
    }

    /**
     * Create a copy with a different indentation size (§13 `indentSize`).
     *
     * @param  int  $indentSize  Expected number of spaces per indentation level
     * @return self New instance with updated indent size
     */
    public function withIndentSize(int $indentSize): self
    {
        return new self($indentSize, $this->strict);
    }

    /**
     * Create a copy with different strict mode setting.
     *
     * @param  bool  $strict  Enable/disable strict mode validation
     * @return self New instance with updated strict setting
     */
    public function withStrict(bool $strict): self
    {
        return new self($this->indentSize, $strict);
    }
}
