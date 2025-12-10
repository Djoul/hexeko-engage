<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\Cognito\SendSmsJob;
use App\Models\CognitoAuditLog;
use App\Services\Localization\LocaleManager;
use Illuminate\Console\Command;

class SendCognitoSmsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cognito:send-sms
                            {identifier : Email or phone number}
                            {code : Verification code}
                            {--trigger=CustomSMSLambda_SignUp : The trigger source}
                            {--locale=fr-FR : The locale for the SMS}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Manually send a Cognito SMS notification for testing';

    /**
     * Execute the console command.
     */
    public function handle(LocaleManager $localeManager): int
    {
        $identifier = $this->argument('identifier');
        if (! is_string($identifier)) {
            $this->error('Invalid identifier argument');

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

        // Mask PII for CLI output
        $maskedIdentifier = $this->maskIdentifier($identifier);
        $this->info("Sending Cognito SMS to: {$maskedIdentifier}");
        $this->info("Trigger: {$triggerSource}");
        $this->info("Locale: {$locale}");

        // Hash identifier for RGPD compliance
        $identifierHash = $localeManager->hashIdentifier($identifier);

        // Create audit log
        $auditLog = CognitoAuditLog::createAudit(
            identifierHash: $identifierHash,
            type: 'sms',
            triggerSource: $triggerSource,
            locale: $locale,
            payload: [
                'identifier' => $identifier,
                'code' => $code,
                'trigger_source' => $triggerSource,
                'source' => 'manual_command',
            ],
            status: 'queued',
            sourceIp: '127.0.0.1'
        );

        $this->comment("Audit log created (ID: {$auditLog->id})");

        // Dispatch job
        SendSmsJob::dispatch(
            identifier: $identifier,
            code: $code,
            triggerSource: $triggerSource,
            locale: $locale,
            auditLogId: $auditLog->id
        );

        $this->info('✅ SMS job queued successfully!');
        $this->comment('Run queue worker to process: php artisan queue:work');

        return 0;
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
