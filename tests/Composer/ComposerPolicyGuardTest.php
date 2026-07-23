<?php

declare(strict_types=1);

/**
 * Dependency-free regression coverage for bin/composer-policy.
 *
 * Run with: php tests/Composer/ComposerPolicyGuardTest.php
 */

const MAX_ROUTE_LOGICAL_LINE_LENGTH = 16384;
const MAX_ROUTE_SEGMENTS = 64;
const MAX_ROUTE_TOKENS = 256;

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
function runCommand(array $command, array $environment, string $workingDirectory): array
{
    $process = proc_open(
        $command,
        [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes,
        $workingDirectory,
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
    $directory = dirname($path);

    if (! is_dir($directory) && ! mkdir($directory, 0700, true) && ! is_dir($directory)) {
        fail("Could not create {$directory}.");
    }

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

function writeSyntheticDistribution(string $repositoryRoot, string $version = '2.10.2'): void
{
    $pharPath = $repositoryRoot.'/tools/composer/composer-2.10.2.phar';
    writeFile($pharPath, <<<PHP
<?php

file_put_contents((string) getenv('TRUSTED_COMPOSER_MARKER'), json_encode([
    'arguments' => array_slice(\$argv, 1),
    'cwd' => getcwd(),
    'php_binary' => PHP_BINARY,
    'composer_home' => getenv('COMPOSER_HOME'),
    'composer_auth' => getenv('COMPOSER_AUTH'),
    'home_config_exists' => is_file((string) getenv('COMPOSER_HOME').'/config.json'),
    'home_auth_exists' => is_file((string) getenv('COMPOSER_HOME').'/auth.json'),
], JSON_THROW_ON_ERROR)."\\n", FILE_APPEND);

if (in_array('--version', \$argv, true)) {
    echo "Composer version {$version}\\n";
    exit(0);
}

if ((\$argv[1] ?? null) === 'policy' && in_array('--help', \$argv, true)) {
    exit(0);
}
PHP);

    $digest = hash_file('sha256', $pharPath);
    assertTrue(is_string($digest), 'Could not hash the synthetic Composer distribution.');
    writeFile($repositoryRoot.'/tools/composer/composer-2.10.2.phar.sha256', "release: 2.10.2\nsource: https://getcomposer.org/download/2.10.2/composer.phar\nverification: separately downloaded official SHA-256 from https://getcomposer.org/download/2.10.2/composer.phar.sha256sum\n{$digest} composer-2.10.2.phar\n");
}

function createTemporaryRepository(string $sourceRoot): string
{
    $temporaryRoot = sys_get_temp_dir().'/sendportal-composer-policy-'.bin2hex(random_bytes(8));

    if (! mkdir($temporaryRoot, 0700, true) && ! is_dir($temporaryRoot)) {
        fail('Could not create the temporary Composer test directory.');
    }

    $guard = $sourceRoot.'/bin/composer-policy';
    assertTrue(copy($guard, $temporaryRoot.'/composer-policy'), 'Could not copy the Composer policy guard.');
    writeFile($temporaryRoot.'/bin/composer-policy', (string) file_get_contents($temporaryRoot.'/composer-policy'));
    unlink($temporaryRoot.'/composer-policy');
    writeFile($temporaryRoot.'/composer.json', (string) file_get_contents($sourceRoot.'/composer.json'));

    $commandContract = $sourceRoot.'/tools/composer/ComposerPolicyCommandContract.php';

    if (is_file($commandContract)) {
        writeFile($temporaryRoot.'/tools/composer/ComposerPolicyCommandContract.php', (string) file_get_contents($commandContract));
    }

    return $temporaryRoot;
}

function createTemporaryDirectory(string $prefix): string
{
    $temporaryRoot = sys_get_temp_dir().'/'.$prefix.'-'.bin2hex(random_bytes(8));

    if (! mkdir($temporaryRoot, 0700, true) && ! is_dir($temporaryRoot)) {
        fail('Could not create the temporary Composer test directory.');
    }

    return $temporaryRoot;
}

function assertionEnvironment(string $repositoryRoot, string $shadowMarker, string $trustedMarker): array
{
    $shadowPath = $repositoryRoot.'/path-shadow/composer';
    writeFile($shadowPath, <<<'PHP'
<?php

file_put_contents((string) getenv('PATH_SHADOW_MARKER'), "executed\n", FILE_APPEND);
echo "Composer version 2.10.2\n";
PHP);

    chmod($shadowPath, 0700);

    $environment = getenv();
    $environment['PATH'] = $repositoryRoot.'/path-shadow'.PATH_SEPARATOR.(string) ($environment['PATH'] ?? '');
    $environment['PATH_SHADOW_MARKER'] = $shadowMarker;
    $environment['TRUSTED_COMPOSER_MARKER'] = $trustedMarker;
    unset(
        $environment['COMPOSER_BIN'],
        $environment['COMPOSER'],
        $environment['COMPOSER_POLICY'],
        $environment['COMPOSER_NO_AUDIT'],
        $environment['COMPOSER_NO_BLOCKING'],
        $environment['COMPOSER_NO_SECURITY_BLOCKING'],
        $environment['COMPOSER_IGNORE_PLATFORM_REQ'],
        $environment['COMPOSER_IGNORE_PLATFORM_REQS'],
    );

    return $environment;
}

function assertNoComposerRan(string $trustedMarker, string $shadowMarker, string $message): void
{
    assertTrue(! file_exists($trustedMarker), "{$message}: trusted distribution ran.");
    assertTrue(! file_exists($shadowMarker), "{$message}: PATH shadow ran.");
}

function assertOnlyVersionProbeRan(string $trustedMarker, string $shadowMarker, string $message): void
{
    assertTrue(! file_exists($shadowMarker), "{$message}: PATH shadow ran.");
    $invocations = array_filter(explode("\n", (string) file_get_contents($trustedMarker)));
    assertTrue(count($invocations) === 1, "{$message}: only the version probe may run.");
    $probe = json_decode((string) reset($invocations), true, 512, JSON_THROW_ON_ERROR);
    assertTrue($probe['arguments'] === ['--version', '--no-interaction'], "{$message}: policy help and delegation must not run.");
}

function assertGuardStructure(string $guard): void
{
    foreach ([
        "'COMPOSER_BIN'",
        "composer-2.10.2.phar",
        "composer-2.10.2.phar.sha256",
        'hash_file',
        'PHP_BINARY',
        "'policy', '--help', '--no-interaction'",
    ] as $fragment) {
        assertTrue(str_contains($guard, $fragment), "Guard static audit is missing {$fragment}.");
    }

    assertTrue(! str_contains($guard, "getenv('PATH')"), 'Guard must not select Composer through PATH.');
    assertTrue(! str_contains($guard, 'resolveComposer'), 'Guard must not retain the PATH Composer resolver.');
}

/**
 * @return list<string>
 */
function trackedFiles(string $repositoryRoot): array
{
    [$status, $output] = runCommand(['git', 'ls-files', '-z'], getenv(), $repositoryRoot);
    assertTrue($status === 0, 'Could not enumerate tracked files for the route audit.');

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

        if (strlen($buffer) > MAX_ROUTE_LOGICAL_LINE_LENGTH) {
            throw new RuntimeException('Route audit logical line length exceeds the configured bound.');
        }

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
 * @return list<string>
 */
function commandChainSegments(string $logicalLine, bool $strict = true): array
{
    if (strlen($logicalLine) > MAX_ROUTE_LOGICAL_LINE_LENGTH) {
        throw new RuntimeException('Route audit logical line length exceeds the configured bound.');
    }

    $segments = [];
    $buffer = '';
    $quote = null;
    $escaped = false;
    $length = strlen($logicalLine);

    for ($index = 0; $index < $length; ++$index) {
        $character = $logicalLine[$index];

        if ($escaped) {
            $buffer .= $character;
            $escaped = false;

            continue;
        }

        if ($character === '\\' && $quote !== "'") {
            $buffer .= $character;
            $escaped = true;

            continue;
        }

        if ($character === "'" && $quote === "'") {
            $quote = null;
            $buffer .= $character;

            continue;
        }

        if ($character === '"' && $quote === '"') {
            $quote = null;
            $buffer .= $character;

            continue;
        }

        if ($quote === null && in_array($character, ["'", '"'], true)) {
            $quote = $character;
            $buffer .= $character;

            continue;
        }

        if ($quote === null && in_array($character, ['&', '|', ';'], true)) {
            if (($character === '&' || $character === '|') && ($logicalLine[$index + 1] ?? null) === $character) {
                ++$index;
            }

            $segment = trim($buffer);

            if ($segment !== '') {
                $segments[] = $segment;

                if (count($segments) > MAX_ROUTE_SEGMENTS) {
                    throw new RuntimeException('Route audit segment count exceeds the configured bound.');
                }
            }

            $buffer = '';

            continue;
        }

        $buffer .= $character;
    }

    if ($strict && $escaped) {
        throw new RuntimeException('Route audit encountered a dangling escape.');
    }

    if ($strict && $quote !== null) {
        throw new RuntimeException('Route audit encountered an unterminated quote.');
    }

    $segment = trim($buffer);

    if ($segment !== '') {
        $segments[] = $segment;
    }

    if (count($segments) > MAX_ROUTE_SEGMENTS) {
        throw new RuntimeException('Route audit segment count exceeds the configured bound.');
    }

    return $segments;
}

/**
 * @return list<string>
 */
function commandTokens(string $segment, bool $strict = true): array
{
    $tokens = [];
    $token = '';
    $tokenStarted = false;
    $quote = null;
    $escaped = false;
    $length = strlen($segment);

    $appendToken = static function () use (&$tokens, &$token, &$tokenStarted): void {
        if (! $tokenStarted) {
            return;
        }

        $tokens[] = $token;
        $token = '';
        $tokenStarted = false;

        if (count($tokens) > MAX_ROUTE_TOKENS) {
            throw new RuntimeException('Route audit token count exceeds the configured bound.');
        }
    };

    for ($index = 0; $index < $length; ++$index) {
        $character = $segment[$index];

        if ($escaped) {
            $token .= $character;
            $tokenStarted = true;
            $escaped = false;

            continue;
        }

        if ($character === '\\' && $quote !== "'") {
            $tokenStarted = true;
            $escaped = true;

            continue;
        }

        if ($character === "'" && $quote === "'") {
            $quote = null;
            $tokenStarted = true;

            continue;
        }

        if ($character === '"' && $quote === '"') {
            $quote = null;
            $tokenStarted = true;

            continue;
        }

        if ($quote === null && in_array($character, ["'", '"'], true)) {
            $quote = $character;
            $tokenStarted = true;

            continue;
        }

        if ($quote === null && ctype_space($character)) {
            $appendToken();

            continue;
        }

        $token .= $character;
        $tokenStarted = true;
    }

    if ($strict && $escaped) {
        throw new RuntimeException('Route audit encountered a dangling escape.');
    }

    if ($strict && $quote !== null) {
        throw new RuntimeException('Route audit encountered an unterminated quote.');
    }

    $appendToken();

    return $tokens;
}

function isPotentialInvocationSegment(string $segment): bool
{
    return parseInvocation(commandTokens($segment, false)) !== null;
}

function unquoteYamlCommandScalar(string $logicalLine): string
{
    foreach (['run:', 'command:'] as $prefix) {
        if (! str_starts_with($logicalLine, $prefix)) {
            continue;
        }

        $value = ltrim(substr($logicalLine, strlen($prefix)));
        $quote = $value[0] ?? '';

        if (! in_array($quote, ["'", '"'], true)) {
            return $logicalLine;
        }

        $decoded = '';
        $closingIndex = null;
        $escaped = false;
        $length = strlen($value);

        for ($index = 1; $index < $length; ++$index) {
            $character = $value[$index];

            if ($quote === '"') {
                if ($escaped) {
                    $escaped = false;

                    continue;
                }

                if ($character === '\\') {
                    $escaped = true;

                    continue;
                }
            }

            if ($character !== $quote) {
                if ($quote === "'") {
                    $decoded .= $character;
                }

                continue;
            }

            if ($quote === "'" && ($value[$index + 1] ?? null) === "'") {
                $decoded .= "'";
                ++$index;

                continue;
            }

            $closingIndex = $index;

            break;
        }

        if ($closingIndex === null) {
            return $logicalLine;
        }

        $remainder = ltrim(substr($value, $closingIndex + 1));

        if ($remainder !== '' && ! str_starts_with($remainder, '#')) {
            return $logicalLine;
        }

        if ($quote === '"') {
            $decodedValue = json_decode(substr($value, 0, $closingIndex + 1), true);

            if (! is_string($decodedValue)) {
                return $logicalLine;
            }

            $decoded = $decodedValue;
        }

        return $prefix.' '.$decoded;
    }

    return $logicalLine;
}

/**
 * @param list<string> $tokens
 * @return array{executable: string, operation: string}|null
 */
function parseInvocation(array $tokens): ?array
{
    while ($tokens !== []) {
        $token = $tokens[0];

        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*=/', $token) === 1 || in_array($token, ['run:', 'command:'], true)) {
            array_shift($tokens);

            continue;
        }

        if ($token === 'command') {
            array_shift($tokens);

            if ($tokens !== [] && in_array($tokens[0], ['-v', '-V'], true)) {
                return null;
            }

            while ($tokens !== [] && in_array($tokens[0], ['-p', '--'], true)) {
                $option = array_shift($tokens);

                if ($option === '--') {
                    break;
                }
            }

            if ($tokens !== [] && str_starts_with($tokens[0], '-')) {
                return ['executable' => 'unsupported-wrapper', 'operation' => 'unknown'];
            }

            continue;
        }

        if ($token === 'env') {
            array_shift($tokens);

            while ($tokens !== []) {
                $option = $tokens[0];

                if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*=/', $option) === 1 || in_array($option, ['-i', '--ignore-environment'], true)) {
                    array_shift($tokens);

                    continue;
                }

                if ($option === '-u') {
                    array_shift($tokens);

                    if ($tokens === []) {
                        return ['executable' => 'unsupported-wrapper', 'operation' => 'unknown'];
                    }

                    array_shift($tokens);

                    continue;
                }

                if (str_starts_with($option, '--unset=') && strlen($option) > strlen('--unset=')) {
                    array_shift($tokens);

                    continue;
                }

                if ($option === '--') {
                    array_shift($tokens);

                    break;
                }

                if (str_starts_with($option, '-')) {
                    return ['executable' => 'unsupported-wrapper', 'operation' => 'unknown'];
                }

                break;
            }

            continue;
        }

        if ($token === 'sudo') {
            array_shift($tokens);

            while ($tokens !== []) {
                $option = $tokens[0];

                if (in_array($option, ['-v', '-V', '-l', '--list'], true)) {
                    return null;
                }

                if (in_array($option, ['-n', '--non-interactive', '-E', '--preserve-env', '-H', '--set-home'], true)) {
                    array_shift($tokens);

                    continue;
                }

                if (in_array($option, ['-u', '--user', '-g', '--group', '-h', '--host'], true)) {
                    array_shift($tokens);

                    if ($tokens === []) {
                        return ['executable' => 'unsupported-wrapper', 'operation' => 'unknown'];
                    }

                    array_shift($tokens);

                    continue;
                }

                if (preg_match('/^--(?:user|group|host|preserve-env)=.+$/', $option) === 1) {
                    array_shift($tokens);

                    continue;
                }

                if ($option === '--') {
                    array_shift($tokens);

                    break;
                }

                if (str_starts_with($option, '-')) {
                    return ['executable' => 'unsupported-wrapper', 'operation' => 'unknown'];
                }

                break;
            }

            continue;
        }

        break;
    }

    if ($tokens === []) {
        return null;
    }

    $executable = array_shift($tokens);
    $basename = strtolower(basename($executable));

    if (preg_match('/^php(?:[0-9.]+)?$/', $basename) === 1) {
        while ($tokens !== []) {
            $option = $tokens[0];

            if (in_array($option, ['-l', '--syntax-check', '-r', '--run', '-i', '--info', '-v', '--version', '-m', '--modules', '--ini'], true)) {
                return null;
            }

            if ($option === '--') {
                array_shift($tokens);

                break;
            }

            if (in_array($option, ['-n', '-q', '-s', '-w'], true)) {
                array_shift($tokens);

                continue;
            }

            if (in_array($option, ['-c', '-d', '-z', '-f'], true)) {
                array_shift($tokens);

                if ($tokens === []) {
                    return ['executable' => 'unsupported-wrapper', 'operation' => 'unknown'];
                }

                if ($option === '-f') {
                    break;
                }

                array_shift($tokens);

                continue;
            }

            if (preg_match('/^-(?:c|d|z).+$/', $option) === 1) {
                array_shift($tokens);

                continue;
            }

            if (str_starts_with($option, '-')) {
                return ['executable' => 'unsupported-wrapper', 'operation' => 'unknown'];
            }

            break;
        }

        if ($tokens === []) {
            return null;
        }

        $executable = array_shift($tokens);
        $basename = strtolower(basename($executable));
    }

    $executableClass = str_ends_with(str_replace('\\', '/', $executable), 'bin/composer-policy')
        ? 'guard'
        : (in_array($basename, ['composer', 'composer.phar'], true) ? $basename : null);

    if ($executableClass === null) {
        return null;
    }

    while ($tokens !== [] && str_starts_with($tokens[0], '-')) {
        $option = array_shift($tokens);

        if (in_array($option, ['--working-dir', '-d'], true) && $tokens !== []) {
            array_shift($tokens);
        }
    }

    $operation = strtolower((string) ($tokens[0] ?? ''));

    if (! in_array($operation, ['install', 'update', 'require', 'remove', 'create-project'], true)) {
        return null;
    }

    return ['executable' => $executableClass, 'operation' => $operation];
}

/**
 * @return array{classification: string, rationale: string}
 */
function classifyRoute(string $path, string $executable): array
{
    if (str_starts_with($path, '.planning/') || str_starts_with($path, 'tests/')) {
        return ['classification' => 'non-production', 'rationale' => 'planning, history, research, and test coverage are not production dependency routes'];
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
        return ['classification' => 'unclassified', 'rationale' => 'Composer mutation command has no approved production route classification'];
    }

    if ($executable !== 'guard') {
        return ['classification' => 'unsupported', 'rationale' => 'supported dependency route invokes Composer directly instead of the repository guard'];
    }

    return ['classification' => 'supported', 'rationale' => 'this command-chain segment invokes the repository guard'];
}

/**
 * @return list<array{path: string, line: int, logical: string, chain: int, segment: string, executable: string, operation: string, classification: string, rationale: string}>
 */
function auditRoutes(string $repositoryRoot): array
{
    $guard = file_get_contents($repositoryRoot.'/bin/composer-policy');
    assertTrue($guard !== false, 'Could not read bin/composer-policy for the route audit.');
    assertGuardStructure($guard);

    $records = [];

    foreach (trackedFiles($repositoryRoot) as $path) {
        $contents = file_get_contents($repositoryRoot.'/'.$path);
        assertTrue($contents !== false, "Could not inspect tracked file {$path} for the route audit.");

        $trimmedContents = ltrim($contents);

        if (
            str_contains($contents, "\0")
            || str_starts_with($trimmedContents, '<?php')
            || str_starts_with($trimmedContents, '#!/usr/bin/env php')
        ) {
            continue;
        }

        foreach (normalizedLogicalLines($contents) as $logicalLine) {
            $shellLine = unquoteYamlCommandScalar($logicalLine['text']);

            foreach (commandChainSegments($shellLine, false) as $chainIndex => $segment) {
                if (! isPotentialInvocationSegment($segment)) {
                    continue;
                }

                $invocation = parseInvocation(commandTokens($segment));

                if ($invocation === null) {
                    continue;
                }

                $route = classifyRoute($path, $invocation['executable']);
                $records[] = [
                    'path' => $path,
                    'line' => $logicalLine['line'],
                    'logical' => $logicalLine['text'],
                    'chain' => $chainIndex,
                    'segment' => $segment,
                    'executable' => $invocation['executable'],
                    'operation' => $invocation['operation'],
                    'classification' => $route['classification'],
                    'rationale' => $route['rationale'],
                ];
            }
        }
    }

    usort($records, static fn (array $left, array $right): int => [$left['path'], $left['line'], $left['chain'], $left['executable'], $left['operation']] <=> [$right['path'], $right['line'], $right['chain'], $right['executable'], $right['operation']]);

    return $records;
}

/**
 * @param list<array{path: string, line: int, logical: string, chain: int, segment: string, executable: string, operation: string, classification: string, rationale: string}> $records
 * @return list<string>
 */
function routeAuditFailures(array $records, bool $requireGuardedEvidence = false): array
{
    $failures = [];

    foreach ($records as $record) {
        if (in_array($record['classification'], ['unclassified', 'unsupported'], true)) {
            $failures[] = "{$record['path']}:{$record['line']}:{$record['chain']} {$record['rationale']}";
        }
    }

    if ($requireGuardedEvidence) {
        foreach (['README.md', '.github/workflows/'] as $requiredPath) {
            $found = (bool) array_filter($records, static fn (array $record): bool => $record['classification'] === 'supported' && ($record['path'] === $requiredPath || str_starts_with($record['path'], $requiredPath)));

            if (! $found) {
                $failures[] = "No guarded Composer mutation record was found in {$requiredPath}.";
            }
        }
    }

    return $failures;
}

/**
 * @param list<array{path: string, line: int, logical: string, chain: int, segment: string, executable: string, operation: string, classification: string, rationale: string}> $records
 */
function printRouteAuditRecords(array $records): void
{
    foreach ($records as $record) {
        fwrite(STDOUT, sprintf(
            "ROUTE path=%s line=%d chain=%d segment=%s executable=%s operation=%s classification=%s logical=%s rationale=%s\n",
            $record['path'],
            $record['line'],
            $record['chain'],
            $record['segment'],
            $record['executable'],
            $record['operation'],
            $record['classification'],
            $record['logical'],
            $record['rationale'],
        ));
    }
}

function initializeFixtureRepository(string $sourceRoot, string $contents): string
{
    $fixtureRoot = sys_get_temp_dir().'/sendportal-composer-route-'.bin2hex(random_bytes(8));
    writeFile($fixtureRoot.'/bin/composer-policy', (string) file_get_contents($sourceRoot.'/bin/composer-policy'));
    writeFile($fixtureRoot.'/.github/workflows/routes.yml', $contents);
    [$status, $output] = runCommand(['git', 'init', '--quiet'], getenv(), $fixtureRoot);
    assertTrue($status === 0, "Could not initialize the route-audit fixture repository: {$output}");
    [$status, $output] = runCommand(['git', 'add', 'bin/composer-policy', '.github/workflows/routes.yml'], getenv(), $fixtureRoot);
    assertTrue($status === 0, "Could not stage the route-audit fixture repository: {$output}");

    return $fixtureRoot;
}

function assertFixtureRouteFails(string $sourceRoot, string $command, int $chainIndex, string $form, string $operation): void
{
    $fixtureRoot = initializeFixtureRepository($sourceRoot, 'run: '.$command."\n");

    try {
        $records = auditRoutes($fixtureRoot);
        $failures = routeAuditFailures($records);
        assertTrue($failures !== [], "Fixture {$form} must fail the route audit.");
        $offendingRecords = array_values(array_filter($records, static fn (array $record): bool => $record['chain'] === $chainIndex
            && $record['executable'] === $form
            && $record['operation'] === $operation
            && $record['classification'] === 'unsupported'));
        assertTrue(count($offendingRecords) === 1, "Fixture {$form} must identify its direct chain segment and operation exactly once.");
    } finally {
        removeDirectory($fixtureRoot);
    }
}

function assertFixtureRouteHasNoMutation(string $sourceRoot, string $command, string $message): void
{
    $fixtureRoot = initializeFixtureRepository($sourceRoot, 'run: '.$command."\n");

    try {
        assertTrue(auditRoutes($fixtureRoot) === [], $message);
    } finally {
        removeDirectory($fixtureRoot);
    }
}

function assertParserRejects(callable $callback, string $expectedMessage): void
{
    try {
        $callback();
    } catch (RuntimeException $exception) {
        assertTrue(str_contains($exception->getMessage(), $expectedMessage), "Parser failure must mention {$expectedMessage}.");

        return;
    }

    fail("Parser must fail closed for {$expectedMessage}.");
}

$repositoryRoot = dirname(__DIR__, 2);
$guard = $repositoryRoot.'/bin/composer-policy';
$guardContents = file_get_contents($guard);

if ($guardContents === false) {
    fail('Could not read bin/composer-policy.');
}

if (($argv[1] ?? null) === '--route-audit') {
    $records = auditRoutes($repositoryRoot);
    printRouteAuditRecords($records);
    $failures = routeAuditFailures($records, true);
    assertTrue($failures === [], "Composer route audit failed: ".implode(' | ', $failures));
    fwrite(STDOUT, 'Composer route audit passed with '.count($records)." classified records.\n");

    exit(0);
}

assertGuardStructure($guardContents);

$temporaryRoots = [];

try {
    $missingRoot = createTemporaryRepository($repositoryRoot);
    $temporaryRoots[] = $missingRoot;
    $missingTrustedMarker = $missingRoot.'/trusted.marker';
    $missingShadowMarker = $missingRoot.'/shadow.marker';
    $missingEnvironment = assertionEnvironment($missingRoot, $missingShadowMarker, $missingTrustedMarker);
    [$status, $output] = runCommand([PHP_BINARY, $missingRoot.'/bin/composer-policy', 'install'], $missingEnvironment, $missingRoot);
    assertTrue($status !== 0 && str_contains($output, 'Composer distribution unavailable'), 'A missing repository distribution must fail closed.');
    assertNoComposerRan($missingTrustedMarker, $missingShadowMarker, 'Missing distribution');

    $successRoot = createTemporaryRepository($repositoryRoot);
    $temporaryRoots[] = $successRoot;
    $successTrustedMarker = $successRoot.'/trusted.marker';
    $successShadowMarker = $successRoot.'/shadow.marker';
    $successEnvironment = assertionEnvironment($successRoot, $successShadowMarker, $successTrustedMarker);
    $successCallerRoot = createTemporaryDirectory('sendportal-composer-caller');
    $temporaryRoots[] = $successCallerRoot;
    writeSyntheticDistribution($successRoot);
    [$status, $output] = runCommand([PHP_BINARY, $successRoot.'/bin/composer-policy', 'update', '--dry-run', '--prefer-dist', '-vvv'], $successEnvironment, $successCallerRoot);
    assertTrue($status === 0, "A matching repository distribution must be delegated to: {$output}");
    assertTrue(! file_exists($successShadowMarker), 'A compliant-looking PATH shadow must never execute.');
    $invocations = array_filter(explode("\n", (string) file_get_contents($successTrustedMarker)));
    assertTrue(count($invocations) === 3, 'The trusted distribution must receive version, policy, and delegated calls only.');
    foreach ($invocations as $invocation) {
        $record = json_decode($invocation, true, 512, JSON_THROW_ON_ERROR);
        assertTrue($record['cwd'] === realpath($successRoot), 'Every trusted Composer process must use the canonical repository root.');
    }
    $delegation = json_decode((string) end($invocations), true, 512, JSON_THROW_ON_ERROR);
    assertTrue($delegation['php_binary'] === PHP_BINARY, 'The trusted distribution must be invoked through PHP_BINARY.');
    assertTrue($delegation['arguments'] === ['update', '--dry-run', '--prefer-dist', '-vvv'], 'Delegation must preserve requested Composer arguments.');

    foreach ([
        'tampered' => static function (string $root): void {
            file_put_contents($root.'/tools/composer/composer-2.10.2.phar', "\nchanged", FILE_APPEND);
        },
        'malformed record' => static function (string $root): void {
            writeFile($root.'/tools/composer/composer-2.10.2.phar.sha256', "not a provenance record\n");
        },
        'unreadable distribution' => static function (string $root): void {
            chmod($root.'/tools/composer/composer-2.10.2.phar', 0000);
        },
    ] as $scenario => $mutate) {
        $root = createTemporaryRepository($repositoryRoot);
        $temporaryRoots[] = $root;
        $trustedMarker = $root.'/trusted.marker';
        $shadowMarker = $root.'/shadow.marker';
        $environment = assertionEnvironment($root, $shadowMarker, $trustedMarker);
        writeSyntheticDistribution($root);
        $mutate($root);
        [$status] = runCommand([PHP_BINARY, $root.'/bin/composer-policy', 'install'], $environment, $root);
        assertTrue($status !== 0, "The {$scenario} distribution must fail closed.");
        assertNoComposerRan($trustedMarker, $shadowMarker, "{$scenario} distribution");
    }

    $wrongVersionRoot = createTemporaryRepository($repositoryRoot);
    $temporaryRoots[] = $wrongVersionRoot;
    $wrongVersionTrustedMarker = $wrongVersionRoot.'/trusted.marker';
    $wrongVersionShadowMarker = $wrongVersionRoot.'/shadow.marker';
    $wrongVersionEnvironment = assertionEnvironment($wrongVersionRoot, $wrongVersionShadowMarker, $wrongVersionTrustedMarker);
    writeSyntheticDistribution($wrongVersionRoot, '2.10.3');
    [$status] = runCommand([PHP_BINARY, $wrongVersionRoot.'/bin/composer-policy', 'install'], $wrongVersionEnvironment, $wrongVersionRoot);
    assertTrue($status !== 0, 'A distribution reporting another Composer version must fail closed.');
    assertOnlyVersionProbeRan($wrongVersionTrustedMarker, $wrongVersionShadowMarker, 'Wrong-version distribution');

    $overrideRoot = createTemporaryRepository($repositoryRoot);
    $temporaryRoots[] = $overrideRoot;
    $overrideTrustedMarker = $overrideRoot.'/trusted.marker';
    $overrideShadowMarker = $overrideRoot.'/shadow.marker';
    $overrideEnvironment = assertionEnvironment($overrideRoot, $overrideShadowMarker, $overrideTrustedMarker);
    writeSyntheticDistribution($overrideRoot);

    foreach ([
        'COMPOSER_BIN',
        'COMPOSER',
        'COMPOSER_POLICY',
        'COMPOSER_NO_AUDIT',
        'COMPOSER_NO_BLOCKING',
        'COMPOSER_NO_SECURITY_BLOCKING',
        'COMPOSER_IGNORE_PLATFORM_REQ',
        'COMPOSER_IGNORE_PLATFORM_REQS',
        'COMPOSER_POLICY_ADVISORIES_BLOCK',
        'COMPOSER_POLICY_MALWARE_BLOCK',
        'COMPOSER_POLICY_ABANDONED_BLOCK',
        'COMPOSER_SECURITY_BLOCKING_ABANDONED',
        'COMPOSER_AUDIT_ABANDONED',
    ] as $name) {
        $environment = $overrideEnvironment;
        $environment[$name] = '1';
        [$status, $output] = runCommand([PHP_BINARY, $overrideRoot.'/bin/composer-policy', 'install'], $environment, $overrideRoot);
        assertTrue($status !== 0 && str_contains($output, 'Composer override rejected'), "{$name} must be rejected before Composer starts.");
        assertNoComposerRan($overrideTrustedMarker, $overrideShadowMarker, "{$name} override");
    }

    $externalManifestRoot = createTemporaryDirectory('sendportal-composer-external');
    $temporaryRoots[] = $externalManifestRoot;
    writeFile($externalManifestRoot.'/composer.json', json_encode([
        'name' => 'example/policy-free-project',
        'require' => new stdClass(),
    ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)."\n");

    $directorySelectors = [
        ['--working-dir='.$externalManifestRoot, 'validate'],
        ['--working-dir', $externalManifestRoot, 'validate'],
        ['-d='.$externalManifestRoot, 'validate'],
        ['-d', $externalManifestRoot, 'validate'],
        ['-d'.$externalManifestRoot, 'validate'],
        ['--working-dir'],
        ['-d'],
    ];

    foreach ($directorySelectors as $arguments) {
        @unlink($overrideTrustedMarker);
        @unlink($overrideShadowMarker);
        [$status, $output] = runCommand([PHP_BINARY, $overrideRoot.'/bin/composer-policy', ...$arguments], $overrideEnvironment, $successCallerRoot);
        assertTrue($status !== 0 && str_contains($output, 'Composer override rejected'), 'Every Composer working-directory selector must be rejected.');
        assertNoComposerRan($overrideTrustedMarker, $overrideShadowMarker, implode(' ', $arguments));
    }

    foreach ([
        ['--working-dir='.$externalManifestRoot, 'install', '--prefer-dist', '--no-interaction'],
        ['-d', $externalManifestRoot, 'install', '--prefer-dist', '--no-interaction'],
        ['-d='.$externalManifestRoot, 'install', '--prefer-dist', '--no-interaction'],
        ['-d'.$externalManifestRoot, 'install', '--prefer-dist', '--no-interaction'],
    ] as $arguments) {
        @unlink($overrideTrustedMarker);
        @unlink($overrideShadowMarker);
        [$status, $output] = runCommand([PHP_BINARY, $overrideRoot.'/bin/composer-policy', ...$arguments], $overrideEnvironment, $successCallerRoot);
        assertTrue($status !== 0 && str_contains($output, 'Composer override rejected'), 'A policy-free external manifest must not be installable through the guard.');
        assertNoComposerRan($overrideTrustedMarker, $overrideShadowMarker, implode(' ', $arguments));
        assertTrue(! file_exists($externalManifestRoot.'/composer.lock'), 'Rejected external installs must not create a lockfile.');
        assertTrue(! is_dir($externalManifestRoot.'/vendor'), 'Rejected external installs must not create a vendor tree.');
    }

    $hostileHome = createTemporaryDirectory('sendportal-composer-hostile-home');
    $temporaryRoots[] = $hostileHome;
    writeFile($hostileHome.'/config.json', json_encode([
        'config' => [
            'allow-plugins' => false,
            'disable-tls' => true,
            'platform' => ['php' => '8.2.0'],
            'secure-http' => false,
        ],
        'repositories' => [
            ['type' => 'composer', 'url' => 'http://packages.invalid'],
        ],
        'policy' => [
            'advisories' => [
                'ignore' => ['vendor/*'],
                'ignore-id' => ['PKSA-hostile-global' => 'hostile global exception'],
                'ignore-severity' => ['high'],
            ],
        ],
    ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)."\n");
    writeFile($hostileHome.'/auth.json', "{\"http-basic\":{\"repo.packagist.org\":{\"username\":\"copied\",\"password\":\"forbidden\"}}}\n");
    @unlink($overrideTrustedMarker);
    @unlink($overrideShadowMarker);
    $hostileEnvironment = $overrideEnvironment;
    $hostileEnvironment['COMPOSER_HOME'] = $hostileHome;
    $hostileEnvironment['COMPOSER_AUTH'] = '{"github-oauth":{"github.com":"task-1-auth-sentinel"}}';
    [$status, $output] = runCommand([PHP_BINARY, $overrideRoot.'/bin/composer-policy', 'validate'], $hostileEnvironment, $overrideRoot);
    assertTrue($status === 0, "The isolated guarded child must still delegate validate: {$output}");
    $hostileInvocations = array_values(array_filter(explode("\n", (string) file_get_contents($overrideTrustedMarker))));
    assertTrue(count($hostileInvocations) === 3, 'The hostile-home case must run only version, policy, and validate.');

    foreach ($hostileInvocations as $invocation) {
        $record = json_decode($invocation, true, 512, JSON_THROW_ON_ERROR);
        assertTrue(is_string($record['composer_home']) && $record['composer_home'] !== $hostileHome, 'Every child must receive a guard-owned Composer home.');
        assertTrue(! $record['home_config_exists'], 'Hostile global config.json must not reach a guarded child.');
        assertTrue(! $record['home_auth_exists'], 'Global auth.json must not be copied into the guard-owned home.');
        assertTrue($record['composer_auth'] === $hostileEnvironment['COMPOSER_AUTH'], 'COMPOSER_AUTH must be preserved explicitly.');
    }

    $allowedCommands = [
        ['validate'],
        ['audit'],
        ['install'],
        ['update'],
        ['--no-cache', '-vvv', 'update', '--dry-run'],
    ];

    foreach ($allowedCommands as $arguments) {
        @unlink($overrideTrustedMarker);
        @unlink($overrideShadowMarker);
        [$status, $output] = runCommand([PHP_BINARY, $overrideRoot.'/bin/composer-policy', ...$arguments], $overrideEnvironment, $overrideRoot);
        assertTrue($status === 0, 'Canonical guarded command must delegate: '.implode(' ', $arguments)." {$output}");
        $invocations = array_values(array_filter(explode("\n", (string) file_get_contents($overrideTrustedMarker))));
        assertTrue(count($invocations) === 3, 'Canonical guarded commands must run version, policy, and delegation only.');
    }

    $deniedCommands = [
        ['config', 'policy.advisories.block', 'false'],
        ['global', 'config', 'policy.advisories.ignore-severity', 'high'],
        ['create-project', 'vendor/package'],
        ['self-update'],
        ['require', 'vendor/package'],
        ['remove', 'vendor/package'],
        ['i'],
        ['u'],
        ['upgrade'],
        ['unknown-command'],
        ['--file', 'other.json', 'install'],
        ['--global', 'install'],
        ['--project-dir', '/tmp', 'install'],
    ];

    foreach ($deniedCommands as $arguments) {
        @unlink($overrideTrustedMarker);
        @unlink($overrideShadowMarker);
        [$status, $output] = runCommand([PHP_BINARY, $overrideRoot.'/bin/composer-policy', ...$arguments], $overrideEnvironment, $overrideRoot);
        assertTrue($status !== 0 && str_contains($output, 'Composer command rejected'), 'Unreviewed command must fail at the command contract: '.implode(' ', $arguments));
        assertNoComposerRan($overrideTrustedMarker, $overrideShadowMarker, implode(' ', $arguments));
    }

    $manifestMutations = [
        'advisory blocking disabled' => static function (array &$manifest): void {
            $manifest['config']['policy']['advisories']['block'] = false;
        },
        'extra advisory id' => static function (array &$manifest): void {
            $manifest['config']['policy']['advisories']['ignore-id']['PKSA-extra-test'] = 'unapproved';
        },
        'broad advisory ignore' => static function (array &$manifest): void {
            $manifest['config']['policy']['advisories']['ignore'] = ['vendor/*'];
        },
        'severity advisory ignore' => static function (array &$manifest): void {
            $manifest['config']['policy']['advisories']['ignore-severity'] = ['high'];
        },
        'platform emulation' => static function (array &$manifest): void {
            $manifest['config']['platform'] = ['php' => '8.2.0'];
        },
        'Roave restored' => static function (array &$manifest): void {
            $manifest['require-dev']['roave/security-advisories'] = 'dev-master';
        },
        'PHP bound changed' => static function (array &$manifest): void {
            $manifest['require']['php'] = '^8.4';
        },
        'Laravel bound changed' => static function (array &$manifest): void {
            $manifest['require']['laravel/framework'] = '^12.0';
        },
        'Core bound changed' => static function (array &$manifest): void {
            $manifest['require']['mettle/sendportal-core'] = '^4.0';
        },
    ];

    foreach ($manifestMutations as $scenario => $mutateManifest) {
        $manifestRoot = createTemporaryRepository($repositoryRoot);
        $temporaryRoots[] = $manifestRoot;
        $trustedMarker = $manifestRoot.'/trusted.marker';
        $shadowMarker = $manifestRoot.'/shadow.marker';
        $environment = assertionEnvironment($manifestRoot, $shadowMarker, $trustedMarker);
        writeSyntheticDistribution($manifestRoot);
        $manifest = json_decode((string) file_get_contents($manifestRoot.'/composer.json'), true, 512, JSON_THROW_ON_ERROR);
        $mutateManifest($manifest);
        writeFile($manifestRoot.'/composer.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n");
        [$status, $output] = runCommand([PHP_BINARY, $manifestRoot.'/bin/composer-policy', 'update', '--dry-run'], $environment, $manifestRoot);
        assertTrue($status !== 0 && str_contains($output, 'Composer manifest policy rejected'), "Manifest mutation {$scenario} must fail before Composer.");
        assertNoComposerRan($trustedMarker, $shadowMarker, "Manifest mutation {$scenario}");
    }

    $guarded = 'php bin/'.'composer-policy install';
    foreach ([
        ['composer'.' --no-interaction '.'install', 0, 'composer', 'install'],
        ['/tmp/'.'composer.phar '.'install', 0, 'composer.phar', 'install'],
        ['/opt/'.'composer '.'update', 0, 'composer', 'update'],
        ['composer'.' install && '.$guarded, 0, 'composer', 'install'],
        [$guarded.' && composer'.' install', 1, 'composer', 'install'],
        [$guarded.' & '.'composer'.' install', 1, 'composer', 'install'],
        ['composer'.' install & '.$guarded, 0, 'composer', 'install'],
        [implode(' ', ['command', 'composer', 'install']), 0, 'composer', 'install'],
        [implode(' ', ['env', '-i', 'composer', 'update']), 0, 'composer', 'update'],
        [implode(' ', ['env', '-u', 'NAME', 'composer', 'require', 'vendor/package']), 0, 'composer', 'require'],
        [implode(' ', ['CI=1', 'command', '--', 'composer', 'remove', 'vendor/package']), 0, 'composer', 'remove'],
        [implode(' ', ['php', '/tmp/'.'composer.phar', 'install']), 0, 'composer.phar', 'install'],
        [implode(' ', ['sudo', '-n', 'composer', 'update']), 0, 'composer', 'update'],
        ['"'.'composer'.'" install', 0, 'composer', 'install'],
        ['com'.'\\'.'poser install', 0, 'composer', 'install'],
        ['CI='.'"two words"'.' '.'composer'.' install', 0, 'composer', 'install'],
        ['com'.'"pos"'.'er install', 0, 'composer', 'install'],
        ['"'.'composer install'.'"', 0, 'composer', 'install'],
        ["'".'composer install'."'", 0, 'composer', 'install'],
        ['"'.'env -i composer update'.'"', 0, 'composer', 'update'],
        [$guarded.' || '.'composer'.' install', 1, 'composer', 'install'],
        [$guarded.'; '.'composer'.' install', 1, 'composer', 'install'],
        [$guarded.' | '.'composer'.' install', 1, 'composer', 'install'],
    ] as [$command, $chainIndex, $form, $operation]) {
        assertFixtureRouteFails($repositoryRoot, $command, $chainIndex, $form, $operation);
    }

    foreach ([
        'single-quoted ampersand' => implode(' ', ['echo', "'composer", '&', "install'"]),
        'double-quoted ampersand' => implode(' ', ['echo', '"composer', '&', 'install"']),
        'escaped ampersand' => implode(' ', ['echo', 'composer', '\&', 'install']),
        'quoted command prose' => implode(' ', ['echo', '"composer', 'install"']),
    ] as $message => $command) {
        assertFixtureRouteHasNoMutation($repositoryRoot, $command, "Fixture {$message} must not produce a Composer mutation.");
    }

    assertTrue(commandChainSegments('first && second') === ['first', 'second'], 'The && operator must remain one list separator.');
    assertParserRejects(static fn (): array => commandChainSegments(str_repeat('x', MAX_ROUTE_LOGICAL_LINE_LENGTH + 1)), 'logical line length');
    assertParserRejects(static fn (): array => commandChainSegments(implode(' & ', array_fill(0, MAX_ROUTE_SEGMENTS + 1, 'true'))), 'segment count');
    assertParserRejects(static fn (): array => commandTokens(implode(' ', array_fill(0, MAX_ROUTE_TOKENS + 1, 'token'))), 'token count');
    assertParserRejects(static fn (): array => commandChainSegments("echo 'unterminated"), 'unterminated quote');
    assertParserRejects(static fn (): array => commandChainSegments('echo dangling'.'\\'), 'dangling escape');

    $productionRecords = auditRoutes($repositoryRoot);
    assertTrue(routeAuditFailures($productionRecords, true) === [], 'Production route audit must pass using only tracked records.');
    assertTrue(! (bool) array_filter($productionRecords, static fn (array $record): bool => str_contains($record['path'], 'sendportal-composer-route-')), 'Production route evidence must not include temporary fixture paths.');
} finally {
    foreach ($temporaryRoots as $temporaryRoot) {
        removeDirectory($temporaryRoot);
    }
}

fwrite(STDOUT, "Composer policy guard tests passed.\n");
