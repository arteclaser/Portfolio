<?php

namespace App\Services;

/**
 * Consulta endereços externos com proteções contra SSRF:
 * - somente http/https nas portas 80 e 443, sem usuário/senha na URL;
 * - o nome é resolvido antes e TODOS os IPs precisam ser públicos;
 * - a conexão é fixada no IP validado (evita troca de DNS no meio do caminho);
 * - redirecionamentos seguidos manualmente, revalidados e limitados;
 * - limite de tempo e de tamanho da resposta.
 */
class SafeFetcher
{
    private const BLOCKED_V4 = [
        '0.0.0.0/8', '10.0.0.0/8', '100.64.0.0/10', '127.0.0.0/8', '169.254.0.0/16', '172.16.0.0/12',
        '192.0.0.0/24', '192.0.2.0/24', '192.88.99.0/24', '192.168.0.0/16', '198.18.0.0/15',
        '198.51.100.0/24', '203.0.113.0/24', '224.0.0.0/4', '240.0.0.0/4',
    ];

    private const BLOCKED_V6 = [
        '::/128', '::1/128', '::ffff:0:0/96', '64:ff9b::/96', '64:ff9b:1::/48', '100::/64', '2001::/32',
        '2001:10::/28', '2001:20::/28', '2001:db8::/32', '2002::/16', 'fc00::/7', 'fe80::/10', 'fec0::/10', 'ff00::/8',
    ];

    /** @var callable(string): list<string>|null */
    private $resolver;

    public function __construct(
        private int $timeout = 6,
        private int $connectTimeout = 3,
        private int $maxRedirects = 3,
        ?callable $resolver = null,
    ) {
        $this->resolver = $resolver;
    }

    /**
     * @param  list<string>  $acceptedTypes  prefixos de Content-Type aceitos (ex.: text/html)
     * @return array{url: string, status: int, content_type: string, body: string}
     */
    public function get(string $url, array $acceptedTypes, int $maxBytes, string $accept = 'text/html,application/xhtml+xml;q=0.9,*/*;q=0.5'): array
    {
        if (! function_exists('curl_init')) {
            throw new FetchException('A extensão cURL do PHP não está disponível no servidor.');
        }

        $current = $url;
        for ($hop = 0; $hop <= $this->maxRedirects; $hop++) {
            [$host, $port] = $this->validateUrl($current);
            $ip = $this->resolvePublicIp($host);
            $response = $this->request($current, $host, $port, $ip, $maxBytes, $accept);

            if ($response['status'] >= 300 && $response['status'] < 400 && $response['location']) {
                $current = $this->absolutize($response['location'], $current);

                continue;
            }
            if ($response['status'] >= 400) {
                throw new FetchException("O site respondeu com o código {$response['status']}.");
            }
            $type = strtolower(trim(explode(';', $response['content_type'])[0] ?? ''));
            $ok = false;
            foreach ($acceptedTypes as $prefix) {
                if (str_starts_with($type, $prefix)) {
                    $ok = true;
                }
            }
            if (! $ok) {
                throw new FetchException('O endereço não retornou um conteúdo do tipo esperado.');
            }

            return [
                'url' => $current,
                'status' => $response['status'],
                'content_type' => $response['content_type'],
                'body' => $response['body'],
            ];
        }

        throw new FetchException('O endereço fez redirecionamentos demais.');
    }

    /** @return array{0: string, 1: int} */
    public function validateUrl(string $url): array
    {
        if (strlen($url) > 2048) {
            throw new FetchException('O endereço é longo demais.');
        }
        $parts = parse_url($url);
        $scheme = strtolower($parts['scheme'] ?? '');
        $host = strtolower(rtrim($parts['host'] ?? '', '.'));
        if (! in_array($scheme, ['http', 'https'], true) || $host === '') {
            throw new FetchException('Use um endereço completo começando com http:// ou https://.');
        }
        if (isset($parts['user']) || isset($parts['pass'])) {
            throw new FetchException('Endereços com usuário ou senha não são aceitos.');
        }
        $port = (int) ($parts['port'] ?? ($scheme === 'https' ? 443 : 80));
        if (! in_array($port, [80, 443], true)) {
            throw new FetchException('Somente as portas padrão (80 e 443) são permitidas.');
        }
        if ($host === 'localhost' || preg_match('/\.(localhost|local|internal|lan|home|intranet|corp)$/', $host)) {
            throw new FetchException('Endereços internos ou locais não são permitidos.');
        }

        return [trim($host, '[]'), $port];
    }

