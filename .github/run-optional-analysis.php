<?php

declare(strict_types=1);

use FastForward\CodeStandards\ServiceProvider\StandardsServiceProvider;
use FastForward\CodeStandards\Workflow\OptionalAnalysisRunner;
use FastForward\CodeStandards\Workflow\SummaryRenderer;
use FastForward\CodeStandards\Workflow\WorkflowInput;
use FastForward\CodeStandards\Workflow\WorkflowServiceProvider;
use FastForward\Container\ContainerFactory;
use Symfony\Component\Console\Output\NullOutput;

$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';
foreach (['AnalysisResult', 'NativeReportParser', 'SummaryRenderer', 'WorkflowInput', 'BoundedProcess', 'OptionalAnalysisRunner', 'WorkflowServiceProvider'] as $class) {
    require __DIR__ . '/OptionalAnalysis/' . $class . '.php';
}
$input = WorkflowInput::fromEnvironment(getenv());
$container = ContainerFactory::create(new StandardsServiceProvider($root . '/vendor', new NullOutput()), new WorkflowServiceProvider($root));
$results = $container->get(OptionalAnalysisRunner::class)->run($input);
$summary = $container->get(SummaryRenderer::class)->render($results);
$path = getenv('GITHUB_STEP_SUMMARY');
if (!is_string($path) || '' === $path || strlen($summary) !== file_put_contents($path, $summary, FILE_APPEND)) {
    throw new RuntimeException('Cannot write the optional-analysis summary.');
}
$policies = ['Magic Numbers' => $input->magicNumbersPolicy, 'Rector' => $input->rectorPolicy];
$failure = false;
foreach ($results as $result) {
    $failure = $result->blocks($policies[$result->tool]) || $failure;
}
$output = getenv('GITHUB_OUTPUT');
$values = "summary-written=true\nblocking=" . ($failure ? 'true' : 'false') . "\n";
if (!is_string($output) || '' === $output || strlen($values) !== file_put_contents($output, $values, FILE_APPEND)) {
    throw new RuntimeException('Cannot record optional-analysis summary completion and policy.');
}
// Classification completed; the workflow's mandatory final gate applies this explicit blocking decision.
exit(0);
