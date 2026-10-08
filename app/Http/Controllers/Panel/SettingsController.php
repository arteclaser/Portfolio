<?php

namespace App\Http\Controllers\Panel;

use App\Support\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class SettingsController extends PanelController
{
    public function edit()
    {
        Gate::authorize('manage-settings');

        return view('panel.settings', [
            'portfolio' => $this->portfolio(),
            'serverLimitBytes' => ActionMediaController::serverUploadLimit(),
        ]);
    }

    public function update(Request $request, ActivityLogger $log)
    {
        Gate::authorize('manage-settings');
        $portfolio = $this->portfolio();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'short_name' => ['nullable', 'string', 'max:60'],
            'tagline' => ['nullable', 'string', 'max:255'],
            'subtitle' => ['nullable', 'string', 'max:255'],
            'hero_kicker' => ['nullable', 'string', 'max:255'],
            'hero_title' => ['nullable', 'string', 'max:255'],
            'hero_highlight' => ['nullable', 'string', 'max:255'],
            'hero_description' => ['nullable', 'string', 'max:255'],
            'about' => ['nullable', 'string', 'max:10000'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'footer_note' => ['nullable', 'string', 'max:255'],
            'max_images_per_action' => ['required', 'integer', 'between:1,50'],
            'max_upload_mb' => ['required', 'numeric', 'between:0.5,50'],
            'activity_types' => ['nullable', 'string', 'max:3000'],
            'is_demo' => ['nullable', 'boolean'],
        ], [], ['name' => 'nome', 'max_images_per_action' => 'limite de imagens', 'max_upload_mb' => 'tamanho máximo']);

        $before = $portfolio->settings;
        $settings = $portfolio->settings ?? [];
        $settings['max_images_per_action'] = (int) $data['max_images_per_action'];
        $settings['max_upload_mb'] = (float) $data['max_upload_mb'];
        $settings['activity_types'] = array_values(array_unique(array_filter(array_map('trim', preg_split('/\R/', (string) ($data['activity_types'] ?? ''))))));

        $portfolio->fill(collect($data)->except(['max_images_per_action', 'max_upload_mb', 'activity_types', 'is_demo'])->all());
        $portfolio->settings = $settings;
        $portfolio->is_demo = $request->boolean('is_demo');
        $portfolio->save();
        $log->log($request->user(), 'settings.updated', $portfolio, 'Atualizou as configurações do portfólio', ['antes' => $before, 'depois' => $settings]);

        $warning = null;
        $server = ActionMediaController::serverUploadLimit();
        if ($server && $portfolio->maxUploadBytes() > $server) {
            $warning = 'Atenção: o PHP do servidor aceita no máximo '.round($server / 1048576, 1).' MB por envio (upload_max_filesize/post_max_size). Ajuste no cPanel para o limite valer.';
        }

        return redirect()->route('panel.settings.edit')->with('status', 'Configurações salvas.')->with('warning', $warning);
    }
}
