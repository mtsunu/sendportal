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

$repositoryRoot = dirname(__DIR__, 2);
$guard = $repositoryRoot.'/bin/composer-policy';
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
