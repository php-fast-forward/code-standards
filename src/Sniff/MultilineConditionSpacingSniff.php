<?php

declare(strict_types=1);

namespace FastForward\CodeStandards\Sniff;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Standards\PSR12\Sniffs\ControlStructures\ControlStructureSpacingSniff;
use PhpCsFixer\WhitespacesFixerConfig;

/** Adapts native PSR-12 condition wrappers to ECS spacing without duplicating their token/fix implementation. */
final class MultilineConditionSpacingSniff extends ControlStructureSpacingSniff
{
    /** @param WhitespacesFixerConfig $spacing Native consumer spacing, including explicitly selected standard deviations. */
    public function __construct(private readonly WhitespacesFixerConfig $spacing)
    {
        parent::__construct();
    }

    /** @return list<int> Restricts the native policy to this package's reviewed conditional-statement scope. */
    public function register(): array
    {
        return [T_IF, T_ELSEIF, T_WHILE];
    }

    /** Delegates wrapper placement and indentation to PHPCS using its own public tab-width configuration. */
    public function process(File $phpcsFile, int $stackPtr): void
    {
        $tab = str_repeat(' ', $phpcsFile->config->tabWidth);
        $this->indent = strlen(str_replace("\t", $tab, $this->spacing->getIndent()));
        parent::process($phpcsFile, $stackPtr);
        $this->completeFirstExpressionIndent($phpcsFile, $stackPtr);
    }

    /**
     * Pads outside the first token when PHPCS's text/comment exclusion leaves it at column one.
     * ECS runs Sniffs before Fixers; otherwise StatementIndentation shifts valid continuations with that token.
     */
    private function completeFirstExpressionIndent(File $file, int $statement): void
    {
        $tokens = $file->getTokens();
        $open = $tokens[$statement]['parenthesis_opener'] ?? null;
        $close = $tokens[$statement]['parenthesis_closer'] ?? null;
        if (
            null === $open
            || null === $close
            || $tokens[$open]['line'] === $tokens[$close]['line']
        ) {
            return;
        }

        $first = $file->findNext(T_WHITESPACE, $open + 1, $close, true);
        if (
            false === $first
            || 1 !== $tokens[$first]['column']
            || $tokens[$first]['content'] !== $file->fixer->getTokenContent($first)
        ) {
            return;
        }

        $statementStart = $file->findFirstOnLine(T_WHITESPACE, $statement, true);
        $padding = str_repeat(' ', $tokens[$statementStart]['column'] + $this->indent - 1);
        if ($file->addFixableError('Indent the first expression before the native fixer pass.', $first, 'FirstExpressionIndentCompatibility')) {
            $file->fixer->addContentBefore($first, $padding);
        }
    }
}
