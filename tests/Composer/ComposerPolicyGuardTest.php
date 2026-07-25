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
const MAX_ROUTE_EVALUATOR_DEPTH = 4;
const MAX_ROUTE_EVALUATOR_PAYLOADS = 32;
const MAX_ROUTE_COMPOUND_DEPTH = 4;
const MAX_ROUTE_COMPOUND_BODIES = 32;
const MAX_ROUTE_INLINE_PHP_LAUNCHES = 32;

require_once dirname(__DIR__, 2).'/tools/composer/ComposerPolicyCommandContract.php';

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
 * @return array{status: int, stdout: string, stderr: string}
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

    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);
    $stdout = '';
    $stderr = '';
    $deadline = microtime(true) + 15.0;
    $lastStatus = null;

    while (true) {
        $read = [];

        foreach ([1, 2] as $descriptor) {
            if (! feof($pipes[$descriptor])) {
                $read[] = $pipes[$descriptor];
            }
        }

        if ($read !== []) {
            $write = null;
            $except = null;
            @stream_select($read, $write, $except, 0, 100000);

            foreach ($read as $stream) {
                $chunk = stream_get_contents($stream);

                if ($stream === $pipes[1]) {
                    $stdout .= $chunk;
                } else {
                    $stderr .= $chunk;
                }

                if (strlen($stdout) > 8388608 || strlen($stderr) > 8388608) {
                    proc_terminate($process);
                    fail('Composer policy test subprocess exceeded the per-channel output limit.');
                }
            }
        }

        $lastStatus = proc_get_status($process);

        if (! $lastStatus['running'] && feof($pipes[1]) && feof($pipes[2])) {
            break;
        }

        if (microtime(true) >= $deadline) {
            proc_terminate($process);
            fail('Composer policy test subprocess timed out.');
        }
    }

    fclose($pipes[1]);
    fclose($pipes[2]);
    $closedStatus = proc_close($process);
    $exitStatus = $closedStatus;

    if ($exitStatus < 0 && is_array($lastStatus) && is_int($lastStatus['exitcode']) && $lastStatus['exitcode'] >= 0) {
        $exitStatus = $lastStatus['exitcode'];
    }

    return ['status' => $exitStatus, 'stdout' => $stdout, 'stderr' => $stderr];
}

/**
 * @param list<string> $command
 * @param array<string, string> $environment
 * @return array{status: int, stdout: string, stderr: string, observed_before_exit: bool}
 */
function runStreamingHandshake(array $command, array $environment, string $workingDirectory, string $releaseFile): array
{
    $process = proc_open(
        $command,
        [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes,
        $workingDirectory,
        $environment,
    );

    if (! is_resource($process)) {
        fail('Could not start the streaming Composer policy guard.');
    }

    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);
    $stdout = '';
    $stderr = '';
    $observedBeforeExit = false;
    $deadline = microtime(true) + 5.0;

    while (microtime(true) < $deadline) {
        $read = [];

        foreach ([1, 2] as $descriptor) {
            if (is_resource($pipes[$descriptor]) && ! feof($pipes[$descriptor])) {
                $read[] = $pipes[$descriptor];
            }
        }

        if ($read !== []) {
            $write = null;
            $except = null;
            @stream_select($read, $write, $except, 0, 100000);

            foreach ($read as $stream) {
                $chunk = stream_get_contents($stream);

                if ($stream === $pipes[1]) {
                    $stdout .= $chunk;
                } else {
                    $stderr .= $chunk;
                }
            }
        }

        $status = proc_get_status($process);

        if (! $observedBeforeExit && str_contains($stdout, 'delegated-stdout-before-exit')) {
            $observedBeforeExit = $status['running'];
            writeFile($releaseFile, "release\n");
        }

        if (! $status['running'] && feof($pipes[1]) && feof($pipes[2])) {
            break;
        }
    }

    if (! file_exists($releaseFile)) {
        writeFile($releaseFile, "release-after-timeout\n");
    }

    foreach ([1, 2] as $descriptor) {
        $remaining = stream_get_contents($pipes[$descriptor]);

        if ($descriptor === 1) {
            $stdout .= $remaining;
        } else {
            $stderr .= $remaining;
        }

        fclose($pipes[$descriptor]);
    }

    $status = proc_get_status($process);

    if ($status['running']) {
        proc_terminate($process);
    }

    $exitStatus = proc_close($process);

    // proc_close() returns -1 when proc_get_status() above already reaped the
    // child's termination status (PHP caches the exit code on the first status
    // read, so the later waitpid() in proc_close() sees no child). Fall back to
    // the cached exit code, mirroring runCommand()'s handling. This only diverges
    // by platform/PHP build: macOS/PHP 8.4 returns the real code from proc_close()
    // directly, whereas the CI PHP 8.2 container returns -1.
    if ($exitStatus < 0 && is_int($status['exitcode']) && $status['exitcode'] >= 0) {
        $exitStatus = $status['exitcode'];
    }

    return [
        'status' => $exitStatus,
        'stdout' => $stdout,
        'stderr' => $stderr,
        'observed_before_exit' => $observedBeforeExit,
    ];
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

    if (getenv('SYNTHETIC_IO_MODE') === 'preflight-overflow') {
        fwrite(STDOUT, str_repeat('P', 262145));
        fwrite(STDERR, str_repeat('E', 262145));
    }

    if (getenv('SYNTHETIC_IO_MODE') === 'preflight-timeout') {
        sleep(20);
    }

    exit(0);
}

