<?php

declare(strict_types=1);

namespace App\Jobs\Cognito;

use App\Models\CognitoAuditLog;
use App\Models\User;
use App\Services\Sms\SmsModeService;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendSmsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $backoff = 10;

    public function __construct(
        public string $identifier,
        public ?string $sub,
        public ?string $code,
        public string $triggerSource,
        public string $locale,
        public int $auditLogId
    ) {}

    public function handle(SmsModeService $smsService): void
    {
        // Mask PII for logging
        $maskedIdentifier = $this->maskIdentifier($this->identifier);

        Log::info('Sending Cognito SMS notification', [
            'identifier' => $maskedIdentifier,
            'sub' => $this->sub ? substr($this->sub, 0, 8).'...' : null,
            'trigger_source' => $this->triggerSource,
            'locale' => $this->locale,
            'audit_log_id' => $this->auditLogId,
        ]);

        // Set locale for translations
        app()->setLocale($this->locale);

        // Lookup user and get real phone number
        $phoneNumber = $this->getUserPhoneNumber();

        if ($phoneNumber === null) {
            throw new Exception("No phone number found for user (identifier: {$maskedIdentifier})");
        }

        // Build SMS message based on trigger source
        $message = $this->buildSmsMessage();

        // Send SMS via SMSMode with real phone number from database
        $smsService->sendSms($phoneNumber, $message);

        // Update audit log to sent
        $auditLog = CognitoAuditLog::find($this->auditLogId);
        if ($auditLog !== null) {
            $auditLog->update(['status' => 'sent']);
        }

        Log::info('Cognito SMS notification sent successfully', [
            'identifier' => $maskedIdentifier,
            'phone' => $this->maskIdentifier($phoneNumber),
            'audit_log_id' => $this->auditLogId,
        ]);
    }

    private function getUserPhoneNumber(): ?string
    {
        // Try to find user by Cognito sub first (most reliable)
        if ($this->sub !== null) {
            $user = User::where('cognito_id', $this->sub)->first();
            if ($user !== null && $user->phone !== null) {
                return $user->phone;
            }
        }

        // Fallback: try to find by email
        if (filter_var($this->identifier, FILTER_VALIDATE_EMAIL)) {
            $user = User::where('email', $this->identifier)->first();
            if ($user !== null && $user->phone !== null) {
                return $user->phone;
            }
        }

        // Fallback: if identifier looks like a phone number, use it directly
        if (preg_match('/^\+\d{10,15}$/', $this->identifier)) {
            return $this->identifier;
        }

        return null;
    }

    public function failed(Throwable $exception): void
    {
        Log::error('Cognito SMS notification failed', [
            'identifier' => $this->maskIdentifier($this->identifier),
            'trigger_source' => $this->triggerSource,
            'audit_log_id' => $this->auditLogId,
            'error' => $exception->getMessage(),
        ]);

        // Update audit log to failed
        $auditLog = CognitoAuditLog::find($this->auditLogId);
        if ($auditLog !== null) {
            $auditLog->update([
                'status' => 'failed',
                'error_message' => $exception->getMessage(),
            ]);
        }
    }

    private function buildSmsMessage(): string
    {
        $code = $this->code ?? '******';

        // Determine message type based on trigger source
        if (str_contains($this->triggerSource, 'ConfirmSignUp') || str_contains($this->triggerSource, 'ResendCode')) {
            return __('sms.cognito.verification', ['code' => $code]);
        }

        if (str_contains($this->triggerSource, 'ForgotPassword') || str_contains($this->triggerSource, 'AdminCreateUser')) {
            return __('sms.cognito.reset_password', ['code' => $code]);
        }

        // Default to MFA code
        return __('sms.cognito.mfa', ['code' => $code]);
    }

    private function maskIdentifier(string $identifier): string
    {
        // Email: show first 2 chars + last 2 chars of local part, hide domain except TLD
        if (str_contains($identifier, '@')) {
            [$local, $domain] = explode('@', $identifier);
            $localMasked = substr($local, 0, 2).'***'.substr($local, -2);

            $domainParts = explode('.', $domain);
            $tld = array_pop($domainParts);
            $domainMasked = '***.'.$tld;

            return $localMasked.'@'.$domainMasked;
        }

        // Phone: show first 3 and last 2 digits
        if (preg_match('/^\+?(\d{1,3})/', $identifier, $matches)) {
            $countryCode = $matches[1];
            $remaining = substr($identifier, strlen($countryCode) + 1);
            $masked = substr($remaining, 0, 2).'***'.substr($remaining, -2);

            return '+'.$countryCode.' '.$masked;
        }

        // Fallback: show first and last 2 chars
        return substr($identifier, 0, 2).'***'.substr($identifier, -2);
    }
}
