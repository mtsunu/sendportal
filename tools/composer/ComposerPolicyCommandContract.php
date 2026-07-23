<?php

declare(strict_types=1);

/**
 * Bounded command contract for the repository Composer policy guard.
 *
 * This intentionally models only the global options and commands used by this
 * repository. It is not a generic Composer CLI parser.
 */
final class ComposerPolicyCommandContract
{
    /** @var list<string> */
    private const ALLOWED_COMMANDS = ['validate', 'audit', 'install', 'update'];

    /** @var list<string> */
    private const FORBIDDEN_ALIASES = ['i', 'u', 'upgrade'];

    /** @var list<string> */
    private const VALUELESS_GLOBAL_OPTIONS = [
        '--no-cache',
        '--verbose',
        '-v',
        '-vv',
        '-vvv',
        '--ansi',
        '--no-ansi',
        '--quiet',
        '-q',
        '--profile',
        '--no-interaction',
        '-n',
        '--no-plugins',
        '--no-scripts',
    ];

    /** @var list<string> */
    private const FORBIDDEN_SELECTORS = [
        '--working-dir',
        '-d',
        '--file',
        '-f',
        '--global',
        '-g',
        '--project-dir',
    ];

    /**
     * @param list<string> $arguments
     * @return array{allowed: bool, command: string, command_index: int|null, reason: string}
     */
    public static function decide(array $arguments): array
    {
        foreach ($arguments as $argument) {
            if (self::isForbiddenSelector($argument)) {
                return [
                    'allowed' => false,
                    'command' => '',
                    'command_index' => null,
                    'reason' => 'project or configuration selector is forbidden',
                ];
            }
        }

        foreach ($arguments as $index => $argument) {
            if (in_array($argument, self::VALUELESS_GLOBAL_OPTIONS, true)) {
                continue;
            }

            if (str_starts_with($argument, '-')) {
                return [
                    'allowed' => false,
                    'command' => '',
                    'command_index' => null,
                    'reason' => 'global option is outside the repository contract',
                ];
            }

            $command = strtolower($argument);

            if (in_array($command, self::ALLOWED_COMMANDS, true)) {
                return [
                    'allowed' => true,
                    'command' => $command,
                    'command_index' => $index,
                    'reason' => 'canonical repository command',
                ];
            }

            return [
                'allowed' => false,
                'command' => $command,
                'command_index' => $index,
                'reason' => in_array($command, self::FORBIDDEN_ALIASES, true)
                    ? 'Composer command aliases are forbidden'
                    : 'command is outside the repository contract',
            ];
        }

        return [
            'allowed' => false,
            'command' => '',
            'command_index' => null,
            'reason' => 'a canonical Composer command is required',
        ];
    }

    public static function isAllowedCommand(string $command): bool
    {
        return in_array(strtolower($command), self::ALLOWED_COMMANDS, true);
    }

    public static function isForbiddenAlias(string $command): bool
    {
        return in_array(strtolower($command), self::FORBIDDEN_ALIASES, true);
    }

    private static function isForbiddenSelector(string $argument): bool
    {
        foreach (self::FORBIDDEN_SELECTORS as $selector) {
            if ($argument === $selector || str_starts_with($argument, $selector.'=')) {
                return true;
            }
        }

        return str_starts_with($argument, '-d')
            && strlen($argument) > 2
            && ! in_array($argument, ['--no-plugins', '--no-scripts'], true);
    }
}
