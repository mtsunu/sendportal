<?php

declare(strict_types=1);

/**
 * Dependency-free CLI regression coverage for bin/composer-policy.
 *
 * Run with: php tests/Composer/ComposerPolicyGuardTest.php
 */

function fail(string $message): never
{
    fwrite(STDERR, "FAIL: {$message}\n");

    exit(1);
}

function assertTrue(bool $condition, string $message): void
{
    if (! $condition) {
        fail($message);
    }
}

/**
 * @param list<string> $command
 * @param array<string, string> $environment
 * @return array{int, string}
 */
function runCommand(array $command, array $environment): array
{
    $process = proc_open(
        $command,
        [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes,
        null,
        $environment,
    );

    if (! is_resource($process)) {
        fail('Could not start the Composer policy guard.');
    }

    $output = stream_get_contents($pipes[1]).stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    return [proc_close($process), $output];
}

function writeFile(string $path, string $contents): void
{
    if (file_put_contents($path, $contents) === false) {
        fail("Could not write {$path}.");
    }
}

function removeDirectory(string $path): void
{
    if (! file_exists($path)) {
        return;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );

    foreach ($iterator as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }

    rmdir($path);
}

/**
 * @return list<string>
 */
function trackedFiles(string $repositoryRoot): array
{
    $process = proc_open(['git', 'ls-files', '-z'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $repositoryRoot);

    if (! is_resource($process)) {
        fail('Could not enumerate tracked files for the route audit.');
    }

    $output = stream_get_contents($pipes[1]);
    $errors = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    if (proc_close($process) !== 0) {
        fail("Could not enumerate tracked files for the route audit: {$errors}");
    }

    return array_values(array_filter(explode("\0", $output), static fn (string $path): bool => $path !== ''));
}

/**
 * @return list<array{line: int, text: string}>
 */
function normalizedLogicalLines(string $contents): array
{
    $logicalLines = [];
    $buffer = '';
    $startLine = 1;
    $lineNumber = 1;

    foreach (preg_split('/\R/', $contents) as $line) {
        $trimmed = rtrim($line);
        $continues = str_ends_with($trimmed, '\\');
        $fragment = $continues ? substr($trimmed, 0, -1) : $line;
        $buffer .= ($buffer === '' ? '' : ' ').$fragment;

        if (! $continues) {
            $logicalLines[] = [
                'line' => $startLine,
                'text' => trim((string) preg_replace('/\s+/', ' ', $buffer)),
            ];
            $buffer = '';
            $startLine = $lineNumber + 1;
        }

        ++$lineNumber;
    }

    if ($buffer !== '') {
        $logicalLines[] = ['line' => $startLine, 'text' => trim((string) preg_replace('/\s+/', ' ', $buffer))];
    }

    return $logicalLines;
}

/**
 * @return array{classification: string, rationale: string}
 */
function classifyRoute(string $path, string $form): array
{
    if (str_starts_with($path, '.planning/')) {
        return ['classification' => 'unsupported', 'rationale' => 'planning, historical, or research prose is not an executable dependency route'];
    }

    if (str_starts_with($path, 'tests/')) {
        return ['classification' => 'unsupported', 'rationale' => 'test or fixture coverage is not an operator dependency route'];
    }

    $supported = $path === 'README.md'
        || str_starts_with($path, '.github/workflows/')
        || str_starts_with($path, 'scripts/')
        || str_starts_with($path, 'bin/')
        || str_starts_with($path, 'docker/')
        || str_starts_with($path, 'containers/')
        || str_starts_with($path, 'Dockerfile')
        || $path === 'Makefile';

    if (! $supported) {
        return ['classification' => 'unclassified', 'rationale' => 'matched Composer mutation text has no approved route classification'];
    }

    if (! str_contains($form, 'bin/composer-policy')) {
        return ['classification' => 'unsupported', 'rationale' => 'supported executable or operator route does not reach bin/composer-policy'];
    }

    return ['classification' => 'supported', 'rationale' => 'complete command chain invokes bin/composer-policy'];
}

function assertGuardStructure(string $guard): void
{
    $requiredFragments = [
        "'COMPOSER_BIN'",
        "if (is_string(\$value) && \$value !== '')",
        "runComposer([PHP_BINARY, \$composerPath, '--version', '--no-interaction'])",
        "runComposer([PHP_BINARY, \$composerPath, 'policy', '--help', '--no-interaction'])",
        'runComposer([PHP_BINARY, $composerPath, ...$arguments])',
    ];

    foreach ($requiredFragments as $fragment) {
        assertTrue(str_contains($guard, $fragment), "Guard static audit is missing {$fragment}.");
    }

    $override = strpos($guard, 'rejectOverrides($arguments);');
    $resolved = strpos($guard, '$composerPath = resolveComposer();');
    $version = strpos($guard, "runComposer([PHP_BINARY, \$composerPath, '--version', '--no-interaction'])");
    $policy = strpos($guard, "runComposer([PHP_BINARY, \$composerPath, 'policy', '--help', '--no-interaction'])");
    $delegation = strpos($guard, 'runComposer([PHP_BINARY, $composerPath, ...$arguments])');

    assertTrue(is_int($override) && is_int($resolved) && is_int($version) && is_int($policy) && is_int($delegation), 'Guard preflight positions must be discoverable.');
    assertTrue($override < $resolved && $resolved < $version && $version < $policy && $policy < $delegation, 'Guard must finish override, path, version, and policy preflights before delegation.');
}

function auditRoutes(string $repositoryRoot): void
{
    $guard = file_get_contents($repositoryRoot.'/bin/composer-policy');

    if ($guard === false) {
        fail('Could not read bin/composer-policy for the route audit.');
    }

    assertGuardStructure($guard);

    $records = [];
    $pattern = '/(?<![A-Za-z0-9_\\/-])(?:(?:php\\s+)?(?:\\.\\/)?bin\\/composer-policy|composer)\\s+(install|update|require|remove|create-project)\\b/i';

    foreach (trackedFiles($repositoryRoot) as $path) {
        $contents = file_get_contents($repositoryRoot.'/'.$path);

        if ($contents === false) {
            fail("Could not inspect tracked file {$path} for the route audit.");
        }

        foreach (normalizedLogicalLines($contents) as $logicalLine) {
            if (preg_match_all($pattern, $logicalLine['text'], $matches, PREG_OFFSET_CAPTURE) !== false) {
                foreach ($matches[1] as $match) {
                    $route = classifyRoute($path, $logicalLine['text']);
                    $records[] = [
                        'path' => $path,
                        'line' => $logicalLine['line'],
                        'form' => $logicalLine['text'],
                        'operation' => strtolower($match[0]),
                        'classification' => $route['classification'],
                        'rationale' => $route['rationale'],
                    ];
                }
            }
        }
    }

    usort($records, static fn (array $left, array $right): int => [$left['path'], $left['line'], $left['operation'], $left['form']] <=> [$right['path'], $right['line'], $right['operation'], $right['form']]);

    foreach ($records as $record) {
        fwrite(STDOUT, sprintf(
            "ROUTE path=%s line=%d operation=%s classification=%s form=%s rationale=%s\n",
            $record['path'],
            $record['line'],
            $record['operation'],
            $record['classification'],
            $record['form'],
            $record['rationale'],
        ));

        assertTrue($record['classification'] !== 'unclassified', "Unclassified Composer route: {$record['path']}:{$record['line']}");
        assertTrue($record['classification'] !== 'unsupported' || str_starts_with($record['path'], '.planning/') || str_starts_with($record['path'], 'tests/'), "Unsupported executable route: {$record['path']}:{$record['line']}");
    }

    assertTrue($records !== [], 'The route audit must discover Composer mutation forms.');
    fwrite(STDOUT, 'Composer route audit passed with '.count($records)." classified records.\n");
}

$repositoryRoot = dirname(__DIR__, 2);
$guard = $repositoryRoot.'/bin/composer-policy';

if (($argv[1] ?? null) === '--route-audit') {
    auditRoutes($repositoryRoot);

    exit(0);
}
$temporaryRoot = sys_get_temp_dir().'/sendportal-composer-policy-'.bin2hex(random_bytes(8));

if (! mkdir($temporaryRoot.'/bin', 0700, true) && ! is_dir($temporaryRoot.'/bin')) {
    fail('Could not create the temporary Composer test directory.');
}

try {
    $oldMarker = $temporaryRoot.'/old-operation.marker';
    $oldComposer = $temporaryRoot.'/old-composer';
    writeFile($oldComposer, <<<'PHP'
<?php

if (in_array('--version', $argv, true)) {
    echo "Composer version 2.9.5\n";
    exit(0);
}

file_put_contents((string) getenv('FAKE_COMPOSER_MARKER'), "old operation\n");
PHP);
    copy($oldComposer, $temporaryRoot.'/bin/composer');
    chmod($temporaryRoot.'/bin/composer', 0700);

    $environment = getenv();
    $environment['PATH'] = $temporaryRoot.'/bin';
    $environment['FAKE_COMPOSER_MARKER'] = $oldMarker;
    unset(
        $environment['COMPOSER_BIN'],
        $environment['COMPOSER'],
        $environment['COMPOSER_POLICY'],
        $environment['COMPOSER_NO_BLOCKING'],
        $environment['COMPOSER_NO_SECURITY_BLOCKING'],
        $environment['COMPOSER_IGNORE_PLATFORM_REQ'],
        $environment['COMPOSER_IGNORE_PLATFORM_REQS'],
    );

    [$status, $output] = runCommand([PHP_BINARY, $guard, 'install', '--no-interaction'], $environment);
    assertTrue($status !== 0, 'Composer 2.9.5 must be rejected before delegation.');
    assertTrue(str_contains($output, 'Composer >= 2.10.0 is required'), 'The rejection must name the Composer 2.10.0 floor.');
    assertTrue(! file_exists($oldMarker), 'The old Composer operation must not run.');

    $compliantMarker = $temporaryRoot.'/compliant-operation.json';
    $policyMarker = $temporaryRoot.'/policy.marker';
    writeFile($temporaryRoot.'/bin/composer', <<<'PHP'
<?php

if (in_array('--version', $argv, true)) {
    echo "Composer version 2.10.2\n";
    exit(0);
}

if (($argv[1] ?? null) === 'policy' && in_array('--help', $argv, true)) {
    file_put_contents((string) getenv('FAKE_POLICY_MARKER'), PHP_BINARY."\n");
    exit(0);
}

file_put_contents((string) getenv('FAKE_COMPOSER_MARKER'), json_encode([
    'arguments' => array_slice($argv, 1),
    'php_binary' => PHP_BINARY,
], JSON_THROW_ON_ERROR));
PHP);
    chmod($temporaryRoot.'/bin/composer', 0700);
    $environment['FAKE_COMPOSER_MARKER'] = $compliantMarker;
    $environment['FAKE_POLICY_MARKER'] = $policyMarker;

    [$status, $output] = runCommand([PHP_BINARY, $guard, 'update', '--dry-run', '--no-interaction'], $environment);
    assertTrue($status === 0, "A compliant Composer must run successfully: {$output}");
    assertTrue(file_get_contents($policyMarker) === PHP_BINARY."\n", 'The policy capability probe must run through PHP_BINARY.');
    $delegation = json_decode((string) file_get_contents($compliantMarker), true, 512, JSON_THROW_ON_ERROR);
    assertTrue($delegation['php_binary'] === PHP_BINARY, 'Delegation must run through the test process PHP_BINARY.');
    assertTrue($delegation['arguments'] === ['update', '--dry-run', '--no-interaction'], 'Delegation must preserve Composer arguments.');

    $shimMarker = $temporaryRoot.'/shim.marker';
    $shim = $temporaryRoot.'/composer-bin-shim';
    writeFile($shim, <<<'PHP'
<?php

file_put_contents((string) getenv('FAKE_SHIM_MARKER'), "shim invoked\n");

if (in_array('--version', $argv, true)) {
    echo "Composer version 2.10.2\n";
    exit(0);
}

require (string) getenv('FAKE_OLD_COMPOSER');
PHP);
    chmod($shim, 0700);
    $spoofEnvironment = $environment;
    $spoofEnvironment['COMPOSER_BIN'] = $shim;
    $spoofEnvironment['FAKE_SHIM_MARKER'] = $shimMarker;
    $spoofEnvironment['FAKE_OLD_COMPOSER'] = $oldComposer;
    unlink($compliantMarker);

    [$status, $output] = runCommand([PHP_BINARY, $guard, 'install'], $spoofEnvironment);
    assertTrue($status !== 0, 'COMPOSER_BIN must be rejected.');
    assertTrue(str_contains($output, 'COMPOSER_BIN is not allowed'), 'COMPOSER_BIN rejection must be actionable.');
    assertTrue(! file_exists($shimMarker), 'The version-spoofing COMPOSER_BIN shim must not run.');
    assertTrue(! file_exists($compliantMarker), 'The spoofing case must not delegate to Composer.');

    foreach (['--ignore-platform-req=ext-json', '--ignore-platform-reqs', '--no-blocking', '--no-security-blocking', '--no-audit'] as $override) {
        [$status] = runCommand([PHP_BINARY, $guard, 'install', $override], $environment);
        assertTrue($status !== 0, "{$override} must be rejected before Composer runs.");
        assertTrue(! file_exists($compliantMarker), "{$override} must not delegate to Composer.");
    }

    foreach (['COMPOSER', 'COMPOSER_POLICY', 'COMPOSER_NO_AUDIT', 'COMPOSER_NO_BLOCKING', 'COMPOSER_NO_SECURITY_BLOCKING', 'COMPOSER_IGNORE_PLATFORM_REQ', 'COMPOSER_IGNORE_PLATFORM_REQS'] as $name) {
        $overrideEnvironment = $environment;
        $overrideEnvironment[$name] = '1';
        [$status] = runCommand([PHP_BINARY, $guard, 'install'], $overrideEnvironment);
        assertTrue($status !== 0, "{$name} must be rejected before Composer runs.");
        assertTrue(! file_exists($compliantMarker), "{$name} must not delegate to Composer.");
    }
} finally {
    removeDirectory($temporaryRoot);
}

fwrite(STDOUT, "Composer policy guard tests passed.\n");
