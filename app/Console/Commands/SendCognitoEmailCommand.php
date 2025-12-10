<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\Cognito\SendAuthEmailJob;
use App\Models\CognitoAuditLog;
use App\Services\Localization\LocaleManager;
use Illuminate\Console\Command;

class SendCognitoEmailCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cognito:send-email
                            {email : Email address}
                            {code : Verification code}
                            {--trigger=CustomEmailLambda_ForgotPassword : The trigger source}
                            {--locale=fr-FR : The locale for the email}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Manually send a Cognito email notification for testing';

    /**
     * Execute the console command.
     */
    public function handle(LocaleManager $localeManager): int
    {
        $email = $this->argument('email');
        if (! is_string($email)) {
            $this->error('Invalid email argument');

            return 1;
        }

        $code = $this->argument('code');
        if (! is_string($code)) {
            $this->error('Invalid code argument');

            return 1;
        }

        $triggerSource = $this->option('trigger');
        if (! is_string($triggerSource)) {
            $this->error('Invalid trigger option');

            return 1;
        }

        $locale = $this->option('locale');
        if (! is_string($locale)) {
            $this->error('Invalid locale option');

            return 1;
        }

        // Validate email format
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Invalid email format');

            return 1;
        }

        // Mask PII for CLI output
        $maskedEmail = $this->maskEmail($email);
        $this->info("Sending Cognito Email to: {$maskedEmail}");
        $this->info("Trigger: {$triggerSource}");
        $this->info("Locale: {$locale}");

        // Hash identifier for RGPD compliance
        $identifierHash = $localeManager->hashIdentifier($email);

        // Create audit log
        $auditLog = CognitoAuditLog::createAudit(
            identifierHash: $identifierHash,
            type: 'email',
            triggerSource: $triggerSource,
            locale: $locale,
            payload: [
                'email' => $email,
                'code' => $code,
                'trigger_source' => $triggerSource,
                'source' => 'manual_command',
            ],
            status: 'queued',
            sourceIp: '127.0.0.1'
        );

        $this->comment("Audit log created (ID: {$auditLog->id})");

        // Dispatch job
        SendAuthEmailJob::dispatch(
            email: $email,
            code: $code,
            triggerSource: $triggerSource,
            locale: $locale,
            auditLogId: $auditLog->id
        );

        $this->info('✅ Email job queued successfully!');
        $this->comment('Run queue worker to process: php artisan queue:work');

        return 0;
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
}
