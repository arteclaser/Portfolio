<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('portfolios', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('short_name', 60)->nullable();
            $table->string('tagline')->nullable();
            $table->string('subtitle')->nullable();
            $table->string('hero_kicker')->nullable();
            $table->string('hero_title')->nullable();
            $table->string('hero_highlight')->nullable();
            $table->string('hero_description')->nullable();
            $table->text('about')->nullable();
            $table->string('accent_color', 7)->default('#2155E8');
            $table->string('contact_email')->nullable();
            $table->string('footer_note')->nullable();
            // Limites de mídia, tipos de atividade e outras escolhas de produto.
            $table->json('settings')->nullable();
            // Marca o ambiente como demonstração (conteúdo ilustrativo).
            $table->boolean('is_demo')->default(false);
            $table->timestamps();
        });

        Schema::create('media', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('portfolio_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            // image | document
            $table->string('kind', 20)->default('image');
            $table->string('disk', 40)->default('local');
            $table->string('directory');
            $table->string('mime', 100);
            $table->unsignedBigInteger('size');
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->string('original_name')->nullable();
            $table->json('variants')->nullable();
            $table->string('alt', 500)->nullable();
            $table->string('caption', 500)->nullable();
            $table->string('credit')->nullable();
            $table->unsignedTinyInteger('focal_x')->default(50);
            $table->unsignedTinyInteger('focal_y')->default(50);
            // Origem externa (miniatura de link escolhida pela equipe).
            $table->string('source_url', 2048)->nullable();
            $table->timestamps();
        });

        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('portfolio_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('pages')->nullOnDelete();
            // area | programa | projeto | pagina
            $table->string('kind', 20)->default('area');
            $table->string('title');
            $table->string('slug');
            $table->string('menu_title')->nullable();
            $table->text('description')->nullable();
            $table->foreignId('cover_media_id')->nullable()->constrained('media')->nullOnDelete();
            $table->string('accent_color', 7)->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->boolean('show_in_menu')->default(true);
            // Conteúdo aprovado que o público vê. As edições ficam nos blocos de trabalho.
            $table->json('published_snapshot')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('has_unpublished_changes')->default(false);
            $table->timestamp('archived_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['portfolio_id', 'slug']);
        });

        Schema::create('page_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('page_id')->constrained()->cascadeOnDelete();
            $table->string('type', 40);
            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_hidden')->default(false);
            $table->json('settings')->nullable();
            $table->timestamps();
        });

        Schema::create('page_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('page_id')->constrained()->cascadeOnDelete();
            $table->json('snapshot');
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('note')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('page_user', function (Blueprint $table) {
            $table->foreignId('page_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['page_id', 'user_id']);
        });

        Schema::create('custom_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('page_id')->constrained()->cascadeOnDelete();
            $table->string('key', 80);
            $table->string('label');
            $table->string('help', 500)->nullable();
            // text | number | date | select | url
            $table->string('type', 20);
            $table->json('options')->nullable();
            $table->boolean('is_required')->default(false);
            $table->boolean('is_public')->default(true);
            $table->unsignedInteger('position')->default(0);
            // Campos com valores registrados são arquivados, nunca apagados.
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
            $table->unique(['page_id', 'key']);
        });

        Schema::create('team_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('portfolio_id')->constrained()->cascadeOnDelete();
            // Vínculo opcional com a conta de acesso. O cadastro institucional é independente.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('page_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('role_title')->nullable();
            $table->string('function')->nullable();
            $table->foreignId('photo_media_id')->nullable()->constrained('media')->nullOnDelete();
            $table->text('bio')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('contact_phone', 40)->nullable();
            $table->boolean('contact_is_public')->default(false);
            $table->date('started_on')->nullable();
            $table->date('ended_on')->nullable();
            $table->boolean('is_public')->default(true);
            $table->unsignedInteger('position')->default(0);
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
        });

        Schema::create('page_team_member', function (Blueprint $table) {
            $table->foreignId('page_id')->constrained()->cascadeOnDelete();
            $table->foreignId('team_member_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('position')->default(0);
            $table->primary(['page_id', 'team_member_id']);
        });

        Schema::create('partners', function (Blueprint $table) {
            $table->id();
            $table->foreignId('portfolio_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('url', 2048)->nullable();
            $table->foreignId('logo_media_id')->nullable()->constrained('media')->nullOnDelete();
            $table->text('description')->nullable();
            $table->boolean('is_public')->default(true);
            $table->unsignedInteger('position')->default(0);
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
        });

        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event', 80);
            $table->string('subject_type', 80)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('description', 500)->nullable();
            $table->json('properties')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->nullable();
            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
        Schema::dropIfExists('partners');
        Schema::dropIfExists('page_team_member');
        Schema::dropIfExists('team_members');
        Schema::dropIfExists('custom_fields');
        Schema::dropIfExists('page_user');
        Schema::dropIfExists('page_revisions');
        Schema::dropIfExists('page_blocks');
        Schema::dropIfExists('pages');
        Schema::dropIfExists('media');
        Schema::dropIfExists('portfolios');
    }
};
