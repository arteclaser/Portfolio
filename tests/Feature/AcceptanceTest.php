<?php

namespace Tests\Feature;

use App\Models\Action;
use App\Models\ActionLink;
use App\Models\Page;
use App\Models\User;
use App\Services\ActionWorkflow;
use App\Services\IndicatorSummary;
use App\Services\PublicActions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * Verificações de aceite da primeira versão, na ordem do documento de requisitos.
 */
class AcceptanceTest extends TestCase
{
    use RefreshDatabase;

    private Page $eco;

    private Page $tur;

    protected function setUp(): void
    {
        parent::setUp();
        $this->makePortfolio();
        $this->eco = $this->makePage('Desenvolvimento Econômico');
        $this->tur = $this->makePage('Turismo');
    }

    /** 1. O Master cria uma página e um usuário, define seu perfil e restringe suas áreas. */
    public function test_master_creates_page_and_user_with_profile_and_restricted_areas(): void
    {
        $master = $this->makeUser(User::MASTER);

        $this->actingAs($master)->post(route('panel.pages.store'), [
            'title' => 'Inovação', 'kind' => 'area', 'show_in_menu' => 1,
        ])->assertRedirect();
        $page = Page::where('title', 'Inovação')->firstOrFail();
        $this->assertSame('inovacao', $page->slug);

        $this->actingAs($master)->post(route('panel.users.store'), [
            'name' => 'Pessoa Editora', 'email' => 'editora@exemplo.invalid', 'role' => User::EDITOR, 'page_ids' => [$page->id],
        ])->assertRedirect();

        $user = User::where('email', 'editora@exemplo.invalid')->firstOrFail();
        $this->assertSame(User::EDITOR, $user->role);
        $this->assertNull($user->password, 'Convidado define a própria senha.');
        $this->assertSame([$page->id], $user->pages()->pluck('pages.id')->all());

        // A restrição vale no servidor: ação de outra área é inacessível.
        $other = $this->makePublishedAction($this->tur, $master);
        $this->actingAs($user)->get(route('panel.actions.edit', $other))->assertForbidden();
        // E só o Master administra usuários.
        $this->actingAs($user)->get(route('panel.users.index'))->assertForbidden();
    }

    /** 2. O Colaborador salva rascunho e envia para revisão, mas não publica nem acessa outra área pela API. */
    public function test_collaborator_drafts_and_submits_but_cannot_publish_or_reach_other_areas(): void
    {
        $collab = $this->makeUser(User::COLLABORATOR, [$this->eco]);

        $this->actingAs($collab)->post(route('panel.actions.store'), ['title' => 'Rascunho incompleto', 'primary_page_id' => $this->eco->id])
            ->assertRedirect();
        $action = Action::firstOrFail();

        // Rascunho incompleto é aceito.
        $this->actingAs($collab)->put(route('panel.actions.update', $action), ['title' => 'Rascunho incompleto', 'primary_page_id' => $this->eco->id])
            ->assertRedirect()->assertSessionHasNoErrors();

        // Encaminhar exige os campos de publicação, com erros por campo.
        $this->actingAs($collab)->post(route('panel.actions.submit', $action))
            ->assertSessionHasErrors(['summary', 'description', 'starts_on']);

        $this->actingAs($collab)->put(route('panel.actions.update', $action), $this->completeData($this->eco))->assertRedirect();
        $this->actingAs($collab)->post(route('panel.actions.submit', $action))->assertRedirect();
        $this->assertSame('in_review', $action->fresh()->working_state);

        // Não publica (nem com chamada direta ao servidor).
        $this->actingAs($collab)->post(route('panel.actions.publish', $action))->assertForbidden();
        $this->assertNull($action->fresh()->published_version_id);

        // Em revisão, o colaborador não edita mais.
        $this->actingAs($collab)->put(route('panel.actions.update', $action), $this->completeData($this->eco, ['title' => 'Mudança']))->assertForbidden();

        // Outra área: nenhuma operação permitida.
        $editor = $this->makeUser(User::EDITOR, [$this->tur]);
        $foreign = app(ActionWorkflow::class)->create($this->portfolio, $editor, ['title' => 'De outra área', 'primary_page_id' => $this->tur->id]);
        $this->actingAs($collab)->get(route('panel.actions.edit', $foreign))->assertForbidden();
        $this->actingAs($collab)->put(route('panel.actions.update', $foreign), $this->completeData($this->tur))->assertForbidden();
        $this->actingAs($collab)->get(route('panel.actions.media', $foreign))->assertForbidden();
        $this->actingAs($collab)->post(route('panel.actions.media.store', $foreign), ['photos' => [UploadedFile::fake()->image('a.jpg')]])->assertForbidden();
        $this->actingAs($collab)->post(route('panel.actions.links.store', $foreign), ['url' => 'https://exemplo.com'])->assertForbidden();
        $this->actingAs($collab)->get(route('panel.actions.preview.content', $foreign))->assertForbidden();

        // Também não consegue mover a própria ação para uma área sem acesso.
        $draft = app(ActionWorkflow::class)->create($this->portfolio, $collab, ['title' => 'Outro', 'primary_page_id' => $this->eco->id]);
        $this->actingAs($collab)->put(route('panel.actions.update', $draft), ['primary_page_id' => $this->tur->id])->assertForbidden();

        // E a lista do painel mostra apenas a área autorizada.
        $this->actingAs($collab)->get(route('panel.actions.index'))->assertOk()->assertDontSee('De outra área');
    }

