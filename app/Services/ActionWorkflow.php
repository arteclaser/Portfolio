<?php

namespace App\Services;

use App\Models\Action;
use App\Models\ActionVersion;
use App\Models\CustomField;
use App\Models\Page;
use App\Models\Portfolio;
use App\Models\User;
use App\Support\ActivityLogger;
use App\Support\PageTree;
use App\Support\Slug;
use App\Support\Text;
use App\Support\VersionDiff;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Fluxo editorial: cadastro → rascunho → revisão → publicação.
 *
 * A versão publicada nunca é editada. Editar uma ação publicada cria uma versão
 * de trabalho; o público continua vendo a versão aprovada até a nova aprovação.
 */
class ActionWorkflow
{
    public function __construct(
        private ActivityLogger $log,
        private PageTree $tree,
    ) {}

    public function create(Portfolio $portfolio, User $user, array $data): Action
    {
        return DB::transaction(function () use ($portfolio, $user, $data) {
            $action = Action::create(['portfolio_id' => $portfolio->id, 'created_by' => $user->id]);
            $version = new ActionVersion([
                'action_id' => $action->id,
                'number' => 1,
                'status' => 'draft',
                'author_id' => $user->id,
                'last_editor_id' => $user->id,
            ]);
            $this->fill($version, $data, $action);
            $version->save();
            $this->syncRelations($version, $data);

            $action->working_version_id = $version->id;
            $action->working_state = 'draft';
            $action->save();

            $this->log->log($user, 'action.created', $action, 'Criou o rascunho "'.$version->displayTitle().'"');

            return $action;
        });
    }

    /** Salva a versão de trabalho (rascunho incompleto é permitido). */
    public function save(Action $action, User $user, array $data): ActionVersion
    {
        $version = $this->requireWorking($action);

        return DB::transaction(function () use ($action, $version, $user, $data) {
            $before = VersionDiff::signature($version->fresh());
            $this->fill($version, $data, $action);
            $version->last_editor_id = $user->id;
            $version->save();
            $this->syncRelations($version, $data);
            $changed = VersionDiff::changedLabels($before, VersionDiff::signature($version->fresh()));

            $this->log->log($user, 'action.saved', $action, 'Salvou a versão '.$version->number, ['changed' => $changed]);

            return $version;
        });
    }

    /** Cria a versão de trabalho a partir da publicada (ou devolve a existente). */
    public function startWorkingCopy(Action $action, User $user): ActionVersion
    {
        if ($action->workingVersion) {
            return $action->workingVersion;
        }
        $published = $action->publishedVersion ?? throw ValidationException::withMessages(['action' => 'A ação não tem versão publicada.']);

        return DB::transaction(function () use ($action, $published, $user) {
            $copy = $this->cloneVersion($published, $user);
            $action->working_version_id = $copy->id;
            $action->working_state = 'draft';
            $action->save();
            $this->log->log($user, 'action.working_copy', $action, 'Criou a versão de trabalho '.$copy->number.' a partir da versão publicada');

            return $copy;
        });
    }

    public function submit(Action $action, User $user): void
    {
        $version = $this->requireWorking($action);
        $this->assertPublishable($action, $version);

        $version->status = 'in_review';
        $version->submitted_at = now();
        $version->submitted_by = $user->id;
        $version->review_notes = null;
        $version->save();
        $action->working_state = 'in_review';
        $action->save();

        $this->log->log($user, 'action.submitted', $action, 'Encaminhou a versão '.$version->number.' para revisão');
    }

    public function returnForChanges(Action $action, User $reviewer, string $notes): void
    {
        $version = $this->requireWorking($action);
        $version->status = 'returned';
        $version->reviewer_id = $reviewer->id;
        $version->reviewed_at = now();
        $version->review_notes = $notes;
        $version->save();
        $action->working_state = 'returned';
        $action->save();

        $this->log->log($reviewer, 'action.returned', $action, 'Devolveu a versão '.$version->number.' com observações');
    }

