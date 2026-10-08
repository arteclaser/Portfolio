<?php

use Illuminate\Support\Facades\Schedule;

// Alternativa ao cron direto do backup: com "php artisan schedule:run" executado de hora em hora
// (no minuto 0), esta tarefa roda diariamente às 03:00. Ver docs/IMPLANTACAO-HOSTGATOR.md.
Schedule::command('mostraqui:backup --manter=14')->dailyAt('03:00');
