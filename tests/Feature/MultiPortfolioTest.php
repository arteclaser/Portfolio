<?php

namespace Tests\Feature;

use App\Models\Action;
use App\Models\Page;
use App\Models\Portfolio;
use App\Models\TeamMember;
use App\Models\User;
use App\Services\ActionWorkflow;
use App\Services\MediaStore;
use App\Support\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use App\Notifications\InviteNotification;
use Tests\Concerns\UsesRoutingMode;
use Tests\TestCase;

/** Vários portfólios na mesma instalação (modo caminho): isolamento total e criação automática. */
class MultiPortfolioTest extends TestCase
{
    use RefreshDatabase;
    use UsesRoutingMode { setUp as routingSetUp; }

    protected function routingEnv(): array
    {
        return ['PORTFOLIO_ROUTING' => 'path'];
    }

    private Portfolio $a;

    private Portfolio $b;

    private Page $pageA;

    private Page $pageB;

    private User $masterA;

    private User $masterB;

    protected function setUp(): void
    {
        $this->routingSetUp();
        $this->b = $this->makePortfolio([], 'outra-cidade');
        $this->pageB = $this->makePage('Turismo');
        $this->masterB = $this->makeUser(User::MASTER);

        $this->a = $this->makePortfolio([], 'capinzal');
        $this->pageA = $this->makePage('Turismo');
        $this->masterA = $this->makeUser(User::MASTER);
    }

    /** Cria conteúdo publicado no portfólio B e volta ao A. */
    private function contentInB(): array
    {
        $this->usePortfolio($this->b);
        $action = $this->makePublishedAction($this->pageB, $this->masterB, ['title' => 'Ação exclusiva de B']);
        $media = app(MediaStore::class)->storeUploadedImage(UploadedFile::fake()->image('b.jpg', 400, 300), $this->b, $this->masterB);
        $action->publishedVersion->media()->create(['media_id' => $media->id, 'is_cover' => true]);
        $member = TeamMember::create(['name' => 'Pessoa de B', 'page_id' => $this->pageB->id, 'is_public' => true]);
        $this->usePortfolio($this->a);

        return [$action, $media, $member];
    }

    public function test_master_of_one_portfolio_cannot_reach_another_portfolio_through_the_panel(): void
    {
        [$action, $media, $member] = $this->contentInB();

        $this->actingAs($this->masterA);
        $this->get(route('panel.actions.edit', $action->id))->assertNotFound();
        $this->put(route('panel.actions.update', $action->id), ['primary_page_id' => $this->pageA->id])->assertNotFound();
        $this->post(route('panel.actions.publish', $action->id))->assertNotFound();
        $this->get(route('panel.media.file', [$media->uuid, 'md']))->assertNotFound();
        $this->get(route('panel.pages.blocks', $this->pageB->id))->assertNotFound();
        $this->get(route('panel.team.edit', $member->id))->assertNotFound();
        $this->get(route('panel.users.edit', $this->masterB->id))->assertNotFound();
        $this->get(route('panel.users.index'))->assertOk()->assertDontSee($this->masterB->email);
        $this->get(route('panel.actions.index'))->assertOk()->assertDontSee('Ação exclusiva de B');

        // Referências cruzadas em formulários são recusadas ou ignoradas.
        $own = app(ActionWorkflow::class)->create($this->a, $this->masterA, ['title' => 'Minha', 'primary_page_id' => $this->pageA->id]);
        $this->put(route('panel.actions.update', $own), ['primary_page_id' => $this->pageB->id])->assertSessionHasErrors('primary_page_id');
        $this->put(route('panel.actions.update', $own), ['primary_page_id' => $this->pageA->id, 'related_page_ids' => [$this->pageB->id]])
            ->assertSessionHasErrors('related_page_ids.0');
        $this->put(route('panel.actions.update', $own), ['primary_page_id' => $this->pageA->id, 'team' => [$member->id => ['selected' => 1]]])
            ->assertSessionHasNoErrors();
        $this->assertSame(0, $own->fresh()->workingVersion->teamMembers()->count());
        $this->post(route('panel.users.store'), ['name' => 'X', 'email' => 'x@exemplo.invalid', 'role' => User::EDITOR, 'page_ids' => [$this->pageB->id]])
            ->assertSessionHasErrors('page_ids.0');
    }

    public function test_public_sites_only_show_their_own_content_and_files(): void
    {
        [$action, $media] = $this->contentInB();

        $this->get('/outra-cidade/acoes/'.$action->slug)->assertOk()->assertSee('Ação exclusiva de B');
        $this->get('/outra-cidade/midia/'.$media->uuid.'/md')->assertOk();

        $this->get('/capinzal/acoes/'.$action->slug)->assertNotFound();
        $this->get('/capinzal/midia/'.$media->uuid.'/md')->assertNotFound();
        $this->get('/capinzal/acoes')->assertOk()->assertDontSee('Ação exclusiva de B');
        $this->get('/capinzal')->assertOk()->assertDontSee('Ação exclusiva de B');

        // O mesmo endereço de ação pode existir em portfólios diferentes.
        $mine = $this->makePublishedAction($this->pageA, $this->masterA, ['title' => 'Ação exclusiva de B']);
        $this->assertSame($action->slug, $mine->slug);
        $this->get('/capinzal/acoes/'.$mine->slug)->assertOk();
        $this->assertSame(2, Action::withoutGlobalScopes()->count());

        $this->get('/nao-existe')->assertNotFound();
    }

