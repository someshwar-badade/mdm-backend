<?php

namespace Modules\Notifications\Infrastructure\Integrations;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FcmService
{
    /**
     * Send command payload to a specific device using FCM.
     */
    public function sendCommandNotification(string $fcmToken, array $commandData): bool
    {
        $accessToken = $this->getGoogleAccessToken();
        if (!$accessToken) {
            Log::error('FCM Service: Unable to retrieve Google OAuth2 access token.');
            return false;
        }

        $credentialsPath = base_path(env('FIREBASE_CREDENTIALS', 'storage/app/firebase-service-account.json'));
        if (!file_exists($credentialsPath)) {
            Log::error("FCM Service: Service account file not found at: {$credentialsPath}");
            return false;
        }
        $credentials = json_decode(file_get_contents($credentialsPath), true);
        $projectId = $credentials['project_id'] ?? null;

        if (!$projectId) {
            Log::error('FCM Service: project_id not found in service account file.');
            return false;
        }

        $url = "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send";

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$accessToken}",
            'Content-Type' => 'application/json'
        ])
        ->when(app()->environment('local', 'testing'), function ($http) {
            return $http->withoutVerifying();
        })
        ->post($url, [
            'message' => [
                'token' => $fcmToken,
                'data' => [
                    'command' => $commandData['command'],
                    'command_id' => (string) ($commandData['id'] ?? $commandData['command_id'] ?? '0'),
                    'payload' => $commandData['payload'] ?? ''
                ]
            ]
        ]);

        if ($response->failed()) {
            Log::error('FCM Message sending failed', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);
            return false;
        }

        return true;
    }

    /**
     * Generate OAuth2 Access Token using Service Account credentials.
     */
    private function getGoogleAccessToken(): ?string
    {
        $credentialsPath = base_path(env('FIREBASE_CREDENTIALS', 'storage/app/firebase-service-account.json'));
        
        if (!file_exists($credentialsPath)) {
            Log::error("FCM Service: FIREBASE_CREDENTIALS file not found at: {$credentialsPath}");
            return null;
        }

        $credentials = json_decode(file_get_contents($credentialsPath), true);
        
        $header = json_encode(['alg' => 'RS256', 'typ' => 'JWT']);
        $now = time();
        $payload = json_encode([
            'iss' => $credentials['client_email'],
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
            'aud' => 'https://oauth2.googleapis.com/token',
            'exp' => $now + 3600,
            'iat' => $now
        ]);

        $base64UrlHeader = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
        $base64UrlPayload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));

        $signatureInput = $base64UrlHeader . "." . $base64UrlPayload;
        
        $privateKey = $credentials['private_key'] ?? null;
        if (!$privateKey) {
            Log::error('FCM Service: private_key not found in credentials.');
            return null;
        }

        if (!openssl_sign($signatureInput, $signature, $privateKey, 'SHA256')) {
            Log::error('FCM Service: openssl_sign failed to sign the assertion.');
            return null;
        }
        
        $base64UrlSignature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
        $jwt = $signatureInput . "." . $base64UrlSignature;

        $response = Http::asForm()
            ->when(app()->environment('local', 'testing'), function ($http) {
                return $http->withoutVerifying();
            })
            ->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt
            ]);

        if ($response->failed()) {
            Log::error('FCM Service: Token exchange failed', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);
            return null;
        }

        return $response->json('access_token');
    }
}
