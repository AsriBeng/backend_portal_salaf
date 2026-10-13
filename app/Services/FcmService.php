<?php

namespace App\Services;

use App\Models\DeviceToken;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Kirim push notification lewat Firebase Cloud Messaging HTTP v1.
 * Butuh file service account JSON dari Firebase Console (FCM_CREDENTIALS di .env).
 * Jika belum diatur, pengiriman dilewati tanpa error.
 */
class FcmService
{
    /** Harus sama dengan ID saluran notifikasi yang dibuat di aplikasi Android (MainActivity.kt). */
    public const CHANNEL_ANDROID = 'portal_salaf_penting';

    public function enabled(): bool
    {
        $path = $this->credentialsPath();

        return $path !== null && is_file($path);
    }

    /**
     * @param  array<int, string>  $tokens
     * @param  array<string, string>  $data
     */
    public function send(array $tokens, string $title, string $body, array $data = []): void
    {
        if ($tokens === [] || ! $this->enabled()) {
            return;
        }

        $credentials = $this->credentials();
        $url = "https://fcm.googleapis.com/v1/projects/{$credentials['project_id']}/messages:send";
        $accessToken = $this->accessToken($credentials);

        foreach ($tokens as $token) {
            $response = Http::timeout(15)->withToken($accessToken)->post($url, [
                'message' => [
                    'token' => $token,
                    'notification' => ['title' => $title, 'body' => $body],
                    'data' => array_map('strval', $data),
                    'android' => [
                        // Prioritas tinggi: dikirim segera meskipun HP sedang mode hemat daya (Doze)
                        'priority' => 'high',
                        'ttl' => '86400s',
                        'notification' => [
                            // Saluran berprioritas tinggi yang dibuat aplikasi (MainActivity.kt)
                            'channel_id' => self::CHANNEL_ANDROID,
                            'notification_priority' => 'PRIORITY_HIGH',
                            'default_sound' => true,
                            'default_vibrate_timings' => true,
                            'visibility' => 'PUBLIC',
                        ],
                    ],
                    'apns' => [
                        'headers' => ['apns-priority' => '10'],
                        'payload' => ['aps' => ['sound' => 'default']],
                    ],
                ],
            ]);

            $ujungToken = substr($token, -8);

            if ($response->status() === 404 || str_contains($response->body(), 'UNREGISTERED')) {
                // Token sudah tidak berlaku (aplikasi dihapus / logout)
                DeviceToken::where('token', $token)->delete();
                Log::info("FCM: token …{$ujungToken} tidak berlaku, dihapus");
            } elseif ($response->failed()) {
                Log::warning('FCM gagal mengirim notifikasi', ['token' => "…{$ujungToken}", 'status' => $response->status(), 'body' => $response->body()]);
            } else {
                // Dicatat supaya waktu kirim di server bisa dibandingkan dengan waktu muncul di HP
                Log::info("FCM terkirim ke …{$ujungToken}: {$title}");
            }
        }
    }

    private function credentialsPath(): ?string
    {
        $path = config('services.fcm.credentials');

        if (! $path) {
            return null;
        }

        return str_starts_with($path, '/') || preg_match('/^[A-Za-z]:[\\\\\/]/', $path) ? $path : base_path($path);
    }

    /** @return array<string, string> */
    private function credentials(): array
    {
        $json = json_decode((string) file_get_contents($this->credentialsPath()), true);

        if (! isset($json['client_email'], $json['private_key'], $json['project_id'])) {
            throw new RuntimeException('File kredensial FCM tidak valid.');
        }

        return $json;
    }

    /** @param  array<string, string>  $credentials */
    private function accessToken(array $credentials): string
    {
        return Cache::remember('fcm_access_token', now()->addMinutes(50), function () use ($credentials) {
            $tokenUri = $credentials['token_uri'] ?? 'https://oauth2.googleapis.com/token';
            $now = time();

            $segments = [
                $this->base64Url(json_encode(['alg' => 'RS256', 'typ' => 'JWT'])),
                $this->base64Url(json_encode([
                    'iss' => $credentials['client_email'],
                    'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                    'aud' => $tokenUri,
                    'iat' => $now,
                    'exp' => $now + 3600,
                ])),
            ];

            openssl_sign(implode('.', $segments), $signature, $credentials['private_key'], OPENSSL_ALGO_SHA256);
            $segments[] = $this->base64Url($signature);

            return Http::asForm()->post($tokenUri, [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => implode('.', $segments),
            ])->throw()->json('access_token');
        });
    }

    private function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
