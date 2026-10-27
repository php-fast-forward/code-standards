<?php

declare(strict_types=1);

namespace CodeStandardsFixture;

use PhpCsFixer\AbstractFixer;
use PhpCsFixer\FixerDefinition\FixerDefinition;
use PhpCsFixer\FixerDefinition\FixerDefinitionInterface;
use PhpCsFixer\Tokenizer\Token;
use PhpCsFixer\Tokenizer\Tokens;
use SplFileInfo;

/** A synthetic consumer checker loaded only by explicitly selected trusted config. */
final class BootstrapFixer extends AbstractFixer
{
    public function getName(): string
    {
        return 'Fixture/replace_marker';
    }

    public function getDefinition(): FixerDefinitionInterface
    {
        return new FixerDefinition('Replace a synthetic fixture marker.', []);
    }

    public function isCandidate(Tokens $tokens): bool
    {
        return true;
    }

    protected function applyFix(SplFileInfo $file, Tokens $tokens): void
    {
        foreach ($tokens as $index => $token) {
            if ($token->isGivenKind(T_CONSTANT_ENCAPSED_STRING) && "'needs-fix'" === $token->getContent()) {
                $tokens[$index] = new Token([T_CONSTANT_ENCAPSED_STRING, "'fixed'"]);
            }
        }
    }
}
