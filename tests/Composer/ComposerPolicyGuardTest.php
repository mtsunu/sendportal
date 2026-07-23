<?php

declare(strict_types=1);

/**
 * Dependency-free regression coverage for bin/composer-policy.
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
    'php_binary' => PHP_BINARY,
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
    writeFile($repositoryRoot.'/tools/composer/composer-2.10.2.phar.sha256', "release: 2.10.2\nsource: https://getcomposer.org/download/2.10.2/composer.phar\nverification: https://getcomposer.org/download/2.10.2/composer.phar.sha256sum\n{$digest} composer-2.10.2.phar\n");
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

$repositoryRoot = dirname(__DIR__, 2);
$guard = $repositoryRoot.'/bin/composer-policy';
$guardContents = file_get_contents($guard);

if ($guardContents === false) {
    fail('Could not read bin/composer-policy.');
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
    writeSyntheticDistribution($successRoot);
    [$status, $output] = runCommand([PHP_BINARY, $successRoot.'/bin/composer-policy', 'update', '--dry-run', '--no-interaction'], $successEnvironment, $successRoot);
    assertTrue($status === 0, "A matching repository distribution must be delegated to: {$output}");
    assertTrue(! file_exists($successShadowMarker), 'A compliant-looking PATH shadow must never execute.');
    $invocations = array_filter(explode("\n", (string) file_get_contents($successTrustedMarker)));
    assertTrue(count($invocations) === 3, 'The trusted distribution must receive version, policy, and delegated calls only.');
    $delegation = json_decode((string) end($invocations), true, 512, JSON_THROW_ON_ERROR);
    assertTrue($delegation['php_binary'] === PHP_BINARY, 'The trusted distribution must be invoked through PHP_BINARY.');
    assertTrue($delegation['arguments'] === ['update', '--dry-run', '--no-interaction'], 'Delegation must preserve requested Composer arguments.');

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

    foreach (['COMPOSER_BIN', 'COMPOSER', 'COMPOSER_POLICY', 'COMPOSER_NO_AUDIT', 'COMPOSER_NO_BLOCKING', 'COMPOSER_NO_SECURITY_BLOCKING', 'COMPOSER_IGNORE_PLATFORM_REQ', 'COMPOSER_IGNORE_PLATFORM_REQS'] as $name) {
        $environment = $overrideEnvironment;
        $environment[$name] = '1';
        [$status, $output] = runCommand([PHP_BINARY, $overrideRoot.'/bin/composer-policy', 'install'], $environment, $overrideRoot);
        assertTrue($status !== 0 && str_contains($output, 'Composer override rejected'), "{$name} must be rejected before Composer starts.");
        assertNoComposerRan($overrideTrustedMarker, $overrideShadowMarker, "{$name} override");
    }
} finally {
    foreach ($temporaryRoots as $temporaryRoot) {
        removeDirectory($temporaryRoot);
    }
}

fwrite(STDOUT, "Composer policy guard tests passed.\n");
