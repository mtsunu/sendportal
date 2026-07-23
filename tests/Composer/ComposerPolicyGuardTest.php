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
    writeFile($temporaryRoot.'/bin/composer', <<<'PHP'
<?php

if (in_array('--version', $argv, true)) {
    echo "Composer version 2.9.5\n";
    exit(0);
}

file_put_contents((string) getenv('FAKE_COMPOSER_MARKER'), "old operation\n");
PHP);
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
} finally {
    removeDirectory($temporaryRoot);
}

fwrite(STDOUT, "Composer policy guard tests passed.\n");
