<?php

namespace App\Services;

use App\Models\Page;
use App\Models\Portfolio;
use App\Models\User;
use App\Support\ActivityLogger;
use App\Support\PortfolioSlug;
use App\Support\Slug;
use App\Support\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use InvalidArgumentException;

/**
 * Cria portfólios e contas iniciais. Um portfólio novo fica disponível na hora,
 * em mostraqui.net/{endereço} ou {endereço}.mostraqui.net, sem configuração extra.
 */
class Installer
{
    public const INITIAL_AREAS = [
        'Desenvolvimento Econômico' => '#2155E8',
        'Inovação' => '#1B47C9',
        'Turismo' => '#067647',
    ];

    public function __construct(private ActivityLogger $log) {}

    /** Instalação inicial: cria o primeiro portfólio apenas se ainda não houver nenhum. */
    public function ensurePortfolio(string $name, ?string $shortName = null, bool $createAreas = true, bool $demo = false, ?string $slug = null): Portfolio
    {
        return Portfolio::query()->orderBy('id')->first()
            ?? $this->createPortfolio($name, $shortName, $slug, $createAreas ? array_keys(self::INITIAL_AREAS) : [], null, $demo);
    }

    /**
     * @param  list<string>  $areas  títulos das áreas iniciais (editáveis depois)
     */
    public function createPortfolio(string $name, ?string $shortName, ?string $slug, array $areas = [], ?User $creator = null, bool $demo = false): Portfolio
    {
        $slug = $slug !== null && $slug !== '' ? mb_strtolower(trim($slug)) : PortfolioSlug::suggest($shortName ?: $name);
        if ($problem = PortfolioSlug::problem($slug)) {
            throw new InvalidArgumentException($problem);
        }

        return DB::transaction(function () use ($name, $shortName, $slug, $areas, $creator, $demo) {
            $portfolio = Portfolio::create([
                'name' => $name,
                'slug' => $slug,
                'short_name' => $shortName,
                'tagline' => 'Portfólio',
                'subtitle' => $shortName ? $name : null,
                'hero_kicker' => 'Memória • Projetos • Resultados',
                'hero_title' => 'Ações que transformam',
                'hero_highlight' => $shortName ? $shortName.'.' : null,
                'hero_description' => $areas ? implode(', ', array_slice($areas, 0, -1)).(count($areas) > 1 ? ' e ' : '').end($areas) : null,
                'accent_color' => '#2155E8',
                'settings' => Portfolio::DEFAULT_SETTINGS,
                'is_demo' => $demo,
                'is_active' => true,
                'is_listed' => true,
                'created_by' => $creator?->id,
            ]);

            Tenant::run($portfolio, function () use ($portfolio, $areas, $creator) {
                foreach (array_values($areas) as $position => $title) {
                    $page = Page::create([
                        'portfolio_id' => $portfolio->id,
                        'kind' => 'area',
                        'title' => $title,
                        'slug' => Slug::make($title),
                        'accent_color' => self::INITIAL_AREAS[$title] ?? null,
                        'position' => $position,
                    ]);
                    $page->blocks()->create(['type' => 'lista_acoes', 'position' => 0, 'settings' => ['heading' => 'Ações', 'limit' => 9]]);
                    app(PagePublisher::class)->publish($page, $creator, 'Publicação inicial');
                }
                $this->log->log($creator, 'portfolio.created', $portfolio, 'Criou o portfólio "'.$portfolio->name.'" ('.$portfolio->slug.')');
            });

            return $portfolio;
        });
    }

    /** Administrador da plataforma: cria portfólios e atua como Master em qualquer um. */
    public function createPlatformAdmin(string $name, string $email, ?string $password): User
    {
        $user = new User;
        $user->forceFill([
            'portfolio_id' => null,
            'name' => $name,
            'email' => mb_strtolower(trim($email)),
            'password' => $password,
            'role' => User::MASTER,
            'is_platform_admin' => true,
            'is_active' => true,
            'password_set_at' => $password ? now() : null,
            'invited_at' => $password ? null : now(),
        ])->save();
        $this->log->log($user, 'platform.admin_created', $user, 'Administrador da plataforma criado');

        return $user;
    }

    /** Master de um portfólio específico. Sem senha, fica com convite pendente. */
    public function createMaster(Portfolio $portfolio, string $name, string $email, ?string $password = null): User
    {
        $user = new User;
        $user->forceFill([
            'portfolio_id' => $portfolio->id,
            'name' => $name,
            'email' => mb_strtolower(trim($email)),
            'password' => $password,
            'role' => User::MASTER,
            'is_active' => true,
            'password_set_at' => $password ? now() : null,
            'invited_at' => $password ? null : now(),
        ])->save();
        Tenant::run($portfolio, fn () => $this->log->log($user, 'portfolio.master_created', $user, 'Master do portfólio criado: '.$user->email));

        return $user;
    }

    public function inviteUrl(User $user): string
    {
        return route('invite.accept', ['token' => Password::broker('invites')->createToken($user), 'email' => $user->email]);
    }
}
