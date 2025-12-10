<?php

declare(strict_types=1);

namespace App\Jobs\Cognito;

use App\Mail\CognitoResetPasswordMail;
use App\Mail\CognitoVerificationMail;
use App\Models\CognitoAuditLog;
use App\Services\Localization\LocaleManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendAuthEmailJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $backoff = 10;

    public function __construct(
        public string $email,
        public ?string $sub,
        public ?string $code,
        public string $triggerSource,
        public string $locale,
        public int $auditLogId
    ) {}

    public function handle(LocaleManager $localeManager): void
    {
        // Mask PII for logging
        $maskedEmail = $this->maskEmail($this->email);

        Log::info('Sending Cognito Email notification', [
            'email' => $maskedEmail,
            'sub' => $this->sub ? substr($this->sub, 0, 8).'...' : null,
            'trigger_source' => $this->triggerSource,
            'locale' => $this->locale,
            'audit_log_id' => $this->auditLogId,
        ]);

        // Determine which Mailable to use based on trigger source
        $mailable = $this->getMailable();

        // Send email via Postmark
        Mail::to($this->email)->send($mailable);

        // Update audit log to sent
        $auditLog = CognitoAuditLog::find($this->auditLogId);
        if ($auditLog !== null) {
            $auditLog->update(['status' => 'sent']);
        }

        Log::info('Cognito Email notification sent successfully', [
            'email' => $maskedEmail,
            'audit_log_id' => $this->auditLogId,
        ]);
    }

    private function maskEmail(string $email): string
    {
        if (! str_contains($email, '@')) {
            return substr($email, 0, 2).'***'.substr($email, -2);
        }

        [$local, $domain] = explode('@', $email);
        $localMasked = substr($local, 0, 2).'***'.substr($local, -2);

        $domainParts = explode('.', $domain);
        $tld = array_pop($domainParts);
        $domainMasked = '***.'.$tld;

        return $localMasked.'@'.$domainMasked;
    }

    private function getMailable(): Mailable
    {
        $code = $this->code ?? '******';

        // Determine email type based on trigger source
        // Default to reset password (ForgotPassword, AdminCreateUser, etc.)
        if (str_contains($this->triggerSource, 'CustomEmailSender_ForgotPassword')) {
            return new CognitoResetPasswordMail($code, $this->locale);
        }

        return new CognitoVerificationMail($code, $this->locale);
    }

    public function failed(Throwable $exception): void
    {
        Log::error('Cognito Email notification failed', [
            'email' => $this->maskEmail($this->email),
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
}