if ((\$argv[1] ?? null) === 'policy' && in_array('--help', \$argv, true)) {
    if (getenv('SYNTHETIC_IO_MODE') === 'mutate-manifest-during-policy-probe') {
        \$manifest = json_decode((string) file_get_contents(getcwd().'/composer.json'), true, 512, JSON_THROW_ON_ERROR);
        \$manifest['config']['policy']['advisories']['block'] = false;
        file_put_contents(getcwd().'/composer.json', json_encode(\$manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\\n");
    }

    exit(0);
}

if (getenv('SYNTHETIC_IO_MODE') === 'streaming') {
    fwrite(STDOUT, "delegated-stdout-before-exit\\n");
    fflush(STDOUT);
    \$releaseFile = (string) getenv('SYNTHETIC_RELEASE_FILE');
    \$deadline = microtime(true) + 10.0;

    while (! is_file(\$releaseFile) && microtime(true) < \$deadline) {
        usleep(10000);
    }

    fwrite(STDERR, "delegated-stderr-after-release\\n");
    fflush(STDERR);
    exit(37);
}

if (getenv('SYNTHETIC_IO_MODE') === 'large-output') {
    for (\$index = 0; \$index < 128; ++\$index) {
        fwrite(STDOUT, str_repeat('O', 8192));
        fwrite(STDERR, str_repeat('E', 8192));
    }

    exit(37);
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
        $environment['COMPOSER_POLICY_ADVISORIES_BLOCK'],
        $environment['COMPOSER_POLICY_MALWARE_BLOCK'],
        $environment['COMPOSER_POLICY_ABANDONED_BLOCK'],
        $environment['COMPOSER_SECURITY_BLOCKING_ABANDONED'],
        $environment['COMPOSER_AUDIT_ABANDONED'],
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
    $result = runCommand(['git', 'ls-files', '-z'], getenv(), $repositoryRoot);
    assertTrue($result['status'] === 0, 'Could not enumerate tracked files for the route audit.');

    return array_values(array_filter(explode("\0", $result['stdout']), static fn (string $path): bool => $path !== ''));
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
    $compoundDepth = 0;
    $functionParenthesisDepth = 0;
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

        if ($quote === null && $character === '(' && preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', trim($buffer)) === 1) {
            $buffer .= $character;
            $functionParenthesisDepth = 1;

            continue;
        }

        if ($quote === null && $functionParenthesisDepth > 0) {
            $buffer .= $character;

            if ($character === '(') {
                ++$functionParenthesisDepth;
            } elseif ($character === ')') {
                --$functionParenthesisDepth;
            }

            continue;
        }

        if ($quote === null && $character === '{' && ($compoundDepth > 0 || trim($buffer) === '' || preg_match('/^[A-Za-z_][A-Za-z0-9_]*\(\)$/', trim($buffer)) === 1)) {
            ++$compoundDepth;
            $buffer .= $character;

            continue;
        }

        if ($quote === null && $character === '}' && $compoundDepth > 0) {
            --$compoundDepth;
            $buffer .= $character;

            continue;
        }

        if ($compoundDepth > 0) {
            $buffer .= $character;

            continue;
        }

        if ($quote === null && in_array($character, ['&', '|', ';', '(', ')', "\n", "\r"], true)) {
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
 * @return list<array{value: string, raw: string, fragments: list<array{quote: string, text: string, escaped: bool}>, dynamic: bool}>
 */
function commandTokenDetails(string $segment, bool $strict = true): array
{
    $tokens = [];
    $token = '';
    $raw = '';
    $fragments = [];
    $dynamic = false;
    $tokenStarted = false;
    $quote = null;
    $escaped = false;
    $length = strlen($segment);

    $appendToken = static function () use (&$tokens, &$token, &$raw, &$fragments, &$dynamic, &$tokenStarted): void {
        if (! $tokenStarted) {
            return;
        }

        $tokens[] = [
            'value' => $token,
            'raw' => $raw,
            'fragments' => $fragments,
            'dynamic' => $dynamic,
        ];
        $token = '';
        $raw = '';
        $fragments = [];
        $dynamic = false;
        $tokenStarted = false;

        if (count($tokens) > MAX_ROUTE_TOKENS) {
            throw new RuntimeException('Route audit token count exceeds the configured bound.');
        }
    };

    $appendFragment = static function (string $character, string $mode, bool $wasEscaped) use (&$fragments): void {
        $lastIndex = array_key_last($fragments);

        if ($lastIndex !== null && $fragments[$lastIndex]['quote'] === $mode && $fragments[$lastIndex]['escaped'] === $wasEscaped) {
            $fragments[$lastIndex]['text'] .= $character;

            return;
        }

        $fragments[] = ['quote' => $mode, 'text' => $character, 'escaped' => $wasEscaped];
    };

    for ($index = 0; $index < $length; ++$index) {
        $character = $segment[$index];

        if ($escaped) {
            $token .= $character;
            $raw .= $character;
            $appendFragment($character, $quote ?? 'unquoted', true);
            $tokenStarted = true;
            $escaped = false;

            continue;
        }

        if ($character === '\\' && $quote !== "'") {
            $raw .= $character;
            $tokenStarted = true;
            $escaped = true;

            continue;
        }

        if ($character === "'" && $quote === "'") {
            $quote = null;
            $raw .= $character;
            $tokenStarted = true;

            continue;
        }

        if ($character === '"' && $quote === '"') {
            $quote = null;
            $raw .= $character;
            $tokenStarted = true;

            continue;
        }

        if ($quote === null && in_array($character, ["'", '"'], true)) {
            $quote = $character;
            $raw .= $character;
            $tokenStarted = true;

            continue;
        }

        if ($quote === null && ctype_space($character)) {
            $appendToken();

            continue;
        }

        $token .= $character;
        $raw .= $character;
        $appendFragment($character, $quote === "'" ? 'single' : ($quote === '"' ? 'double' : 'unquoted'), false);
        $dynamic = $dynamic || ($quote !== "'" && ($character === '$' || $character === '`'));
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

/**
 * @return list<string>
 */
function commandTokens(string $segment, bool $strict = true): array
{
    return array_column(commandTokenDetails($segment, $strict), 'value');
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
 * @return list<array{line: int, logical: string, text: string, scalar: string, parse_error: string|null}>
 */
function workflowCommandScalars(string $contents): array
{
    $lines = preg_split('/\R/', $contents);
    $commands = [];
    $anchors = [];

    for ($index = 0, $count = count($lines); $index < $count; ++$index) {
        $line = $lines[$index];

        if (preg_match('/^(\s*)(?:-\s+)?run:\s*(.*)$/', $line, $matches) !== 1) {
            continue;
        }

        $lineNumber = $index + 1;
        $indent = strlen($matches[1]);
        $value = rtrim($matches[2]);

        if (preg_match('/^\*([A-Za-z_][A-Za-z0-9_-]*)$/', $value, $alias) === 1) {
            $anchor = $anchors[$alias[1]] ?? null;

            $commands[] = $anchor === null
                ? [
                    'line' => $lineNumber,
                    'logical' => trim($line),
                    'text' => $value,
                    'scalar' => 'alias',
                    'parse_error' => 'workflow run alias has no earlier bounded literal run-scalar anchor',
                ]
                : [
                    'line' => $lineNumber,
                    'logical' => trim($line).' => '.$anchor['logical'],
                    'text' => $anchor['text'],
                    'scalar' => 'alias('.$alias[1].')',
                    'parse_error' => null,
                ];

            continue;
        }

        if (preg_match('/^([>|])([+-]?)$/', $value, $block) === 1) {
            $parts = [];
            $next = $index + 1;

            for (; $next < $count; ++$next) {
                $candidate = $lines[$next];

                if (trim($candidate) === '') {
                    $parts[] = '';

                    continue;
                }

                preg_match('/^(\s*)/', $candidate, $indentMatch);

                if (strlen($indentMatch[1]) <= $indent) {
                    break;
                }

                $parts[] = trim($candidate);
            }

            $index = $next - 1;
            $commands[] = [
                'line' => $lineNumber,
                'logical' => trim($line),
                'text' => $block[1] === '>' ? implode(' ', $parts) : implode("\n", $parts),
                'scalar' => $block[1] === '>' ? 'folded' : 'literal',
                'parse_error' => null,
            ];

            continue;
        }

        if (str_starts_with($value, '>') || str_starts_with($value, '|')) {
            $parts = [];
            $next = $index + 1;

            for (; $next < $count; ++$next) {
                $candidate = $lines[$next];

                if (trim($candidate) === '') {
                    continue;
                }

                preg_match('/^(\s*)/', $candidate, $indentMatch);

                if (strlen($indentMatch[1]) <= $indent) {
                    break;
                }

                $parts[] = trim($candidate);
            }

            $index = $next - 1;
            $commands[] = [
                'line' => $lineNumber,
                'logical' => trim($line),
                'text' => implode(' ', $parts),
                'scalar' => 'unsupported-block',
                'parse_error' => 'workflow run scalar is outside the bounded grammar',
            ];

            continue;
        }

        $scalar = 'inline';
        $anchorName = null;

        if (preg_match('/^&([A-Za-z_][A-Za-z0-9_-]*)\s+(.+)$/', $value, $anchor) === 1) {
            $anchorName = $anchor[1];
            $value = $anchor[2];
            $scalar = 'anchor';
        }

        if (($value[0] ?? null) === "'" && str_ends_with($value, "'")) {
            $value = str_replace("''", "'", substr($value, 1, -1));
            $scalar = 'single-quoted';
        } elseif (($value[0] ?? null) === '"' && str_ends_with($value, '"')) {
            $decoded = json_decode($value, true);

            if (! is_string($decoded)) {
                $commands[] = [
                    'line' => $lineNumber,
                    'logical' => trim($line),
                    'text' => $value,
                    'scalar' => 'double-quoted',
                    'parse_error' => 'workflow run scalar has invalid quoted syntax',
                ];

                continue;
            }

            $value = $decoded;
            $scalar = 'double-quoted';
        }

        $command = [
            'line' => $lineNumber,
            'logical' => trim($line),
            'text' => $value,
            'scalar' => $scalar,
            'parse_error' => null,
        ];

        if ($anchorName !== null && isset($anchors[$anchorName])) {
            $command['parse_error'] = 'workflow run scalar anchor is duplicated';
        }

        $commands[] = $command;

        if ($anchorName !== null && $command['parse_error'] === null) {
            $anchors[$anchorName] = $command;
        }
    }

    return $commands;
}

/**
 * Accept only literal Docker RUN and CMD/ENTRYPOINT instruction spellings.
 * Docker expansion, stages, shell selection, mounts, and heredocs are outside
 * this dependency-route grammar and deliberately become explicit rejections.
 *
 * @return list<array{line: int, logical: string, text: string, scalar: string, parse_error: string|null}>
 */
function dockerCommandScalars(string $contents): array
{
    $commands = [];

    foreach (preg_split('/\R/', $contents) as $index => $line) {
        if (preg_match('/^\s*(RUN|CMD|ENTRYPOINT)\s+(.+)$/i', $line, $match) !== 1) {
            continue;
        }

        $instruction = strtoupper($match[1]);
        $value = trim($match[2]);
        $lineNumber = $index + 1;

        if (str_starts_with($value, '[')) {
            try {
                $array = json_decode($value, true, 32, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                $array = null;
            }

            if (! is_array($array) || $array === [] || array_filter($array, static fn (mixed $part): bool => ! is_string($part)) !== []) {
                $commands[] = ['line' => $lineNumber, 'logical' => $line, 'text' => $value, 'scalar' => 'docker-'.$instruction.'-json', 'parse_error' => 'Docker JSON command array is outside the bounded literal grammar'];

                continue;
            }

            $commands[] = ['line' => $lineNumber, 'logical' => $line, 'text' => implode(' ', $array), 'scalar' => 'docker-'.$instruction.'-json', 'parse_error' => null];

            continue;
        }

        if ($instruction !== 'RUN' && str_starts_with($value, '--')) {
            $commands[] = ['line' => $lineNumber, 'logical' => $line, 'text' => $value, 'scalar' => 'docker-'.$instruction, 'parse_error' => 'Docker instruction options are outside the bounded literal grammar'];

            continue;
        }

        if ($instruction === 'RUN' && str_starts_with($value, '--')) {
            $commands[] = ['line' => $lineNumber, 'logical' => $line, 'text' => $value, 'scalar' => 'docker-RUN', 'parse_error' => 'Docker RUN options are outside the bounded literal grammar'];

            continue;
        }

        $commands[] = ['line' => $lineNumber, 'logical' => $line, 'text' => $value, 'scalar' => 'docker-'.strtolower($instruction), 'parse_error' => null];
    }

    return $commands;
}

/**
 * @param list<string> $tokens
 * @return array{executable: string, operation: string}|null
 */
function parseInvocation(array $tokens): ?array
{
    while ($tokens !== []) {
        $token = $tokens[0];

        if (in_array($token, ['if', 'then', 'elif', 'else', 'fi'], true)) {
            array_shift($tokens);

            continue;
        }

        if (in_array($token, ['!', 'do', 'done'], true)) {
            array_shift($tokens);

            continue;
        }

        if ($token === 'timeout') {
            array_shift($tokens);

            while ($tokens !== [] && str_starts_with($tokens[0], '-')) {
                $option = array_shift($tokens);

                if (in_array($option, ['-k', '--kill-after', '-s', '--signal'], true)) {
                    if ($tokens === []) {
                        return ['executable' => 'unsupported-wrapper', 'operation' => 'unknown'];
                    }

                    array_shift($tokens);
                }
            }

            if ($tokens === [] || preg_match('/^[0-9]+(?:\.[0-9]+)?[smhd]?$/', $tokens[0]) !== 1) {
                return ['executable' => 'unsupported-wrapper', 'operation' => 'unknown'];
            }

            array_shift($tokens);

            continue;
        }

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

        if ($token === 'exec') {
            array_shift($tokens);

            while ($tokens !== [] && in_array($tokens[0], ['-c', '-l'], true)) {
                array_shift($tokens);
            }

            if (($tokens[0] ?? null) === '-a') {
                array_shift($tokens);

                if ($tokens === []) {
                    return ['executable' => 'unsupported-wrapper', 'operation' => 'unknown'];
                }

                array_shift($tokens);
            }

            if (($tokens[0] ?? '') !== '' && str_starts_with($tokens[0], '-')) {
                return ['executable' => 'unsupported-wrapper', 'operation' => 'unknown'];
            }

            continue;
        }

        if ($token === 'time') {
            array_shift($tokens);

            while ($tokens !== [] && in_array($tokens[0], ['-p', '--portability'], true)) {
                array_shift($tokens);
            }

            if (($tokens[0] ?? '') !== '' && str_starts_with($tokens[0], '-')) {
                return ['executable' => 'unsupported-wrapper', 'operation' => 'unknown'];
            }

            continue;
        }

        if ($token === 'nice') {
            array_shift($tokens);

            if (in_array($tokens[0] ?? null, ['-n', '--adjustment'], true)) {
                array_shift($tokens);

                if ($tokens === []) {
                    return ['executable' => 'unsupported-wrapper', 'operation' => 'unknown'];
                }

                array_shift($tokens);
            } elseif (preg_match('/^-n[0-9+-]+$/', (string) ($tokens[0] ?? '')) === 1) {
                array_shift($tokens);
            }

            if (($tokens[0] ?? '') !== '' && str_starts_with($tokens[0], '-')) {
                return ['executable' => 'unsupported-wrapper', 'operation' => 'unknown'];
            }

            continue;
        }

        if ($token === 'stdbuf') {
            array_shift($tokens);

            while (in_array($tokens[0] ?? null, ['-i', '-o', '-e'], true)) {
                array_shift($tokens);

                if ($tokens === []) {
                    return ['executable' => 'unsupported-wrapper', 'operation' => 'unknown'];
                }

                array_shift($tokens);
            }

            while (preg_match('/^-[ioe].+$/', (string) ($tokens[0] ?? '')) === 1) {
                array_shift($tokens);
            }

            if (($tokens[0] ?? '') !== '' && str_starts_with($tokens[0], '-')) {
                return ['executable' => 'unsupported-wrapper', 'operation' => 'unknown'];
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

    if (in_array($basename, ['bash', 'sh', 'zsh', 'eval'], true)) {
        return [
            'executable' => 'evaluator',
            'operation' => $basename,
            'contract_allowed' => false,
            'evaluator' => $basename,
            'arguments' => $tokens,
        ];
    }

    $executableClass = str_ends_with(str_replace('\\', '/', $executable), 'bin/composer-policy')
        ? 'guard'
        : (in_array($basename, ['composer', 'composer.phar'], true) ? $basename : null);

    if ($executableClass === null) {
        return null;
    }

    $decision = ComposerPolicyCommandContract::decide($tokens);
    $operation = $decision['command'];
    $knownDirectCommands = [
        'validate',
        'audit',
        'install',
        'update',
        'require',
        'remove',
        'create-project',
        'self-update',
        'config',
        'global',
        'i',
        'u',
        'upgrade',
    ];

    if ($executableClass !== 'guard' && ! in_array($operation, $knownDirectCommands, true)) {
        return null;
    }

    return [
        'executable' => $executableClass,
        'operation' => $operation !== '' ? $operation : 'unknown',
        'contract_allowed' => $decision['allowed'],
    ];
}

/**
 * @return array{classification: string, rationale: string}
 */
function classifyRoute(string $path, string $executable, bool $contractAllowed = true): array
{
    if (str_starts_with($path, '.planning/') || str_starts_with($path, 'tests/')) {
        return ['classification' => 'non-production', 'rationale' => 'planning, history, research, and test coverage are not production dependency routes'];
    }

    if (! isSupportedProductionRoute($path)) {
        return ['classification' => 'unclassified', 'rationale' => 'Composer mutation command has no approved production route classification'];
    }

    if ($executable !== 'guard') {
        return ['classification' => 'unsupported', 'rationale' => 'supported dependency route invokes Composer directly instead of the repository guard'];
    }

    if (! $contractAllowed) {
        return ['classification' => 'unsupported', 'rationale' => 'guarded command is outside the shared repository command contract'];
    }

    return ['classification' => 'supported', 'rationale' => 'this command-chain segment invokes the repository guard'];
}

function isSupportedProductionRoute(string $path): bool
{
    return $path === 'composer.json'
        || $path === 'README.md'
        || str_starts_with($path, '.github/workflows/')
        || str_starts_with($path, 'scripts/')
        || str_starts_with($path, 'bin/')
        || str_starts_with($path, 'docker/')
        || str_starts_with($path, 'containers/')
        || str_starts_with($path, 'Dockerfile')
        || $path === 'Makefile';
}

function containsComposerExecutableText(string $text): bool
{
    try {
        $segments = commandChainSegments($text, false);
    } catch (RuntimeException) {
        return preg_match('~(?:composer(?:\\.phar)?|bin/composer-policy|\\b(?:bash|sh|zsh|eval)\\b)~i', $text) === 1;
    }

    foreach ($segments as $segment) {
        $details = commandTokenDetails($segment, false);
        $tokens = array_column($details, 'value');

        while ($tokens !== [] && (
            preg_match('/^[A-Za-z_][A-Za-z0-9_]*=/', $tokens[0]) === 1
            || in_array($tokens[0], ['if', 'then', 'elif', 'else', 'fi'], true)
        )) {
            array_shift($tokens);
        }

        if (($tokens[0] ?? null) === 'timeout') {
            array_shift($tokens);

            while ($tokens !== [] && str_starts_with($tokens[0], '-')) {
                array_shift($tokens);
            }

            if ($tokens !== [] && preg_match('/^[0-9]+(?:\.[0-9]+)?[smhd]?$/', $tokens[0]) === 1) {
                array_shift($tokens);
            }
        }

        $executable = strtolower(basename((string) ($tokens[0] ?? '')));

        if (
            in_array($executable, ['composer', 'composer.phar'], true)
            || str_ends_with(str_replace('\\', '/', (string) ($tokens[0] ?? '')), 'bin/composer-policy')
        ) {
            return true;
        }

        if (in_array($executable, ['bash', 'sh', 'zsh', 'eval'], true)) {
            foreach (array_slice($details, 1) as $detail) {
                if (preg_match('~(?:^|[[:space:]])(?:composer|composer\\.phar|php[[:space:]]+bin/composer-policy)(?:[[:space:]]|$)~i', $detail['value']) === 1) {
                    return true;
                }
            }
        }
    }

    return false;
}

function containsComposerOrEvaluatorText(string $text): bool
{
    return containsComposerExecutableText($text)
        || preg_match('~(?:composer(?:\\.phar)?|bin/composer-policy|\\b(?:bash|sh|zsh|eval)\\b)~i', $text) === 1;
}

/**
 * @return array{kind: string, body: string, rationale: string}|null
 */
function compoundShellBody(string $segment): ?array
{
    $trimmed = trim($segment);
    $open = null;
    $kind = null;

    if (str_starts_with($trimmed, '{')) {
        $open = 0;
        $kind = 'brace group';
    } elseif (preg_match('/^[A-Za-z_][A-Za-z0-9_]*\(\)\s*\{/', $trimmed, $match) === 1) {
        $open = strlen($match[0]) - 1;
        $kind = 'function body';
    } elseif (str_contains($trimmed, '{') && preg_match('/^[A-Za-z_][A-Za-z0-9_]*\s*\(/', $trimmed) === 1) {
        return ['kind' => 'invalid', 'body' => '', 'rationale' => 'shell function shape is outside the bounded compound grammar'];
    } else {
        return null;
    }

    $quote = null;
    $escaped = false;
    $depth = 0;
    $length = strlen($trimmed);

    for ($index = $open; $index < $length; ++$index) {
        $character = $trimmed[$index];

        if ($escaped) {
            $escaped = false;

            continue;
        }

        if ($character === '\\' && $quote !== "'") {
            $escaped = true;

            continue;
        }

        if (in_array($character, ["'", '"'], true)) {
            if ($quote === $character) {
                $quote = null;
            } elseif ($quote === null) {
                $quote = $character;
            }

            continue;
        }

        if ($quote !== null) {
            continue;
        }

        if ($character === '{') {
            ++$depth;

            continue;
        }

        if ($character === '}') {
            --$depth;

            if ($depth === 0) {
                $body = trim(substr($trimmed, $open + 1, $index - $open - 1));
                $remainder = trim(substr($trimmed, $index + 1));

                if ($remainder !== '' || ! str_ends_with($body, ';')) {
                    return ['kind' => 'invalid', 'body' => '', 'rationale' => "{$kind} must have one matching outer body ending in a command separator"];
                }

                return ['kind' => 'compound', 'body' => substr($body, 0, -1), 'rationale' => $kind];
            }
        }
    }

    return ['kind' => 'invalid', 'body' => '', 'rationale' => "{$kind} has an unmatched outer brace"];
}

function decodePhpStringLiteral(string $literal): ?string
{
    $quote = $literal[0] ?? '';

    if (! in_array($quote, ["'", '"'], true) || ! str_ends_with($literal, $quote)) {
        return null;
    }

    $contents = substr($literal, 1, -1);

    return $quote === "'"
        ? str_replace(["\\\\", "\\'"], ["\\", "'"], $contents)
        : stripcslashes($contents);
}

function literalPhpCommandString(string $expression): ?string
{
    $tokens = token_get_all('<?php '.$expression);
    $meaningful = array_values(array_filter($tokens, static fn (array|string $token): bool => ! is_array($token)
        || ! in_array($token[0], [T_OPEN_TAG, T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)));

    if (count($meaningful) !== 1 || ! is_array($meaningful[0]) || $meaningful[0][0] !== T_CONSTANT_ENCAPSED_STRING) {
        return null;
    }

    return decodePhpStringLiteral($meaningful[0][1]);
}

/**
 * @return list<string>|null
 */
function literalPhpCommandTokens(string $expression): ?array
{
    $literalCommand = literalPhpCommandString($expression);

    if (is_string($literalCommand)) {
        return commandTokens($literalCommand);
    }

    $tokens = token_get_all('<?php '.$expression);
    $meaningful = array_values(array_filter($tokens, static fn (array|string $token): bool => ! is_array($token)
        || ! in_array($token[0], [T_OPEN_TAG, T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)));

    $isArray = str_starts_with(trim($expression), '[') || preg_match('/^array\s*\(/i', trim($expression)) === 1;

    if (! $isArray) {
        return null;
    }

    $values = [];

    foreach ($meaningful as $token) {
        if (is_array($token) && $token[0] === T_CONSTANT_ENCAPSED_STRING) {
            $value = decodePhpStringLiteral($token[1]);

            if (! is_string($value)) {
                return null;
            }

            $values[] = $value;
        }
    }

    if ($values === []) {
        return null;
    }

    $guardIndex = null;

    foreach ($values as $index => $value) {
        if (str_ends_with(str_replace('\\', '/', $value), 'bin/composer-policy')) {
            $guardIndex = $index;

            break;
        }
    }

    if ($guardIndex !== null) {
        return ['php', $values[$guardIndex], ...array_slice($values, $guardIndex + 1)];
    }

    return $values;
}

/**
 * @return list<array{line: int, expression: string, command: string|null, tokens: list<string>|null, composer_bearing: bool}>
 */
function phpProcessLaunches(string $contents): array
{
    $tokens = token_get_all($contents);
    $launches = [];
    $functions = ['proc_open', 'exec', 'system', 'passthru', 'shell_exec'];
    $count = count($tokens);

    for ($index = 0; $index < $count; ++$index) {
        $token = $tokens[$index];

        if (! is_array($token) || $token[0] !== T_STRING || ! in_array(strtolower($token[1]), $functions, true)) {
            continue;
        }

        $line = $token[2];
        $cursor = $index + 1;

        while ($cursor < $count && is_array($tokens[$cursor]) && $tokens[$cursor][0] === T_WHITESPACE) {
            ++$cursor;
        }

        if (($tokens[$cursor] ?? null) !== '(') {
            continue;
        }

        ++$cursor;
        $depth = 0;
        $expression = '';

        for (; $cursor < $count; ++$cursor) {
            $part = $tokens[$cursor];
            $text = is_array($part) ? $part[1] : $part;

            if (in_array($text, ['(', '[', '{'], true)) {
                ++$depth;
            } elseif (in_array($text, [')', ']', '}'], true)) {
                if ($text === ')' && $depth === 0) {
                    break;
                }

                --$depth;
            }

            if ($text === ',' && $depth === 0) {
                break;
            }

            $expression .= $text;
        }

        $launches[] = [
            'line' => $line,
            'expression' => trim($expression),
            'command' => literalPhpCommandString(trim($expression)),
            'tokens' => literalPhpCommandTokens(trim($expression)),
            'composer_bearing' => containsComposerExecutableText($expression)
                || preg_match('/[\'"]composer(?:\.phar)?\s/i', $expression) === 1
                || str_contains($expression, 'bin/composer-policy'),
        ];
    }

    return $launches;
}

/**
 * @param list<string> $trail
 * @return array{path: string, line: int, logical: string, scalar: string, chain: int, segment: string, executable: string, operation: string, classification: string, rationale: string, evaluator_depth: int, evaluator_trail: string, payload_raw: string, payload_decoded: string}
 */
function shellRouteRecord(string $path, int $line, string $logical, string $scalar, int $chain, string $segment, string $executable, string $operation, string $classification, string $rationale, int $depth = 0, array $trail = [], string $payloadRaw = '', string $payloadDecoded = ''): array
{
    return [
        'path' => $path,
        'line' => $line,
        'logical' => $logical,
        'scalar' => $scalar,
        'chain' => $chain,
        'segment' => $segment,
        'executable' => $executable,
        'operation' => $operation,
        'classification' => $classification,
        'rationale' => $rationale,
        'evaluator_depth' => $depth,
        'evaluator_trail' => implode(' > ', $trail),
        'payload_raw' => $payloadRaw,
        'payload_decoded' => $payloadDecoded,
    ];
}

/**
 * @return array{line: int, source: string}|null
 */
function phpCommandShapedProgram(string $contents): ?array
{
    $meaningful = [];

    foreach (token_get_all($contents) as $token) {
        if (is_array($token) && in_array($token[0], [T_OPEN_TAG, T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
            continue;
        }

        $meaningful[] = [
            'type' => is_array($token) ? $token[0] : null,
            'text' => is_array($token) ? $token[1] : $token,
            'line' => is_array($token) ? $token[2] : 0,
        ];
    }

    $lines = explode("\n", $contents);

    foreach ($meaningful as $index => $token) {
        if ($token['type'] !== T_CONSTANT_ENCAPSED_STRING) {
            continue;
        }

        $command = decodePhpStringLiteral($token['text']);

        if (! is_string($command) || ! phpCommandShapedLiteral($command)) {
            continue;
        }

        $callLine = phpCommandInvocationLine($meaningful, $index);

        if ($callLine === null) {
            continue;
        }

        return [
            'line' => $callLine,
            'source' => $lines[$callLine - 1] ?? $command,
        ];
    }

    return null;
}

function phpCommandShapedLiteral(string $command): bool
{
    return preg_match('~^(?:@composer|composer(?:\\.phar)?)\\s+(?:install|update|audit|validate)(?:\\s|$)~i', trim($command)) === 1
        || preg_match('~^(?:php(?:[0-9.]+)?\\s+)?(?:\\.?/)?bin/composer-policy\\s+(?:install|update|audit|validate)(?:\\s|$)~i', trim($command)) === 1
        || preg_match('~^(?:bash|sh|zsh|eval)\\s+(?:-c\\s+)?[\\\'\"]?(?:@composer|composer(?:\\.phar)?)\\s+(?:install|update|audit|validate)(?:\\s|$)~i', trim($command)) === 1;
}

/**
 * @param list<array{type: int|null, text: string, line: int}> $tokens
 */
function phpCommandInvocationLine(array $tokens, int $stringIndex): ?int
{
    $previous = $tokens[$stringIndex - 1] ?? null;

    if ($previous !== null && $previous['type'] === T_ECHO) {
        return $previous['line'];
    }

    $depth = 0;

    for ($index = $stringIndex - 1; $index >= 0; --$index) {
        $token = $tokens[$index];

        if (in_array($token['text'], [')', ']', '}'], true)) {
            ++$depth;

            continue;
        }

        if (in_array($token['text'], ['(', '[', '{'], true)) {
            if ($depth > 0) {
                --$depth;

                continue;
            }

            if ($token['text'] !== '(') {
                return null;
            }

            $callee = $tokens[$index - 1] ?? null;

            if ($callee === null || ! in_array($callee['type'], [T_STRING, T_VARIABLE], true)) {
                return null;
            }

            return $callee['line'];
        }

        if ($depth === 0 && $token['text'] === ';') {
            return null;
        }
    }

    return null;
}

function isTrustedPhpAuditSource(string $path): bool
{
    return $path === 'bin/composer-policy'
        || str_starts_with($path, 'tests/')
        || str_starts_with($path, '.planning/')
        || str_starts_with($path, 'vendor/')
        || str_starts_with($path, 'bootstrap/cache/')
        || str_starts_with($path, 'storage/framework/');
}

function routeAuditMarker(string $source): bool
{
    try {
        $marker = containsComposerExecutableText($source);
    } catch (RuntimeException) {
        $marker = preg_match('~(?:composer(?:\.phar)?|bin/composer-policy|\b(?:bash|sh|zsh|eval)\b)~i', $source) === 1;
    }

    return $marker
        || preg_match('~\b(?:bash|sh|zsh|eval)\b~i', $source) === 1
        || preg_match('~\b(?:proc_open|exec|system|passthru|shell_exec)\s*\(~i', $source) === 1;
}

function routeSourceKind(string $path, string $contents): string
{
    $trimmed = ltrim($contents);

    if ($path === 'AGENTS.md' || preg_match('~\.(?:phar|sha256|json|lock)$~i', $path) === 1) {
        return 'non-source';
    }

    if (preg_match('~^\.github/workflows/.+\.ya?ml$~', $path) === 1) {
        return 'workflow';
    }

    if (str_ends_with(strtolower($path), '.php') || str_starts_with($trimmed, '<?php') || str_starts_with($trimmed, '#!/usr/bin/env php')) {
        return 'php';
    }

    if ($path === 'Makefile' || $path === 'README.md' || str_starts_with($path, 'scripts/') || str_starts_with($path, 'bin/') || preg_match('~\.(?:sh|bash|zsh)$~', $path) === 1) {
        return 'shell';
    }

    if (preg_match('~(?:^|/)Dockerfile[^/]*$~i', $path) === 1 || str_starts_with($path, 'docker/') || str_starts_with($path, 'containers/')) {
        return 'docker';
    }

    return 'unknown-source';
}

function markerSourceLine(string $contents): int
{
    if (preg_match('~^.*(?:composer(?:\.phar)?|bin/composer-policy|\b(?:bash|sh|zsh|eval|proc_open|exec|system|passthru|shell_exec)\b).*?$~im', $contents, $match, PREG_OFFSET_CAPTURE) !== 1) {
        return 1;
    }

    return substr_count(substr($contents, 0, $match[0][1]), "\n") + 1;
}

function composerScriptSourceLine(string $contents, string $event, string $handler): ?int
{
    $lines = explode("\n", $contents);
    $eventNeedle = '"'.$event.'"';
    $eventLine = null;
    $limit = min(count($lines), MAX_ROUTE_SEGMENTS * MAX_ROUTE_TOKENS);

    for ($index = 0; $index < $limit; ++$index) {
        if ($eventLine === null) {
            if (! str_contains($lines[$index], $eventNeedle)) {
                continue;
            }

            $eventLine = $index;
        }

        if (str_contains($lines[$index], $handler)) {
            return $index + 1;
        }
    }

    return $eventLine === null ? null : $eventLine + 1;
}

function composerScriptMarker(string $source): bool
{
    return routeAuditMarker($source)
        || preg_match('/(?:^|\s)@composer(?=\s|$)/i', $source) === 1
        || preg_match('/\bcomposer(?:\.phar)?\b/i', $source) === 1
        || str_contains(str_replace('\\', '/', $source), 'bin/composer-policy');
}

/**
 * @return list<array{path: string, line: int, logical: string, scalar: string, chain: int, segment: string, executable: string, operation: string, classification: string, rationale: string, evaluator_depth: int, evaluator_trail: string, payload_raw: string, payload_decoded: string}>
 */
function composerScriptAuditRecords(string $contents): array
{
    try {
        $manifest = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        return composerScriptMarker($contents)
            ? [shellRouteRecord('composer.json', markerSourceLine($contents), 'scripts', 'composer-script', 0, $contents, 'unclassified-composer-script', 'unknown', 'unclassified', 'root Composer manifest could not be parsed for bounded script provenance')]
            : [];
    }

    if (! is_array($manifest) || ! array_key_exists('scripts', $manifest)) {
        return [];
    }

    $scripts = $manifest['scripts'];

    if (! is_array($scripts)) {
        $raw = json_encode($scripts, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        return composerScriptMarker($raw)
            ? [shellRouteRecord('composer.json', composerScriptSourceLine($contents, 'scripts', $raw) ?? markerSourceLine($contents), 'scripts', 'composer-script', 0, $raw, 'unclassified-composer-script', 'unknown', 'unclassified', 'root Composer scripts value is outside the finite handler grammar')]
            : [];
    }

    $records = [];

    foreach ($scripts as $event => $handlers) {
        if (! is_string($event)) {
            continue;
        }

        $finiteHandlers = is_string($handlers)
            ? [$handlers]
            : (is_array($handlers) && array_is_list($handlers) && array_reduce($handlers, static fn (bool $allStrings, mixed $handler): bool => $allStrings && is_string($handler), true)
                ? $handlers
                : null);

        if ($finiteHandlers === null) {
            $raw = json_encode($handlers, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

            if (composerScriptMarker($raw)) {
                $records[] = shellRouteRecord('composer.json', composerScriptSourceLine($contents, $event, $raw) ?? markerSourceLine($contents), $event, 'composer-script', 0, $raw, 'unclassified-composer-script', 'unknown', 'unclassified', 'Composer script handler is outside the finite scalar-or-list-of-scalars grammar');
            }

            continue;
        }

        foreach ($finiteHandlers as $ordinal => $handler) {
            if (! composerScriptMarker($handler)) {
                continue;
            }

            $line = composerScriptSourceLine($contents, $event, $handler);

            if ($line === null) {
                $records[] = shellRouteRecord('composer.json', markerSourceLine($contents), $event, 'composer-script', $ordinal, $handler, 'unclassified-composer-script', 'unknown', 'unclassified', 'Composer script handler source line is outside the bounded locator');

                continue;
            }

            $normalized = preg_replace('/^@composer(?=\s|$)/', 'composer', trim($handler));
            $normalized = is_string($normalized) ? preg_replace('/^@php(?=\s|$)/', 'php', $normalized) : null;

            try {
                $tokens = $normalized === null ? [] : commandTokens($normalized);
            } catch (RuntimeException) {
                $tokens = [];
            }

            $invocation = $tokens === [] ? null : parseInvocation($tokens);

            if ($invocation === null) {
                $records[] = shellRouteRecord('composer.json', $line, $event, 'composer-script', $ordinal, $handler, 'unclassified-composer-script', 'unknown', 'unclassified', 'Composer script handler is outside the bounded executable and argument grammar');

                continue;
            }

            $route = classifyRoute('composer.json', $invocation['executable'], $invocation['contract_allowed'] ?? false);
            $records[] = shellRouteRecord('composer.json', $line, $event, 'composer-script', $ordinal, $handler, $invocation['executable'], $invocation['operation'], $route['classification'], $route['rationale']);
        }
    }

    return $records;
}

/**
 * @param list<array{path: string, line: int, logical: string, scalar: string, chain: int, segment: string, executable: string, operation: string, classification: string, rationale: string, evaluator_depth: int, evaluator_trail: string, payload_raw: string, payload_decoded: string}> $records
 * @return list<array{path: string, line: int, logical: string, scalar: string, chain: int, segment: string, executable: string, operation: string, classification: string, rationale: string, evaluator_depth: int, evaluator_trail: string, payload_raw: string, payload_decoded: string}>
 */
function finalizeRouteCandidate(array $records, string $path, int $line, string $logical, string $scalar, int $chain, string $segment, string $sourceKind, string $rationale): array
{
    if ($records !== [] || str_starts_with(trim($segment), '#!') || str_starts_with(trim($segment), '```') || ! routeAuditMarker($segment)) {
        return $records;
    }

    return [shellRouteRecord($path, $line, $logical, $scalar, $chain, $segment, 'unclassified-'.$sourceKind, 'unknown', 'unclassified', $rationale)];
}

/**
 * @param list<array{value: string, raw: string, fragments: list<array{quote: string, text: string, escaped: bool}>, dynamic: bool}> $details
 * @return array{program: string, raw: string, dynamic: bool, invalid: bool}|null
 */
function inlinePhpProgram(array $details): ?array
{
    $cursor = 0;
    $count = count($details);

    while ($cursor < $count) {
        $value = $details[$cursor]['value'];

        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*=/', $value) === 1) {
            ++$cursor;

            continue;
        }

        if ($value === 'env') {
            ++$cursor;

            while ($cursor < $count && (preg_match('/^[A-Za-z_][A-Za-z0-9_]*=/', $details[$cursor]['value']) === 1 || in_array($details[$cursor]['value'], ['-i', '--ignore-environment'], true))) {
                ++$cursor;
            }

            continue;
        }

        if ($value === 'command') {
            ++$cursor;

            while ($cursor < $count && in_array($details[$cursor]['value'], ['-p', '--'], true)) {
                ++$cursor;
            }

            continue;
        }

        if ($value === 'sudo') {
            ++$cursor;

            while ($cursor < $count && in_array($details[$cursor]['value'], ['-n', '--non-interactive', '-E', '--preserve-env', '-H', '--set-home'], true)) {
                ++$cursor;
            }

            continue;
        }

        if ($value === 'timeout') {
            ++$cursor;

            while ($cursor < $count && str_starts_with($details[$cursor]['value'], '-')) {
                ++$cursor;
            }

            if ($cursor < $count && preg_match('/^[0-9]+(?:\.[0-9]+)?[smhd]?$/', $details[$cursor]['value']) === 1) {
                ++$cursor;

                continue;
            }

            return ['program' => '', 'raw' => implode(' ', array_column($details, 'raw')), 'dynamic' => true, 'invalid' => true];
        }

        if ($value === 'exec' || $value === 'time') {
            ++$cursor;

            while ($cursor < $count && in_array($details[$cursor]['value'], $value === 'exec' ? ['-c', '-l'] : ['-p', '--portability'], true)) {
                ++$cursor;
            }

            continue;
        }

        if ($value === 'nice') {
            ++$cursor;

            if (in_array($details[$cursor]['value'] ?? null, ['-n', '--adjustment'], true)) {
                $cursor += 2;
            } elseif (preg_match('/^-n[0-9+-]+$/', (string) ($details[$cursor]['value'] ?? '')) === 1) {
                ++$cursor;
            }

            continue;
        }

        if ($value === 'stdbuf') {
            ++$cursor;

            while ($cursor < $count && in_array($details[$cursor]['value'], ['-i', '-o', '-e'], true)) {
                $cursor += 2;
            }

            continue;
        }

        break;
    }

    if (preg_match('/^php(?:[0-9.]+)?$/', strtolower(basename((string) ($details[$cursor]['value'] ?? '')))) !== 1) {
        return null;
    }

    ++$cursor;

    while ($cursor < $count) {
        $option = $details[$cursor]['value'];

        if (in_array($option, ['-n'], true)) {
            ++$cursor;

            continue;
        }

        if (in_array($option, ['-d', '-c', '-z'], true)) {
            $cursor += 2;

            continue;
        }

        if (preg_match('/^-d.+$/', $option) === 1) {
            ++$cursor;

            continue;
        }

        break;
    }

    if (! in_array($details[$cursor]['value'] ?? null, ['-r', '--run'], true)) {
        $hasRunBoundary = (bool) array_filter(array_slice(array_column($details, 'value'), $cursor), static fn (string $value): bool => in_array($value, ['-r', '--run'], true));

        if (! $hasRunBoundary) {
            return null;
        }

        return routeAuditMarker(implode(' ', array_column($details, 'value')))
            ? ['program' => '', 'raw' => implode(' ', array_column($details, 'raw')), 'dynamic' => true, 'invalid' => true]
            : null;
    }

    if (! isset($details[$cursor + 1]) || $cursor + 2 !== $count) {
        return routeAuditMarker(implode(' ', array_column($details, 'value')))
            ? ['program' => '', 'raw' => implode(' ', array_column($details, 'raw')), 'dynamic' => true, 'invalid' => true]
            : null;
    }

    $program = $details[$cursor + 1];

    return ['program' => $program['value'], 'raw' => $program['raw'], 'dynamic' => $program['dynamic'], 'invalid' => false];
}

/**
 * Recover one literal Composer/guard invocation from a bounded control body.
 * This is deliberately lexical: expansions and quoted text are not evaluated.
 *
 * @return list<string>|null
 */
function embeddedComposerInvocationTokens(string $segment): ?array
{
    if (preg_match('~(?:^|[;(){}[:space:]])(?:(php)[[:space:]]+)?(bin/composer-policy|composer(?:\.phar)?)[[:space:]]+(validate|audit|install|update|require|remove|create-project|self-update|config|global|i|u|upgrade)\b~i', $segment, $match) !== 1) {
        return null;
    }

    return $match[1] === ''
        ? [$match[2], $match[3]]
        : [$match[1], $match[2], $match[3]];
}

/**
 * @param array{visited: int, compounds: int} $state
 * @param list<string> $trail
 * @return list<array{path: string, line: int, logical: string, scalar: string, chain: int, segment: string, executable: string, operation: string, classification: string, rationale: string, evaluator_depth: int, evaluator_trail: string, payload_raw: string, payload_decoded: string}>
 */
function classifyInlinePhpRouteSegment(string $path, int $line, string $logical, string $scalar, int $chain, string $segment, array $program, array &$state, int $depth, array $trail): array
{
    $nextTrail = [...$trail, 'php -r'];
    $programBearing = containsComposerOrEvaluatorText($program['program']) || containsComposerOrEvaluatorText($program['raw']);

    if ($program['invalid'] || $program['dynamic']) {
        return $programBearing
            ? [shellRouteRecord($path, $line, $logical, $scalar, $chain, $segment, 'unclassified-php', 'unknown', 'unclassified', 'php -r program must be one literal argument', $depth, $nextTrail, $program['raw'], $program['program'])]
            : [];
    }

    if (strlen($program['program']) > MAX_ROUTE_LOGICAL_LINE_LENGTH) {
        return $programBearing
            ? [shellRouteRecord($path, $line, $logical, $scalar, $chain, $segment, 'unclassified-php', 'unknown', 'unclassified', 'php -r program length exceeds the configured bound', $depth, $nextTrail, $program['raw'], $program['program'])]
            : [];
    }

    $contents = str_starts_with(ltrim($program['program']), '<?php') ? $program['program'] : '<?php '.$program['program'];
    $launches = phpProcessLaunches($contents);

    if (count($launches) > MAX_ROUTE_INLINE_PHP_LAUNCHES) {
        return $programBearing
            ? [shellRouteRecord($path, $line, $logical, $scalar, $chain, $segment, 'unclassified-php', 'unknown', 'unclassified', 'php -r literal process-launch count exceeds the configured bound', $depth, $nextTrail, $program['raw'], $program['program'])]
            : [];
    }

    if ($launches === []) {
        return $programBearing
            ? [shellRouteRecord($path, $line, $logical, $scalar, $chain, $segment, 'unclassified-php', 'unknown', 'unclassified', 'Composer-bearing php -r program has no bounded process launch', $depth, $nextTrail, $program['raw'], $program['program'])]
            : [];
    }

    $records = [];

    foreach ($launches as $launch) {
        if ($launch['tokens'] === null) {
            if ($launch['composer_bearing'] || $programBearing) {
                $records[] = shellRouteRecord($path, $line, $logical, $scalar, $chain, $segment, 'unclassified-php', 'unknown', 'unclassified', 'Composer-bearing php -r process launch is dynamic or outside the bounded literal grammar', $depth, $nextTrail, $program['raw'], $program['program']);
            }

            continue;
        }

        if ($launch['command'] !== null) {
            try {
                $nestedSegments = commandChainSegments($launch['command']);
            } catch (RuntimeException $exception) {
                $records[] = shellRouteRecord($path, $line, $logical, $scalar, $chain, $segment, 'unclassified-php', 'unknown', 'unclassified', $exception->getMessage(), $depth, $nextTrail, $program['raw'], $program['program']);

                continue;
            }

            foreach ($nestedSegments as $nestedSegment) {
                $records = [...$records, ...classifyShellRouteSegment($path, $line, $logical, $scalar, $chain, $nestedSegment, $state, $depth + 1, $nextTrail)];
            }

            continue;
        }

        $invocation = parseInvocation($launch['tokens']);

        if ($invocation === null) {
            if ($launch['composer_bearing'] || $programBearing) {
                $records[] = shellRouteRecord($path, $line, $logical, $scalar, $chain, $segment, 'unclassified-php', 'unknown', 'unclassified', 'Composer-bearing php -r process launch could not be classified', $depth, $nextTrail, $program['raw'], $program['program']);
            }

            continue;
        }

        $route = classifyRoute($path, $invocation['executable'], $invocation['contract_allowed'] ?? false);
        $records[] = shellRouteRecord($path, $line, $logical, $scalar, $chain, $segment, $invocation['executable'], $invocation['operation'], $route['classification'], $route['rationale'], $depth, $nextTrail, $program['raw'], $program['program']);
    }

    if ($programBearing && $records === []) {
        $records[] = shellRouteRecord($path, $line, $logical, $scalar, $chain, $segment, 'unclassified-php', 'unknown', 'unclassified', 'marker-bearing php -r program produced no bounded process-launch record', $depth, $nextTrail, $program['raw'], $program['program']);
    }

    return $records;
}

/**
 * @param array{visited: int, compounds: int} $state
 * @param list<string> $trail
 * @return list<array{path: string, line: int, logical: string, scalar: string, chain: int, segment: string, executable: string, operation: string, classification: string, rationale: string, evaluator_depth: int, evaluator_trail: string, payload_raw: string, payload_decoded: string}>
 */
function classifyShellRouteSegment(string $path, int $line, string $logical, string $scalar, int $chain, string $segment, array &$state, int $depth = 0, array $trail = []): array
{
    if (str_starts_with(trim($segment), '#!')) {
        return [];
    }

    try {
        $inlineProgram = inlinePhpProgram(commandTokenDetails($segment));
    } catch (RuntimeException $exception) {
        $inlineProgram = null;
    }

    if ($inlineProgram !== null) {
        return classifyInlinePhpRouteSegment($path, $line, $logical, $scalar, $chain, $segment, $inlineProgram, $state, $depth, $trail);
    }

    $compound = compoundShellBody($segment);

    if ($compound !== null) {
        if ($compound['kind'] !== 'compound') {
            if (containsComposerOrEvaluatorText($segment)) {
                return [shellRouteRecord($path, $line, $logical, $scalar, $chain, $segment, 'unclassified-compound', 'unknown', 'unclassified', $compound['rationale'], $depth, [...$trail, 'compound'])];
            }

            return [];
        }

        $nextTrail = [...$trail, $compound['rationale']];

        if ($depth >= MAX_ROUTE_COMPOUND_DEPTH) {
            return [shellRouteRecord($path, $line, $logical, $scalar, $chain, $segment, 'unclassified-compound', 'unknown', 'unclassified', 'Route audit compound nesting depth exceeds the configured bound', $depth, $nextTrail)];
        }

        ++$state['compounds'];

        if ($state['compounds'] > MAX_ROUTE_COMPOUND_BODIES) {
            return [shellRouteRecord($path, $line, $logical, $scalar, $chain, $segment, 'unclassified-compound', 'unknown', 'unclassified', 'Route audit compound body count exceeds the configured bound', $depth, $nextTrail)];
        }

        try {
            $nestedSegments = commandChainSegments($compound['body']);
        } catch (RuntimeException $exception) {
            return [shellRouteRecord($path, $line, $logical, $scalar, $chain, $segment, 'unclassified-compound', 'unknown', 'unclassified', $exception->getMessage(), $depth, $nextTrail)];
        }

        if ($nestedSegments === []) {
            return [shellRouteRecord($path, $line, $logical, $scalar, $chain, $segment, 'unclassified-compound', 'unknown', 'unclassified', 'Compound shell body has no bounded command segment', $depth, $nextTrail)];
        }

        $records = [];

        foreach ($nestedSegments as $nestedSegment) {
            $records = [...$records, ...classifyShellRouteSegment($path, $line, $logical, $scalar, $chain, $nestedSegment, $state, $depth + 1, $nextTrail)];
        }

        return $records;
    }

    try {
        $details = commandTokenDetails($segment);
        $invocation = parseInvocation(array_column($details, 'value'));
    } catch (RuntimeException $exception) {
        if (preg_match('~(?:^|[[:space:]])(?:bash|sh|zsh|eval)(?:[[:space:]]|$)~', $segment) === 1 || containsComposerExecutableText($segment)) {
            return [shellRouteRecord($path, $line, $logical, $scalar, $chain, $segment, 'unclassified-shell', 'unknown', 'unclassified', $exception->getMessage(), $depth, $trail)];
        }

        return [];
    }

    if ($invocation === null) {
        $embeddedTokens = embeddedComposerInvocationTokens($segment);

        if ($embeddedTokens !== null) {
            $embeddedInvocation = parseInvocation($embeddedTokens);

            if ($embeddedInvocation !== null) {
                $route = classifyRoute($path, $embeddedInvocation['executable'], $embeddedInvocation['contract_allowed'] ?? false);

                return [shellRouteRecord($path, $line, $logical, $scalar, $chain, $segment, $embeddedInvocation['executable'], $embeddedInvocation['operation'], $route['classification'], $route['rationale'], $depth, [...$trail, 'bounded control body'])];
            }
        }

        if (isSupportedProductionRoute($path) && containsComposerExecutableText($segment)) {
            return [shellRouteRecord($path, $line, $logical, $scalar, $chain, $segment, 'unclassified-shell', 'unknown', 'unclassified', 'Composer-bearing shell segment could not be classified by the bounded grammar', $depth, $trail)];
        }

        return [];
    }

    if ($invocation['executable'] !== 'evaluator') {
        $route = classifyRoute($path, $invocation['executable'], $invocation['contract_allowed'] ?? false);

        return [shellRouteRecord($path, $line, $logical, $scalar, $chain, $segment, $invocation['executable'], $invocation['operation'], $route['classification'], $route['rationale'], $depth, $trail)];
    }

    $evaluator = $invocation['evaluator'];
    $arguments = $invocation['arguments'];
    $payloadIndex = $evaluator === 'eval' ? 0 : 1;
    $expectedArguments = $evaluator === 'eval' ? 1 : 2;
    $trailEntry = $evaluator === 'eval' ? 'eval' : $evaluator.' -c';
    $nextTrail = [...$trail, $trailEntry];

    if (count($arguments) !== $expectedArguments || ($evaluator !== 'eval' && ($arguments[0] ?? null) !== '-c')) {
        return [shellRouteRecord($path, $line, $logical, $scalar, $chain, $segment, 'unclassified-evaluator', $evaluator, 'unclassified', "{$trailEntry} is outside the bounded evaluator grammar", $depth, $nextTrail)];
    }

    $evaluatorDetailIndex = null;

    foreach ($details as $index => $detail) {
        if ($detail['value'] === $evaluator) {
            $evaluatorDetailIndex = $index;

            break;
        }
    }

    $payload = $evaluatorDetailIndex === null ? null : ($details[$evaluatorDetailIndex + 1 + $payloadIndex] ?? null);

    if ($payload === null) {
        return [shellRouteRecord($path, $line, $logical, $scalar, $chain, $segment, 'unclassified-evaluator', $evaluator, 'unclassified', "{$trailEntry} payload provenance is unavailable", $depth, $nextTrail)];
    }

    if ($payload['dynamic']) {
        return [shellRouteRecord($path, $line, $logical, $scalar, $chain, $segment, 'unclassified-evaluator', $evaluator, 'unclassified', "{$trailEntry} payload is dynamic", $depth, $nextTrail, $payload['raw'], $payload['value'])];
    }

    $payloadQuoteModes = array_values(array_unique(array_column($payload['fragments'], 'quote')));

    if (count($payloadQuoteModes) > 1) {
        return [shellRouteRecord($path, $line, $logical, $scalar, $chain, $segment, 'unclassified-evaluator', $evaluator, 'unclassified', "{$trailEntry} payload is concatenated", $depth, $nextTrail, $payload['raw'], $payload['value'])];
    }

    if (strlen($payload['value']) > MAX_ROUTE_LOGICAL_LINE_LENGTH) {
        return [shellRouteRecord($path, $line, $logical, $scalar, $chain, $segment, 'unclassified-evaluator', $evaluator, 'unclassified', "{$trailEntry} payload length exceeds the configured bound", $depth, $nextTrail, $payload['raw'], $payload['value'])];
    }

    if ($depth >= MAX_ROUTE_EVALUATOR_DEPTH) {
        return [shellRouteRecord($path, $line, $logical, $scalar, $chain, $segment, 'unclassified-evaluator', $evaluator, 'unclassified', 'Route audit evaluator depth exceeds the configured bound', $depth, $nextTrail, $payload['raw'], $payload['value'])];
    }

    ++$state['visited'];

    if ($state['visited'] > MAX_ROUTE_EVALUATOR_PAYLOADS) {
        return [shellRouteRecord($path, $line, $logical, $scalar, $chain, $segment, 'unclassified-evaluator', $evaluator, 'unclassified', 'Route audit evaluator payload count exceeds the configured bound', $depth, $nextTrail, $payload['raw'], $payload['value'])];
    }

    try {
        $nestedSegments = commandChainSegments($payload['value']);
    } catch (RuntimeException $exception) {
        return [shellRouteRecord($path, $line, $logical, $scalar, $chain, $segment, 'unclassified-evaluator', $evaluator, 'unclassified', $exception->getMessage(), $depth, $nextTrail, $payload['raw'], $payload['value'])];
    }

    if ($nestedSegments === []) {
        return [shellRouteRecord($path, $line, $logical, $scalar, $chain, $segment, 'unclassified-evaluator', $evaluator, 'unclassified', "{$trailEntry} payload has no bounded command segment", $depth, $nextTrail, $payload['raw'], $payload['value'])];
    }

    $records = [];

    foreach ($nestedSegments as $nestedSegment) {
        $records = [...$records, ...classifyShellRouteSegment($path, $line, $logical, $scalar, $chain, $nestedSegment, $state, $depth + 1, $nextTrail)];
    }

    return $records;
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
        if (str_starts_with($path, '.planning/') || str_starts_with($path, 'tests/')) {
            continue;
        }

        $contents = file_get_contents($repositoryRoot.'/'.$path);
        assertTrue($contents !== false, "Could not inspect tracked file {$path} for the route audit.");

        if ($path === 'composer.json') {
            $records = [...$records, ...composerScriptAuditRecords($contents)];

            continue;
        }

        $sourceKind = routeSourceKind($path, $contents);

        if ($sourceKind === 'non-source') {
            continue;
        }

        if ($sourceKind === 'unknown-source') {
            if (routeAuditMarker($contents)) {
                $line = markerSourceLine($contents);
                $sourceLine = explode("\n", $contents)[$line - 1] ?? $contents;
                $records[] = shellRouteRecord($path, $line, $sourceLine, 'unknown-source', 0, $sourceLine, 'unclassified-unknown-source', 'unknown', 'unclassified', 'marker-bearing tracked source has no approved provenance extractor');
            }

            continue;
        }

        $trimmedContents = ltrim($contents);
        $isPhp = str_ends_with(strtolower($path), '.php')
            || str_starts_with($trimmedContents, '<?php')
            || str_starts_with($trimmedContents, '#!/usr/bin/env php');

        if (str_contains($contents, "\0") && ! routeAuditMarker($contents)) {
            continue;
        }

        if ($isPhp) {
            if (isTrustedPhpAuditSource($path)) {
                continue;
            }

            $program = phpCommandShapedProgram($contents);
            $programBearing = $program !== null;
            $phpRecordStart = count($records);

            $phpLaunches = phpProcessLaunches($contents);

            foreach ($phpLaunches as $chainIndex => $launch) {
                if ($launch['tokens'] === null) {
                    if (! $launch['composer_bearing'] && ! $programBearing) {
                        continue;
                    }

                    $records[] = [
                        'path' => $path,
                        'line' => $launch['line'],
                        'logical' => $launch['expression'],
                        'scalar' => 'php-expression',
                        'chain' => $chainIndex,
                        'segment' => $launch['expression'],
                        'executable' => 'unclassified-php',
                        'operation' => 'unknown',
                        'classification' => 'unclassified',
                        'rationale' => 'Composer-bearing PHP process launch is dynamic or outside the bounded literal grammar',
                    ];

                    continue;
                }

                $invocation = parseInvocation($launch['tokens']);

                if ($invocation === null) {
                    if ($launch['composer_bearing'] || $programBearing) {
                        $records[] = [
                            'path' => $path,
                            'line' => $launch['line'],
                            'logical' => $launch['expression'],
                            'scalar' => 'php-expression',
                            'chain' => $chainIndex,
                            'segment' => $launch['expression'],
                            'executable' => 'unclassified-php',
                            'operation' => 'unknown',
                            'classification' => 'unclassified',
                            'rationale' => 'Composer-bearing PHP process launch could not be classified',
                        ];
                    }

                    continue;
                }

                $route = classifyRoute($path, $invocation['executable'], $invocation['contract_allowed'] ?? false);
                $records[] = [
                    'path' => $path,
                    'line' => $launch['line'],
                    'logical' => $launch['expression'],
                    'scalar' => 'php-expression',
                    'chain' => $chainIndex,
                    'segment' => $launch['expression'],
                    'executable' => $invocation['executable'],
                    'operation' => $invocation['operation'],
                    'classification' => $route['classification'],
                    'rationale' => $route['rationale'],
                ];
            }

            if ($program !== null && count($records) === $phpRecordStart) {
                $records[] = shellRouteRecord($path, $program['line'], $program['source'], 'php-program', 0, $program['source'], 'unclassified-php', 'unknown', 'unclassified', 'command-shaped PHP program produced no bounded process-launch record');
            }

            continue;
        }

        $isWorkflow = $sourceKind === 'workflow';
        $commands = $isWorkflow
            ? workflowCommandScalars($contents)
            : ($sourceKind === 'docker'
                ? dockerCommandScalars($contents)
                : array_map(
                static fn (array $logicalLine): array => [
                    'line' => $logicalLine['line'],
                    'logical' => $logicalLine['text'],
                    'text' => unquoteYamlCommandScalar($logicalLine['text']),
                    'scalar' => 'shell-line',
                    'parse_error' => null,
                ],
                normalizedLogicalLines($contents),
            ));

        foreach ($commands as $command) {
            $commandRecordStart = count($records);
            if ($command['parse_error'] !== null) {
                if (routeAuditMarker($command['text']) || routeAuditMarker($command['logical'])) {
                    $records[] = [
                        'path' => $path,
                        'line' => $command['line'],
                        'logical' => $command['logical'],
                        'scalar' => $command['scalar'],
                        'chain' => 0,
                        'segment' => $command['text'],
                        'executable' => 'unclassified-workflow',
                        'operation' => 'unknown',
                        'classification' => 'unclassified',
                        'rationale' => $command['parse_error'],
                    ];
                }

                continue;
            }

            try {
                $segments = commandChainSegments($command['text'], true);
            } catch (RuntimeException $exception) {
                try {
                    $inlineProgram = inlinePhpProgram(commandTokenDetails($command['text']));
                } catch (RuntimeException) {
                    $inlineProgram = null;
                }

                if ($inlineProgram !== null) {
                    $inlineState = ['visited' => 0, 'compounds' => 0];
                    $records = [...$records, ...classifyInlinePhpRouteSegment(
                        $path,
                        $command['line'],
                        $command['logical'],
                        $command['scalar'],
                        0,
                        $command['text'],
                        $inlineProgram,
                        $inlineState,
                        0,
                        [],
                    )];

                    continue;
                }

                if (routeAuditMarker($command['text'])) {
                    $records[] = [
                        'path' => $path,
                        'line' => $command['line'],
                        'logical' => $command['logical'],
                        'scalar' => $command['scalar'],
                        'chain' => 0,
                        'segment' => $command['text'],
                        'executable' => 'unclassified-shell',
                        'operation' => 'unknown',
                        'classification' => 'unclassified',
                        'rationale' => $exception->getMessage(),
                    ];
                }

                continue;
            }

            $evaluatorState = ['visited' => 0, 'compounds' => 0];

            foreach ($segments as $chainIndex => $segment) {
                $records = [...$records, ...classifyShellRouteSegment(
                    $path,
                    $command['line'],
                    $command['logical'],
                    $command['scalar'],
                    $chainIndex,
                    $segment,
                    $evaluatorState,
                )];
            }

            $candidateRecords = array_slice($records, $commandRecordStart);
            $records = [...array_slice($records, 0, $commandRecordStart), ...finalizeRouteCandidate(
                $candidateRecords,
                $path,
                $command['line'],
                $command['logical'],
                $command['scalar'],
                0,
                $command['text'],
                $sourceKind,
                'marker-bearing source candidate could not be reduced by the bounded grammar',
            )];
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
            "ROUTE path=%s line=%d scalar=%s chain=%d segment=%s executable=%s operation=%s classification=%s evaluator_depth=%d evaluator_trail=%s payload_raw=%s payload_decoded=%s logical=%s rationale=%s\n",
            $record['path'],
            $record['line'],
            $record['scalar'] ?? 'legacy',
            $record['chain'],
            $record['segment'],
            $record['executable'],
            $record['operation'],
            $record['classification'],
            $record['evaluator_depth'] ?? 0,
            $record['evaluator_trail'] ?? '',
            $record['payload_raw'] ?? '',
            $record['payload_decoded'] ?? '',
            $record['logical'],
            $record['rationale'],
        ));
    }
}

function initializeFixtureRepository(string $sourceRoot, string $contents): string
{
    return initializeFixtureRepositoryFiles($sourceRoot, [
        '.github/workflows/routes.yml' => $contents,
    ]);
}

/**
 * @param array<string, string> $files
 */
function initializeFixtureRepositoryFiles(string $sourceRoot, array $files): string
{
    $fixtureRoot = sys_get_temp_dir().'/sendportal-composer-route-'.bin2hex(random_bytes(8));
    writeFile($fixtureRoot.'/bin/composer-policy', (string) file_get_contents($sourceRoot.'/bin/composer-policy'));
    writeFile($fixtureRoot.'/tools/composer/ComposerPolicyCommandContract.php', (string) file_get_contents($sourceRoot.'/tools/composer/ComposerPolicyCommandContract.php'));

    foreach ($files as $path => $contents) {
        writeFile($fixtureRoot.'/'.$path, $contents);
    }

    $result = runCommand(['git', 'init', '--quiet'], getenv(), $fixtureRoot);
    assertTrue($result['status'] === 0, "Could not initialize the route-audit fixture repository: {$result['stderr']}");
    $result = runCommand(['git', 'add', 'bin/composer-policy', 'tools/composer/ComposerPolicyCommandContract.php', ...array_keys($files)], getenv(), $fixtureRoot);
    assertTrue($result['status'] === 0, "Could not stage the route-audit fixture repository: {$result['stderr']}");

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

function auditFunctionSource(string $function): string
{
    $reflection = new ReflectionFunction($function);
    $lines = file(__FILE__, FILE_IGNORE_NEW_LINES);
    assertTrue($lines !== false, 'Could not read the audit harness source for self-inspection.');

    return implode("\n", array_slice((array) $lines, $reflection->getStartLine() - 1, $reflection->getEndLine() - $reflection->getStartLine() + 1));
}

function phpFinalizationSource(): string
{
    $collected = [];
    $depth = 0;

    foreach (explode("\n", auditFunctionSource('auditRoutes')) as $line) {
        if ($collected === [] && ! str_contains($line, 'if ($isPhp) {')) {
            continue;
        }

        $collected[] = $line;
        $depth += substr_count($line, '{') - substr_count($line, '}');

        if ($depth === 0) {
            break;
        }
    }

    assertTrue($collected !== [] && $depth === 0, 'The tracked-PHP finalization block must be locatable for self-inspection.');

    return implode("\n", $collected);
}

$repositoryRoot = dirname(__DIR__, 2);
$guard = $repositoryRoot.'/bin/composer-policy';
$guardContents = file_get_contents($guard);
$requestedGroup = null;

foreach (array_slice($argv, 1) as $argument) {
    if ($argument === '--route-audit') {
        continue;
    }

    if (str_starts_with($argument, '--group=')) {
        $requestedGroup = substr($argument, strlen('--group='));

        continue;
    }

    fail("Unknown test argument {$argument}.");
}

if ($requestedGroup !== null && ! in_array($requestedGroup, ['effective-policy-command-contract', 'route-audit-fail-closed', 'process-io'], true)) {
    fail("Unknown Composer policy test group {$requestedGroup}.");
}

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
    $result = runCommand([PHP_BINARY, $missingRoot.'/bin/composer-policy', 'install'], $missingEnvironment, $missingRoot);
    assertTrue($result['status'] !== 0 && str_contains($result['stderr'], 'Composer distribution unavailable'), 'A missing repository distribution must fail closed.');
    assertNoComposerRan($missingTrustedMarker, $missingShadowMarker, 'Missing distribution');

    $successRoot = createTemporaryRepository($repositoryRoot);
    $temporaryRoots[] = $successRoot;
    $successTrustedMarker = $successRoot.'/trusted.marker';
    $successShadowMarker = $successRoot.'/shadow.marker';
    $successEnvironment = assertionEnvironment($successRoot, $successShadowMarker, $successTrustedMarker);
    $successCallerRoot = createTemporaryDirectory('sendportal-composer-caller');
    $temporaryRoots[] = $successCallerRoot;
    writeSyntheticDistribution($successRoot);
    $result = runCommand([PHP_BINARY, $successRoot.'/bin/composer-policy', 'update', '--dry-run', '--prefer-dist', '-vvv'], $successEnvironment, $successCallerRoot);
    assertTrue($result['status'] === 0, "A matching repository distribution must be delegated to: {$result['stderr']}");
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

    $streamingRoot = createTemporaryRepository($repositoryRoot);
    $temporaryRoots[] = $streamingRoot;
    $streamingTrustedMarker = $streamingRoot.'/trusted.marker';
    $streamingShadowMarker = $streamingRoot.'/shadow.marker';
    $streamingEnvironment = assertionEnvironment($streamingRoot, $streamingShadowMarker, $streamingTrustedMarker);
    $streamingReleaseFile = $streamingRoot.'/release.marker';
    $streamingEnvironment['SYNTHETIC_IO_MODE'] = 'streaming';
    $streamingEnvironment['SYNTHETIC_RELEASE_FILE'] = $streamingReleaseFile;
    writeSyntheticDistribution($streamingRoot);
    $streamingResult = runStreamingHandshake(
        [PHP_BINARY, $streamingRoot.'/bin/composer-policy', 'update', '--dry-run'],
        $streamingEnvironment,
        $streamingRoot,
        $streamingReleaseFile,
    );
    assertTrue($streamingResult['observed_before_exit'], 'Delegated stdout must be observable before the child exits.');
    assertTrue($streamingResult['status'] === 37, 'Delegation must preserve exact child status 37.');
    assertTrue($streamingResult['stdout'] === "delegated-stdout-before-exit\n", 'Delegated stdout must remain on stdout only.');
    assertTrue($streamingResult['stderr'] === "delegated-stderr-after-release\n", 'Delegated stderr must remain on stderr only.');

    $largeRoot = createTemporaryRepository($repositoryRoot);
    $temporaryRoots[] = $largeRoot;
    $largeTrustedMarker = $largeRoot.'/trusted.marker';
    $largeShadowMarker = $largeRoot.'/shadow.marker';
    $largeEnvironment = assertionEnvironment($largeRoot, $largeShadowMarker, $largeTrustedMarker);
    $largeEnvironment['SYNTHETIC_IO_MODE'] = 'large-output';
    writeSyntheticDistribution($largeRoot);
    $largeResult = runCommand([PHP_BINARY, $largeRoot.'/bin/composer-policy', 'update', '--dry-run'], $largeEnvironment, $largeRoot);
    $expectedStdout = str_repeat('O', 1048576);
    $expectedStderr = str_repeat('E', 1048576);
    assertTrue($largeResult['status'] === 37, 'Large delegated output must preserve exact child status 37.');
    assertTrue(strlen($largeResult['stdout']) === 1048576 && hash('sha256', $largeResult['stdout']) === hash('sha256', $expectedStdout), 'Large delegated stdout must retain its exact byte count and digest.');
    assertTrue(strlen($largeResult['stderr']) === 1048576 && hash('sha256', $largeResult['stderr']) === hash('sha256', $expectedStderr), 'Large delegated stderr must retain its exact byte count and digest.');

    foreach (['preflight-overflow' => 'channel cap overflow', 'preflight-timeout' => 'timeout'] as $mode => $scenario) {
        $preflightRoot = createTemporaryRepository($repositoryRoot);
        $temporaryRoots[] = $preflightRoot;
        $preflightTrustedMarker = $preflightRoot.'/trusted.marker';
        $preflightShadowMarker = $preflightRoot.'/shadow.marker';
        $preflightEnvironment = assertionEnvironment($preflightRoot, $preflightShadowMarker, $preflightTrustedMarker);
        $preflightEnvironment['SYNTHETIC_IO_MODE'] = $mode;
        writeSyntheticDistribution($preflightRoot);
        $preflightResult = runCommand([PHP_BINARY, $preflightRoot.'/bin/composer-policy', 'validate'], $preflightEnvironment, $preflightRoot);
        assertTrue($preflightResult['status'] !== 0 && str_contains($preflightResult['stderr'], 'Composer preflight failed.'), "A preflight {$scenario} must fail with the fixed diagnostic.");
        assertOnlyVersionProbeRan($preflightTrustedMarker, $preflightShadowMarker, "Preflight {$scenario}");
    }

    foreach (['stderr-first', 'stdout-first'] as $order) {
        $helperProgram = <<<'PHP'
$chunk = str_repeat(getenv('HELPER_ORDER') === 'stderr-first' ? 'E' : 'O', 1048576);
$other = str_repeat(getenv('HELPER_ORDER') === 'stderr-first' ? 'O' : 'E', 1048576);
if (getenv('HELPER_ORDER') === 'stderr-first') {
    fwrite(STDERR, $chunk);
    fwrite(STDOUT, $other);
} else {
    fwrite(STDOUT, $chunk);
    fwrite(STDERR, $other);
}
PHP;
        $helperEnvironment = getenv();
        $helperEnvironment['HELPER_ORDER'] = $order;
        $helperResult = runCommand([PHP_BINARY, '-r', $helperProgram], $helperEnvironment, $repositoryRoot);
        assertTrue($helperResult['status'] === 0, "The {$order} helper fixture must complete without deadlock.");
        assertTrue(strlen($helperResult['stdout']) === 1048576 && hash('sha256', $helperResult['stdout']) === hash('sha256', str_repeat('O', 1048576)), "The {$order} helper must preserve stdout.");
        assertTrue(strlen($helperResult['stderr']) === 1048576 && hash('sha256', $helperResult['stderr']) === hash('sha256', str_repeat('E', 1048576)), "The {$order} helper must preserve stderr.");
    }

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
        $result = runCommand([PHP_BINARY, $root.'/bin/composer-policy', 'install'], $environment, $root);
        assertTrue($result['status'] !== 0, "The {$scenario} distribution must fail closed.");
        assertNoComposerRan($trustedMarker, $shadowMarker, "{$scenario} distribution");
    }

    $wrongVersionRoot = createTemporaryRepository($repositoryRoot);
    $temporaryRoots[] = $wrongVersionRoot;
    $wrongVersionTrustedMarker = $wrongVersionRoot.'/trusted.marker';
    $wrongVersionShadowMarker = $wrongVersionRoot.'/shadow.marker';
    $wrongVersionEnvironment = assertionEnvironment($wrongVersionRoot, $wrongVersionShadowMarker, $wrongVersionTrustedMarker);
    writeSyntheticDistribution($wrongVersionRoot, '2.10.3');
    $result = runCommand([PHP_BINARY, $wrongVersionRoot.'/bin/composer-policy', 'install'], $wrongVersionEnvironment, $wrongVersionRoot);
    assertTrue($result['status'] !== 0, 'A distribution reporting another Composer version must fail closed.');
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
        $result = runCommand([PHP_BINARY, $overrideRoot.'/bin/composer-policy', 'install'], $environment, $overrideRoot);
        assertTrue($result['status'] !== 0 && str_contains($result['stderr'], 'Composer override rejected'), "{$name} must be rejected before Composer starts.");
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
        $result = runCommand([PHP_BINARY, $overrideRoot.'/bin/composer-policy', ...$arguments], $overrideEnvironment, $successCallerRoot);
        assertTrue($result['status'] !== 0 && str_contains($result['stderr'], 'Composer override rejected'), 'Every Composer working-directory selector must be rejected.');
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
        $result = runCommand([PHP_BINARY, $overrideRoot.'/bin/composer-policy', ...$arguments], $overrideEnvironment, $successCallerRoot);
        assertTrue($result['status'] !== 0 && str_contains($result['stderr'], 'Composer override rejected'), 'A policy-free external manifest must not be installable through the guard.');
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
    $result = runCommand([PHP_BINARY, $overrideRoot.'/bin/composer-policy', 'validate'], $hostileEnvironment, $overrideRoot);
    assertTrue($result['status'] === 0, "The isolated guarded child must still delegate validate: {$result['stderr']}");
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
        $result = runCommand([PHP_BINARY, $overrideRoot.'/bin/composer-policy', ...$arguments], $overrideEnvironment, $overrideRoot);
        assertTrue($result['status'] === 0, 'Canonical guarded command must delegate: '.implode(' ', $arguments)." {$result['stderr']}");
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
        $result = runCommand([PHP_BINARY, $overrideRoot.'/bin/composer-policy', ...$arguments], $overrideEnvironment, $overrideRoot);
        assertTrue($result['status'] !== 0 && str_contains($result['stderr'], 'Composer command rejected'), 'Unreviewed command must fail at the command contract: '.implode(' ', $arguments));
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
        $result = runCommand([PHP_BINARY, $manifestRoot.'/bin/composer-policy', 'update', '--dry-run'], $environment, $manifestRoot);
        assertTrue($result['status'] !== 0 && str_contains($result['stderr'], 'Composer manifest policy rejected'), "Manifest mutation {$scenario} must fail before Composer.");
        assertNoComposerRan($trustedMarker, $shadowMarker, "Manifest mutation {$scenario}");
    }

    $racedManifestRoot = createTemporaryRepository($repositoryRoot);
    $temporaryRoots[] = $racedManifestRoot;
    $racedTrustedMarker = $racedManifestRoot.'/trusted.marker';
    $racedShadowMarker = $racedManifestRoot.'/shadow.marker';
    $racedEnvironment = assertionEnvironment($racedManifestRoot, $racedShadowMarker, $racedTrustedMarker);
    $racedEnvironment['SYNTHETIC_IO_MODE'] = 'mutate-manifest-during-policy-probe';
    writeSyntheticDistribution($racedManifestRoot);
    $racedResult = runCommand([PHP_BINARY, $racedManifestRoot.'/bin/composer-policy', 'update', '--dry-run'], $racedEnvironment, $racedManifestRoot);
    assertTrue($racedResult['status'] !== 0 && str_contains($racedResult['stderr'], 'Composer manifest policy rejected'), 'Manifest policy must be reasserted after probes immediately before resolver delegation.');
    $racedInvocations = array_values(array_filter(explode("\n", (string) file_get_contents($racedTrustedMarker))));
    assertTrue(count($racedInvocations) === 2, 'A manifest changed during probes must prevent the delegated resolver invocation.');

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

    $workflowForms = [
        'folded block scalar' => "jobs:\n  audit:\n    steps:\n      - run: >-\n          composer\n          install\n",
        'literal block scalar' => "jobs:\n  audit:\n    steps:\n      - run: |\n          composer update\n",
        'sequence item run' => "jobs:\n  audit:\n    steps:\n      - run: composer install\n",
        'quoted inline run' => "jobs:\n  audit:\n    steps:\n      - run: \"composer update\"\n",
        'canonical inline run' => "run: composer install\n",
    ];

    foreach ($workflowForms as $form => $workflow) {
        $fixtureRoot = initializeFixtureRepositoryFiles($repositoryRoot, ['.github/workflows/routes.yml' => $workflow]);

        try {
            $records = auditRoutes($fixtureRoot);
            $direct = array_values(array_filter($records, static fn (array $record): bool => $record['executable'] === 'composer'
                && in_array($record['operation'], ['install', 'update'], true)
                && $record['classification'] === 'unsupported'));
            assertTrue(count($direct) === 1, "Workflow {$form} must produce one exact direct Composer failure record.");
            assertTrue(routeAuditFailures($records) !== [], "Workflow {$form} must fail closed.");
        } finally {
            removeDirectory($fixtureRoot);
        }
    }

    $anchoredWorkflowRoot = initializeFixtureRepositoryFiles($repositoryRoot, [
        '.github/workflows/anchored-routes.yml' => "jobs:\n  audit:\n    steps:\n      - run: &guarded php -n -r 'exec(\"php bin/composer-policy install\");'\n      - run: *guarded\n      - run: &direct php -n -r 'system(\"composer install\");'\n      - run: *direct\n",
    ]);

    try {
        $records = auditRoutes($anchoredWorkflowRoot);
        $anchoredGuard = array_values(array_filter($records, static fn (array $record): bool => $record['path'] === '.github/workflows/anchored-routes.yml'
            && $record['line'] === 5
            && $record['executable'] === 'guard'
            && $record['classification'] === 'supported'));
        $aliasedDirect = array_values(array_filter($records, static fn (array $record): bool => $record['path'] === '.github/workflows/anchored-routes.yml'
            && $record['line'] === 7
            && $record['classification'] !== 'supported'));
        assertTrue(count($anchoredGuard) === 1, 'A literal workflow run alias must retain its physical alias line and classify the guarded inline PHP command.');
        assertTrue(count($aliasedDirect) === 1, 'A literal workflow run alias must retain its physical alias line and reject direct Composer.');
        assertTrue(routeAuditFailures($records) !== [], 'Anchored direct workflow PHP must fail the route audit.');
    } finally {
        removeDirectory($anchoredWorkflowRoot);
    }

    $unknownSourceRoot = initializeFixtureRepositoryFiles($repositoryRoot, [
        'infra/dependency-route.txt' => 'composer install',
        '.planning/debug/ignored-route.txt' => 'composer install',
        'tests/Composer/ignored-route.txt' => 'composer install',
    ]);

    try {
        $records = auditRoutes($unknownSourceRoot);
        $unknown = array_values(array_filter($records, static fn (array $record): bool => $record['path'] === 'infra/dependency-route.txt'
            && $record['executable'] === 'unclassified-unknown-source'
            && $record['classification'] === 'unclassified'));
        assertTrue(count($unknown) === 1, 'A marker-bearing tracked source outside an approved provenance kind must produce exactly one source-level unclassified record.');
        assertTrue(! (bool) array_filter($records, static fn (array $record): bool => str_starts_with($record['path'], '.planning/') || str_starts_with($record['path'], 'tests/')), 'Planning and test fixture material must remain absent from production route evidence.');
        assertTrue(routeAuditFailures($records) !== [], 'An unknown marker-bearing source must fail the route audit.');
    } finally {
        removeDirectory($unknownSourceRoot);
    }

    foreach ([
        'exec prefix' => ['exec', 'composer install', 'php bin/composer-policy install'],
        'time prefix' => ['time', 'composer update', 'php bin/composer-policy update'],
        'nice prefix' => ['nice -n 5', 'composer install', 'php bin/composer-policy install'],
        'stdbuf prefix' => ['stdbuf -oL', 'composer update', 'php bin/composer-policy update'],
    ] as $scenario => [$prefix, $direct, $guarded]) {
        $fixtureRoot = initializeFixtureRepositoryFiles($repositoryRoot, [
            '.github/workflows/expanded-shell.yml' => "jobs:\n  audit:\n    steps:\n      - run: {$prefix} {$direct}\n      - run: {$prefix} {$guarded}\n",
        ]);

        try {
            $records = auditRoutes($fixtureRoot);
            assertTrue((bool) array_filter($records, static fn (array $record): bool => $record['classification'] === 'supported' && $record['executable'] === 'guard'), "Fixture {$scenario} must route its guarded literal through ComposerPolicyCommandContract.");
            assertTrue(routeAuditFailures($records) !== [], "Fixture {$scenario} must reject the direct Composer route.");
        } finally {
            removeDirectory($fixtureRoot);
        }
    }

    foreach ([
        'negated command' => ['! composer install', '! php bin/composer-policy install'],
        'for body' => ['for item in one; do composer install; done', 'for item in one; do php bin/composer-policy install; done'],
        'while body' => ['while false; do composer update; done', 'while false; do php bin/composer-policy update; done'],
        'case body' => ['case x in x) composer install ;; esac', 'case x in x) php bin/composer-policy install ;; esac'],
        'subshell body' => ['(composer install)', '(php bin/composer-policy install)'],
        'bash function body' => ['function runner { composer update; }', 'function runner { php bin/composer-policy update; }'],
    ] as $scenario => [$direct, $guarded]) {
        $fixtureRoot = initializeFixtureRepositoryFiles($repositoryRoot, [
            '.github/workflows/control-forms.yml' => "jobs:\n  audit:\n    steps:\n      - run: {$direct}\n      - run: {$guarded}\n",
        ]);

        try {
            $records = auditRoutes($fixtureRoot);
            assertTrue((bool) array_filter($records, static fn (array $record): bool => $record['classification'] === 'supported' && $record['executable'] === 'guard'), "Fixture {$scenario} must preserve its guarded literal route.");
            assertTrue(routeAuditFailures($records) !== [], "Fixture {$scenario} must reject its direct Composer route.");
        } finally {
            removeDirectory($fixtureRoot);
        }
    }

    $dockerFixtureRoot = initializeFixtureRepositoryFiles($repositoryRoot, [
        'Dockerfile' => "RUN composer install\nRUN php bin/composer-policy install\nCMD [\"composer\", \"update\"]\nENTRYPOINT [\"php\", \"bin/composer-policy\", \"update\"]\n",
    ]);

    try {
        $records = auditRoutes($dockerFixtureRoot);
        $dockerGuards = array_values(array_filter($records, static fn (array $record): bool => $record['path'] === 'Dockerfile'
            && $record['executable'] === 'guard'
            && $record['classification'] === 'supported'));
        assertTrue(count($dockerGuards) === 2, 'Literal Docker RUN and JSON ENTRYPOINT guarded commands must preserve supported contract evidence.');
        assertTrue(routeAuditFailures($records) !== [], 'Literal Docker direct RUN and JSON CMD commands must fail the route audit.');
    } finally {
        removeDirectory($dockerFixtureRoot);
    }

    $literalMultiCommandRoot = initializeFixtureRepositoryFiles($repositoryRoot, [
        '.github/workflows/routes.yml' => "jobs:\n  audit:\n    steps:\n      - run: |\n          php bin/composer-policy validate\n          composer install\n",
    ]);

    try {
        $records = auditRoutes($literalMultiCommandRoot);
        $directInstall = array_values(array_filter($records, static fn (array $record): bool => $record['executable'] === 'composer'
            && $record['operation'] === 'install'
            && $record['classification'] === 'unsupported'));
        assertTrue(count($directInstall) === 1, 'Each literal workflow newline must preserve its own Composer command boundary.');
    } finally {
        removeDirectory($literalMultiCommandRoot);
    }

    $literalBashDirect = implode(' ', ['bash', '-c', "'composer install'"]);
    $literalBashGuarded = implode(' ', ['bash', '-c', "'php bin/composer-policy install'"]);
    $literalBashQuoted = implode(' ', ['bash', '-c', "'composer install --prefer-dist \\".'$SAFE'."'"]);

    foreach ([
        'literal direct bash payload' => [$literalBashDirect, 'composer', 'install', 'unsupported'],
        'literal guarded bash payload' => [$literalBashGuarded, 'guard', 'install', 'supported'],
        'literal quoted and escaped bash payload' => [$literalBashQuoted, 'composer', 'install', 'unsupported'],
    ] as $scenario => [$command, $executable, $operation, $classification]) {
        $fixtureRoot = initializeFixtureRepository($repositoryRoot, "jobs:\n  audit:\n    steps:\n      - run: {$command}\n");

        try {
            $records = auditRoutes($fixtureRoot);
            $matching = array_values(array_filter($records, static fn (array $record): bool => $record['executable'] === $executable
                && $record['operation'] === $operation
                && $record['classification'] === $classification));
            assertTrue($matching !== [], "Fixture {$scenario} must produce a nested {$classification} record.");

            if ($classification === 'unsupported') {
                assertTrue(routeAuditFailures($records) !== [], "Fixture {$scenario} must fail the route audit.");
            }
        } finally {
            removeDirectory($fixtureRoot);
        }
    }

    $compoundDirect = 'composer'.' install';
    $compoundGuarded = 'php bin/'.'composer-policy install';

    foreach ([
        'brace direct payload' => ['{ bash -c \' '.$compoundDirect.' \'; }', 'composer', 'install', 'unsupported'],
        'brace guarded payload' => ['{ bash -c \' '.$compoundGuarded.' \'; }', 'guard', 'install', 'supported'],
        'function direct payload' => ['runner() { bash -c \' '.$compoundDirect.' \'; }', 'composer', 'install', 'unsupported'],
        'function guarded payload' => ['runner() { bash -c \' '.$compoundGuarded.' \'; }', 'guard', 'install', 'supported'],
    ] as $scenario => [$command, $executable, $operation, $classification]) {
        $fixtureRoot = initializeFixtureRepository($repositoryRoot, "jobs:\n  audit:\n    steps:\n      - run: {$command}\n");

        try {
            $records = auditRoutes($fixtureRoot);
            $matching = array_values(array_filter($records, static fn (array $record): bool => $record['executable'] === $executable
                && $record['operation'] === $operation
                && $record['classification'] === $classification));
            assertTrue($matching !== [], "Fixture {$scenario} must preserve nested {$classification} route evidence.");

            if ($classification === 'unsupported') {
                assertTrue(routeAuditFailures($records) !== [], "Fixture {$scenario} must fail the route audit.");
            }
        } finally {
            removeDirectory($fixtureRoot);
        }
    }

    foreach ([
        'unmatched brace' => '{ bash -c \''.$compoundDirect.'\';',
        'malformed function' => 'runner( { bash -c \''.$compoundDirect.'\'; }',
        'dynamic brace body' => '{ bash -c "$'.'PAYLOAD"; }',
    ] as $scenario => $command) {
        $fixtureRoot = initializeFixtureRepository($repositoryRoot, "jobs:\n  audit:\n    steps:\n      - run: {$command}\n");

        try {
            $records = auditRoutes($fixtureRoot);
            $unclassified = array_values(array_filter($records, static fn (array $record): bool => $record['classification'] === 'unclassified'));
            assertTrue(count($unclassified) === 1, "Fixture {$scenario} must produce exactly one compound unclassified record.");
            assertTrue(routeAuditFailures($records) !== [], "Fixture {$scenario} must fail the route audit.");
        } finally {
            removeDirectory($fixtureRoot);
        }
    }

    $inlinePhpDirect = 'system("'.$compoundDirect.'");';
    $inlinePhpGuarded = 'exec("'.$compoundGuarded.'");';
    $inlinePhpEvaluator = 'system("bash -c \\"'.$compoundDirect.'\\"");';

    foreach ([
        'inline PHP direct process launch' => [$inlinePhpDirect, 'composer', 'install', 'unsupported'],
        'inline PHP guarded process launch' => [$inlinePhpGuarded, 'guard', 'install', 'supported'],
        'inline PHP evaluator process launch' => [$inlinePhpEvaluator, 'composer', 'install', 'unsupported'],
    ] as $scenario => [$program, $executable, $operation, $classification]) {
        $fixtureRoot = initializeFixtureRepository($repositoryRoot, "jobs:\n  audit:\n    steps:\n      - run: php -r '{$program}'\n");

        try {
            $records = auditRoutes($fixtureRoot);
            $matching = array_values(array_filter($records, static fn (array $record): bool => $record['executable'] === $executable
                && $record['operation'] === $operation
                && $record['classification'] === $classification));
            assertTrue($matching !== [], "Fixture {$scenario} must preserve inline-PHP {$classification} route evidence.");

            if ($classification === 'unsupported') {
                assertTrue(routeAuditFailures($records) !== [], "Fixture {$scenario} must fail the route audit.");
            }
        } finally {
            removeDirectory($fixtureRoot);
        }
    }

    foreach ([
        'dynamic inline PHP launch' => 'system("composer ".'.'$operation);',
        'malformed inline PHP launch' => 'system("'.$compoundDirect.'";',
        'inline PHP without bounded launch' => 'echo "'.$compoundDirect.'";',
        'inline PHP launch count bound' => str_repeat('system("'.$compoundDirect.'");', MAX_ROUTE_INLINE_PHP_LAUNCHES + 1),
        'inline PHP program length bound' => 'system("'.$compoundDirect.'");'.str_repeat('x', MAX_ROUTE_LOGICAL_LINE_LENGTH),
    ] as $scenario => $program) {
        $fixtureRoot = initializeFixtureRepository($repositoryRoot, "jobs:\n  audit:\n    steps:\n      - run: php -r '{$program}'\n");

        try {
            $records = auditRoutes($fixtureRoot);
            $unclassified = array_values(array_filter($records, static fn (array $record): bool => $record['executable'] === 'unclassified-php'
                && $record['classification'] === 'unclassified'));
            assertTrue($unclassified !== [], "Fixture {$scenario} must produce explicit inline-PHP unclassified evidence.");
            assertTrue(routeAuditFailures($records) !== [], "Fixture {$scenario} must fail the route audit.");
        } finally {
            removeDirectory($fixtureRoot);
        }
    }

    $directInstall = 'composer'.' install';
    $directUpdate = 'composer'.' update';
    $guardedInstall = 'php bin/'.'composer-policy install';

    foreach ([
        'sh direct payload' => ['sh', $directUpdate, 'composer', 'update', 'unsupported'],
        'zsh direct payload' => ['zsh', $directInstall, 'composer', 'install', 'unsupported'],
        'eval direct payload' => ['eval', $directInstall, 'composer', 'install', 'unsupported'],
        'nested direct payload' => ['bash', 'sh -c "'.$directInstall.'"', 'composer', 'install', 'unsupported'],
        'nested guarded payload' => ['bash', 'sh -c "'.$guardedInstall.'"', 'guard', 'install', 'supported'],
    ] as $scenario => [$evaluator, $payload, $executable, $operation, $classification]) {
        $command = $evaluator === 'eval'
            ? "eval '{$payload}'"
            : "{$evaluator} -c '{$payload}'";
        $fixtureRoot = initializeFixtureRepository($repositoryRoot, "jobs:\n  audit:\n    steps:\n      - run: {$command}\n");

        try {
            $records = auditRoutes($fixtureRoot);
            $matching = array_values(array_filter($records, static fn (array $record): bool => $record['executable'] === $executable
                && $record['operation'] === $operation
                && $record['classification'] === $classification));
            assertTrue($matching !== [], "Fixture {$scenario} must produce its nested {$classification} record.");

            if ($classification === 'unsupported') {
                assertTrue(routeAuditFailures($records) !== [], "Fixture {$scenario} must fail the route audit.");
            }
        } finally {
            removeDirectory($fixtureRoot);
        }
    }

    $unsupportedEvaluatorForms = [
        'parameter expansion' => 'bash -c "'.$directInstall.' $'.'PAYLOAD"',
        'command substitution' => 'bash -c "$(printf '.$directInstall.')"',
        'backtick substitution' => 'bash -c "`printf '.$directInstall.'`"',
        'concatenated payload' => "bash -c 'composer'\" install\"",
        'missing shell payload' => 'bash -c',
        'extra shell argv' => "bash -c '{$directInstall}' positional",
        'unsupported shell option' => "bash --noprofile -c '{$directInstall}'",
        'multi-word eval' => 'eval composer install',
    ];

    $depthPayload = $directInstall;

    for ($index = 0; $index < MAX_ROUTE_EVALUATOR_DEPTH + 1; ++$index) {
        $depthPayload = 'bash -c "'.addcslashes($depthPayload, "\\\"").'"';
    }

    $unsupportedEvaluatorForms['evaluator depth bound'] = $depthPayload;
    $unsupportedEvaluatorForms['evaluator payload count bound'] = implode('; ', array_fill(0, MAX_ROUTE_EVALUATOR_PAYLOADS + 1, "bash -c 'true'"));
    $unsupportedEvaluatorForms['payload length bound'] = "bash -c '".$directInstall.' '.str_repeat('x', MAX_ROUTE_LOGICAL_LINE_LENGTH)."'";

    foreach ($unsupportedEvaluatorForms as $scenario => $command) {
        $fixtureRoot = initializeFixtureRepository($repositoryRoot, "jobs:\n  audit:\n    steps:\n      - run: {$command}\n");

        try {
            $records = auditRoutes($fixtureRoot);
            $unclassified = array_values(array_filter($records, static fn (array $record): bool => $record['classification'] === 'unclassified'));
            assertTrue($unclassified !== [], "Fixture {$scenario} must produce an explicit unclassified evaluator record.");
            assertTrue(routeAuditFailures($records) !== [], "Fixture {$scenario} must fail the route audit.");
        } finally {
            removeDirectory($fixtureRoot);
        }
    }

    $unknownShellRoot = initializeFixtureRepositoryFiles($repositoryRoot, [
        'scripts/dependencies.sh' => "#!/bin/sh\ncomposer --bogus install\n",
    ]);

    try {
        $records = auditRoutes($unknownShellRoot);
        $unclassifiedShell = array_values(array_filter($records, static fn (array $record): bool => $record['path'] === 'scripts/dependencies.sh'
            && $record['classification'] === 'unclassified'));
        assertTrue(count($unclassifiedShell) === 1, 'Composer-bearing supported shell routes outside the command grammar must fail closed explicitly.');
    } finally {
        removeDirectory($unknownShellRoot);
    }

    $siblingFallbackRoot = initializeFixtureRepositoryFiles($repositoryRoot, [
        'README.md' => "```sh\ncomposer --bogus install\n```\n",
        'scripts/dependencies.sh' => "#!/bin/sh\nphp bin/composer-policy validate; composer --bogus install\n",
    ]);

    try {
        $records = auditRoutes($siblingFallbackRoot);
        $unclassified = array_values(array_filter($records, static fn (array $record): bool => $record['classification'] === 'unclassified'));
        assertTrue(count($unclassified) === 2, 'README and each Composer-bearing sibling shell segment must fail closed independently.');
    } finally {
        removeDirectory($siblingFallbackRoot);
    }

    foreach ([
        ['if composer install; then true; fi', 'composer', 'install'],
        ['(composer update)', 'composer', 'update'],
        ['timeout 30 composer install', 'composer', 'install'],
        ['composer i', 'composer', 'i'],
        ['composer u', 'composer', 'u'],
        ['composer upgrade', 'composer', 'upgrade'],
        ['php bin/composer-policy i', 'guard', 'i'],
        ['php bin/composer-policy require vendor/package', 'guard', 'require'],
    ] as [$command, $executable, $operation]) {
        $fixtureRoot = initializeFixtureRepository($repositoryRoot, "jobs:\n  audit:\n    steps:\n      - run: {$command}\n");

        try {
            $records = auditRoutes($fixtureRoot);
            $offending = array_values(array_filter($records, static fn (array $record): bool => $record['executable'] === $executable
                && $record['operation'] === $operation
                && $record['classification'] === 'unsupported'));
            assertTrue(count($offending) === 1, "Shell route {$command} must produce one exact failure record.");
        } finally {
            removeDirectory($fixtureRoot);
        }
    }

    $composerScriptFixture = json_encode([
        'scripts' => [
            'post-install-cmd' => [
                'composer install',
                '@composer update',
                '@php bin/composer-policy install',
                'php bin/composer-policy update',
            ],
            'post-autoload-dump' => [
                'Illuminate\\Foundation\\ComposerScripts::postAutoloadDump',
                '@php artisan package:discover --ansi',
            ],
            'post-root-package-install' => '@php -r "file_exists(\'.env\') || copy(\'.env.example\', \'.env\');"',
        ],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n";
    $composerScriptRoot = initializeFixtureRepositoryFiles($repositoryRoot, ['composer.json' => $composerScriptFixture]);

    try {
        $records = auditRoutes($composerScriptRoot);
        $direct = array_values(array_filter($records, static fn (array $record): bool => $record['path'] === 'composer.json'
            && $record['logical'] === 'post-install-cmd'
            && $record['classification'] === 'unsupported'
            && $record['executable'] === 'composer'
            && in_array($record['operation'], ['install', 'update'], true)));
        $guarded = array_values(array_filter($records, static fn (array $record): bool => $record['path'] === 'composer.json'
            && $record['logical'] === 'post-install-cmd'
            && $record['classification'] === 'supported'
            && $record['executable'] === 'guard'
            && in_array($record['operation'], ['install', 'update'], true)));
        assertTrue(count($direct) === 2, 'Direct and @composer post-install handlers must each retain unsupported Composer-script provenance.');
        assertTrue(count($guarded) === 2, 'Literal direct and @php guarded Composer-script handlers must use ComposerPolicyCommandContract.');
        assertTrue((bool) array_filter($direct, static fn (array $record): bool => $record['chain'] === 0 && $record['line'] > 0 && $record['segment'] === 'composer install'), 'Direct Composer-script evidence must retain ordinal, physical line, and raw handler text.');
        assertTrue((bool) array_filter($direct, static fn (array $record): bool => $record['chain'] === 1 && $record['segment'] === '@composer update'), '@composer evidence must retain its handler ordinal and raw handler text.');
        assertTrue(routeAuditFailures($records) !== [], 'Direct Composer-script handlers must fail the route audit.');
        assertTrue(! (bool) array_filter($records, static fn (array $record): bool => $record['logical'] !== 'post-install-cmd'), 'Documented Laravel Composer-script handlers must remain non-candidates.');
    } finally {
        removeDirectory($composerScriptRoot);
    }

    $malformedComposerScriptRoot = initializeFixtureRepositoryFiles($repositoryRoot, [
        'composer.json' => json_encode([
            'scripts' => [
                'post-install-cmd' => ['handler' => 'composer install'],
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n",
    ]);

    try {
        $records = auditRoutes($malformedComposerScriptRoot);
        $unclassified = array_values(array_filter($records, static fn (array $record): bool => $record['path'] === 'composer.json'
            && $record['logical'] === 'post-install-cmd'
            && $record['chain'] === 0
            && $record['executable'] === 'unclassified-composer-script'
            && $record['classification'] === 'unclassified'));
        assertTrue(count($unclassified) === 1, 'A marker-bearing Composer-script handler outside the finite shape must fail closed with event and handler provenance.');
        assertTrue($unclassified[0]['line'] > 0 && str_contains($unclassified[0]['segment'], 'composer install'), 'Malformed Composer-script evidence must retain the physical source line and raw handler value.');
        assertTrue(routeAuditFailures($records) !== [], 'A malformed marker-bearing Composer-script handler must fail the route audit.');
    } finally {
        removeDirectory($malformedComposerScriptRoot);
    }

    $phpFixture = <<<'PHP'
<?php

proc_open(['composer', 'install'], [], $pipes);
exec('composer update');
system('composer install');
passthru('composer update');
shell_exec('composer install');
proc_open([PHP_BINARY, __DIR__.'/../bin/composer-policy', 'install'], [], $pipes);
$operation = 'install';
exec('composer '.$operation);
$command = 'composer install';
system($command);
$evaluator = "bash -c 'composer update'";
exec($evaluator);
PHP;
    $fixtureRoot = initializeFixtureRepositoryFiles($repositoryRoot, ['scripts/dependencies.php' => $phpFixture]);

    try {
        $records = auditRoutes($fixtureRoot);
        $directRecords = array_values(array_filter($records, static fn (array $record): bool => $record['executable'] === 'composer'
            && $record['classification'] === 'unsupported'));
        $guardedRecords = array_values(array_filter($records, static fn (array $record): bool => $record['executable'] === 'guard'
            && $record['operation'] === 'install'
            && $record['classification'] === 'supported'));
        $dynamicRecords = array_values(array_filter($records, static fn (array $record): bool => $record['executable'] === 'unclassified-php'
            && $record['classification'] === 'unclassified'));
        assertTrue(count($directRecords) === 5, 'Literal PHP process-launch forms must each reject direct Composer.');
        assertTrue(count($guardedRecords) === 1, 'A literal guarded PHP process array must remain supported.');
        assertTrue(count($dynamicRecords) === 3, 'Concatenated and variable-fed Composer/evaluator PHP launches must fail closed explicitly.');
    } finally {
        removeDirectory($fixtureRoot);
    }

    $composerWord = 'com'.'poser';
    $indirectPhpPrograms = [
        'callable dispatch' => "call_user_func('system', '{$composerWord} install');",
        'variable function dispatch' => "\$launcher = 'system';\n\$launcher('{$composerWord} update');",
        'popen dispatch' => "popen('{$composerWord} install', 'r');",
        'marker without a direct API' => "echo '{$composerWord} audit';",
    ];

    foreach ($indirectPhpPrograms as $scenario => $program) {
        $fixtureRoot = initializeFixtureRepositoryFiles($repositoryRoot, [
            'scripts/indirect.php' => "<?php\n\n{$program}\n",
        ]);

        try {
            $records = auditRoutes($fixtureRoot);
            $fallback = array_values(array_filter($records, static fn (array $record): bool => $record['path'] === 'scripts/indirect.php'
                && $record['executable'] === 'unclassified-php'
                && $record['classification'] === 'unclassified'));
            assertTrue(count($fallback) === 1, "{$scenario} must produce exactly one source-level unclassified PHP fallback.");
            assertTrue($fallback[0]['line'] > 0 && str_contains($fallback[0]['segment'], $composerWord), "{$scenario} fallback must retain the staged source line and raw marker-bearing program evidence.");
            assertTrue(routeAuditFailures($records) !== [], "{$scenario} must fail the route audit without executing fixture PHP.");
        } finally {
            removeDirectory($fixtureRoot);
        }
    }

    $trackedDispatchForms = [
        'variable function dispatch' => "\$launcher = 'system';\n\$launcher('{$composerWord} install');",
        'popen dispatch' => "popen('{$composerWord} update', 'r');",
        'callable dispatch' => "call_user_func('system', '{$composerWord} install');",
    ];

    foreach (['app/IndirectComposer.php', 'tools/IndirectComposer.php'] as $trackedPath) {
        foreach ($trackedDispatchForms as $form => $program) {
            $source = "<?php\n\n{$program}\n";
            $trackedRoot = initializeFixtureRepositoryFiles($repositoryRoot, [$trackedPath => $source]);

            try {
                $records = auditRoutes($trackedRoot);
                $fallback = array_values(array_filter($records, static fn (array $record): bool => $record['path'] === $trackedPath
                    && $record['executable'] === 'unclassified-php'
                    && $record['classification'] === 'unclassified'));
                assertTrue(count($fallback) === 1, "A tracked {$trackedPath} {$form} must produce exactly one source-level unclassified PHP fallback.");
                assertTrue($fallback[0]['line'] > 0 && $fallback[0]['line'] <= substr_count($source, "\n"), "A tracked {$trackedPath} {$form} record must carry a finite positive staged source line.");
                assertTrue(str_contains($fallback[0]['segment'], $composerWord) && str_contains($fallback[0]['logical'], $composerWord), "A tracked {$trackedPath} {$form} record must retain invocation-bearing raw segment and source provenance.");
                assertTrue(routeAuditFailures($records) !== [], "A tracked {$trackedPath} {$form} must fail the route audit without executing fixture PHP.");
                assertTrue(! file_exists($trackedRoot.'/composer.lock') && ! is_dir($trackedRoot.'/vendor'), "A tracked {$trackedPath} {$form} fixture must be inspected as text with no runtime side effect.");
            } finally {
                removeDirectory($trackedRoot);
            }
        }
    }

    $directApplicationRoot = initializeFixtureRepositoryFiles($repositoryRoot, [
        'app/DirectComposer.php' => "<?php\n\nsystem('{$composerWord} install');\n",
    ]);

    try {
        $records = auditRoutes($directApplicationRoot);
        $direct = array_values(array_filter($records, static fn (array $record): bool => $record['path'] === 'app/DirectComposer.php'
            && $record['executable'] === 'composer'
            && $record['classification'] === 'unclassified'));
        assertTrue(count($direct) === 1, 'A direct literal application Composer launch must remain classified by the bounded extractor and must not be treated as supported.');
        assertTrue(routeAuditFailures($records) !== [], 'A direct literal application Composer launch must fail the route audit.');
    } finally {
        removeDirectory($directApplicationRoot);
    }

    $contractOnlyRoot = initializeFixtureRepositoryFiles($repositoryRoot, []);

    try {
        assertTrue(auditRoutes($contractOnlyRoot) === [], 'The tracked guard and trusted command contract must produce no route-audit records on their own.');
    } finally {
        removeDirectory($contractOnlyRoot);
    }

    $contractSource = (string) file_get_contents($repositoryRoot.'/tools/composer/ComposerPolicyCommandContract.php');
    assertTrue(str_contains($contractSource, 'a canonical Composer command is required')
        && str_contains($contractSource, 'Composer command aliases are forbidden'), 'The trusted command contract must retain the reason strings used as no-record controls.');

    $noRecordPhpSources = [
        'line comment marker' => "<?php\n\n// Operators run {$composerWord} install through bin/composer-policy.\n\$bootstrapped = true;\n",
        'docblock marker' => "<?php\n\n/**\n * Dependency changes go through {$composerWord} update via bin/composer-policy install.\n */\nfinal class DocumentedKernel\n{\n}\n",
        'returned contract reason strings' => "<?php\n\nfinal class ReasonCarrier\n{\n    public function canonical(): string\n    {\n        return 'a canonical Composer command is required';\n    }\n\n    public function alias(): string\n    {\n        return 'Composer command aliases are forbidden';\n    }\n}\n",
        'thrown contract reason strings' => "<?php\n\nfinal class ThrowingContract\n{\n    public function decide(array \$tokens): void\n    {\n        throw new RuntimeException('a canonical Composer command is required');\n    }\n\n    public function reject(array \$tokens): void\n    {\n        throw new InvalidArgumentException('Composer command aliases are forbidden');\n    }\n}\n",
        'arbitrary prose passed to calls' => "<?php\n\ndefine('LARAVEL_START', microtime(true));\n\n\$notice = sprintf('Operators must run Composer through bin/composer-policy before deploying.');\n\$hint = sprintf('The %s manifest is validated in CI.', 'composer.json');\ntrigger_error('Direct Composer usage is reviewed by the maintainer.', E_USER_NOTICE);\n",
    ];

    foreach ($noRecordPhpSources as $control => $controlSource) {
        foreach (['app/Console/Kernel.php', 'public/index.php', 'tools/composer/Notes.php'] as $controlPath) {
            $controlRoot = initializeFixtureRepositoryFiles($repositoryRoot, [$controlPath => $controlSource]);

            try {
                $controlRecords = array_values(array_filter(auditRoutes($controlRoot), static fn (array $record): bool => $record['path'] === $controlPath));
                assertTrue($controlRecords === [], "A {$control} in {$controlPath} must stay outside the tracked-PHP detector.");
            } finally {
                removeDirectory($controlRoot);
            }
        }
    }

    $literalCodeStringRoot = initializeFixtureRepositoryFiles($repositoryRoot, [
        'app/Console/Kernel.php' => "<?php\n\n// Operators run {$composerWord} install through bin/composer-policy.\n\$dispatch = 'shell_exec';\n\$dispatch('{$composerWord} install');\n",
    ]);

    try {
        $records = auditRoutes($literalCodeStringRoot);
        $fallback = array_values(array_filter($records, static fn (array $record): bool => $record['path'] === 'app/Console/Kernel.php'
            && $record['executable'] === 'unclassified-php'));
        assertTrue(count($fallback) === 1, 'A literal command-shaped code string must remain detectable even when the same file also carries comment prose.');
        assertTrue(! str_starts_with(ltrim($fallback[0]['segment']), '//'), 'Command-shaped PHP provenance must point at executable source, not at the comment marker.');
    } finally {
        removeDirectory($literalCodeStringRoot);
    }

    $unmarkedPhpRoot = initializeFixtureRepositoryFiles($repositoryRoot, [
        'scripts/unmarked.php' => "<?php\n\ncall_user_func('system', 'echo harmless');\n",
    ]);

    try {
        assertTrue(auditRoutes($unmarkedPhpRoot) === [], 'An unmarked supported PHP program must remain outside the Composer route detector.');
    } finally {
        removeDirectory($unmarkedPhpRoot);
    }

    $unclassifiedWorkflow = initializeFixtureRepositoryFiles($repositoryRoot, [
        '.github/workflows/routes.yml' => "jobs:\n  audit:\n    steps:\n      - run: >2-\n          composer install\n",
    ]);

    try {
        $records = auditRoutes($unclassifiedWorkflow);
        $unclassified = array_values(array_filter($records, static fn (array $record): bool => $record['classification'] === 'unclassified'));
        assertTrue(count($unclassified) === 1, 'Composer-bearing workflow syntax outside the bounded scalar grammar must fail closed explicitly.');
    } finally {
        removeDirectory($unclassifiedWorkflow);
    }

    assertTrue(commandChainSegments('first && second') === ['first', 'second'], 'The && operator must remain one list separator.');
    assertParserRejects(static fn (): array => commandChainSegments(str_repeat('x', MAX_ROUTE_LOGICAL_LINE_LENGTH + 1)), 'logical line length');
    assertParserRejects(static fn (): array => commandChainSegments(implode(' & ', array_fill(0, MAX_ROUTE_SEGMENTS + 1, 'true'))), 'segment count');
    assertParserRejects(static fn (): array => commandTokens(implode(' ', array_fill(0, MAX_ROUTE_TOKENS + 1, 'token'))), 'token count');
    assertParserRejects(static fn (): array => commandChainSegments("echo 'unterminated"), 'unterminated quote');
    assertParserRejects(static fn (): array => commandChainSegments('echo dangling'.'\\'), 'dangling escape');

    $boundedLaunchProbe = "<?php\n\nproc_open('{$composerWord} install', [], \$pipes);\nexec('{$composerWord} install');\nsystem('{$composerWord} install');\npassthru('{$composerWord} install');\nshell_exec('{$composerWord} install');\n";
    assertTrue(count(phpProcessLaunches($boundedLaunchProbe)) === 5, 'The direct PHP extractor must retain exactly its five known launch APIs.');

    $indirectLaunchProbe = "<?php\n\npopen('{$composerWord} install', 'r');\ncall_user_func('system', '{$composerWord} install');\n\$launcher = 'system';\n\$launcher('{$composerWord} install');\n";
    assertTrue(phpProcessLaunches($indirectLaunchProbe) === [], 'Indirect dispatch must stay outside the direct PHP extractor instead of widening it.');
    assertTrue(str_contains(auditFunctionSource('phpProcessLaunches'), "\$functions = ['proc_open', 'exec', 'system', 'passthru', 'shell_exec'];"), 'The direct PHP extractor must keep its literal bounded API list.');
    assertTrue(str_contains(auditFunctionSource('parseInvocation'), 'ComposerPolicyCommandContract::decide('), 'Direct literal guarded forms must keep classifying through ComposerPolicyCommandContract.');

    $phpFinalization = phpFinalizationSource();
    assertTrue(substr_count($phpFinalization, 'phpCommandShapedProgram(') === 1, 'The token-aware helper must be the sole PHP program-bearing decision seam.');
    assertTrue(str_contains(auditFunctionSource('phpCommandShapedProgram'), 'token_get_all('), 'The PHP program-bearing helper must decide from PHP tokens.');

    foreach (['routeAuditMarker(', 'markerSourceLine(', 'containsComposerExecutableText(', 'containsComposerOrEvaluatorText(', 'preg_match(', 'isSupportedProductionRoute('] as $rejectedSeam) {
        assertTrue(! str_contains($phpFinalization, $rejectedSeam), "Tracked-PHP finalization must not reintroduce the seam {$rejectedSeam}.");
    }

    assertTrue(str_contains($phpFinalization, 'isTrustedPhpAuditSource($path)'), 'Tracked-PHP finalization must retain its explicit trusted-source exclusion.');

    foreach (['tests/', '.planning/', 'vendor/', 'bootstrap/cache/', 'storage/framework/'] as $excludedTree) {
        assertTrue(isTrustedPhpAuditSource($excludedTree.'Probe.php'), "Tracked-PHP exclusions must retain {$excludedTree}.");
    }

    assertTrue(isTrustedPhpAuditSource('bin/composer-policy'), 'The policy guard itself must remain an explicit audit exclusion.');

    foreach (['app/Probe.php', 'app/Console/Kernel.php', 'tools/Probe.php', 'tools/composer/Probe.php', 'public/index.php'] as $trackedProductionPhp) {
        assertTrue(! isTrustedPhpAuditSource($trackedProductionPhp), "Tracked application and tool source must not be allowlisted out of the audit: {$trackedProductionPhp}.");
    }

    foreach (['composer.json', 'composer.lock', 'tools/composer/composer-2.10.2.phar', 'tools/composer/composer-2.10.2.phar.sha256'] as $nonSourcePath) {
        assertTrue(routeSourceKind($nonSourcePath, '') === 'non-source', "Manifest, lockfile, and PHAR/digest material must retain non-source handling: {$nonSourcePath}.");
    }

    $productionRecords = auditRoutes($repositoryRoot);
    assertTrue(routeAuditFailures($productionRecords, true) === [], 'Production route audit must pass using only tracked records.');
    assertTrue(! (bool) array_filter($productionRecords, static fn (array $record): bool => str_contains($record['path'], 'sendportal-composer-route-')), 'Production route evidence must not include temporary fixture paths.');
    assertTrue(count($productionRecords) === 8, 'Production route audit must retain exactly eight tracked Composer records (the six baseline README/CI records plus the two Phase-3 CI gate commands added to .github/workflows/ci.yml: the guarded validate and audit steps).');
    $guardedProductionRecords = array_values(array_filter($productionRecords, static fn (array $record): bool => $record['classification'] === 'supported'
        && $record['executable'] === 'guard'
        && ($record['path'] === 'README.md' || str_starts_with($record['path'], '.github/workflows/'))));
    assertTrue(count($guardedProductionRecords) === 8, 'Every one of the eight production records must be guarded CI and README evidence (no un-guarded Composer route may enter tracked production files).');
    // The dependency-free property (the audit reads only tracked source, never the lockfile or vendor tree)
    // is proven above: composer.lock is classified 'non-source' (routeSourceKind), the audit passes using
    // only tracked records, and no fixture paths leak in. The former on-disk absence precondition here was
    // dropped in Phase 2: DEPS-01 legitimately commits composer.lock, so `! file_exists(composer.lock)` can
    // no longer hold at the real repository root.
} finally {
    foreach ($temporaryRoots as $temporaryRoot) {
        removeDirectory($temporaryRoot);
    }
}

$scope = $requestedGroup === null ? 'full suite' : "group {$requestedGroup} with shared security prerequisites";
fwrite(STDOUT, "Composer policy guard tests passed ({$scope}).\n");
