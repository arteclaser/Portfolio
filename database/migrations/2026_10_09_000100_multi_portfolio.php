<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Vários portfólios na mesma instalação (mostraqui.net/capinzal ou capinzal.mostraqui.net).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('portfolios', function (Blueprint $table) {
            // Desativado: some do site e bloqueia o painel da equipe do portfólio.
            $table->boolean('is_active')->default(true)->after('is_demo');
            // Listado na página inicial da plataforma.
            $table->boolean('is_listed')->default(true)->after('is_active');
            $table->foreignId('created_by')->nullable()->after('is_listed')->constrained('users')->nullOnDelete();
        });

        Schema::table('users', function (Blueprint $table) {
            // Portfólio ao qual a conta pertence. Nulo apenas para administradores da plataforma.
            $table->foreignId('portfolio_id')->nullable()->after('id')->constrained()->nullOnDelete();
            // Administra a plataforma: cria portfólios e atua como Master em qualquer um deles.
            $table->boolean('is_platform_admin')->default(false)->after('role');
        });

        Schema::table('activity_logs', function (Blueprint $table) {
            $table->foreignId('portfolio_id')->nullable()->after('id')->constrained()->nullOnDelete();
        });

        // Instalações anteriores (um portfólio só): tudo passa a pertencer ao primeiro portfólio.
        $first = DB::table('portfolios')->orderBy('id')->value('id');
        if ($first) {
            DB::table('users')->whereNull('portfolio_id')->update(['portfolio_id' => $first]);
            DB::table('activity_logs')->whereNull('portfolio_id')->update(['portfolio_id' => $first]);
        }
    }

    public function down(): void
    {
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('portfolio_id');
        });
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('portfolio_id');
            $table->dropColumn('is_platform_admin');
        });
        Schema::table('portfolios', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by');
            $table->dropColumn(['is_active', 'is_listed']);
        });
    }
};
