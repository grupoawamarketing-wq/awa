<?php
declare(strict_types=1);
/**
 * github-client.php — Cliente GitHub API com retry + backoff exponencial (PHP 8.4)
 * Uso: GITHUB_TOKEN=ghp_... php scripts/github-client.php
 */

class GitHubClient
{
    private const API_BASE    = 'https://api.github.com';
    private const MAX_RETRIES = 3;
    private const API_VERSION = '2022-11-28';

    private string $token;

    public function __construct(string $token = '')
    {
        $this->token = $token ?: (getenv('GITHUB_TOKEN') ?: getenv('GH_TOKEN') ?: '');
        if (empty($this->token)) {
            throw new \RuntimeException(
                "GITHUB_TOKEN não definido.\n" .
                "Uso: export GITHUB_TOKEN='ghp_seu_token'\n"
            );
        }
    }

    /**
     * Faz uma requisição à API do GitHub com retry + backoff exponencial.
     *
     * @param string               $path    Endpoint (ex: '/rate_limit', '/user')
     * @param string               $method  HTTP method (GET, POST, PATCH, DELETE)
     * @param array<string, mixed> $body    Payload para POST/PATCH
     * @param int                  $attempt Tentativa atual (uso interno)
     * @return array<string, mixed>
     * @throws \RuntimeException em erros não recuperáveis
     */
    public function request(
        string $path,
        string $method = 'GET',
        array $body = [],
        int $attempt = 1
    ): array {
        $backoffSec = (int) pow(2, $attempt - 1); // 1s, 2s, 4s

        $headers = [
            "Authorization: Bearer {$this->token}",
            'Accept: application/vnd.github+json',
            "X-GitHub-Api-Version: " . self::API_VERSION,
            'User-Agent: awamotos-magento2/1.0',
        ];

        $ctx = stream_context_create([
            'http' => [
                'method'        => $method,
                'header'        => implode("\r\n", $headers),
                'content'       => !empty($body) ? json_encode($body) : null,
                'ignore_errors' => true,
                'timeout'       => 30,
            ],
            'ssl' => ['verify_peer' => true],
        ]);

        $url      = self::API_BASE . $path;
        $response = @file_get_contents($url, false, $ctx);
        $status   = $this->parseHttpStatus($http_response_header ?? []);

        if (in_array($status, [200, 201, 204], true)) {
            return $response ? (array) json_decode($response, true) : [];
        }

        // Rate limit / servidor instável → retry com backoff
        if (in_array($status, [403, 429, 500, 502, 503], true) && $attempt <= self::MAX_RETRIES) {
            $resetAt   = $this->extractHeader($http_response_header ?? [], 'X-RateLimit-Reset');
            $waitSec   = $resetAt ? max(1, (int) $resetAt - time()) : $backoffSec;
            $waitSec   = min($waitSec, 60); // cap: 60s

            fwrite(STDERR, sprintf(
                "[Retry] HTTP %d — aguardando %ds (tentativa %d/%d)\n",
                $status, $waitSec, $attempt, self::MAX_RETRIES
            ));
            sleep($waitSec);
            return $this->request($path, $method, $body, $attempt + 1);
        }

        if ($status === 401) {
            throw new \RuntimeException("Token inválido ou expirado (401). Gere em github.com/settings/tokens");
        }

        throw new \RuntimeException("GitHub API erro {$status}: {$response}");
    }

    /** @return array<string, mixed> */
    public function getRateLimit(): array
    {
        return $this->request('/rate_limit');
    }

    /** @return array<string, mixed> */
    public function getCurrentUser(): array
    {
        return $this->request('/user');
    }

    /**
     * @param string[] $headers
     */
    private function parseHttpStatus(array $headers): int
    {
        foreach ($headers as $h) {
            if (preg_match('/HTTP\/\S+\s+(\d{3})/', $h, $m)) {
                return (int) $m[1];
            }
        }
        return 0;
    }

    /**
     * @param string[] $headers
     */
    private function extractHeader(array $headers, string $name): string
    {
        foreach ($headers as $h) {
            if (stripos($h, "{$name}:") === 0) {
                return trim(explode(':', $h, 2)[1]);
            }
        }
        return '';
    }
}

// ── Demo ──────────────────────────────────────────────────────────────────────
if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    try {
        $client   = new GitHubClient();
        $user     = $client->getCurrentUser();
        $limits   = $client->getRateLimit();
        $rate     = $limits['rate'] ?? [];
        $resetAt  = isset($rate['reset'])
            ? (new \DateTimeImmutable('@' . $rate['reset']))->setTimezone(new \DateTimeZone('America/Sao_Paulo'))->format('H:i:s')
            : '?';

        printf("Autenticado como: %s (%s)\n", $user['login'] ?? '?', $user['name'] ?? '?');
        printf("Rate limit: %d/%d (reset %s)\n", $rate['remaining'] ?? 0, $rate['limit'] ?? 0, $resetAt);
    } catch (\RuntimeException $e) {
        fwrite(STDERR, "Erro: {$e->getMessage()}\n");
        exit(1);
    }
}
