<?php

namespace Tests\Feature;

use App\Services\DeployFinisher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/** Conclusão da implantação por FTP (POST /_implantacao/finalizar). */
class DeployEndpointTest extends TestCase
{
    use RefreshDatabase;

    private string $dir;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir().'/implantacao-'.bin2hex(random_bytes(4));
        mkdir($this->dir.'/.implantacao', 0755, true);
        copy(base_path('.env.example'), $this->dir.'/.env.example');
        $this->app->instance(DeployFinisher::class, new DeployFinisher($this->dir));
        $this->token = bin2hex(random_bytes(32));
        file_put_contents($this->dir.'/'.DeployFinisher::TOKEN_FILE, hash('sha256', $this->token)."\n");
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->dir);
        if ($this->app?->maintenanceMode()->active()) {
            $this->app->maintenanceMode()->deactivate();
        }
        parent::tearDown();
    }

    private function finish(?string $token = null)
    {
        return $this->postJson('/_implantacao/finalizar', [], ['X-Implantacao-Token' => $token ?? $this->token]);
    }

    public function test_route_does_not_exist_without_a_valid_single_use_code(): void
    {
        $this->finish(bin2hex(random_bytes(32)))->assertNotFound();
        $this->finish('curto')->assertNotFound();
        $this->postJson('/_implantacao/finalizar')->assertNotFound();
        $this->get('/_implantacao/finalizar')->assertStatus(405);

        // Código esquecido no servidor perde a validade em uma hora.
        touch($this->dir.'/'.DeployFinisher::TOKEN_FILE, time() - DeployFinisher::TOKEN_TTL - 60);
        $this->finish()->assertNotFound();

        unlink($this->dir.'/'.DeployFinisher::TOKEN_FILE);
        $this->finish()->assertNotFound();
    }

    public function test_first_deploy_creates_env_with_new_app_key_and_asks_for_configuration(): void
    {
        $response = $this->finish()->assertStatus(202)->assertJsonPath('status', 'configurar');

        $env = file_get_contents($this->dir.'/.env');
        $this->assertMatchesRegularExpression('/^APP_KEY=base64:[A-Za-z0-9+\/]{43}=$/m', $env);
        $this->assertSame('0600', substr(sprintf('%o', fileperms($this->dir.'/.env')), -4));
        $this->assertStringContainsString('DB_DATABASE', $response->json('mensagens.0'));
        foreach (['storage/framework/views', 'storage/framework/cache/data', 'storage/logs', 'bootstrap/cache'] as $dir) {
            $this->assertDirectoryExists($this->dir.'/'.$dir);
        }

        // Na segunda vez o .env é mantido como está.
        $this->finish();
        $this->assertSame($env, file_get_contents($this->dir.'/.env'));
    }

    public function test_finishes_during_maintenance_updates_database_and_brings_site_back(): void
    {
        file_put_contents($this->dir.'/.env', "APP_KEY=\nDB_CONNECTION=sqlite\n");
        $this->app->maintenanceMode()->activate(['except' => [], 'redirect' => null, 'retry' => 30, 'refresh' => 15, 'secret' => null, 'status' => 503, 'template' => null]);
        $this->get('/entrar')->assertStatus(503);

        $response = $this->finish()->assertOk()->assertJsonPath('status', 'concluido');

        $this->assertFalse($this->app->maintenanceMode()->active());
        $this->assertContains('Banco de dados já estava atualizado.', $response->json('mensagens'));
        $this->assertStringContainsString('APP_KEY estava vazia', $response->json('mensagens.0'));
        $this->assertMatchesRegularExpression('/^APP_KEY=base64:\S+$/m', file_get_contents($this->dir.'/.env'));
        $this->assertSame(PHP_VERSION, $response->json('php'));
        $this->assertEmpty($response->headers->getCookies());
        $this->get('/entrar')->assertOk();
    }
}
