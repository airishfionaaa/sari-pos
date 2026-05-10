<?php
/**
 * PusherService — server-side Pusher HTTP API integration.
 * Keys loaded exclusively from .env — NEVER hardcoded in JS files.
 */
class PusherService
{
    private string $appId;
    private string $appKey;
    private string $appSecret;
    private string $cluster;

    public function __construct()
    {
        $this->appId     = EnvLoader::get('PUSHER_APP_ID',      '');
        $this->appKey    = EnvLoader::get('PUSHER_APP_KEY',     '');
        $this->appSecret = EnvLoader::get('PUSHER_APP_SECRET',  '');
        $this->cluster   = EnvLoader::get('PUSHER_APP_CLUSTER', 'mt1');
    }

    public function isConfigured(): bool
    {
        return !empty($this->appId) && $this->appId !== 'your_pusher_app_id';
    }

    /** Trigger event on a Pusher channel */
    public function trigger(string $channel, string $event, array $data): bool
    {
        if (!$this->isConfigured()) return false;

        $body      = json_encode(['name' => $event, 'channel' => $channel, 'data' => json_encode($data)]);
        $ts        = time();
        $path      = "/apps/{$this->appId}/events";
        $queryStr  = $this->buildQueryString($body, $ts, $path);
        $signature = hash_hmac('sha256', "POST\n{$path}\n{$queryStr}", $this->appSecret);
        $url       = "https://api-{$this->cluster}.pusher.com{$path}?{$queryStr}&auth_signature={$signature}";

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT        => 5,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return $code === 200;
    }

    private function buildQueryString(string $body, int $ts, string $path): string
    {
        return http_build_query([
            'auth_key'       => $this->appKey,
            'auth_timestamp' => $ts,
            'auth_version'   => '1.0',
            'body_md5'       => md5($body),
        ]);
    }

    /** Return ONLY public config (key + cluster) for JS — secret stays server-side */
    public function getJsConfig(): array
    {
        return [
            'key'     => $this->appKey,
            'cluster' => $this->cluster,
        ];
    }
}
