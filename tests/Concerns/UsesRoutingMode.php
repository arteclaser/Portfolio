<?php

namespace Tests\Concerns;

use Illuminate\Support\Env;

/**
 * Executa a classe de testes em outro formato de endereço (PORTFOLIO_ROUTING). As rotas
 * são registradas na criação da aplicação, por isso o ambiente é trocado antes do
 * setUp e restaurado depois do tearDown.
 */
trait UsesRoutingMode
{
    /** @var array<string, string|false> */
    private array $previousEnv = [];

    /** @return array<string, string> */
    abstract protected function routingEnv(): array;

    protected function setUp(): void
    {
        foreach ($this->routingEnv() as $key => $value) {
            $this->previousEnv[$key] = getenv($key);
            $this->putEnv($key, $value);
        }
        // O leitor do .env é compartilhado entre testes: recomeça para respeitar os valores acima.
        Env::enablePutenv();
        parent::setUp();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        foreach ($this->previousEnv as $key => $value) {
            $this->putEnv($key, $value);
        }
        Env::enablePutenv();
    }

    private function putEnv(string $key, string|false $value): void
    {
        if ($value === false) {
            putenv($key);
            unset($_ENV[$key], $_SERVER[$key]);

            return;
        }
        putenv("{$key}={$value}");
        $_ENV[$key] = $_SERVER[$key] = $value;
    }
}
