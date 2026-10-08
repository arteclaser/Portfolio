<?php

namespace Tests\Unit;

use App\Services\SafeFetcher;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Classificação de endereços IP usada na proteção contra SSRF (sem rede nem banco). */
class SafeFetcherIpTest extends TestCase
{
    public static function internos(): array
    {
        return [
            'loopback' => ['127.0.0.1'], 'rede 10' => ['10.0.0.8'], 'rede 172.16' => ['172.20.1.1'],
            'rede 192.168' => ['192.168.0.10'], 'link-local / metadados de nuvem' => ['169.254.169.254'],
            'CGNAT' => ['100.64.10.1'], 'não especificado' => ['0.0.0.0'], 'multicast' => ['239.1.1.1'],
            'documentação' => ['203.0.113.5'], 'IPv6 loopback' => ['::1'], 'IPv6 local único' => ['fd12::1'],
            'IPv6 link-local' => ['fe80::1'], 'IPv4 mapeado em IPv6' => ['::ffff:10.0.0.1'], 'texto inválido' => ['nao-e-ip'],
        ];
    }

    #[DataProvider('internos')]
    public function test_enderecos_internos_sao_bloqueados(string $ip): void
    {
        $this->assertFalse(SafeFetcher::isPublicIp($ip));
    }

    public function test_enderecos_publicos_sao_aceitos(): void
    {
        foreach (['8.8.8.8', '1.1.1.1', '2001:4860:4860::8888'] as $ip) {
            $this->assertTrue(SafeFetcher::isPublicIp($ip), $ip);
        }
    }

    public function test_redirecionamento_relativo_e_resolvido_na_origem(): void
    {
        $fetcher = new SafeFetcher;
        $this->assertSame('https://exemplo.com/b', $fetcher->absolutize('/b', 'https://exemplo.com/a/c'));
        $this->assertSame('https://exemplo.com/a/d', $fetcher->absolutize('d', 'https://exemplo.com/a/c'));
        $this->assertSame('https://outro.com/x', $fetcher->absolutize('//outro.com/x', 'https://exemplo.com/'));
    }
}