    public function resolvePublicIp(string $host): string
    {
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            $ips = [$host];
        } else {
            $ips = $this->resolver ? ($this->resolver)($host) : $this->resolve($host);
        }
        if (! $ips) {
            throw new FetchException('Não foi possível localizar o site informado.');
        }
        foreach ($ips as $ip) {
            if (! self::isPublicIp($ip)) {
                throw new FetchException('Endereços internos ou locais não são permitidos.');
            }
        }
        foreach ($ips as $ip) {
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                return $ip;
            }
        }

        return $ips[0];
    }

    /** @return list<string> */
    private function resolve(string $host): array
    {
        $ips = [];
        $records = @dns_get_record($host, DNS_A | DNS_AAAA) ?: [];
        foreach ($records as $record) {
            if (isset($record['ip'])) {
                $ips[] = $record['ip'];
            }
            if (isset($record['ipv6'])) {
                $ips[] = $record['ipv6'];
            }
        }
        if (! $ips) {
            $ips = @gethostbynamel($host) ?: [];
        }

        return array_values(array_unique($ips));
    }

    public static function isPublicIp(string $ip): bool
    {
        if (! filter_var($ip, FILTER_VALIDATE_IP)) {
            return false;
        }
        if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return false;
        }
        $isV4 = (bool) filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4);
        foreach ($isV4 ? self::BLOCKED_V4 : self::BLOCKED_V6 as $cidr) {
            if (self::inCidr($ip, $cidr)) {
                return false;
            }
        }

        return true;
    }

    private static function inCidr(string $ip, string $cidr): bool
    {
        [$subnet, $bits] = explode('/', $cidr);
        $ipBin = @inet_pton($ip);
        $subnetBin = @inet_pton($subnet);
        if ($ipBin === false || $subnetBin === false || strlen($ipBin) !== strlen($subnetBin)) {
            return false;
        }
        $bits = (int) $bits;
        $bytes = intdiv($bits, 8);
        if (strncmp($ipBin, $subnetBin, $bytes) !== 0) {
            return false;
        }
        $remainder = $bits % 8;
        if ($remainder === 0) {
            return true;
        }
        $mask = (0xFF << (8 - $remainder)) & 0xFF;

        return (ord($ipBin[$bytes]) & $mask) === (ord($subnetBin[$bytes]) & $mask);
    }

    /** @return array{status: int, location: ?string, content_type: string, body: string} */
    private function request(string $url, string $host, int $port, string $ip, int $maxBytes, string $accept): array
    {
        $body = '';
        $headers = [];
        $tooLarge = false;
        $resolveIp = str_contains($ip, ':') ? "[{$ip}]" : $ip;

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RESOLVE => ["{$host}:{$port}:{$resolveIp}"],
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_CONNECTTIMEOUT => $this->connectTimeout,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_ENCODING => '',
            CURLOPT_USERAGENT => 'MostraquiPortfolio/1.0 (+'.config('app.url').')',
            CURLOPT_HTTPHEADER => ['Accept: '.$accept, 'Accept-Language: pt-BR,pt;q=0.9,en;q=0.5'],
            CURLOPT_PROXY => (string) config('services.link_preview.proxy', ''),
            CURLOPT_HEADERFUNCTION => function ($ch, string $line) use (&$headers, $maxBytes, &$tooLarge) {
                $parts = explode(':', $line, 2);
                if (count($parts) === 2) {
                    $name = strtolower(trim($parts[0]));
                    $headers[$name] = trim($parts[1]);
                    if ($name === 'content-length' && (int) $headers[$name] > $maxBytes) {
                        $tooLarge = true;

                        return -1;
                    }
                }

                return strlen($line);
            },
            CURLOPT_WRITEFUNCTION => function ($ch, string $chunk) use (&$body, $maxBytes, &$tooLarge) {
                if (strlen($body) + strlen($chunk) > $maxBytes) {
                    $tooLarge = true;

                    return 0;
                }
                $body .= $chunk;

                return strlen($chunk);
            },
        ]);
        $ok = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $errno = curl_errno($ch);
        curl_close($ch);

        if ($tooLarge) {
            throw new FetchException('O conteúdo do endereço é maior que o limite permitido.');
        }
        if ($ok === false || $errno !== 0) {
            throw new FetchException($errno === CURLE_OPERATION_TIMEDOUT
                ? 'O site demorou demais para responder.'
                : 'Não foi possível conectar ao site informado.');
        }

        return [
            'status' => $status,
            'location' => $headers['location'] ?? null,
            'content_type' => $headers['content-type'] ?? '',
            'body' => $body,
        ];
    }

    public function absolutize(string $location, string $base): string
    {
        $location = trim($location);
        if (preg_match('#^https?://#i', $location)) {
            return $location;
        }
        $b = parse_url($base);
        $origin = ($b['scheme'] ?? 'https').'://'.($b['host'] ?? '').(isset($b['port']) ? ':'.$b['port'] : '');
        if (str_starts_with($location, '//')) {
            return ($b['scheme'] ?? 'https').':'.$location;
        }
        if (str_starts_with($location, '/')) {
            return $origin.$location;
        }
        $path = $b['path'] ?? '/';
        $dir = substr($path, 0, (int) strrpos($path, '/') + 1);

        return $origin.$dir.$location;
    }
}
