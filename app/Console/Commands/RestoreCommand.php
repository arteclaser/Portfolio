<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use ZipArchive;

/** Restaura uma cópia gerada por mostraqui:backup. Substitui TODOS os dados atuais. */
class RestoreCommand extends Command
{
    protected $signature = 'mostraqui:restaurar {arquivo : Caminho do .zip} {--forcar : Não pedir confirmação}';

    protected $description = 'Restaura banco de dados e arquivos a partir de uma cópia de segurança.';

    public function handle(): int
    {
        $file = $this->argument('arquivo');
        $zip = new ZipArchive;
        if (! is_file($file) || $zip->open($file) !== true) {
            $this->error('Arquivo de cópia inválido: '.$file);

            return self::FAILURE;
        }
        $manifest = json_decode((string) $zip->getFromName('manifest.json'), true);
        if (($manifest['app'] ?? null) !== 'mostraqui-portfolio') {
            $this->error('Este arquivo não é uma cópia deste sistema.');

            return self::FAILURE;
        }
        $this->info('Cópia de '.$manifest['created_at'].' · '.array_sum($manifest['tables'] ?? []).' registros · '.($manifest['media_files'] ?? 0).' arquivos');
        if (! $this->option('forcar') && ! $this->confirm('Isto APAGA os dados atuais e restaura a cópia. Continuar?')) {
            return self::FAILURE;
        }

        // Garante o esquema atual sem apagar tabelas; depois esvazia e reinsere os dados.
        Artisan::call('migrate', ['--force' => true]);

        Schema::disableForeignKeyConstraints();
        try {
            $ordered = self::dependencyOrder();
            foreach (array_reverse($ordered) as $table) {
                DB::table($table)->delete();
            }
            $inBackup = array_keys($manifest['tables'] ?? []);
            foreach (array_values(array_intersect($ordered, $inBackup)) as $table) {
                if ($table === 'migrations' || ! Schema::hasTable($table)) {
                    continue;
                }
                $columns = array_flip(Schema::getColumnListing($table));
                $stream = $zip->getStream("database/{$table}.jsonl");
                if (! $stream) {
                    continue;
                }
                $batch = [];
                $n = 0;
                while (($line = fgets($stream)) !== false) {
                    $row = json_decode($line, true);
                    if (! is_array($row)) {
                        continue;
                    }
                    $batch[] = array_intersect_key($row, $columns);
                    if (count($batch) >= 200) {
                        DB::table($table)->insert($batch);
                        $n += count($batch);
                        $batch = [];
                    }
                }
                if ($batch) {
                    DB::table($table)->insert($batch);
                    $n += count($batch);
                }
                fclose($stream);
                $this->line("{$table}: {$n}");
            }
        } finally {
            Schema::enableForeignKeyConstraints();
        }

        $mediaRoot = \Illuminate\Support\Facades\Storage::disk('local')->path('media');
        File::deleteDirectory($mediaRoot);
        File::ensureDirectoryExists($mediaRoot, 0750);
        $restored = 0;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (! str_starts_with($name, 'media/') || str_ends_with($name, '/') || str_contains($name, '..')) {
                continue;
            }
            $target = $mediaRoot.'/'.substr($name, 6);
            File::ensureDirectoryExists(dirname($target), 0750);
            file_put_contents($target, $zip->getFromIndex($i));
            $restored++;
        }
        $zip->close();
        $this->info("Restauração concluída. {$restored} arquivos de mídia restaurados.");

        return self::SUCCESS;
    }

    /**
     * Tabelas na ordem em que podem ser preenchidas sem violar chaves estrangeiras
     * (as referenciadas antes das que referenciam).
     *
     * @return list<string>
     */
    public static function dependencyOrder(): array
    {
        $tables = array_values(array_filter(
            Schema::getTableListing(schemaQualified: false),
            fn ($t) => $t !== 'migrations' && ! str_starts_with($t, 'sqlite_')
        ));
        $deps = [];
        foreach ($tables as $table) {
            $deps[$table] = [];
            foreach (Schema::getForeignKeys($table) as $fk) {
                $foreign = $fk['foreign_table'];
                if ($foreign !== $table && in_array($foreign, $tables, true)) {
                    $deps[$table][] = $foreign;
                }
            }
        }
        $ordered = [];
        $visit = function (string $t, array $stack = []) use (&$visit, &$ordered, $deps) {
            if (in_array($t, $ordered, true) || in_array($t, $stack, true)) {
                return;
            }
            foreach ($deps[$t] as $d) {
                $visit($d, [...$stack, $t]);
            }
            $ordered[] = $t;
        };
        foreach ($tables as $t) {
            $visit($t);
        }

        return $ordered;
    }
}
