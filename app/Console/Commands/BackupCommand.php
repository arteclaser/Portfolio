<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use ZipArchive;

/**
 * Cópia de segurança completa em um único .zip: todas as tabelas (JSON) e os
 * arquivos de mídia. Funciona sem mysqldump, o que é útil em hospedagem compartilhada.
 */
class BackupCommand extends Command
{
    protected $signature = 'mostraqui:backup {--manter=14 : Quantas cópias manter} {--destino= : Pasta de destino}';

    protected $description = 'Gera cópia de segurança do banco de dados e dos arquivos.';

    public const SKIP_TABLES = ['sessions', 'cache', 'cache_locks', 'password_reset_tokens', 'jobs', 'job_batches', 'failed_jobs'];

    public function handle(): int
    {
        if (! class_exists(ZipArchive::class)) {
            $this->error('A extensão zip do PHP não está disponível.');

            return self::FAILURE;
        }
        $dir = $this->option('destino') ?: storage_path('app/backups');
        File::ensureDirectoryExists($dir, 0750);
        $file = $dir.'/backup-'.now()->format('Ymd-His').'.zip';

        $zip = new ZipArchive;
        if ($zip->open($file, ZipArchive::CREATE | ZipArchive::EXCL) !== true) {
            $this->error('Não foi possível criar o arquivo '.$file);

            return self::FAILURE;
        }

        $counts = [];
        $tmpFiles = [];
        foreach ($this->tables() as $table) {
            $tmp = tempnam(sys_get_temp_dir(), 'bkp');
            $tmpFiles[] = $tmp;
            $handle = fopen($tmp, 'wb');
            $count = 0;
            $order = Schema::hasColumn($table, 'id') ? 'id' : Schema::getColumnListing($table)[0];
            DB::table($table)->orderBy($order)->chunk(500, function ($rows) use ($handle, &$count) {
                foreach ($rows as $row) {
                    fwrite($handle, json_encode((array) $row, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE)."\n");
                    $count++;
                }
            });
            fclose($handle);
            $zip->addFile($tmp, "database/{$table}.jsonl");
            $counts[$table] = $count;
        }

        $mediaRoot = \Illuminate\Support\Facades\Storage::disk('local')->path('media');
        $files = 0;
        if (is_dir($mediaRoot)) {
            foreach (File::allFiles($mediaRoot) as $f) {
                $zip->addFile($f->getPathname(), 'media/'.str_replace('\\', '/', $f->getRelativePathname()));
                $files++;
            }
        }

        $zip->addFromString('manifest.json', json_encode([
            'app' => 'mostraqui-portfolio',
            'format' => 1,
            'created_at' => now()->toIso8601String(),
            'laravel' => app()->version(),
            'database_driver' => DB::connection()->getDriverName(),
            'tables' => $counts,
            'media_files' => $files,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $zip->close();
        foreach ($tmpFiles as $tmp) {
            @unlink($tmp);
        }
        @chmod($file, 0640);

        $this->info('Cópia criada: '.$file.' ('.round(filesize($file) / 1048576, 2).' MB, '.array_sum($counts).' registros, '.$files.' arquivos)');
        $this->prune($dir, max(1, (int) $this->option('manter')));

        return self::SUCCESS;
    }

    /** @return list<string> */
    private function tables(): array
    {
        $tables = array_map(fn ($t) => is_array($t) ? ($t['name'] ?? reset($t)) : $t, Schema::getTableListing(schemaQualified: false));

        return array_values(array_filter($tables, fn ($t) => ! in_array($t, self::SKIP_TABLES, true) && ! str_starts_with($t, 'sqlite_')));
    }

    private function prune(string $dir, int $keep): void
    {
        $files = glob($dir.'/backup-*.zip') ?: [];
        rsort($files);
        foreach (array_slice($files, $keep) as $old) {
            @unlink($old);
            $this->line('Cópia antiga removida: '.basename($old));
        }
    }
}
