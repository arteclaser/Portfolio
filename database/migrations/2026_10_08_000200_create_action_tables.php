<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Identidade estável da ação. O conteúdo fica em action_versions.
        Schema::create('actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('portfolio_id')->constrained()->cascadeOnDelete();
            // Endereço público vigente (copiado da versão publicada).
            $table->string('slug')->nullable();
            $table->unsignedBigInteger('published_version_id')->nullable()->index();
            $table->unsignedBigInteger('working_version_id')->nullable()->index();
            // Situação editorial da versão de trabalho: draft | in_review | returned
            $table->string('working_state', 20)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('first_published_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->foreignId('archived_by')->nullable()->constrained('users')->nullOnDelete();
            // Lixeira (exclusão reversível).
            $table->softDeletes();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['portfolio_id', 'slug']);
        });

        Schema::create('action_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('action_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('number');
            // draft | in_review | returned | published | superseded | discarded
            $table->string('status', 20)->default('draft');
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('last_editor_id')->nullable()->constrained('users')->nullOnDelete();

            // Identificação
            $table->string('title')->nullable();
            $table->string('slug')->nullable();
            $table->text('summary')->nullable();
            $table->longText('description')->nullable();
            $table->foreignId('primary_page_id')->nullable()->constrained('pages')->nullOnDelete();
            $table->foreignId('program_page_id')->nullable()->constrained('pages')->nullOnDelete();

            // Contexto
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->string('location')->nullable();
            $table->string('activity_type', 80)->nullable();
            // planejada | em_andamento | concluida | cancelada
            $table->string('activity_status', 20)->nullable();

            // Resultados
            $table->text('objectives')->nullable();
            $table->text('results')->nullable();

            // Publicação
            $table->boolean('is_featured')->default(false);
            $table->string('change_note', 500)->nullable();
            $table->unsignedBigInteger('restored_from_version_id')->nullable();
            $table->text('search_text')->nullable();

            // Revisão
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('review_notes')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['action_id', 'number']);
            $table->index('starts_on');
        });

        Schema::create('action_version_page', function (Blueprint $table) {
            $table->foreignId('action_version_id')->constrained()->cascadeOnDelete();
            $table->foreignId('page_id')->constrained()->cascadeOnDelete();
            $table->primary(['action_version_id', 'page_id']);
        });

        Schema::create('action_version_team_member', function (Blueprint $table) {
            $table->foreignId('action_version_id')->constrained()->cascadeOnDelete();
            $table->foreignId('team_member_id')->constrained()->cascadeOnDelete();
            $table->string('role_in_action')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->primary(['action_version_id', 'team_member_id'], 'avtm_primary');
        });

        Schema::create('action_version_partner', function (Blueprint $table) {
            $table->foreignId('action_version_id')->constrained()->cascadeOnDelete();
            $table->foreignId('partner_id')->constrained()->cascadeOnDelete();
            $table->primary(['action_version_id', 'partner_id']);
        });

        Schema::create('action_indicators', function (Blueprint $table) {
            $table->id();
            $table->foreignId('action_version_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->decimal('value', 18, 2)->nullable();
            $table->string('unit', 80)->nullable();
            $table->string('period', 120)->nullable();
            $table->string('source', 500)->nullable();
            // Contagem de participações (não representa pessoas únicas).
            $table->boolean('is_participation')->default(false);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('action_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('action_version_id')->constrained()->cascadeOnDelete();
            $table->foreignId('media_id')->constrained('media')->cascadeOnDelete();
            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_cover')->default(false);
            $table->string('caption', 500)->nullable();
            $table->string('credit')->nullable();
            $table->string('alt', 500)->nullable();
            $table->unsignedTinyInteger('focal_x')->default(50);
            $table->unsignedTinyInteger('focal_y')->default(50);
            $table->timestamps();
        });

        Schema::create('action_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('action_version_id')->constrained()->cascadeOnDelete();
            $table->string('url', 2048);
            // youtube | instagram | facebook | site
            $table->string('provider', 30)->default('site');
            // video | reportagem | publicacao | link
            $table->string('kind', 30)->default('link');
            $table->string('embed_id', 120)->nullable();
            $table->string('title', 500)->nullable();
            $table->text('description')->nullable();
            $table->string('source_name')->nullable();
            $table->foreignId('image_media_id')->nullable()->constrained('media')->nullOnDelete();
            // URL de imagem sugerida pelos metadados do site (não é baixada sem escolha da equipe).
            $table->string('suggested_image_url', 2048)->nullable();
            // ok | partial | failed | manual
            $table->string('preview_status', 20)->default('manual');
            $table->string('preview_message', 500)->nullable();
            $table->timestamp('fetched_at')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('action_field_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('action_version_id')->constrained()->cascadeOnDelete();
            $table->foreignId('custom_field_id')->constrained()->cascadeOnDelete();
            $table->text('value')->nullable();
            $table->unique(['action_version_id', 'custom_field_id'], 'afv_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('action_field_values');
        Schema::dropIfExists('action_links');
        Schema::dropIfExists('action_media');
        Schema::dropIfExists('action_indicators');
        Schema::dropIfExists('action_version_partner');
        Schema::dropIfExists('action_version_team_member');
        Schema::dropIfExists('action_version_page');
        Schema::dropIfExists('action_versions');
        Schema::dropIfExists('actions');
    }
};
