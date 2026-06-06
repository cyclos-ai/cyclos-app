<?php

namespace App\Services\QuickBooks;

use App\Models\Tenant\QuickBooksCredential;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class QuickBooksService
{
    private const AUTHORIZE_URL = 'https://appcenter.intuit.com/connect/oauth2';
    private const TOKEN_URL      = 'https://oauth.platform.intuit.com/oauth2/v1/tokens/bearer';
    private const REVOKE_URL     = 'https://developer.api.intuit.com/v2/oauth2/tokens/revoke';
    private const API_BASE_PROD  = 'https://quickbooks.api.intuit.com';
    private const API_BASE_SANDBOX = 'https://sandbox-quickbooks.api.intuit.com';

    private ?string $clientId;
    private ?string $clientSecret;
    private string $environment;
    private string $redirectUri;
    private string $scopes;
    private string $minorVersion;

    public function __construct()
    {
        $this->clientId     = config('services.quickbooks.client_id');
        $this->clientSecret = config('services.quickbooks.client_secret');
        $this->environment  = config('services.quickbooks.environment', 'production');
        $this->redirectUri  = config('services.quickbooks.redirect_uri', 'https://demo.cyclos.ai/quickbooks/callback');
        $this->scopes       = config('services.quickbooks.scopes', 'com.intuit.quickbooks.accounting com.intuit.quickbooks.payment openid');
        $this->minorVersion = (string) config('services.quickbooks.minor_version', '73');
    }

    /**
     * Check if the QuickBooks app credentials are configured.
     */
    public function isConfigured(): bool
    {
        return ! empty($this->clientId) && ! empty($this->clientSecret);
    }

    /**
     * Check if the tenant has a connected QuickBooks company.
     */
    public function isConnected(): bool
    {
        $credential = QuickBooksCredential::current();

        return $credential !== null
            && $credential->is_connected
            && ! empty($credential->realm_id);
    }

    /**
     * Build the Intuit authorization URL for the OAuth2 consent screen.
     */
    public function getAuthorizationUrl(string $state): string
    {
        $params = [
            'client_id'     => $this->clientId,
            'scope'         => $this->scopes,
            'redirect_uri'  => $this->redirectUri,
            'response_type' => 'code',
            'state'         => $state,
        ];

        return self::AUTHORIZE_URL . '?' . http_build_query($params);
    }

    /**
     * Handle the OAuth2 callback: exchange the auth code for tokens and persist.
     */
    public function handleCallback(string $code, string $realmId): QuickBooksCredential
    {
        $response = Http::timeout(15)
            ->retry(1, 250)
            ->withBasicAuth($this->clientId, $this->clientSecret)
            ->asForm()
            ->withHeaders(['Accept' => 'application/json'])
            ->post(self::TOKEN_URL, [
                'grant_type'   => 'authorization_code',
                'code'         => $code,
                'redirect_uri' => $this->redirectUri,
            ]);

        if (! $response->successful()) {
            Log::error('QuickBooks: token exchange failed', [
                'status'   => $response->status(),
                'response' => $response->json() ?? $response->body(),
            ]);

            throw new \RuntimeException('QuickBooks token exchange failed.');
        }

        $data = $response->json();

        $credential = QuickBooksCredential::current() ?? new QuickBooksCredential();

        $credential->realm_id                 = $realmId;
        $credential->access_token              = $data['access_token'] ?? null;
        $credential->refresh_token             = $data['refresh_token'] ?? null;
        $credential->token_expires_at          = now()->addSeconds((int) ($data['expires_in'] ?? 3600));
        $credential->refresh_token_expires_at  = now()->addSeconds((int) ($data['x_refresh_token_expires_in'] ?? 8726400));
        $credential->environment               = $this->environment;
        $credential->is_active                 = true;
        $credential->is_connected              = true;
        $credential->last_used_at              = now();
        $credential->last_error                = null;
        $credential->save();

        // Best-effort fetch of the company name for display purposes.
        $info = $this->getCompanyInfo();
        if ($info && ! empty($info['CompanyName'])) {
            $credential->company_name = $info['CompanyName'];
            $credential->save();
        }

        return $credential->refresh();
    }

    /**
     * Disconnect QuickBooks: revoke the refresh token (best-effort) and delete the row.
     */
    public function disconnect(): void
    {
        $credential = QuickBooksCredential::current();

        if (! $credential) {
            return;
        }

        if (! empty($credential->refresh_token)) {
            try {
                Http::timeout(15)
                    ->retry(1, 250)
                    ->withBasicAuth($this->clientId, $this->clientSecret)
                    ->withHeaders(['Accept' => 'application/json'])
                    ->post(self::REVOKE_URL, [
                        'token' => $credential->refresh_token,
                    ]);
            } catch (\Throwable $e) {
                Log::warning('QuickBooks: token revoke failed', ['message' => $e->getMessage()]);
            }
        }

        $credential->delete();
    }

    /**
     * Ensure a valid access token, refreshing if expired. Returns null if not
     * connected or the refresh fails.
     */
    public function ensureValidToken(): ?QuickBooksCredential
    {
        $credential = QuickBooksCredential::current();

        if (! $credential || ! $credential->is_connected) {
            return null;
        }

        if (! $credential->isTokenExpired()) {
            return $credential;
        }

        try {
            $response = Http::timeout(15)
                ->retry(1, 250)
                ->withBasicAuth($this->clientId, $this->clientSecret)
                ->asForm()
                ->withHeaders(['Accept' => 'application/json'])
                ->post(self::TOKEN_URL, [
                    'grant_type'    => 'refresh_token',
                    'refresh_token' => $credential->refresh_token,
                ]);
        } catch (\Throwable $e) {
            Log::error('QuickBooks: token refresh request failed', ['message' => $e->getMessage()]);

            $credential->last_error = 'Token refresh request failed: ' . $e->getMessage();
            $credential->save();

            return null;
        }

        if (! $response->successful()) {
            Log::error('QuickBooks: token refresh failed', [
                'status'   => $response->status(),
                'response' => $response->json() ?? $response->body(),
            ]);

            $credential->is_connected = false;
            $credential->last_error   = 'Token refresh failed (status ' . $response->status() . '). Reconnect required.';
            $credential->save();

            return null;
        }

        $data = $response->json();

        $credential->access_token     = $data['access_token'] ?? $credential->access_token;
        $credential->token_expires_at = now()->addSeconds((int) ($data['expires_in'] ?? 3600));

        // Intuit rotates the refresh token on each refresh.
        if (! empty($data['refresh_token'])) {
            $credential->refresh_token = $data['refresh_token'];
        }
        if (! empty($data['x_refresh_token_expires_in'])) {
            $credential->refresh_token_expires_at = now()->addSeconds((int) $data['x_refresh_token_expires_in']);
        }

        $credential->last_used_at = now();
        $credential->last_error   = null;
        $credential->save();

        return $credential->refresh();
    }

    /**
     * Fetch the connected company's info (name, country).
     *
     * @return array{CompanyName?: string, Country?: string}|null
     */
    public function getCompanyInfo(): ?array
    {
        $credential = QuickBooksCredential::current();

        if (! $credential || empty($credential->realm_id)) {
            return null;
        }

        $response = $this->request('GET', "companyinfo/{$credential->realm_id}");

        if ($response === null || isset($response['error'])) {
            return null;
        }

        $info = $response['CompanyInfo'] ?? null;

        if (! is_array($info)) {
            return null;
        }

        return [
            'CompanyName' => $info['CompanyName'] ?? null,
            'Country'     => $info['Country'] ?? null,
        ];
    }

    /**
     * Run a QBO SQL-like query.
     *
     * @return array|null Decoded 'QueryResponse' or ['error' => ..] on failure
     */
    public function query(string $sql): ?array
    {
        $response = $this->request('GET', 'query', [], ['query' => $sql]);

        if ($response === null) {
            return null;
        }

        if (isset($response['error'])) {
            return $response;
        }

        return $response['QueryResponse'] ?? [];
    }

    /**
     * Core authenticated request to the QuickBooks API. Never throws.
     *
     * @param  string $method HTTP method
     * @param  string $path   Entity path, e.g. "invoice" or "companyinfo/{realmId}"
     * @param  array  $body   Request body (for POST)
     * @param  array  $query  Extra query parameters
     * @return array|null     Decoded JSON, ['error' => .., 'status' => ..], or null
     */
    public function request(string $method, string $path, array $body = [], array $query = []): ?array
    {
        $credential = $this->ensureValidToken();

        if (! $credential) {
            Log::warning('QuickBooks: request attempted without a valid token', ['path' => $path]);

            return ['error' => 'QuickBooks is not connected.', 'status' => 401];
        }

        $query = array_merge(['minorversion' => $this->minorVersion], $query);

        // Bake the query string onto the URL so POST requests carry both the
        // minorversion param and a JSON body (QBO requires both).
        $url = $this->apiBase()
            . '/v3/company/' . $credential->realm_id
            . '/' . ltrim($path, '/')
            . '?' . http_build_query($query);

        try {
            $request = Http::timeout(15)
                ->retry(1, 250)
                ->withToken($credential->access_token)
                ->withHeaders([
                    'Accept'       => 'application/json',
                    'Content-Type' => 'application/json',
                ]);

            $method = strtoupper($method);

            $response = $method === 'GET'
                ? $request->get($url)
                : $request->{strtolower($method)}($url, $body);

            if ($response->successful()) {
                return $response->json() ?? [];
            }

            $status = $response->status();
            $payload = $response->json() ?? [];
            $message = $this->parseFault($payload) ?? 'QuickBooks API error';

            Log::warning("QuickBooks: {$method} {$path} returned {$status}", [
                'query'    => $query,
                'response' => $payload,
            ]);

            return ['error' => $message, 'status' => $status];
        } catch (\Throwable $e) {
            Log::error('QuickBooks: request failed', [
                'path'    => $path,
                'message' => $e->getMessage(),
            ]);

            return ['error' => $e->getMessage(), 'status' => 0];
        }
    }

    /**
     * Resolve the API base URL for the configured environment.
     */
    private function apiBase(): string
    {
        return $this->environment === 'sandbox'
            ? self::API_BASE_SANDBOX
            : self::API_BASE_PROD;
    }

    /**
     * Extract a human-readable message from a QBO Fault payload.
     */
    private function parseFault(array $payload): ?string
    {
        $errors = $payload['Fault']['Error'] ?? null;

        if (! is_array($errors) || empty($errors)) {
            return null;
        }

        $messages = [];
        foreach ($errors as $error) {
            $messages[] = $error['Detail'] ?? ($error['Message'] ?? 'Unknown error');
        }

        return implode('; ', array_filter($messages));
    }
}
