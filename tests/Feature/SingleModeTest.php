<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Modo portfólio único (PORTFOLIO_ROUTING=single, padrão): Capinzal direto em mostraqui.net. */
class SingleModeTest extends TestCase
{
    use RefreshDatabase;

    public function test_before_installation_the_domain_shows_installation_pending(): void
    {
        $this->get('/')->assertStatus(503)->assertSee('Instalação pendente');
        $this->get('/acoes')->assertStatus(503);
    }

    public function test_portfolio_answers_on_the_domain_root(): void
    {
        $this->makePortfolio([], 'capinzal');
        $page = $this->makePage('Turismo');
        $master = $this->makeUser(User::MASTER);
        $action = $this->makePublishedAction($page, $master, ['title' => 'Rota das Vinícolas']);
        Tenant::forget();

        $this->assertSame(url('/'), $this->portfolio->publicUrl());
        $this->get('/')->assertOk()->assertSee('Rota das Vinícolas');
        $this->get('/acoes/'.$action->slug)->assertOk()
            ->assertSee('<meta property="og:url" content="'.url('/acoes/'.$action->slug).'">', false);
        $this->get('/p/'.$page->slug)->assertOk()->assertSee('Turismo');
        // Sem prefixo de portfólio no endereço.
        $this->get('/capinzal')->assertNotFound();
        $this->get('/capinzal/acoes/'.$action->slug)->assertNotFound();
        $this->get('/entrar')->assertOk()->assertSee('href="'.url('/').'"', false);
    }

    public function test_only_the_chosen_portfolio_is_shown_and_platform_management_is_hidden(): void
    {
        $other = $this->makePortfolio([], 'outra-cidade');
        $otherPage = $this->makePage('Cultura');
        $otherMaster = $this->makeUser(User::MASTER);
        $this->makePublishedAction($otherPage, $otherMaster, ['title' => 'Ação da outra cidade']);

        $capinzal = $this->makePortfolio([], 'capinzal');
        $page = $this->makePage('Turismo');
        $master = $this->makeUser(User::MASTER);
        $this->makePublishedAction($page, $master, ['title' => 'Ação de Capinzal']);
        Tenant::forget();

        // Sem PORTFOLIO_SINGLE, o domínio mostra o primeiro portfólio ativo.
        $this->get('/')->assertOk()->assertSee('Ação da outra cidade')->assertDontSee('Ação de Capinzal');
        config(['portfolio.single' => 'capinzal']);
        $this->get('/')->assertOk()->assertSee('Ação de Capinzal')->assertDontSee('Ação da outra cidade');

        // A equipe de outro portfólio não entra no painel deste endereço.
        $this->actingAs($otherMaster)->get('/painel')->assertRedirect(route('login'));
        $this->assertGuest();

        // A administração da plataforma gerencia só o portfólio exibido, sem a seção "Plataforma".
        $admin = User::factory()->create(['role' => User::MASTER, 'is_platform_admin' => true, 'portfolio_id' => null]);
        $this->actingAs($admin)->get('/painel')->assertOk()
            ->assertSee($capinzal->short_name)->assertDontSee('Portfólios</a>', false);
        $this->actingAs($admin)->get(route('panel.platform.index'))->assertForbidden();
        $this->actingAs($admin)->post(route('panel.platform.store'), ['name' => 'Nova'])->assertForbidden();
        $this->actingAs($admin)->post(route('panel.platform.manage', $other->id))->assertForbidden();
        $this->actingAs($admin)->get(route('panel.actions.index'))->assertOk()
            ->assertSee('Ação de Capinzal')->assertDontSee('Ação da outra cidade');
    }

    public function test_deactivated_portfolio_is_not_shown(): void
    {
        $this->makePortfolio([], 'capinzal')->update(['is_active' => false]);
        Tenant::forget();

        $this->get('/')->assertStatus(503);
    }
}