    /** 3. O Editor autorizado publica; visitantes acessam somente a versão aprovada. */
    public function test_authorized_editor_publishes_and_visitors_only_see_approved_version(): void
    {
        $editor = $this->makeUser(User::EDITOR, [$this->eco]);
        $collab = $this->makeUser(User::COLLABORATOR, [$this->eco]);
        $workflow = app(ActionWorkflow::class);

        $action = $workflow->create($this->portfolio, $collab, ['primary_page_id' => $this->eco->id]);
        $workflow->save($action, $collab, $this->completeData($this->eco, ['title' => 'Oficina de Empreendedorismo']));
        $workflow->submit($action->fresh(), $collab);

        $draftSlug = $action->fresh()->workingVersion->slug;
        $this->get('/acoes/'.$draftSlug)->assertNotFound();
        $this->get(route('actions.index'))->assertDontSee('Oficina de Empreendedorismo');

        // Editor de outra área não pode publicar.
        $outsider = $this->makeUser(User::EDITOR, [$this->tur]);
        $this->actingAs($outsider)->post(route('panel.actions.publish', $action))->assertForbidden();

        $this->actingAs($editor)->post(route('panel.actions.publish', $action))->assertRedirect();
        $action->refresh();
        $this->assertNotNull($action->published_version_id);
        $this->assertSame($editor->id, $action->publishedVersion->reviewer_id);

        $this->get('/acoes/'.$action->slug)->assertOk()->assertSee('Oficina de Empreendedorismo');
        $this->get(route('actions.index'))->assertSee('Oficina de Empreendedorismo');
    }

    /** 4. Alterar uma ação publicada não modifica sua versão pública antes da nova aprovação. */
    public function test_editing_published_action_keeps_public_version_until_new_approval(): void
    {
        $editor = $this->makeUser(User::EDITOR, [$this->eco]);
        $action = $this->makePublishedAction($this->eco, $editor, ['title' => 'Título aprovado', 'summary' => 'Resumo aprovado']);

        $this->actingAs($editor)->post(route('panel.actions.working', $action))->assertRedirect();
        $this->actingAs($editor)->put(route('panel.actions.update', $action), $this->completeData($this->eco, [
            'title' => 'Título em revisão', 'summary' => 'Resumo novo', 'slug' => $action->slug,
        ]))->assertRedirect();

        $this->get('/acoes/'.$action->slug)->assertOk()->assertSee('Título aprovado')->assertDontSee('Título em revisão');

        $this->actingAs($editor)->post(route('panel.actions.publish', $action))->assertRedirect();
        $this->get('/acoes/'.$action->fresh()->slug)->assertOk()->assertSee('Título em revisão');

        // O histórico guarda as duas versões, com autor e revisor.
        $this->assertSame(2, $action->versions()->count());
        $this->assertSame('superseded', $action->versions()->where('number', 1)->first()->status);
    }

