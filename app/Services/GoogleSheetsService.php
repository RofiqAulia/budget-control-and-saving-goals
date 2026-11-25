<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GoogleSheetsService
{
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    private const SCOPE = 'https://www.googleapis.com/auth/spreadsheets';

    /** @var array<string, mixed> */
    private array $credentials;

    public function __construct(string $credentialsJson, private string $spreadsheetId, private ?string $sheetName = null)
    {
        $decoded = json_decode($credentialsJson, true);

        if (! is_array($decoded) || ! isset($decoded['client_email'], $decoded['private_key'])) {
            throw new \InvalidArgumentException('Service account JSON must contain client_email and private_key.');
        }

        // Private keys arrive with literal \n sequences when pasted into a textarea.
        $decoded['private_key'] = str_replace(['\\n', "\r\n"], ["\n", "\n"], $decoded['private_key']);

        $this->credentials = $decoded;
    }

    /**
     * Replace the sheet contents with the given rows.
     *
     * @param  array<int, array<int, mixed>>  $rows
     * @return array{ok: bool, message: string}
     */
    public function push(array $rows): array
    {
        try {
            $token = $this->accessToken();

            if (! $token) {
                return ['ok' => false, 'message' => 'Could not obtain an access token from Google.'];
            }

            $range = $this->sheetName ? $this->sheetName.'!A1' : 'A1';
            $clearRange = $this->sheetName ? $this->sheetName.'!A1:Z10000' : 'A1:Z10000';

            $base = "https://sheets.googleapis.com/v4/spreadsheets/{$this->spreadsheetId}";

            // Clear the previous report so stale rows don't linger.
            // Google rejects an empty body sent as application/json, so send an explicit {}.
            $clear = Http::withToken($token)
                ->withBody('{}', 'application/json')
                ->post($base.'/values/'.rawurlencode($clearRange).':clear');

            if (! $clear->successful()) {
                return ['ok' => false, 'message' => $this->readableError($clear->status(), $clear->json())];
            }

            $response = Http::withToken($token)->put(
                $base.'/values/'.rawurlencode($range).'?valueInputOption=USER_ENTERED',
                ['values' => $rows]
            );

            if (! $response->successful()) {
                return ['ok' => false, 'message' => $this->readableError($response->status(), $response->json())];
            }

            $updated = $response->json('updates.updatedRows') ?? count($rows);

            return ['ok' => true, 'message' => "Synced {$updated} rows to Google Sheets."];
        } catch (\Throwable $e) {
            Log::error('Google Sheets sync failed: '.$e->getMessage());

            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * @return array{ok: bool, title: ?string, message: string}
     */
    public function test(): array
    {
        try {
            $token = $this->accessToken();

            if (! $token) {
                return ['ok' => false, 'title' => null, 'message' => 'Could not obtain an access token.'];
            }

            $response = Http::withToken($token)->get(
                "https://sheets.googleapis.com/v4/spreadsheets/{$this->spreadsheetId}?fields=properties.title"
            );

            if (! $response->successful()) {
                return ['ok' => false, 'title' => null, 'message' => $this->readableError($response->status(), $response->json())];
            }

            return [
                'ok' => true,
                'title' => $response->json('properties.title'),
                'message' => 'Connected to the spreadsheet.',
            ];
        } catch (\Throwable $e) {
            return ['ok' => false, 'title' => null, 'message' => $e->getMessage()];
        }
    }

    /**
     * Read a range back — used to inspect the target tab and verify a sync.
     *
     * @return array{ok: bool, values: array, message: string}
     */
    public function read(string $range = 'A1:F10'): array
    {
        try {
            $token = $this->accessToken();

            if (! $token) {
                return ['ok' => false, 'values' => [], 'message' => 'Could not obtain an access token.'];
            }

            $target = $this->sheetName ? $this->sheetName.'!'.$range : $range;

            $response = Http::withToken($token)->get(
                "https://sheets.googleapis.com/v4/spreadsheets/{$this->spreadsheetId}/values/".rawurlencode($target)
            );

            if (! $response->successful()) {
                return ['ok' => false, 'values' => [], 'message' => $this->readableError($response->status(), $response->json())];
            }

            return ['ok' => true, 'values' => $response->json('values') ?? [], 'message' => 'ok'];
        } catch (\Throwable $e) {
            return ['ok' => false, 'values' => [], 'message' => $e->getMessage()];
        }
    }

    private function accessToken(): ?string
    {
        $now = time();

        $claims = [
            'iss' => $this->credentials['client_email'],
            'scope' => self::SCOPE,
            'aud' => self::TOKEN_URL,
            'iat' => $now,
            'exp' => $now + 3600,
        ];

        $jwt = $this->signJwt($claims);

        if (! $jwt) {
            return null;
        }

        $response = Http::asForm()->timeout(15)->post(self::TOKEN_URL, [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $jwt,
        ]);

        if (! $response->successful()) {
            Log::warning('Google token exchange failed', ['body' => $response->json()]);

            return null;
        }

        return $response->json('access_token');
    }

    /**
     * Sign an RS256 JWT with the service account private key.
     *
     * @param  array<string, mixed>  $claims
     */
    private function signJwt(array $claims): ?string
    {
        $encode = fn (array $data) => rtrim(strtr(base64_encode(json_encode($data)), '+/', '-_'), '=');

        $header = $encode(['alg' => 'RS256', 'typ' => 'JWT']);
        $payload = $encode($claims);
        $signingInput = $header.'.'.$payload;

        $key = openssl_pkey_get_private($this->credentials['private_key']);

        if (! $key) {
            Log::error('Google service account private key could not be loaded.');

            return null;
        }

        $signature = '';
        if (! openssl_sign($signingInput, $signature, $key, OPENSSL_ALGO_SHA256)) {
            return null;
        }

        return $signingInput.'.'.rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');
    }

    private function readableError(int $status, ?array $body): string
    {
        $reason = $body['error']['message'] ?? null;

        return match (true) {
            $status === 403 => 'Permission denied. Share the spreadsheet with the service account email as Editor.'.($reason ? " ({$reason})" : ''),
            $status === 404 => 'Spreadsheet not found. Check the spreadsheet ID.'.($reason ? " ({$reason})" : ''),
            $status === 400 && str_contains((string) $reason, 'Unable to parse range') => 'Sheet tab "'.($this->sheetName ?: 'first tab').'" not found. Create the tab or leave the tab name empty.'.($reason ? " ({$reason})" : ''),
            default => $reason ?? "Google Sheets returned HTTP {$status}.",
        };
    }
}
