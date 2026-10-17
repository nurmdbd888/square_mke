<?php

namespace App\Domain\Google;

use App\Models\GoogleConnection;
use App\Models\User;
use Google\Client as GoogleClient;
use Google\Service\Oauth2;
use RuntimeException;

final class GoogleOAuthService
{
    public function client(?GoogleConnection $connection = null): GoogleClient
    {
        $client = new GoogleClient;
        $client->setClientId((string) config('google.client_id'));
        $client->setClientSecret((string) config('google.client_secret'));
        $client->setRedirectUri((string) config('google.redirect_uri'));
        $client->setAccessType('offline');
        $client->setPrompt('consent');
        $client->setScopes(config('google.scopes'));

        if ($connection !== null && $connection->refresh_token) {
            $client->setAccessToken([
                'access_token' => $connection->access_token,
                'refresh_token' => $connection->refresh_token,
                'expires_in' => $connection->token_expires_at?->diffInSeconds(now()) ?? 0,
            ]);
            if ($client->isAccessTokenExpired()) {
                $token = $client->fetchAccessTokenWithRefreshToken($connection->refresh_token);
                if (isset($token['error'])) {
                    throw new RuntimeException((string) $token['error']);
                }
                $connection->update([
                    'access_token' => $token['access_token'] ?? null,
                    'token_expires_at' => now()->addSeconds((int) ($token['expires_in'] ?? 3600)),
                ]);
                $client->setAccessToken($token);
            }
        }

        return $client;
    }

    public function authorizationUrl(): string
    {
        if (config('google.client_id') === '') {
            throw new RuntimeException(__('Google OAuth is not configured (GOOGLE_CLIENT_ID).'));
        }

        return $this->client()->createAuthUrl();
    }

    public function connectFromCallback(string $code, User $user): GoogleConnection
    {
        $client = $this->client();
        $token = $client->fetchAccessTokenWithAuthCode($code);
        if (isset($token['error'])) {
            throw new RuntimeException((string) ($token['error_description'] ?? $token['error']));
        }

        $oauth = new Oauth2($client);
        $me = $oauth->userinfo->get();

        GoogleConnection::query()->delete();

        return GoogleConnection::query()->create([
            'connected_by' => $user->id,
            'google_email' => $me->email,
            'access_token' => $token['access_token'] ?? null,
            'refresh_token' => $token['refresh_token'] ?? '',
            'token_expires_at' => now()->addSeconds((int) ($token['expires_in'] ?? 3600)),
        ]);
    }

    public function disconnect(): void
    {
        $connection = GoogleConnection::current();
        if ($connection === null) {
            return;
        }
        try {
            $client = $this->client($connection);
            $client->revokeToken();
        } catch (\Throwable) {
            // still remove local tokens
        }
        $connection->delete();
    }

    public function isConfigured(): bool
    {
        return config('google.client_id') !== '' && config('google.client_secret') !== '';
    }
}