    /** 5. O envio acima do limite de fotos é recusado com mensagem clara, inclusive no servidor. */
    public function test_photo_limits_are_enforced_by_the_server(): void
    {
        $this->portfolio->update(['settings' => ['max_images_per_action' => 2, 'max_upload_mb' => 1] + $this->portfolio->settings]);
        $editor = $this->makeUser(User::EDITOR, [$this->eco]);
        $action = app(ActionWorkflow::class)->create($this->portfolio, $editor, ['primary_page_id' => $this->eco->id]);

        $three = [UploadedFile::fake()->image('a.jpg', 640, 480), UploadedFile::fake()->image('b.jpg', 640, 480), UploadedFile::fake()->image('c.jpg', 640, 480)];
        $this->actingAs($editor)->postJson(route('panel.actions.media.store', $action), ['photos' => $three])
            ->assertStatus(422)->assertJsonFragment(['ok' => false])
            ->assertJsonPath('message', fn ($m) => str_contains($m, 'Limite de 2 imagens por ação'));

        $this->actingAs($editor)->postJson(route('panel.actions.media.store', $action), ['photos' => [UploadedFile::fake()->image('a.jpg', 640, 480)]])
            ->assertOk();
        $big = UploadedFile::fake()->image('grande.jpg', 640, 480)->size(2048);
        $this->actingAs($editor)->postJson(route('panel.actions.media.store', $action), ['photos' => [$big]])
            ->assertStatus(422)->assertJsonPath('errors.0', fn ($m) => str_contains($m, 'ultrapassa o limite de 1 MB'));

        // Arquivo que não é imagem, mesmo com extensão .jpg.
        $fake = UploadedFile::fake()->createWithContent('falso.jpg', '<?php echo 1;');
        $this->actingAs($editor)->postJson(route('panel.actions.media.store', $action), ['photos' => [$fake]])
            ->assertStatus(422)->assertJsonPath('errors.0', fn ($m) => str_contains($m, 'Formato não aceito'));

        $this->assertSame(1, $action->fresh()->workingVersion->media()->count());
    }

