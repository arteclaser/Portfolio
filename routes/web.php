<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\SetupController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\Panel\PlatformPortfolioController;
use App\Http\Controllers\PlatformController;
use App\Http\Controllers\Panel\ActionController;
use App\Http\Controllers\Panel\ActionLinkController;
use App\Http\Controllers\Panel\ActionMediaController;
use App\Http\Controllers\Panel\ActionReviewController;
use App\Http\Controllers\Panel\ActivityController;
use App\Http\Controllers\Panel\BlockController;
use App\Http\Controllers\Panel\CustomFieldController;
use App\Http\Controllers\Panel\DashboardController;
use App\Http\Controllers\Panel\MediaLibraryController;
use App\Http\Controllers\Panel\PageController;
use App\Http\Controllers\Panel\PartnerController;
use App\Http\Controllers\Panel\SettingsController;
use App\Http\Controllers\Panel\TeamController;
use App\Http\Controllers\Panel\UserController;
use Illuminate\Support\Facades\Route;

$central = Route::middleware([]);
if (config('portfolio.routing') === 'subdomain') {
    // No modo subdomínio, painel e acesso ficam só no domínio principal.
    $central->domain(config('portfolio.base_domain'));
}

$central->group(function () {
Route::get('/', [PlatformController::class, 'home'])->name('platform.home');

// Primeiro acesso: cria o portfólio e o primeiro Master (exige SETUP_TOKEN e nenhum usuário).
Route::get('/instalar', [SetupController::class, 'show'])->name('setup');
Route::post('/instalar', [SetupController::class, 'store'])->middleware('throttle:setup')->name('setup.store');

Route::middleware('guest')->group(function () {
    Route::get('/entrar', [LoginController::class, 'show'])->name('login');
    Route::post('/entrar', [LoginController::class, 'login'])->middleware('throttle:login')->name('login.attempt');
    Route::get('/esqueci-senha', [PasswordController::class, 'request'])->name('password.request');
    Route::post('/esqueci-senha', [PasswordController::class, 'email'])->middleware('throttle:password-email')->name('password.email');
    Route::get('/redefinir-senha/{token}', [PasswordController::class, 'reset'])->name('password.reset');
    Route::post('/redefinir-senha', [PasswordController::class, 'update'])->middleware('throttle:login')->name('password.update');
    Route::get('/convite/{token}', [PasswordController::class, 'invite'])->name('invite.accept');
    Route::post('/convite', [PasswordController::class, 'acceptInvite'])->middleware('throttle:login')->name('invite.store');
});

Route::post('/sair', [LoginController::class, 'logout'])->middleware('auth')->name('logout');

Route::prefix('painel')->name('panel.')->middleware(['auth', 'active', 'panel.tenant', 'panel'])->group(function () {
    // Plataforma: portfólios (somente administração da plataforma)
    Route::get('/plataforma/portfolios', [PlatformPortfolioController::class, 'index'])->name('platform.index');
    Route::get('/plataforma/portfolios/novo', [PlatformPortfolioController::class, 'create'])->name('platform.create');
    Route::post('/plataforma/portfolios', [PlatformPortfolioController::class, 'store'])->name('platform.store');
    Route::get('/plataforma/portfolios/{portfolioId}', [PlatformPortfolioController::class, 'edit'])->name('platform.edit')->whereNumber('portfolioId');
    Route::put('/plataforma/portfolios/{portfolioId}', [PlatformPortfolioController::class, 'update'])->name('platform.update')->whereNumber('portfolioId');
    Route::post('/plataforma/portfolios/{portfolioId}/gerenciar', [PlatformPortfolioController::class, 'manage'])->name('platform.manage')->whereNumber('portfolioId');

    Route::get('/', DashboardController::class)->name('dashboard');

    // Mídia privada (rascunhos e prévias), sempre com autorização.
    Route::get('/arquivo/{media:uuid}/{variant}', [MediaController::class, 'panel'])
        ->name('media.file')->where('variant', 'sm|md|lg|xl|file');

    // Ações
    Route::get('/acoes', [ActionController::class, 'index'])->name('actions.index');
    Route::get('/acoes/lixeira', [ActionController::class, 'trash'])->name('actions.trash');
    Route::get('/acoes/nova', [ActionController::class, 'create'])->name('actions.create');
    Route::post('/acoes', [ActionController::class, 'store'])->name('actions.store');
    Route::get('/acoes/{action}', [ActionController::class, 'edit'])->name('actions.edit');
    Route::put('/acoes/{action}', [ActionController::class, 'update'])->name('actions.update');
    Route::post('/acoes/{action}/versao-de-trabalho', [ActionController::class, 'startWorkingCopy'])->name('actions.working');
    Route::post('/acoes/{action}/arquivar', [ActionController::class, 'archive'])->name('actions.archive');
    Route::post('/acoes/{action}/desarquivar', [ActionController::class, 'unarchive'])->name('actions.unarchive');
    Route::post('/acoes/{action}/lixeira', [ActionController::class, 'destroy'])->name('actions.destroy');
    Route::post('/acoes/{trashedAction}/restaurar', [ActionController::class, 'restore'])->name('actions.restore');
    Route::delete('/acoes/{trashedAction}/excluir', [ActionController::class, 'forceDelete'])->name('actions.force-delete');

    Route::get('/acoes/{action}/fotos', [ActionMediaController::class, 'index'])->name('actions.media');
    Route::post('/acoes/{action}/fotos', [ActionMediaController::class, 'store'])->middleware('throttle:uploads')->name('actions.media.store');
    Route::post('/acoes/{action}/fotos/organizar', [ActionMediaController::class, 'update'])->name('actions.media.update');

    Route::get('/acoes/{action}/links', [ActionLinkController::class, 'index'])->name('actions.links');
    Route::post('/acoes/{action}/links/previa', [ActionLinkController::class, 'preview'])->middleware('throttle:link-preview')->name('actions.links.preview');
    Route::post('/acoes/{action}/links', [ActionLinkController::class, 'store'])->middleware('throttle:link-preview')->name('actions.links.store');
    Route::put('/acoes/{action}/links/{link}', [ActionLinkController::class, 'update'])->name('actions.links.update');
    Route::post('/acoes/{action}/links/{link}/imagem', [ActionLinkController::class, 'image'])->middleware('throttle:uploads')->name('actions.links.image');
    Route::post('/acoes/{action}/links/{link}/mover', [ActionLinkController::class, 'move'])->name('actions.links.move');
    Route::delete('/acoes/{action}/links/{link}', [ActionLinkController::class, 'destroy'])->name('actions.links.destroy');

    Route::get('/acoes/{action}/revisao', [ActionReviewController::class, 'show'])->name('actions.review');
    Route::post('/acoes/{action}/enviar-para-revisao', [ActionReviewController::class, 'submit'])->name('actions.submit');
    Route::post('/acoes/{action}/publicar', [ActionReviewController::class, 'publish'])->name('actions.publish');
    Route::post('/acoes/{action}/devolver', [ActionReviewController::class, 'returnForChanges'])->name('actions.return');
    Route::post('/acoes/{action}/descartar-alteracoes', [ActionReviewController::class, 'discard'])->name('actions.discard');
    Route::get('/acoes/{action}/previa', [ActionReviewController::class, 'preview'])->name('actions.preview');
    Route::get('/acoes/{action}/previa/conteudo', [ActionReviewController::class, 'previewContent'])->name('actions.preview.content');
    Route::get('/acoes/{action}/historico', [ActionReviewController::class, 'history'])->name('actions.history');
    Route::post('/acoes/{action}/versoes/{version}/restaurar', [ActionReviewController::class, 'restoreVersion'])->name('actions.versions.restore');

    // Páginas, blocos e campos
    Route::get('/paginas', [PageController::class, 'index'])->name('pages.index');
    Route::get('/paginas/nova', [PageController::class, 'create'])->name('pages.create');
    Route::post('/paginas', [PageController::class, 'store'])->name('pages.store');
    Route::get('/paginas/{page}', [PageController::class, 'edit'])->name('pages.edit');
    Route::put('/paginas/{page}', [PageController::class, 'update'])->name('pages.update');
    Route::post('/paginas/{page}/arquivar', [PageController::class, 'archive'])->name('pages.archive');
    Route::post('/paginas/{page}/desarquivar', [PageController::class, 'unarchive'])->name('pages.unarchive');
    Route::post('/paginas/{page}/publicar', [PageController::class, 'publish'])->name('pages.publish');
    Route::get('/paginas/{page}/previa', [PageController::class, 'preview'])->name('pages.preview');
    Route::get('/paginas/{page}/previa/conteudo', [PageController::class, 'previewContent'])->name('pages.preview.content');
    Route::get('/paginas/{page}/historico', [PageController::class, 'history'])->name('pages.history');
    Route::post('/paginas/{page}/revisoes/{revision}/restaurar', [PageController::class, 'restoreRevision'])->name('pages.revisions.restore');

    Route::get('/paginas/{page}/blocos', [BlockController::class, 'index'])->name('pages.blocks');
    Route::post('/paginas/{page}/blocos', [BlockController::class, 'store'])->name('pages.blocks.store');
    Route::get('/paginas/{page}/blocos/{block}', [BlockController::class, 'edit'])->name('pages.blocks.edit');
    Route::put('/paginas/{page}/blocos/{block}', [BlockController::class, 'update'])->name('pages.blocks.update');
    Route::post('/paginas/{page}/blocos/{block}/mover', [BlockController::class, 'move'])->name('pages.blocks.move');
    Route::post('/paginas/{page}/blocos/{block}/visibilidade', [BlockController::class, 'toggle'])->name('pages.blocks.toggle');
    Route::delete('/paginas/{page}/blocos/{block}', [BlockController::class, 'destroy'])->name('pages.blocks.destroy');

    Route::get('/paginas/{page}/campos', [CustomFieldController::class, 'index'])->name('pages.fields');
    Route::post('/paginas/{page}/campos', [CustomFieldController::class, 'store'])->name('pages.fields.store');
    Route::put('/paginas/{page}/campos/{field}', [CustomFieldController::class, 'update'])->name('pages.fields.update');
    Route::post('/paginas/{page}/campos/{field}/arquivar', [CustomFieldController::class, 'archive'])->name('pages.fields.archive');
    Route::post('/paginas/{page}/campos/{field}/reativar', [CustomFieldController::class, 'unarchive'])->name('pages.fields.unarchive');
    Route::delete('/paginas/{page}/campos/{field}', [CustomFieldController::class, 'destroy'])->name('pages.fields.destroy');

    // Equipe e parceiros
    Route::get('/equipe', [TeamController::class, 'index'])->name('team.index');
    Route::get('/equipe/novo', [TeamController::class, 'create'])->name('team.create');
    Route::post('/equipe', [TeamController::class, 'store'])->name('team.store');
    Route::get('/equipe/{member}', [TeamController::class, 'edit'])->name('team.edit');
    Route::put('/equipe/{member}', [TeamController::class, 'update'])->name('team.update');
    Route::post('/equipe/{member}/arquivar', [TeamController::class, 'archive'])->name('team.archive');
    Route::post('/equipe/{member}/desarquivar', [TeamController::class, 'unarchive'])->name('team.unarchive');

    Route::get('/parceiros', [PartnerController::class, 'index'])->name('partners.index');
    Route::get('/parceiros/novo', [PartnerController::class, 'create'])->name('partners.create');
    Route::post('/parceiros', [PartnerController::class, 'store'])->name('partners.store');
    Route::get('/parceiros/{partner}', [PartnerController::class, 'edit'])->name('partners.edit');
    Route::put('/parceiros/{partner}', [PartnerController::class, 'update'])->name('partners.update');
    Route::post('/parceiros/{partner}/arquivar', [PartnerController::class, 'archive'])->name('partners.archive');
    Route::post('/parceiros/{partner}/desarquivar', [PartnerController::class, 'unarchive'])->name('partners.unarchive');

    // Biblioteca de mídias
    Route::get('/midias', [MediaLibraryController::class, 'index'])->name('media.index');
    Route::post('/midias', [MediaLibraryController::class, 'store'])->middleware('throttle:uploads')->name('media.store');
    Route::get('/midias/{media:uuid}', [MediaLibraryController::class, 'edit'])->name('media.edit');
    Route::put('/midias/{media:uuid}', [MediaLibraryController::class, 'update'])->name('media.update');
    Route::delete('/midias/{media:uuid}', [MediaLibraryController::class, 'destroy'])->name('media.destroy');

    // Administração (Master)
    Route::get('/usuarios', [UserController::class, 'index'])->name('users.index');
    Route::get('/usuarios/novo', [UserController::class, 'create'])->name('users.create');
    Route::post('/usuarios', [UserController::class, 'store'])->name('users.store');
    Route::get('/usuarios/{user}', [UserController::class, 'edit'])->name('users.edit');
    Route::put('/usuarios/{user}', [UserController::class, 'update'])->name('users.update');
    Route::post('/usuarios/{user}/convite', [UserController::class, 'invite'])->name('users.invite');
    Route::post('/usuarios/{user}/desativar', [UserController::class, 'deactivate'])->name('users.deactivate');
    Route::post('/usuarios/{user}/reativar', [UserController::class, 'reactivate'])->name('users.reactivate');

    Route::get('/configuracoes', [SettingsController::class, 'edit'])->name('settings.edit');
    Route::put('/configuracoes', [SettingsController::class, 'update'])->name('settings.update');
    Route::get('/atividades', ActivityController::class)->name('activity.index');
});

if (config('portfolio.routing') === 'subdomain') {
    Route::get('/{portfolio}/{rest?}', [PlatformController::class, 'redirectToSubdomain'])->where('rest', '.*')->name('platform.redirect');
}
});
