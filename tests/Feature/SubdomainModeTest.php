<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\UsesRoutingMode;
use Tests\TestCase;

/** Modo subdomínio (PORTFOLIO_ROUTING=subdomain): capinzal.mostraqui.test. */
class SubdomainModeTest extends TestCase
{
    use RefreshDatabase;
    use UsesRoutingMode { setUp as routingSetUp; }

    protected function routingEnv(): array
    {
        return ['PORTFOLIO_ROUTING' => 'subdomain', 'PORTFOLIO_BASE_DOMAIN' => 'mostraqui.test', 'APP_URL' => 'http://mostraqui.test'];
    }

    protected function setUp(): void
    {
        $this->routingSetUp();
        $this->makePortfolio([], 'capinzal');
        $this->makePage('Turismo');
    }

    public function test_each_portfolio_answers_on_its_own_subdomain(): void
    {
        $master = $this->makeUser(User::MASTER);
        $action = $this->makePublishedAction($this->portfolio->pages()->first(), $master, ['title' => 'Ação no subdomínio']);

        $this->assertSame('http://capinzal.mostraqui.test', $this->portfolio->publicUrl());
        $this->get('http://capinzal.mostraqui.test/')->assertOk()->assertSee('Ação no subdomínio');
        $this->get('http://capinzal.mostraqui.test/acoes/'.$action->slug)->assertOk()
            ->assertSee('<meta property="og:url" content="http://capinzal.mostraqui.test/acoes/'.$action->slug.'">', false);
        $this->get('http://desconhecido.mostraqui.test/')->assertNotFound();
    }

    public function test_base_domain_serves_platform_and_panel_only(): void
    {
        $this->get('http://mostraqui.test/')->assertOk()->assertSee('Portfólio Capinzal');
        $this->get('http://mostraqui.test/painel')->assertRedirect();
        $this->get('http://mostraqui.test/entrar')->assertOk();
        // Painel e acesso não respondem nos subdomínios.
        $this->get('http://capinzal.mostraqui.test/painel')->assertNotFound();
        $this->get('http://capinzal.mostraqui.test/entrar')->assertNotFound();
    }

    public function test_old_path_addresses_redirect_to_the_subdomain(): void
    {
        $this->get('http://mostraqui.test/capinzal/acoes?q=feira')
            ->assertStatus(301)->assertRedirect('http://capinzal.mostraqui.test/acoes?q=feira');
        $this->get('http://mostraqui.test/nao-existe/acoes')->assertNotFound();
        $this->get('http://www.mostraqui.test/')->assertStatus(301)->assertRedirect('http://mostraqui.test/');
        // Endereços do tempo do portfólio único (mostraqui.test/acoes/...) também seguem para o subdomínio.
        $this->get('http://mostraqui.test/acoes/feira')->assertStatus(301)->assertRedirect('http://capinzal.mostraqui.test/acoes/feira');
    }
}