    /** 6. Um link sem prévia automática recebe título e imagem manualmente e continua abrindo a origem. */
    public function test_link_without_preview_accepts_manual_title_and_image_and_keeps_origin(): void
    {
        config(['services.link_preview.enabled' => false]);
        $editor = $this->makeUser(User::EDITOR, [$this->eco]);
        $action = app(ActionWorkflow::class)->create($this->portfolio, $editor, ['primary_page_id' => $this->eco->id]);
        app(ActionWorkflow::class)->save($action, $editor, $this->completeData($this->eco));

        $url = 'https://jornal.exemplo.com.br/noticias/feira-2026';
        $this->actingAs($editor)->post(route('panel.actions.links.store', $action), ['url' => $url])->assertRedirect();
        $link = ActionLink::firstOrFail();
        $this->assertSame('manual', $link->preview_status);

        $this->actingAs($editor)->put(route('panel.actions.links.update', [$action, $link]), [
            'kind' => 'reportagem', 'title' => 'Reportagem sobre a feira', 'source_name' => 'Jornal Exemplo',
            'image' => UploadedFile::fake()->image('capa.jpg', 800, 450),
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertNotNull($link->fresh()->image_media_id);

        $this->actingAs($editor)->post(route('panel.actions.publish', $action))->assertRedirect();
        $html = $this->get('/acoes/'.$action->fresh()->slug)->assertOk()->getContent();
        $this->assertStringContainsString('Reportagem sobre a feira', $html);
        $this->assertStringContainsString('href="'.$url.'"', $html);
        $this->assertStringContainsString($url, strip_tags($html), 'O endereço original fica visível.');

        // A imagem escolhida passa a ser pública junto com a ação.
        $media = $link->fresh()->image;
        $this->get(route('media.show', [$media->uuid, 'sm']))->assertOk();
    }

    /** 7. Uma ação em duas áreas mantém um único cadastro e uma única contagem geral. */
    public function test_action_in_two_areas_has_single_record_and_single_count(): void
    {
        $editor = $this->makeUser(User::MASTER);
        $this->makePublishedAction($this->eco, $editor, [
            'title' => 'Rota turística e econômica',
            'related_page_ids' => [$this->tur->id],
            'indicators' => [['label' => 'Empresas atendidas', 'value' => 12, 'unit' => 'empresas', 'source' => 'Relatório interno']],
        ]);

        $this->assertSame(1, Action::count());
        $this->get(route('pages.show', 'desenvolvimento-economico'))->assertOk()->assertSee('Rota turística e econômica');
        $this->get(route('pages.show', 'turismo'))->assertOk()->assertSee('Rota turística e econômica');

        $actions = app(PublicActions::class);
        $summary = app(IndicatorSummary::class)->summarize($actions->query($this->portfolio));
        $this->assertSame(1, $summary['action_count']);
        $this->assertSame(12.0, $summary['groups'][0]['total']);
        $this->assertSame(1, $actions->filtered($this->portfolio, ['area' => $this->eco->id])->count());
        $this->assertSame(1, $actions->filtered($this->portfolio, ['area' => $this->tur->id])->count());
    }

    /** 8. O conteúdo salvo persiste após sair e entrar; arquivos privados não ficam acessíveis pelo endereço. */
    public function test_saved_content_persists_across_sessions_and_private_files_are_protected(): void
    {
        $collab = $this->makeUser(User::COLLABORATOR, [$this->eco], ['email' => 'colab@exemplo.invalid', 'password' => 'SenhaForte123']);

        $this->post(route('login.attempt'), ['email' => 'colab@exemplo.invalid', 'password' => 'SenhaForte123'])->assertRedirect(route('panel.dashboard'));
        $this->post(route('panel.actions.store'), ['title' => 'Texto que deve persistir', 'primary_page_id' => $this->eco->id]);
        $action = Action::firstOrFail();
        $this->postJson(route('panel.actions.media.store', $action), ['photos' => [UploadedFile::fake()->image('privada.jpg', 640, 480)]])->assertOk();
        $media = $action->fresh()->workingVersion->media()->first()->media;
        $this->post(route('logout'))->assertRedirect(route('login'));
        $this->assertGuest();

        // Arquivo de rascunho: invisível ao público e a quem não está autenticado.
        $this->get(route('media.show', [$media->uuid, 'md']))->assertNotFound();
        $this->get(route('panel.media.file', [$media->uuid, 'md']))->assertRedirect(route('login'));
        // Nada do disco privado é servido por caminho direto.
        $this->get('/storage/'.$media->variants['md']['path'])->assertNotFound();

        $this->post(route('login.attempt'), ['email' => 'colab@exemplo.invalid', 'password' => 'SenhaForte123'])->assertRedirect();
        $this->get(route('panel.actions.edit', $action))->assertOk()->assertSee('Texto que deve persistir');
        $this->get(route('panel.media.file', [$media->uuid, 'md']))->assertOk();

        // Outro colaborador, de outra área, também não acessa o arquivo.
        $stranger = $this->makeUser(User::COLLABORATOR, [$this->tur]);
        $this->actingAs($stranger)->get(route('panel.media.file', [$media->uuid, 'md']))->assertForbidden();
    }

    /** 9. Busca, filtros e navegação funcionam (o teste de teclado e celular roda no navegador: tests/browser). */
    public function test_public_search_and_filters(): void
    {
        $master = $this->makeUser(User::MASTER);
        $this->makePublishedAction($this->eco, $master, ['title' => 'Capacitação de empreendedores', 'activity_type' => 'Capacitação', 'starts_on' => '2025-03-01']);
        $this->makePublishedAction($this->tur, $master, ['title' => 'Roteiro de turismo rural', 'activity_type' => 'Programa', 'activity_status' => 'em_andamento', 'starts_on' => '2026-04-01']);

        $this->get(route('actions.index', ['q' => 'empreendedor']))->assertSee('Capacitação de empreendedores')->assertDontSee('Roteiro de turismo rural');
        $this->get(route('actions.index', ['q' => 'TURISMO']))->assertSee('Roteiro de turismo rural');
        $this->get(route('actions.index', ['area' => 'turismo']))->assertSee('Roteiro de turismo rural')->assertDontSee('Capacitação de empreendedores');
        $this->get(route('actions.index', ['ano' => 2025]))->assertSee('Capacitação de empreendedores')->assertDontSee('Roteiro de turismo rural');
        $this->get(route('actions.index', ['tipo' => 'Programa']))->assertSee('Roteiro de turismo rural')->assertDontSee('Capacitação de empreendedores');
        $this->get(route('actions.index', ['situacao' => 'em_andamento']))->assertSee('Roteiro de turismo rural')->assertDontSee('Capacitação de empreendedores');
        $this->get(route('actions.index', ['q' => 'inexistente']))->assertSee('Nenhuma ação encontrada');
    }
}
