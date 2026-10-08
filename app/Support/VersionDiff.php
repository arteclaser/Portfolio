<?php

namespace App\Support;

use App\Models\ActionVersion;

/** Compara versões para registrar e exibir quais partes mudaram. */
class VersionDiff
{
    /** @return array<string, mixed> */
    public static function signature(?ActionVersion $version): array
    {
        if (! $version) {
            return [];
        }
        $version->loadMissing(['relatedPages', 'teamMembers', 'partners', 'indicators', 'media', 'links', 'fieldValues']);
        $sig = [];
        foreach (array_keys(ActionVersion::CONTENT_FIELDS) as $field) {
            $value = $version->{$field};
            $sig[$field] = $value instanceof \DateTimeInterface ? $value->format('Y-m-d') : (is_bool($value) ? (int) $value : (string) $value);
        }
        $sig['related'] = $version->relatedPages->pluck('id')->sort()->values()->all();
        $sig['team'] = $version->teamMembers->map(fn ($m) => $m->id.':'.$m->pivot->role_in_action)->all();
        $sig['partners'] = $version->partners->pluck('id')->sort()->values()->all();
        $sig['indicators'] = $version->indicators->map(fn ($i) => [$i->label, (string) $i->value, $i->unit, $i->period, $i->source, (int) $i->is_participation])->all();
        $sig['media'] = $version->media->map(fn ($m) => [$m->media_id, (int) $m->is_cover, $m->position, $m->caption, $m->credit, $m->alt, $m->focal_x, $m->focal_y])->all();
        $sig['links'] = $version->links->map(fn ($l) => [$l->url, $l->title, $l->description, $l->source_name, $l->image_media_id, $l->position])->all();
        $sig['fields'] = $version->fieldValues->sortBy('custom_field_id')->map(fn ($v) => $v->custom_field_id.':'.$v->value)->values()->all();

        return $sig;
    }

    /** @return list<string> rótulos das partes alteradas */
    public static function changedLabels(array $before, array $after): array
    {
        $labels = ActionVersion::CONTENT_FIELDS + [
            'related' => 'Áreas relacionadas',
            'team' => 'Equipe responsável',
            'partners' => 'Instituições parceiras',
            'indicators' => 'Indicadores',
            'media' => 'Fotografias',
            'links' => 'Vídeos e reportagens',
            'fields' => 'Campos adicionais',
        ];
        $changed = [];
        foreach ($labels as $key => $label) {
            if (($before[$key] ?? null) != ($after[$key] ?? null)) {
                $changed[] = $label;
            }
        }

        return $changed;
    }

    /** @return list<string> */
    public static function between(?ActionVersion $older, ActionVersion $newer): array
    {
        return self::changedLabels(self::signature($older), self::signature($newer));
    }
}