    public function publish(Action $action, User $reviewer, ?string $note = null): void
    {
        $version = $this->requireWorking($action);
        $this->assertPublishable($action, $version);

        DB::transaction(function () use ($action, $version, $reviewer, $note) {
            $previous = $action->publishedVersion;
            if ($previous) {
                $previous->status = 'superseded';
                $previous->save();
            }
            $version->status = 'published';
            $version->reviewer_id = $reviewer->id;
            $version->reviewed_at = now();
            $version->published_at = now();
            $version->published_by = $reviewer->id;
            if ($note) {
                $version->change_note = $note;
            }
            $version->save();

            $action->published_version_id = $version->id;
            $action->working_version_id = null;
            $action->working_state = null;
            $action->slug = $version->slug;
            $action->first_published_at ??= now();
            $action->save();

            $this->log->log($reviewer, 'action.published', $action, 'Publicou a versão '.$version->number.' de "'.$version->displayTitle().'"');
        });
    }

    /** Descarta a versão de trabalho de uma ação publicada (a versão pública permanece). */
    public function discardWorking(Action $action, User $user): void
    {
        $version = $this->requireWorking($action);
        if (! $action->isPublished()) {
            throw ValidationException::withMessages(['action' => 'Rascunhos nunca publicados vão para a lixeira em vez de serem descartados.']);
        }
        $version->status = 'discarded';
        $version->save();
        $action->working_version_id = null;
        $action->working_state = null;
        $action->save();

        $this->log->log($user, 'action.discarded', $action, 'Descartou a versão de trabalho '.$version->number);
    }

    /** Restaura o conteúdo de uma versão antiga como nova versão de trabalho. */
    public function restoreVersion(Action $action, ActionVersion $source, User $user): ActionVersion
    {
        if ($source->action_id !== $action->id) {
            abort(404);
        }
        if ($action->workingVersion) {
            throw ValidationException::withMessages(['action' => 'Já existe uma versão de trabalho. Publique-a ou descarte-a antes de restaurar outra versão.']);
        }

        return DB::transaction(function () use ($action, $source, $user) {
            $copy = $this->cloneVersion($source, $user);
            $copy->restored_from_version_id = $source->id;
            $copy->save();
            $action->working_version_id = $copy->id;
            $action->working_state = 'draft';
            $action->save();
            $this->log->log($user, 'action.restored_version', $action, 'Restaurou o conteúdo da versão '.$source->number.' como versão de trabalho '.$copy->number);

            return $copy;
        });
    }

    public function archive(Action $action, User $user): void
    {
        $action->archived_at = now();
        $action->archived_by = $user->id;
        $action->save();
        $this->log->log($user, 'action.archived', $action, 'Arquivou a ação');
    }

    public function unarchive(Action $action, User $user): void
    {
        $action->archived_at = null;
        $action->archived_by = null;
        $action->save();
        $this->log->log($user, 'action.unarchived', $action, 'Retirou a ação do arquivo');
    }

    public function trash(Action $action, User $user): void
    {
        $action->deleted_by = $user->id;
        $action->save();
        $action->delete();
        $this->log->log($user, 'action.trashed', $action, 'Moveu a ação para a lixeira');
    }

    public function restoreFromTrash(Action $action, User $user): void
    {
        $action->restore();
        $action->deleted_by = null;
        $action->save();
        $this->log->log($user, 'action.untrashed', $action, 'Restaurou a ação da lixeira');
    }

    public function forceDelete(Action $action, User $user): void
    {
        $title = $action->currentVersion()?->displayTitle() ?? 'Ação';
        $id = $action->id;
        DB::transaction(fn () => $action->forceDelete());
        $this->log->log($user, 'action.force_deleted', null, 'Excluiu permanentemente "'.$title.'" (#'.$id.')');
    }

