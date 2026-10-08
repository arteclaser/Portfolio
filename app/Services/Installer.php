<?php

namespace App\Services;

use App\Models\Page;
use App\Models\Portfolio;
use App\Models\User;
use App\Support\ActivityLogger;
use App\Support\Slug;
use Illuminate\Support\Facades\DB;

class Installer
{
    public const INITIAL_AREAS = [
        'Desenvolvimento Econômico' => '#2155E8',
        'Inovação' => '#1B47C9',
        'Turismo' => '#067647',
    ];

    public function __construct(private ActivityLogger $log) {}

    public function ensurePortfolio(string $name, ?string $shortName = null, bool $createAreas = true, bool $demo = false): Portfolio
    {
        $portfolio = Portfolio::current();
        if ($portfolio) {
            return $portfolio;
        }

        return DB::transaction(function () use ($name, $shortName, $createAreas, $demo) {
            $portfolio = Portfolio::create([
                'name' => $name,
                'slug' => Slug::make($shortName ?: $name, 'portfolio'),
                'short_name' => $shortName,
                'tagline' => 'Portfólio da Secretaria',
                'subtitle' => 'de Desenvolvimento Econômico, Inovação e Turismo',
                'hero_kicker' => 'Memória • Projetos • Resultados',
                'hero_title' => 'Ações que transformam',
                'hero_highlight' => $shortName ? $shortName.'.' : null,
                'hero_description' => 'Desenvolvimento Econômico, Inovação e Turismo',
                'accent_color' => '#2155E8',
                'settings' => Portfolio::DEFAULT_SETTINGS,
                'is_demo' => $demo,
            ]);
            Portfolio::forgetCurrent();

            if ($createAreas) {
                $position = 0;
                foreach (self::INITIAL_AREAS as $title => $color) {
                    $page = Page::create([
                        'portfolio_id' => $portfolio->id,
                        'kind' => 'area',
                        'title' => $title,
                        'slug' => Slug::make($title),
                        'accent_color' => $color,
                        'position' => $position++,
                    ]);
                    // Área publicada com conteúdo mínimo; o Master edita depois.
                    $page->blocks()->create(['type' => 'lista_acoes', 'position' => 0, 'settings' => ['heading' => 'Ações', 'limit' => 9]]);
                    app(PagePublisher::class)->publish($page, null, 'Publicação inicial');
                }
            }

            return $portfolio;
        });
    }

    public function createMaster(string $name, string $email, string $password): User
    {
        $user = new User;
        $user->forceFill([
            'name' => $name,
            'email' => mb_strtolower(trim($email)),
            'password' => $password,
            'role' => User::MASTER,
            'is_active' => true,
            'password_set_at' => now(),
        ])->save();
        $this->log->log($user, 'setup.master_created', $user, 'Primeiro Administrador Master criado na instalação');

        return $user;
    }
}
