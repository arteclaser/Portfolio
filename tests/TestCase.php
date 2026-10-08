<?php

namespace Tests;

use App\Models\Action;
use App\Models\Page;
use App\Models\Portfolio;
use App\Models\User;
use App\Services\ActionWorkflow;
use App\Services\PagePublisher;
use App\Support\Slug;
use App\Support\Tenant;
use Illuminate\Support\Facades\URL;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Storage;

abstract class TestCase extends BaseTestCase
{
    protected Portfolio $portfolio;

    protected function setUp(): void
    {
        parent::setUp();
        Tenant::forget();
        Storage::fake('local');
    }

    protected function tearDown(): void
    {
        Tenant::forget();
        parent::tearDown();
    }

    protected function makePortfolio(array $settings = [], string $slug = 'capinzal', bool $activate = true): Portfolio
    {
        $portfolio = Portfolio::create([
            'name' => 'Portfólio '.ucfirst($slug),
            'slug' => $slug,
            'short_name' => ucfirst($slug),
            'settings' => array_merge(Portfolio::DEFAULT_SETTINGS, $settings),
        ]);
        if ($activate) {
            $this->usePortfolio($portfolio);
        }

        return $portfolio;
    }

    /** Define o portfólio do teste (como faria o middleware numa requisição). */
    protected function usePortfolio(Portfolio $portfolio): void
    {
        $this->portfolio = $portfolio;
        Tenant::set($portfolio);
        URL::defaults(['portfolio' => $portfolio->slug]);
    }

    /** Endereço público dentro do portfólio atual (modo caminho). */
    protected function pub(string $path = ''): string
    {
        return '/'.$this->portfolio->slug.($path === '' ? '' : '/'.ltrim($path, '/'));
    }

    protected function makePage(string $title, ?Page $parent = null, bool $publish = true): Page
    {
        $page = Page::create([
            'portfolio_id' => $this->portfolio->id,
            'parent_id' => $parent?->id,
            'kind' => $parent ? 'programa' : 'area',
            'title' => $title,
            'slug' => Slug::make($title),
        ]);
        $page->blocks()->create(['type' => 'lista_acoes', 'position' => 0, 'settings' => ['limit' => 9]]);
        if ($publish) {
            app(PagePublisher::class)->publish($page, null);
        }

        return $page->fresh();
    }

    /** @param  list<Page>  $pages */
    protected function makeUser(string $role, array $pages = [], array $attributes = []): User
    {
        $user = User::factory()->create(['role' => $role, 'portfolio_id' => $this->portfolio->id] + $attributes);
        $user->pages()->sync(array_map(fn (Page $p) => $p->id, $pages));

        return $user;
    }

    protected function completeData(Page $page, array $overrides = []): array
    {
        return array_merge([
            'title' => 'Feira de Inovação',
            'summary' => 'Resumo da feira.',
            'description' => 'Descrição completa da feira.',
            'primary_page_id' => $page->id,
            'starts_on' => '2026-05-10',
            'activity_status' => 'concluida',
        ], $overrides);
    }

    protected function makePublishedAction(Page $page, User $author, array $data = []): Action
    {
        $workflow = app(ActionWorkflow::class);
        $action = $workflow->create($this->portfolio, $author, ['primary_page_id' => $page->id]);
        $workflow->save($action, $author, $this->completeData($page, $data));
        $workflow->publish($action->fresh(), $author);

        return $action->fresh();
    }

    /** Uma imagem JPEG real, gerada com GD. */
    protected function jpeg(int $width = 800, int $height = 600): string
    {
        $img = imagecreatetruecolor($width, $height);
        imagefill($img, 0, 0, imagecolorallocate($img, 30, 80, 200));
        $path = tempnam(sys_get_temp_dir(), 'img').'.jpg';
        imagejpeg($img, $path, 90);
        imagedestroy($img);

        return $path;
    }
}