    /** @return array<string, string> campo do formulário => mensagem */
    public function publicationErrors(Action $action, ActionVersion $version): array
    {
        $errors = [];
        if (blank($version->title)) {
            $errors['title'] = 'Informe o nome da ação para publicar.';
        }
        if (blank($version->summary)) {
            $errors['summary'] = 'Informe o resumo para publicar.';
        }
        if (blank($version->description)) {
            $errors['description'] = 'Informe a descrição para publicar.';
        }
        $primary = $version->primary_page_id ? Page::find($version->primary_page_id) : null;
        if (! $primary || $primary->isArchived()) {
            $errors['primary_page_id'] = 'Escolha uma área principal ativa para publicar.';
        }
        if (! $version->starts_on) {
            $errors['starts_on'] = 'Informe a data ou o início do período para publicar.';
        } elseif ($version->ends_on && $version->ends_on->lt($version->starts_on)) {
            $errors['ends_on'] = 'A data de término deve ser igual ou posterior à de início.';
        }
        if ($version->slug && Action::withTrashed()->where('portfolio_id', $action->portfolio_id)
            ->where('slug', $version->slug)->where('id', '!=', $action->id)->exists()) {
            $errors['slug'] = 'Este endereço já é usado por outra ação.';
        }
        if ($primary) {
            $values = $version->fieldValues()->pluck('value', 'custom_field_id');
            foreach ($this->fieldsForPage($primary->id) as $field) {
                if ($field->is_required && blank($values[$field->id] ?? null)) {
                    $errors['fields.'.$field->id] = "Preencha \"{$field->label}\" para publicar.";
                }
            }
        }

        return $errors;
    }

