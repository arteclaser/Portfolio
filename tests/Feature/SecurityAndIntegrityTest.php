<?php

namespace Tests\Feature;

use App\Models\Action;
use App\Models\CustomField;
use App\Models\Page;
use App\Models\TeamMember;
use App\Models\User;
use App\Services\ActionWorkflow;
use App\Services\FetchException;
use App\Services\IndicatorSummary;
use App\Services\LinkPreview;
use App\Services\PublicActions;
use App\Services\SafeFetcher;
use App\Support\Markdown;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class SecurityAndIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private Page $area;

    protected function setUp(): void
    {
        parent::setUp();
        $this->makePortfolio();
        $this->area = $this->makePage('Inovação');
    }

    public function test_safe_fetcher_blocks_internal_and_local_destinations(): void
    {
        $fetcher = new SafeFetcher;
        foreach (['127.0.0.1', '10.1.2.3', '172.16.0.1', '192.168.1.10', '169.254.169.254', '100.64.0.1', '0.0.0.0', '::1', 'fc00::1', 'fe80::1', '::ffff:127.0.0.1', '224.0.0.1'] as $ip) {
            $this->assertFalse(SafeFetcher::isPublicIp($ip), "{$ip} deveria ser bloqueado");
        }
        $this->assertTrue(SafeFetcher::isPublicIp('8.8.8.8'));
        $this->assertTrue(SafeFetcher::isPublicIp('2001:4860:4860::8888'));

        foreach ([
            'http://localhost/', 'http://intranet.local/', 'ftp://exemplo.com/', 'file:///etc/passwd', 'https://user:senha@exemplo.com/',
            'https://exemplo.com:8080/', 'http://127.0.0.1/', 'http://[::1]/', 'javascript:alert(1)',
        ] as $url) {
            try {
                $fetcher->validateUrl($url);
                $fetcher->resolvePublicIp(parse_url($url, PHP_URL_HOST) ? trim(parse_url($url, PHP_URL_HOST), '[]') : '');
                $this->fail("{$url} deveria ser recusado");
            } catch (FetchException) {
                $this->addToAssertionCount(1);
            }
        }

        // Nome que resolve para IP interno (ou mistura público + interno) é recusado.
        $rebinding = new SafeFetcher(resolver: fn () => ['93.184.216.34', '10.0.0.5']);
        $this->expectException(FetchException::class);
        $rebinding->resolvePublicIp('ataque.exemplo.com');
    }

    public function test_link_preview_parses_metadata_without_executing_html(): void
    {
        $html = '<html><head><meta charset="utf-8"><title>Título &amp; site</title>'
            .'<meta property="og:title" content="Feira &lt;b&gt;de&lt;/b&gt; Inovação">'
            .'<meta property="og:description" content="Descrição">'
            .'<meta property="og:image" content="/img/capa.jpg"><script>alert(1)</script></head><body></body></html>';
        $meta = app(LinkPreview::class)->parseHtml($html, 'text/html; charset=utf-8');
        $this->assertSame('Feira <b>de</b> Inovação', $meta['og:title']);
        $this->assertSame('/img/capa.jpg', $meta['og:image']);

        $preview = app(LinkPreview::class);
        $this->assertSame(['provider' => 'youtube', 'kind' => 'video', 'embed_id' => 'dQw4w9WgXcQ'], $preview->detect('https://youtu.be/dQw4w9WgXcQ'));
        $this->assertSame('dQw4w9WgXcQ', $preview->detect('https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=5')['embed_id']);
        $this->assertSame('p/CxYz123_ab', $preview->detect('https://www.instagram.com/p/CxYz123_ab/')['embed_id']);
        $this->assertSame('site', $preview->detect('https://jornal.exemplo.com/noticia')['provider']);
    }

    public function test_markdown_is_sanitized(): void
    {
        $out = Markdown::render("<script>alert(1)</script>\n\n[clique](javascript:alert(1)) ![x](https://rastreador.exemplo/p.gif)\n\n# Título");
        $this->assertStringNotContainsString('<script>', $out);
        $this->assertStringNotContainsString('javascript:', $out);
        $this->assertStringNotContainsString('<img', $out);
        $this->assertStringContainsString('<h2>Título</h2>', $out);
    }

    public function test_public_pages_never_reveal_drafts_review_notes_or_internal_contacts(): void
    {
        $master = $this->makeUser(User::MASTER);
        $member = TeamMember::create(['portfolio_id' => $this->portfolio->id, 'name' => 'Pessoa Equipe', 'page_id' => $this->area->id,
            'contact_email' => 'interno@exemplo.invalid', 'contact_is_public' => false, 'is_public' => true]);
        $action = $this->makePublishedAction($this->area, $master, ['title' => 'Ação pública', 'team' => [['id' => $member->id]]]);

        $workflow = app(ActionWorkflow::class);
        $workflow->startWorkingCopy($action, $master);
        $workflow->save($action->fresh(), $master, $this->completeData($this->area, ['title' => 'Mudança secreta', 'slug' => $action->slug]));
        $workflow->submit($action->fresh(), $master);
        $workflow->returnForChanges($action->fresh(), $master, 'Observação interna do revisor');

        foreach ([route('home'), route('actions.index'), route('actions.show', $action->slug), route('pages.show', 'inovacao'), route('team.index')] as $url) {
            $html = $this->get($url)->assertOk()->getContent();
            $this->assertStringNotContainsString('Mudança secreta', $html, $url);
            $this->assertStringNotContainsString('Observação interna do revisor', $html, $url);
            $this->assertStringNotContainsString('interno@exemplo.invalid', $html, $url);
        }
        $this->get(route('actions.show', $action->slug))->assertSee('Pessoa Equipe');

        // A prévia exige login.
        $this->get(route('panel.actions.preview.content', $action))->assertRedirect(route('login'));

        // Ação arquivada some do site.
        $workflow->archive($action->fresh(), $master);
        $this->get(route('actions.show', $action->slug))->assertNotFound();
    }

    public function test_public_responses_have_security_headers_and_no_cookies(): void
    {
        $response = $this->get(route('home'))->assertOk();
        $this->assertStringContainsString("default-src 'self'", $response->headers->get('Content-Security-Policy'));
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        $this->assertSame([], $response->headers->getCookies());
    }

    public function test_deactivated_user_is_logged_out_and_cannot_log_in(): void
    {
        $master = $this->makeUser(User::MASTER);
        $user = $this->makeUser(User::EDITOR, [$this->area], ['email' => 'ed@exemplo.invalid', 'password' => 'SenhaForte123']);

        $this->actingAs($user)->get(route('panel.dashboard'))->assertOk();
        $this->actingAs($master)->post(route('panel.users.deactivate', $user))->assertRedirect();
        $this->actingAs($user->fresh())->get(route('panel.dashboard'))->assertRedirect(route('login'));

        auth()->logout();
        $this->post(route('login.attempt'), ['email' => 'ed@exemplo.invalid', 'password' => 'SenhaForte123'])->assertSessionHasErrors('email');
        $this->assertGuest();

        // O último Master ativo não pode ser desativado nem rebaixado.
        $this->actingAs($master)->post(route('panel.users.deactivate', $master))->assertSessionHasErrors('user');
    }

    public function test_login_is_rate_limited(): void
    {
        $this->makeUser(User::EDITOR, [], ['email' => 'alvo@exemplo.invalid']);
        for ($i = 0; $i < 5; $i++) {
            $this->post(route('login.attempt'), ['email' => 'alvo@exemplo.invalid', 'password' => 'errada'])->assertSessionHasErrors('email');
        }
        $this->post(route('login.attempt'), ['email' => 'alvo@exemplo.invalid', 'password' => 'errada'])->assertStatus(429);
    }

    public function test_web_setup_requires_token_and_works_only_once(): void
    {
        $this->get(route('setup'))->assertNotFound();
        config(['services.setup.token' => 'codigo-secreto-longo']);
        $this->get(route('setup'))->assertOk();

        $data = ['setup_token' => 'errado', 'portfolio_name' => 'P', 'name' => 'Master', 'email' => 'm@exemplo.invalid', 'password' => 'SenhaForte123', 'password_confirmation' => 'SenhaForte123'];
        $this->post(route('setup.store'), $data)->assertSessionHasErrors('setup_token');
        $this->post(route('setup.store'), ['setup_token' => 'codigo-secreto-longo'] + $data)->assertRedirect(route('panel.dashboard'));
        $this->assertTrue(User::where('email', 'm@exemplo.invalid')->first()->isMaster());

        auth()->logout();
        $this->get(route('setup'))->assertNotFound();
    }

    public function test_invited_user_sets_own_password_and_link_is_single_use(): void
    {
        $user = User::factory()->create(['password' => null, 'role' => User::EDITOR, 'email' => 'convite@exemplo.invalid']);
        $token = Password::broker('invites')->createToken($user);

        $this->get(route('invite.accept', ['token' => $token, 'email' => $user->email]))->assertOk();
        $this->post(route('invite.store'), ['token' => $token, 'email' => $user->email, 'password' => 'curta', 'password_confirmation' => 'curta'])
            ->assertSessionHasErrors('password');
        $this->post(route('invite.store'), ['token' => $token, 'email' => $user->email, 'password' => 'SenhaForte123', 'password_confirmation' => 'SenhaForte123'])
            ->assertRedirect(route('panel.dashboard'));
        $this->assertAuthenticatedAs($user->fresh());

        auth()->logout();
        $this->post(route('invite.store'), ['token' => $token, 'email' => $user->email, 'password' => 'OutraSenha123', 'password_confirmation' => 'OutraSenha123'])
            ->assertSessionHasErrors('email');
    }

    public function test_custom_field_schema_changes_never_silently_drop_values(): void
    {
        $master = $this->makeUser(User::MASTER);
        $field = $this->area->fields()->create(['key' => 'publico', 'label' => 'Público-alvo', 'type' => 'text', 'is_public' => true]);
        $action = $this->makePublishedAction($this->area, $master, ['fields' => [$field->id => 'Empreendedores locais']]);
        $this->get(route('actions.show', $action->slug))->assertSee('Empreendedores locais');

        $this->actingAs($master)->put(route('panel.pages.fields.update', [$this->area, $field]), ['label' => 'Público-alvo', 'type' => 'number'])
            ->assertSessionHasErrors('type');
        $this->actingAs($master)->delete(route('panel.pages.fields.destroy', [$this->area, $field]))->assertSessionHasErrors('field');
        $this->assertDatabaseHas('custom_fields', ['id' => $field->id]);

        $this->actingAs($master)->post(route('panel.pages.fields.archive', [$this->area, $field]))->assertRedirect();
        $this->get(route('actions.show', $action->slug))->assertDontSee('Empreendedores locais');
        $this->assertDatabaseHas('action_field_values', ['custom_field_id' => $field->id, 'value' => 'Empreendedores locais']);

        // Campo obrigatório bloqueia a publicação até ser preenchido.
        $required = $this->area->fields()->create(['key' => 'obrig', 'label' => 'Obrigatório', 'type' => 'text', 'is_required' => true]);
        $draft = app(ActionWorkflow::class)->create($this->portfolio, $master, ['primary_page_id' => $this->area->id]);
        app(ActionWorkflow::class)->save($draft, $master, $this->completeData($this->area));
        $this->actingAs($master)->post(route('panel.actions.publish', $draft))->assertSessionHasErrors('fields.'.$required->id);
    }

    public function test_indicators_respect_documentation_units_and_participation_rules(): void
    {
        $master = $this->makeUser(User::MASTER);
        $this->makePublishedAction($this->area, $master, ['title' => 'A', 'indicators' => [
            ['label' => 'Participações', 'value' => 30, 'unit' => 'participações', 'source' => 'Listas de presença', 'is_participation' => 1],
            ['label' => 'Investimento', 'value' => 1000, 'unit' => 'R$', 'source' => 'Prestação de contas'],
            ['label' => 'Sem fonte', 'value' => 999, 'unit' => 'pessoas'],
        ]]);
        $b = $this->makePublishedAction($this->area, $master, ['title' => 'B', 'indicators' => [
            ['label' => 'Participações', 'value' => 20, 'unit' => 'participações', 'source' => 'Listas de presença', 'is_participation' => 1],
            ['label' => 'Investimento', 'value' => 5, 'unit' => 'horas', 'source' => 'Relatório'],
        ]]);

        $summary = app(IndicatorSummary::class)->summarize(app(PublicActions::class)->query($this->portfolio));
        $groups = collect($summary['groups'])->keyBy(fn ($g) => $g['label'].'|'.$g['unit']);
        $this->assertSame(50.0, $groups['Participações|participações']['total']);
        $this->assertTrue($groups['Participações|participações']['is_participation']);
        $this->assertSame(1000.0, $groups['Investimento|R$']['total'], 'Unidades diferentes não se somam.');
        $this->assertSame(5.0, $groups['Investimento|horas']['total']);
        $this->assertFalse($groups->has('Sem fonte|pessoas'), 'Indicador sem fonte não entra.');

        $this->get(route('actions.show', $b->slug))->assertSee('não representa pessoas únicas');
        $this->get(route('home'))->assertDontSee('999');
    }

    public function test_archive_trash_and_permanent_delete_permissions(): void
    {
        $master = $this->makeUser(User::MASTER);
        $editor = $this->makeUser(User::EDITOR, [$this->area]);
        $action = $this->makePublishedAction($this->area, $master);

        $this->actingAs($editor)->post(route('panel.actions.archive', $action))->assertForbidden();
        $editor->forceFill(['can_archive' => true])->save();
        $this->actingAs($editor->fresh())->post(route('panel.actions.archive', $action))->assertRedirect();
        $this->assertNotNull($action->fresh()->archived_at);

        $this->actingAs($editor->fresh())->post(route('panel.actions.destroy', $action))->assertRedirect();
        $this->assertSoftDeleted('actions', ['id' => $action->id]);
        $this->actingAs($editor->fresh())->delete(route('panel.actions.force-delete', $action->id), ['confirm_title' => 'Feira de Inovação'])->assertForbidden();

        $this->actingAs($master)->delete(route('panel.actions.force-delete', $action->id), ['confirm_title' => 'nome errado'])->assertSessionHasErrors('confirm_title');
        $this->actingAs($master)->delete(route('panel.actions.force-delete', $action->id), ['confirm_title' => 'Feira de Inovação'])->assertRedirect();
        $this->assertDatabaseMissing('actions', ['id' => $action->id]);
    }

    public function test_concurrent_edit_does_not_overwrite_silently(): void
    {
        $editor = $this->makeUser(User::EDITOR, [$this->area]);
        $action = app(ActionWorkflow::class)->create($this->portfolio, $editor, ['title' => 'Original', 'primary_page_id' => $this->area->id]);
        $stale = (string) ($action->workingVersion->updated_at->getTimestamp() - 60);

        $this->actingAs($editor)->put(route('panel.actions.update', $action), ['title' => 'Tentativa', 'primary_page_id' => $this->area->id, 'version_stamp' => $stale])
            ->assertSessionHas('warning')->assertSessionHasInput('title', 'Tentativa');
        $this->assertSame('Original', $action->fresh()->workingVersion->title);
    }

    public function test_restore_old_version_creates_new_working_version(): void
    {
        $editor = $this->makeUser(User::EDITOR, [$this->area]);
        $action = $this->makePublishedAction($this->area, $editor, ['title' => 'Versão um']);
        $workflow = app(ActionWorkflow::class);
        $workflow->startWorkingCopy($action, $editor);
        $workflow->save($action->fresh(), $editor, $this->completeData($this->area, ['title' => 'Versão dois', 'slug' => $action->slug]));
        $workflow->publish($action->fresh(), $editor);

        $first = $action->versions()->where('number', 1)->first();
        $this->actingAs($editor)->post(route('panel.actions.versions.restore', [$action, $first]))->assertRedirect();
        $action->refresh();
        $this->assertSame('Versão um', $action->workingVersion->title);
        $this->assertSame(3, $action->workingVersion->number);
        $this->get(route('actions.show', $action->slug))->assertSee('Versão dois');
    }

    public function test_page_changes_are_published_only_on_publish(): void
    {
        $master = $this->makeUser(User::MASTER);
        $this->actingAs($master)->post(route('panel.pages.blocks.store', $this->area), ['type' => 'texto'])->assertRedirect();
        $block = $this->area->blocks()->where('type', 'texto')->first();
        $this->actingAs($master)->put(route('panel.pages.blocks.update', [$this->area, $block]), ['heading' => 'Sobre', 'text' => 'Texto novo da área'])->assertRedirect();

        $this->get(route('pages.show', 'inovacao'))->assertDontSee('Texto novo da área');
        $this->actingAs($master)->get(route('panel.pages.preview.content', $this->area))->assertSee('Texto novo da área');
        $this->actingAs($master)->post(route('panel.pages.publish', $this->area))->assertRedirect();
        $this->get(route('pages.show', 'inovacao'))->assertSee('Texto novo da área');

        // Ocultar não altera o site até publicar de novo.
        $this->actingAs($master)->post(route('panel.pages.blocks.toggle', [$this->area, $block]));
        $this->get(route('pages.show', 'inovacao'))->assertSee('Texto novo da área');

        // Editor não muda a estrutura (endereço), mas edita conteúdo da própria área.
        $editor = $this->makeUser(User::EDITOR, [$this->area]);
        $this->actingAs($editor)->post(route('panel.pages.store'), ['title' => 'Nova', 'kind' => 'area'])->assertForbidden();
        $this->actingAs($editor)->put(route('panel.pages.update', $this->area), ['slug' => 'outro', 'title' => 'X', 'kind' => 'area', 'description' => 'Descrição do editor'])->assertRedirect();
        $this->assertSame('inovacao', $this->area->fresh()->slug);
        $this->assertSame('Descrição do editor', $this->area->fresh()->description);
    }

    public function test_backup_and_restore_roundtrip(): void
    {
        $master = $this->makeUser(User::MASTER);
        $action = $this->makePublishedAction($this->area, $master, ['title' => 'Ação guardada']);
        $media = app(\App\Services\MediaStore::class)->storeUploadedImage(UploadedFile::fake()->image('f.jpg', 500, 400), $this->portfolio, $master);
        $action->publishedVersion->media()->create(['media_id' => $media->id, 'is_cover' => true]);

        $dir = sys_get_temp_dir().'/bkp-'.uniqid();
        $this->assertSame(0, Artisan::call('mostraqui:backup', ['--destino' => $dir]));
        $file = glob($dir.'/backup-*.zip')[0];

        Action::query()->forceDelete();
        Storage::disk('local')->deleteDirectory('media');
        $this->assertSame(0, Action::count());

        $this->assertSame(0, Artisan::call('mostraqui:restaurar', ['arquivo' => $file, '--forcar' => true]));
        $this->assertSame('Ação guardada', Action::first()->publishedVersion->title);
        $this->assertTrue(Storage::disk('local')->exists($media->variants['md']['path']));
    }

    public function test_demo_data_is_refused_in_production(): void
    {
        $this->app['env'] = 'production';
        $this->assertSame(1, Artisan::call('mostraqui:demo'));
        $this->assertFalse($this->portfolio->fresh()->is_demo);
    }

    public function test_unpublished_page_cover_and_unpublished_related_pages_stay_private(): void
    {
        $master = $this->makeUser(User::MASTER);
        $hidden = $this->makePage('Página ainda não publicada', null, false);
        $action = $this->makePublishedAction($this->area, $master, ['title' => 'Ação com relacionada', 'related_page_ids' => [$hidden->id]]);
        $this->get(route('actions.show', $action->slug))->assertOk()->assertDontSee('Página ainda não publicada');

        // Nova capa salva, mas não publicada: o site continua sem ela e o arquivo segue privado.
        $this->actingAs($master)->put(route('panel.pages.update', $this->area), [
            'title' => 'Inovação', 'kind' => 'area', 'slug' => 'inovacao', 'cover' => UploadedFile::fake()->image('capa.jpg', 1200, 600),
        ])->assertRedirect()->assertSessionHasNoErrors();
        $cover = $this->area->fresh()->cover;
        $this->assertNotNull($cover);
        $this->get(route('areas.index'))->assertOk()->assertDontSee($cover->uuid);
        $this->get(route('media.show', [$cover->uuid, 'md']))->assertNotFound();

        $this->actingAs($master)->post(route('panel.pages.publish', $this->area))->assertRedirect();
        $this->get(route('areas.index'))->assertSee($cover->uuid);
        $this->get(route('media.show', [$cover->uuid, 'md']))->assertOk();
    }
}