    public function test_platform_admin_creates_portfolio_that_is_online_immediately(): void
    {
        $admin = User::factory()->create(['role' => User::MASTER, 'is_platform_admin' => true, 'portfolio_id' => null]);

        // Master de um portfólio não administra a plataforma.
        $this->actingAs($this->masterA)->get(route('panel.platform.index'))->assertForbidden();
        $this->actingAs($this->masterA)->post(route('panel.platform.store'), ['name' => 'X'])->assertForbidden();

        $this->actingAs($admin)->post(route('panel.platform.store'), ['name' => 'Secretaria de Exemplo', 'slug' => 'painel'])
            ->assertSessionHasErrors('slug');
        $this->actingAs($admin)->post(route('panel.platform.store'), ['name' => 'Outra', 'slug' => 'capinzal'])
            ->assertSessionHasErrors('slug');

        Notification::fake();
        $this->actingAs($admin)->post(route('panel.platform.store'), [
            'name' => 'Secretaria de Turismo de Piratuba', 'short_name' => 'Piratuba', 'slug' => '',
            'areas' => "Turismo\nCultura", 'master_name' => 'Pessoa Master', 'master_email' => 'master@piratuba.invalid',
        ])->assertRedirect()->assertSessionHas('warning');

        $new = Portfolio::where('slug', 'piratuba')->firstOrFail();
        $this->get('/piratuba')->assertOk()->assertSee('Piratuba');
        $this->get('/piratuba/areas')->assertOk()->assertSee('Turismo')->assertSee('Cultura');
        $master = User::where('email', 'master@piratuba.invalid')->firstOrFail();
        $this->assertSame($new->id, $master->portfolio_id);
        $this->assertTrue($master->hasPendingInvite());
        Notification::assertSentTo($master, InviteNotification::class);

        // Sem e-mail configurado, o link de convite aparece para ser copiado.
        config(['mail.default' => 'log']);
        $this->actingAs($admin)->post(route('panel.platform.store'), ['name' => 'Outra Secretaria', 'slug' => 'outra-secretaria', 'master_name' => 'P', 'master_email' => 'p@outra.invalid'])
            ->assertSessionHas('invite_link');
        $this->get(route('platform.home'))->assertOk()->assertSee('Secretaria de Turismo de Piratuba');

        // A administração escolhe qual portfólio gerenciar.
        $this->actingAs($admin)->post(route('panel.platform.manage', $new->id))->assertRedirect(route('panel.dashboard'));
        $this->actingAs($admin)->get(route('panel.pages.index'))->assertOk()->assertSee('Cultura');
        $this->actingAs($admin)->post(route('panel.platform.manage', $this->a->id));
        $this->actingAs($admin)->get(route('panel.pages.index'))->assertOk()->assertDontSee('Cultura');
    }

    public function test_deactivated_portfolio_leaves_the_air_and_blocks_its_team(): void
    {
        $admin = User::factory()->create(['role' => User::MASTER, 'is_platform_admin' => true, 'portfolio_id' => null]);
        $this->actingAs($admin)->put(route('panel.platform.update', $this->b->id), ['name' => $this->b->name, 'slug' => 'outra-cidade', 'is_listed' => 1])
            ->assertRedirect();
        $this->assertFalse($this->b->fresh()->is_active);

        $this->get('/outra-cidade')->assertNotFound();
        $this->get(route('platform.home'))->assertDontSee($this->b->name);
        $this->actingAs($this->masterB)->get(route('panel.dashboard'))->assertRedirect(route('login'));
        $this->get('/capinzal')->assertOk();
    }

    public function test_unlisted_portfolio_stays_online_but_off_the_platform_list(): void
    {
        $this->b->update(['is_listed' => false]);
        $this->get(route('platform.home'))->assertOk()->assertSee($this->a->name)->assertDontSee($this->b->name);
        $this->get('/outra-cidade')->assertOk();
    }

    public function test_cli_creates_portfolio_with_master_invite(): void
    {
        Tenant::forget();
        $this->artisan('mostraqui:novo-portfolio', ['nome' => 'Fundação de Cultura', '--curto' => 'Cultura', '--areas' => ['Patrimônio'], '--master-email' => 'm@cultura.invalid'])
            ->expectsOutputToContain('Endereço público:')
            ->assertSuccessful();
        $this->assertTrue(Portfolio::where('slug', 'cultura')->exists());
        $this->artisan('mostraqui:novo-portfolio', ['nome' => 'Repetido', '--endereco' => 'cultura'])->assertFailed();
    }

    public function test_addresses_from_single_portfolio_mode_keep_working(): void
    {
        config(['portfolio.single' => 'capinzal']);
        $this->get('/acoes/feira?ano=2026')->assertStatus(301)->assertRedirect(url('/capinzal/acoes/feira?ano=2026'));
        $this->get('/p/turismo')->assertStatus(301)->assertRedirect(url('/capinzal/p/turismo'));
        $this->get('/equipe')->assertStatus(301)->assertRedirect(url('/capinzal/equipe'));
        $this->get('/capinzal/acoes')->assertOk();
    }
}