    public function assertPublishable(Action $action, ActionVersion $version): void
    {
        $errors = $this->publicationErrors($action, $version);
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * Campos adicionais ativos que se aplicam a uma área: os da própria área e os
     * das páginas acima dela.
     *
     * @return \Illuminate\Support\Collection<int, CustomField>
     */
    public function fieldsForPage(?int $pageId)
    {
        if (! $pageId) {
            return collect();
        }
        $ids = array_merge([$pageId], $this->tree->ancestorIds($pageId));

        return CustomField::query()->active()->whereIn('page_id', $ids)->orderBy('position')->orderBy('id')->get();
    }

    private function requireWorking(Action $action): ActionVersion
    {
        $action->loadMissing('workingVersion');

        return $action->workingVersion ?? throw ValidationException::withMessages([
            'action' => 'Esta ação não tem versão de trabalho. Clique em "Editar" para criar uma.',
        ]);
    }

    private function fill(ActionVersion $version, array $data, Action $action): void
    {
        foreach (['title', 'summary', 'description', 'location', 'activity_type', 'activity_status', 'objectives', 'results', 'change_note'] as $field) {
            if (array_key_exists($field, $data)) {
                $value = is_string($data[$field]) ? trim($data[$field]) : $data[$field];
                $version->{$field} = $value === '' ? null : $value;
            }
        }
        foreach (['primary_page_id', 'program_page_id'] as $field) {
            if (array_key_exists($field, $data)) {
                $version->{$field} = $data[$field] ? (int) $data[$field] : null;
            }
        }
        foreach (['starts_on', 'ends_on'] as $field) {
            if (array_key_exists($field, $data)) {
                $version->{$field} = $data[$field] ?: null;
            }
        }
        if (array_key_exists('is_featured', $data)) {
            $version->is_featured = (bool) $data['is_featured'];
        }

        $requested = array_key_exists('slug', $data) ? trim((string) $data['slug']) : (string) $version->slug;
        $base = Slug::make($requested !== '' ? $requested : $version->title, 'acao');
        if ($requested !== '' || blank($version->slug) || ! $action->isPublished()) {
            $version->slug = Slug::unique($base, fn ($slug) => Action::withTrashed()
                ->where('portfolio_id', $action->portfolio_id)->where('slug', $slug)
                ->where('id', '!=', $action->id ?? 0)->exists());
        }

        $version->search_text = Text::searchable($version->title, $version->summary, $version->description, $version->location, $version->activity_type);
    }

    private function syncRelations(ActionVersion $version, array $data): void
    {
        if (array_key_exists('related_page_ids', $data)) {
            $ids = array_values(array_unique(array_filter(array_map('intval', (array) $data['related_page_ids']))));
            $ids = array_values(array_diff($ids, [(int) $version->primary_page_id]));
            $version->relatedPages()->sync($ids);
        }
        if (array_key_exists('team', $data)) {
            $sync = [];
            $position = 0;
            foreach ((array) $data['team'] as $row) {
                $id = (int) ($row['id'] ?? 0);
                if ($id && ! isset($sync[$id])) {
                    $sync[$id] = ['role_in_action' => trim((string) ($row['role'] ?? '')) ?: null, 'position' => $position++];
                }
            }
            $version->teamMembers()->sync($sync);
        }
        if (array_key_exists('partner_ids', $data)) {
            $version->partners()->sync(array_values(array_unique(array_filter(array_map('intval', (array) $data['partner_ids'])))));
        }
        if (array_key_exists('indicators', $data)) {
            $version->indicators()->delete();
            $position = 0;
            foreach ((array) $data['indicators'] as $row) {
                if (blank($row['label'] ?? null) && blank($row['value'] ?? null)) {
                    continue;
                }
                $version->indicators()->create([
                    'label' => trim((string) ($row['label'] ?? '')),
                    'value' => ($row['value'] ?? '') === '' ? null : $row['value'],
                    'unit' => trim((string) ($row['unit'] ?? '')) ?: null,
                    'period' => trim((string) ($row['period'] ?? '')) ?: null,
                    'source' => trim((string) ($row['source'] ?? '')) ?: null,
                    'is_participation' => ! empty($row['is_participation']),
                    'position' => $position++,
                ]);
            }
        }
        if (array_key_exists('fields', $data)) {
            foreach ((array) $data['fields'] as $fieldId => $value) {
                $value = is_string($value) ? trim($value) : $value;
                $version->fieldValues()->updateOrCreate(
                    ['custom_field_id' => (int) $fieldId],
                    ['value' => $value === '' ? null : $value],
                );
            }
        }
    }

    public function cloneVersion(ActionVersion $source, User $author): ActionVersion
    {
        $source->loadMissing(['relatedPages', 'teamMembers', 'partners', 'indicators', 'media', 'links', 'fieldValues']);
        $number = (int) ActionVersion::where('action_id', $source->action_id)->max('number') + 1;

        $copy = $source->replicate(['status', 'submitted_at', 'submitted_by', 'reviewed_at', 'reviewer_id', 'review_notes', 'published_at', 'published_by', 'change_note', 'restored_from_version_id']);
        $copy->number = $number;
        $copy->status = 'draft';
        $copy->author_id = $author->id;
        $copy->last_editor_id = $author->id;
        $copy->save();

        $copy->relatedPages()->sync($source->relatedPages->pluck('id')->all());
        $team = [];
        foreach ($source->teamMembers as $member) {
            $team[$member->id] = ['role_in_action' => $member->pivot->role_in_action, 'position' => $member->pivot->position];
        }
        $copy->teamMembers()->sync($team);
        $copy->partners()->sync($source->partners->pluck('id')->all());
        foreach ($source->indicators as $indicator) {
            $copy->indicators()->create($indicator->only(['label', 'value', 'unit', 'period', 'source', 'is_participation', 'position']));
        }
        foreach ($source->media as $item) {
            $copy->media()->create($item->only(['media_id', 'position', 'is_cover', 'caption', 'credit', 'alt', 'focal_x', 'focal_y']));
        }
        foreach ($source->links as $link) {
            $copy->links()->create($link->only(['url', 'provider', 'kind', 'embed_id', 'title', 'description', 'source_name', 'image_media_id', 'suggested_image_url', 'preview_status', 'preview_message', 'fetched_at', 'position']));
        }
        foreach ($source->fieldValues as $value) {
            $copy->fieldValues()->create(['custom_field_id' => $value->custom_field_id, 'value' => $value->value]);
        }

        return $copy;
    }
}
