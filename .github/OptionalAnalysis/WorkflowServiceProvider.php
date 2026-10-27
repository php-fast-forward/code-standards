<?php

declare(strict_types=1);

namespace FastForward\CodeStandards\Workflow;

use FastForward\CodeStandards\Filesystem\PhpFiles;
use FastForward\CodeStandards\Git\FileSelection;
use FastForward\CodeStandards\Project\ProjectScopeResolver;
use FastForward\Container\Factory\FactoryInterface;
use FastForward\Container\Factory\InvokableFactory;
use Interop\Container\ServiceProviderInterface;

/** Binds only the trusted workflow checkout root; existing native factories/autowiring supply collaborators. */
final readonly class WorkflowServiceProvider implements ServiceProviderInterface
{
    public function __construct(private string $root) {}

    /** @return array<string, FactoryInterface> The scalar checkout identity cannot be inferred by autowiring. */
    public function getFactories(): array
    {
        return [OptionalAnalysisRunner::class => new InvokableFactory(
            OptionalAnalysisRunner::class,
            FileSelection::class,
            PhpFiles::class,
            ProjectScopeResolver::class,
            BoundedProcess::class,
            NativeReportParser::class,
            $this->root,
        )];
    }

    /** @return array<string, callable> This helper decorates no public runtime service. */
    public function getExtensions(): array
    {
        return [];
    }
}
