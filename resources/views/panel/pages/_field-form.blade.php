<div class="grid-2">
    <x-field name="label" :id="$prefix.'-label'" label="Rótulo" :value="$f->label" required :use-old="$prefix === 'new'" />
    <x-field name="type" :id="$prefix.'-type'" label="Tipo" type="select" :options="\App\Models\CustomField::TYPES" :value="$f->type" :use-old="$prefix === 'new'" />
</div>
<x-field name="help" :id="$prefix.'-help'" label="Texto de ajuda" :value="$f->help" :use-old="$prefix === 'new'" />
<x-field name="options_text" :id="$prefix.'-options'" label="Opções (para seleção, uma por linha)" type="textarea" rows="3" :value="implode(PHP_EOL, $f->optionList())" :use-old="$prefix === 'new'" />
<div class="grid-3">
    <x-field name="position" :id="$prefix.'-position'" label="Ordem" type="number" min="0" :value="$f->position ?? 0" :use-old="$prefix === 'new'" />
    <div class="field--check" style="align-self:end"><input type="checkbox" id="{{ $prefix }}-req" name="is_required" value="1" @checked($f->is_required)><label for="{{ $prefix }}-req">Obrigatório para publicar</label></div>
    <div class="field--check" style="align-self:end"><input type="checkbox" id="{{ $prefix }}-pub" name="is_public" value="1" @checked($f->is_public)><label for="{{ $prefix }}-pub">Exibir no site</label></div>
</div>
