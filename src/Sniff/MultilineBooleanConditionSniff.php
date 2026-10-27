<?php

declare(strict_types=1);

namespace FastForward\CodeStandards\Sniff;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PhpCsFixer\WhitespacesFixerConfig;

/** Splits symbolic boolean condition parts using PHPCS's existing scope metadata and whitespace fixes. */
final readonly class MultilineBooleanConditionSniff implements Sniff
{
    /** @param WhitespacesFixerConfig $spacing Native ECS spacing resolved after consumer configuration. */
    public function __construct(private WhitespacesFixerConfig $spacing) {}

    /** @return list<int> The while token covers both while and do-while conditions. */
    public function register(): array
    {
        return [T_IF, T_ELSEIF, T_WHILE];
    }

    /** Checks only an existing multiline condition; application code is never evaluated. */
    public function process(File $phpcsFile, int $stackPtr): void
    {
        $tokens = $phpcsFile->getTokens();
        $range = $this->conditionRange($phpcsFile, $tokens, $stackPtr);
        if (null === $range) {
            return;
        }

        [$open, $close] = $range;
        $indent = $this->baseIndent($tokens, $stackPtr);
        $outerDepth = count($tokens[$open]['nested_parenthesis'] ?? []);

        for ($index = $open + 1; $index < $close; ++$index) {
            if (in_array($tokens[$index]['code'], [T_FUNCTION, T_CLOSURE, T_FN, T_ANON_CLASS], true)) {
                $end = $this->declarationEnd($phpcsFile, $tokens, $index, $close);
                if (null === $end) {
                    return;
                }

                $index = $end;
                continue;
            }

            if (in_array($tokens[$index]['code'], [T_BOOLEAN_OR, T_BOOLEAN_AND], true)) {
                $depth = max(1, count($tokens[$index]['nested_parenthesis'] ?? []) - $outerDepth);
                $this->splitInlineOperator($phpcsFile, $tokens, $index, $open, $indent, $depth);
            }
        }
    }

    /**
     * @param array<int, array<string, mixed>> $tokens Native PHPCS token records.
     * @return array{int, int}|null Validated parentheses, or no eligible condition.
     */
    private function conditionRange(File $file, array $tokens, int $index): ?array
    {
        if (!isset($tokens[$index]['parenthesis_opener'], $tokens[$index]['parenthesis_closer'])) {
            $file->addError('Cannot determine the native condition boundary.', $index, 'MissingConditionBoundary');

            return null;
        }

        $open = $tokens[$index]['parenthesis_opener'];
        $close = $tokens[$index]['parenthesis_closer'];

        return $tokens[$open]['line'] === $tokens[$close]['line'] ? null : [$open, $close];
    }

    /**
     * @param array<int, array<string, mixed>> $tokens Native scopes, including arrow bodies without braces.
     * @return int|null The declaration's inclusive end; an unknown boundary is a visible failing diagnostic.
     */
    private function declarationEnd(File $file, array $tokens, int $index, int $conditionEnd): ?int
    {
        $end = $tokens[$index]['scope_closer'] ?? null;
        if (null === $end || $end <= $index || $end > $conditionEnd) {
            $file->addError('Cannot determine the native function/class expression boundary.', $index, 'MissingExpressionBoundary');

            return null;
        }

        return $end;
    }

    /**
     * @param array<int, array<string, mixed>> $tokens Original token positions remain stable within the fix changeset.
     * @param string $indent Existing statement indentation, retained before native continuation spacing.
     * @param int $depth Native parenthesis nesting relative to the condition opener.
     */
    private function splitInlineOperator(File $file, array $tokens, int $index, int $open, string $indent, int $depth): void
    {
        $previous = $file->findPrevious(T_WHITESPACE, $index - 1, $open, true);
        if (false === $previous || $tokens[$previous]['line'] < $tokens[$index]['line']) {
            return;
        }

        if (!$file->addFixableError('Each symbolic boolean part in a multiline condition must start on its own line.', $index, 'InlineBooleanPart')) {
            return;
        }

        $whitespace = $this->spacing->getLineEnding() . $indent . str_repeat($this->spacing->getIndent(), $depth);
        $file->fixer->beginChangeset();
        if (T_WHITESPACE === $tokens[$index - 1]['code']) {
            $file->fixer->replaceToken($index - 1, $whitespace);
        } else {
            $file->fixer->addContentBefore($index, $whitespace);
        }
        $file->fixer->endChangeset();
    }

    /** @param array<int, array<string, mixed>> $tokens Statement-line whitespace, including literal tabs. */
    private function baseIndent(array $tokens, int $index): string
    {
        $start = $index;
        while ($start > 0 && $tokens[$start - 1]['line'] === $tokens[$index]['line']) {
            --$start;
        }

        return T_WHITESPACE === $tokens[$start]['code'] ? rtrim($tokens[$start]['content'], "\r\n") : '';
    }
}
