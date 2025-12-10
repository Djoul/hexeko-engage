<?php

declare(strict_types=1);

namespace App\Services\Sms;

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SmsModeService
{
    private ?string $apiKey;

    private string $apiUrl;

    private string $sender;

    public function __construct()
    {
        $this->apiKey = config('services.smsmode.api_key');
        $this->apiUrl = config('services.smsmode.api_url', 'https://api.smsmode.com');
        $this->sender = config('services.smsmode.sender', 'UpPlus+');
    }

    /**
     * Send an SMS message via SMSMode API
     *
     * @param  string  $phoneNumber  Phone number in international format (e.g., +33612345678)
     * @param  string  $message  SMS message content
     * @param  string|null  $sender  Optional sender override
     * @return array Response from SMSMode API
     *
     * @throws Exception
     */
    public function sendSms(string $phoneNumber, string $message, ?string $sender = null): array
    {
        if (empty($this->apiKey)) {
            throw new Exception('SMSMode API key is not configured');
        }

        $sender = $sender ?? $this->sender;

        Log::info('Sending SMS via SMSMode', [
            'phone' => $this->maskPhoneNumber($phoneNumber),
            'sender' => $sender,
            'message_length' => strlen($message),
        ]);

        try {
            // SMSMode HTTP API 1.6 - GET request with query parameters
            $response = Http::get("{$this->apiUrl}/http/1.6/sendSMS.do", [
                'accessToken' => $this->apiKey,
                'message' => $message,
                'numero' => $this->formatPhoneNumber($phoneNumber),
                'emetteur' => $sender,
                'notification_url' => config('app.url').'/api/v1/webhooks/smsmode',
            ]);

            // Log full response for debugging
            Log::info('SMSMode API response received', [
                'status' => $response->status(),
                'body' => $response->body(),
                'headers' => $response->headers(),
            ]);

            if ($response->failed()) {
                throw new Exception("SMSMode API error (HTTP {$response->status()}): {$response->body()}");
            }

            // Try to parse JSON response, fallback to raw body
            $result = $response->json();
            if ($result === null) {
                // Non-JSON response, create structured array from body
                $result = [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'success' => $response->successful(),
                ];
            }

            Log::info('SMS sent successfully via SMSMode', [
                'phone' => $this->maskPhoneNumber($phoneNumber),
                'response' => $result,
            ]);

            return $result;
        } catch (Exception $e) {
            Log::error('Failed to send SMS via SMSMode', [
                'phone' => $this->maskPhoneNumber($phoneNumber),
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Format phone number by removing spaces, dashes, and ensuring + prefix
     */
    private function formatPhoneNumber(string $phoneNumber): string
    {
        $cleaned = preg_replace('/[\s\-()]/', '', $phoneNumber);

        if (! str_starts_with($cleaned, '+')) {
            return '+'.$cleaned;
        }

        return $cleaned;
    }

    /**
     * Mask phone number for logging (PII protection)
     */
    private function maskPhoneNumber(string $phoneNumber): string
    {
        if (strlen($phoneNumber) < 6) {
            return '***';
        }

        return substr($phoneNumber, 0, 3).'***'.substr($phoneNumber, -3);
    }
}
