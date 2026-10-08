<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PageBlock extends Model
{
    public const TYPES = [
        'capa' => 'Capa',
        'apresentacao' => 'Apresentação',
        'texto' => 'Texto',
        'acoes_destaque' => 'Ações em destaque',
        'lista_acoes' => 'Lista de ações',
        'equipe' => 'Equipe',
        'galeria' => 'Galeria',
        'videos' => 'Vídeos',
        'reportagens' => 'Reportagens',
        'parceiros' => 'Parceiros',
        'indicadores' => 'Indicadores',
        'documentos' => 'Documentos',
    ];

    protected $fillable = ['page_id', 'type', 'position', 'is_hidden', 'settings'];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'is_hidden' => 'boolean',
        ];
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    public function setting(string $key, mixed $default = null): mixed
    {
        return data_get($this->settings ?? [], $key, $default);
    }
}
