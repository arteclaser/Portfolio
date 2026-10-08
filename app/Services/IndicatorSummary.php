<?php

namespace App\Services;

use App\Models\ActionIndicator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * Consolida indicadores sem inventar nem distorcer números:
 * - só entram indicadores preenchidos e documentados (com fonte);
 * - somas apenas entre o mesmo indicador e a mesma unidade;
 * - contagens de participações são identificadas como tal, não como pessoas únicas;
 * - cada ação conta uma única vez, mesmo associada a várias áreas.
 */
class IndicatorSummary
{
    /**
     * @param  Builder  $publicVersions  consulta de PublicActions (versões publicadas)
     * @return array{action_count: int, groups: list<array{label: string, unit: ?string, total: float, actions: int, is_participation: bool}>}
     */
    public function summarize(Builder $publicVersions): array
    {
        $versionIds = (clone $publicVersions)->pluck('action_versions.id')->unique()->values()->all();

        $groups = [];
        if ($versionIds) {
            $indicators = ActionIndicator::query()
                ->whereIn('action_version_id', $versionIds)
                ->whereNotNull('value')
                ->whereNotNull('source')->where('source', '!=', '')
                ->get();

            foreach ($indicators as $indicator) {
                $unit = trim((string) $indicator->unit);
                $key = Str::lower(Str::ascii(trim($indicator->label))).'|'.Str::lower(Str::ascii($unit)).'|'.(int) $indicator->is_participation;
                $groups[$key] ??= [
                    'label' => trim($indicator->label),
                    'unit' => $unit !== '' ? $unit : null,
                    'total' => 0.0,
                    'versions' => [],
                    'is_participation' => (bool) $indicator->is_participation,
                ];
                $groups[$key]['total'] += (float) $indicator->value;
                $groups[$key]['versions'][$indicator->action_version_id] = true;
            }
        }

        $result = [];
        foreach ($groups as $group) {
            $group['actions'] = count($group['versions']);
            unset($group['versions']);
            $result[] = $group;
        }
        usort($result, fn ($a, $b) => [$b['actions'], $a['label']] <=> [$a['actions'], $b['label']]);

        return ['action_count' => count($versionIds), 'groups' => $result];
    }
}
