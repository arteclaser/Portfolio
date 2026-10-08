<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Action;
use App\Models\Portfolio;
use App\Models\User;
use App\Notifications\InviteNotification;
use App\Services\Installer;
use App\Support\ActivityLogger;
use App\Support\PortfolioSlug;
use App\Support\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Throwable;

/**
 * Gestão da plataforma: criar portfólios (ficam no ar na hora, no próprio endereço),
 * editar, desativar e escolher qual portfólio gerenciar no painel.
 */
class PlatformPortfolioController extends Controller
{
    public function __construct(private Installer $installer, private ActivityLogger $log) {}

    public function index()
    {
        Gate::authorize('manage-platform');
        $portfolios = Portfolio::query()->orderBy('name')->get();
        $stats = [];
        foreach ($portfolios as $p) {
            $stats[$p->id] = Tenant::run($p, fn () => [
                'published' => Action::query()->whereNotNull('published_version_id')->whereNull('archived_at')->count(),
                'users' => User::where('portfolio_id', $p->id)->where('is_active', true)->count(),
            ]);
        }

        return view('panel.platform.index', ['portfolios' => $portfolios, 'stats' => $stats, 'currentId' => Tenant::id()]);
    }

    public function create()
    {
        Gate::authorize('manage-platform');

        return view('panel.platform.form', ['portfolio' => new Portfolio(['is_active' => true, 'is_listed' => true])]);
    }

    public function store(Request $request)
    {
        Gate::authorize('manage-platform');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'short_name' => ['nullable', 'string', 'max:60'],
            'slug' => ['nullable', 'string', 'max:40'],
            'areas' => ['nullable', 'string', 'max:2000'],
            'master_name' => ['nullable', 'required_with:master_email', 'string', 'max:255'],
            'master_email' => ['nullable', 'email', 'max:255', 'unique:users,email'],
        ], ['master_name.required_with' => 'Informe o nome do Master.'], [
            'name' => 'nome', 'slug' => 'endereço', 'master_email' => 'e-mail do Master', 'master_name' => 'nome do Master',
        ]);

        $slug = filled($data['slug'] ?? null) ? mb_strtolower(trim($data['slug'])) : PortfolioSlug::suggest($data['short_name'] ?: $data['name']);
        if ($problem = PortfolioSlug::problem($slug)) {
            throw ValidationException::withMessages(['slug' => $problem]);
        }
        $areas = array_values(array_unique(array_filter(array_map('trim', preg_split('/\R/', (string) ($data['areas'] ?? ''))))));

        try {
            $portfolio = $this->installer->createPortfolio($data['name'], $data['short_name'] ?? null, $slug, $areas, $request->user());
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['slug' => $e->getMessage()]);
        }

        $redirect = redirect()->route('panel.platform.edit', $portfolio->id)
            ->with('status', 'Portfólio criado e já disponível em '.$portfolio->publicUrl().'.');

        if (filled($data['master_email'] ?? null)) {
            $master = $this->installer->createMaster($portfolio, $data['master_name'], $data['master_email']);
            $url = $this->installer->inviteUrl($master);
            $mailed = false;
            if (config('mail.default') !== 'log') {
                try {
                    $master->notify(new InviteNotification($url, $portfolio->name));
                    $mailed = true;
                } catch (Throwable $e) {
                    report($e);
                }
            }
            $mailed ? $redirect->with('warning', 'Convite enviado por e-mail a '.$master->email.'.') : $redirect->with('invite_link', $url);
        }

        return $redirect;
    }

    public function edit(int $portfolioId)
    {
        Gate::authorize('manage-platform');
        $portfolio = Portfolio::findOrFail($portfolioId);

        return view('panel.platform.form', [
            'portfolio' => $portfolio,
            'masters' => User::where('portfolio_id', $portfolio->id)->where('role', User::MASTER)->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, int $portfolioId)
    {
        Gate::authorize('manage-platform');
        $portfolio = Portfolio::findOrFail($portfolioId);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'short_name' => ['nullable', 'string', 'max:60'],
            'slug' => ['required', 'string', 'max:40'],
            'is_active' => ['nullable', 'boolean'],
            'is_listed' => ['nullable', 'boolean'],
            'master_name' => ['nullable', 'required_with:master_email', 'string', 'max:255'],
            'master_email' => ['nullable', 'email', 'max:255', 'unique:users,email'],
        ], [], ['name' => 'nome', 'slug' => 'endereço']);
        $slug = mb_strtolower(trim($data['slug']));
        if ($problem = PortfolioSlug::problem($slug, $portfolio->id)) {
            throw ValidationException::withMessages(['slug' => $problem]);
        }
        $before = $portfolio->only(['name', 'slug', 'is_active', 'is_listed']);
        $portfolio->fill([
            'name' => $data['name'],
            'short_name' => $data['short_name'] ?? null,
            'slug' => $slug,
            'is_active' => $request->boolean('is_active'),
            'is_listed' => $request->boolean('is_listed'),
        ])->save();
        Tenant::run($portfolio, fn () => $this->log->log($request->user(), 'portfolio.updated', $portfolio, 'Atualizou o portfólio', ['antes' => $before, 'depois' => $portfolio->only(array_keys($before))]));

        $redirect = redirect()->route('panel.platform.edit', $portfolio->id)->with('status', 'Portfólio atualizado.'
            .($before['slug'] !== $slug ? ' Atenção: o endereço mudou; links antigos para '.$before['slug'].' deixam de funcionar.' : ''));
        if (filled($data['master_email'] ?? null)) {
            $master = $this->installer->createMaster($portfolio, $data['master_name'], $data['master_email']);
            $redirect->with('invite_link', $this->installer->inviteUrl($master));
        }

        return $redirect;
    }

    /** Passa a gerenciar este portfólio no painel. */
    public function manage(Request $request, int $portfolioId)
    {
        Gate::authorize('manage-platform');
        $portfolio = Portfolio::findOrFail($portfolioId);
        $request->session()->put('panel_portfolio_id', $portfolio->id);

        return redirect()->route('panel.dashboard')->with('status', 'Agora você está gerenciando "'.$portfolio->name.'".');
    }
}
