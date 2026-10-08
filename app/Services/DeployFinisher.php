<?php

namespace App\Services;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Conclusão da implantação por FTP, sem acesso ao terminal do servidor: cria as pastas
 * de trabalho e o .env (com APP_KEY nova) na primeira vez, atualiza o banco, limpa os
 * caches e tira o site da manutenção.
 *
 * É acionada pelo GitHub Actions em POST /_implantacao/finalizar, com um código de uso
 * único: a cada implantação o GitHub sorteia um código, envia por FTP só o hash dele
 * (.implantacao/token.sha256, fora da pasta pública) e o apaga ao terminar.
 */
class DeployFinisher
{
    public const TOKEN_FILE = '.implantacao/token.sha256';

    /** Validade máxima do código, caso a limpeza ao final da implantação não aconteça. */
    public const TOKEN_TTL = 3600;

    private const DIRECTORIES = [
        'storage/app/private', 'storage/framework/cache/data', 'storage/framework/sessions',
        'storage/framework/views', 'storage/logs', 'bootstrap/cache',
    ];

    private string $base;

    public function __construct(?string $basePath = null)
    {
        $this->base = rtrim($basePath ?? base_path(), '/');
    }

    public function path(string $relative): string
    {
        return $this->base.'/'.$relative;
    }

    public function tokenMatches(string $token): bool
    {
        $file = $this->path(self::TOKEN_FILE);
        if (strlen($token) < 32 || ! is_file($file) || filemtime($file) < time() - self::TOKEN_TTL) {
            return false;
        }

        return hash_equals(strtolower(trim((string) file_get_contents($file))), hash('sha256', $token));
    }

    /**
     * @return array{status: string, mensagens: list<string>, migracoes: list<string>, php: string}
     *   status: "concluido", "configurar" (falta preencher o .env) ou "erro"
     */
    public function finish(): array
    {
        $messages = [];
        $migrations = [];
        $result = function (string $status) use (&$messages, &$migrations): array {
            return ['status' => $status, 'mensagens' => $messages, 'migracoes' => $migrations, 'php' => PHP_VERSION];
        };

        try {
            foreach (self::DIRECTORIES as $dir) {
                if (! is_dir($this->path($dir))) {
                    mkdir($this->path($dir), 0755, true);
                }
            }

            if (! is_file($this->path('.env'))) {
                $this->createEnv();
                $messages[] = 'Primeira implantação: o arquivo .env foi criado com uma APP_KEY nova. '
                    .'Preencha DB_DATABASE, DB_USERNAME, DB_PASSWORD, APP_URL e SETUP_TOKEN no Gerenciador de arquivos do cPanel '
                    .'e rode a implantação de novo.';
                $this->up();

                return $result('configurar');
            }

            if ($this->ensureAppKey()) {
                $messages[] = 'APP_KEY estava vazia e foi gerada.';
            }

            $connection = (string) config('database.default');
            if ($connection !== 'sqlite' && (blank(config("database.connections.{$connection}.database")) || blank(config("database.connections.{$connection}.username")))) {
                $messages[] = 'O banco de dados ainda não está configurado no .env (DB_DATABASE e DB_USERNAME). Preencha e rode a implantação de novo.';
                $this->up();

                return $result('configurar');
            }

            try {
                DB::connection()->getPdo();
            } catch (Throwable $e) {
                // Só o código do erro: a mensagem completa pode trazer o usuário do banco.
                preg_match('/SQLSTATE\[\w+\](?: \[\d+\])?/', $e->getMessage(), $code);
                $messages[] = 'Não foi possível conectar ao banco de dados ('.($code[0] ?? class_basename($e)).'). Confira DB_* no .env.';
                $this->up();

                return $result('erro');
            }

            Artisan::call('migrate', ['--force' => true]);
            foreach (preg_split('/\R/', Artisan::output()) as $line) {
                if (preg_match('/(\d{4}_\d{2}_\d{2}_\d{6}_\w+)\s.*DONE/', $line, $m)) {
                    $migrations[] = $m[1];
                }
            }
            $messages[] = $migrations ? count($migrations).' atualização(ões) do banco aplicada(s).' : 'Banco de dados já estava atualizado.';

            Artisan::call('optimize:clear');
            $this->up();
            $messages[] = 'Caches limpos e site no ar.';

            return $result('concluido');
        } catch (Throwable $e) {
            report($e);
            $messages[] = 'Falha ao concluir a implantação: '.class_basename($e).'. Detalhes em storage/logs/laravel.log.';
            $this->up();

            return $result('erro');
        }
    }

    private function up(): void
    {
        rescue(fn () => Artisan::call('up'), null, false);
    }

    private function createEnv(): void
    {
        $env = (string) file_get_contents($this->path('.env.example'));
        $env = $this->withKey($env);
        file_put_contents($this->path('.env'), $env, LOCK_EX);
        @chmod($this->path('.env'), 0600);
    }

    private function ensureAppKey(): bool
    {
        $env = (string) file_get_contents($this->path('.env'));
        if (preg_match('/^APP_KEY=\S+/m', $env)) {
            return false;
        }
        file_put_contents($this->path('.env'), $this->withKey($env), LOCK_EX);

        return true;
    }

    private function withKey(string $env): string
    {
        $line = 'APP_KEY=base64:'.base64_encode(random_bytes(32));

        return preg_match('/^APP_KEY=.*$/m', $env)
            ? preg_replace('/^APP_KEY=.*$/m', $line, $env, 1)
            : $line.PHP_EOL.$env;
    }
}
