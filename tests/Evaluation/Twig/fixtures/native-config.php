<?php

declare(strict_types=1);

use Twig\Extension\AbstractExtension;
use Twig\Node\Node;
use Twig\Token;
use Twig\TokenParser\AbstractTokenParser;
use Twig\TwigFunction;
use TwigCsFixer\Config\Config;
use TwigCsFixer\File\Finder;
use TwigCsFixer\Ruleset\Ruleset;
use TwigCsFixer\Standard\Twig;

$config = new Config('Native Twig evaluation');
$config->setRuleset((new Ruleset())->addStandard(new Twig()));
$config->setFinder((new Finder())->in(__DIR__ . '/templates')->notName('excluded.twig')->append([
    __DIR__ . '/../outside.twig',
    __DIR__ . '/templates/keep a.twig',
]));
$config->addTwigExtension(new class extends AbstractExtension {
    public function getFunctions(): array
    {
        return [new TwigFunction('application_function', static function (): void {
            file_put_contents(__DIR__ . '/function-called', 'unexpected rendering');
        })];
    }

    public function getTokenParsers(): array
    {
        return [new class extends AbstractTokenParser {
            public function parse(Token $token): Node
            {
                file_put_contents(__DIR__ . '/tag-parsed', 'native parsing');
                $this->parser->getStream()->expect(Token::BLOCK_END_TYPE);

                return new Node([], [], $token->getLine());
            }

            public function getTag(): string
            {
                return 'fixture_mark';
            }
        }];
    }
});

return $config;
