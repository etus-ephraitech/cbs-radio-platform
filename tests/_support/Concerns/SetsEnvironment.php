<?php

namespace Tests\Support\Concerns;

use PHPUnit\Framework\Attributes\After;

/**
 * Lets a test set an environment value (an SMS key, a vendor key) and guarantees it is put back afterwards, so
 * one test can never leak a credential, or the lack of one, into the next.
 *
 * CodeIgniter's env() looks in $_ENV, then $_SERVER, then getenv(), so all three are written.
 */
trait SetsEnvironment
{
    /** @var array<string, array{env: mixed, server: mixed, getenv: string|false}> */
    private array $environmentBackup = [];

    protected function setEnv(string $key, ?string $value): void
    {
        if (! array_key_exists($key, $this->environmentBackup)) {
            $this->environmentBackup[$key] = [
                'env'    => $_ENV[$key] ?? null,
                'server' => $_SERVER[$key] ?? null,
                'getenv' => getenv($key),
            ];
        }

        if ($value === null) {
            unset($_ENV[$key], $_SERVER[$key]);
            putenv($key);

            return;
        }

        $_ENV[$key] = $_SERVER[$key] = $value;
        putenv("{$key}={$value}");
    }

    #[After]
    public function restoreEnvironment(): void
    {
        foreach ($this->environmentBackup as $key => $before) {
            if ($before['env'] === null) {
                unset($_ENV[$key]);
            } else {
                $_ENV[$key] = $before['env'];
            }

            if ($before['server'] === null) {
                unset($_SERVER[$key]);
            } else {
                $_SERVER[$key] = $before['server'];
            }

            if ($before['getenv'] === false) {
                putenv($key);
            } else {
                putenv("{$key}={$before['getenv']}");
            }
        }

        $this->environmentBackup = [];
    }
}
