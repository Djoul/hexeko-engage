<?php

declare(strict_types=1);

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Jobs\Cognito\SendAuthEmailJob;
use App\Jobs\Cognito\SendSmsJob;
use App\Models\CognitoAuditLog;
use App\Services\Localization\LocaleManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class CognitoNotificationController extends Controller
{
    public function __construct(
        private LocaleManager $localeManager
    ) {}

    public function sendSms(Request $request): JsonResponse
    {
        // Validate request
        $validator = Validator::make($request->all(), [
            'email' => 'required_without:sub|nullable|email',
            'sub' => 'required_without:email|nullable|string',
            'code' => 'nullable|string',
            'trigger_source' => 'required|string',
            'locale' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $validated = $validator->validated();

        // Extract identifier (email or sub)
        $identifier = $validated['email'] ?? $validated['sub'];
        $sub = $validated['sub'] ?? null;

        // Determine locale
        $locale = $validated['locale'] ?? 'fr-FR';

        // Hash identifier for RGPD compliance
        $identifierHash = $this->localeManager->hashIdentifier($identifier);

        // Mask PII in logs
        $maskedIdentifier = $this->maskIdentifier($identifier);
        Log::info('Cognito SMS notification requested', [
            'identifier' => $maskedIdentifier,
            'sub' => $sub ? substr($sub, 0, 8).'...' : null,
            'trigger_source' => $validated['trigger_source'],
            'locale' => $locale,
        ]);

        // Create encrypted audit log
        $auditLog = CognitoAuditLog::createAudit(
            identifierHash: $identifierHash,
            type: 'sms',
            triggerSource: $validated['trigger_source'],
            locale: $locale,
            payload: $validated,
            status: 'queued',
            sourceIp: $request->ip()
        );

        // Dispatch SendSmsJob to queue
        SendSmsJob::dispatch(
            identifier: $identifier,
            sub: $sub,
            code: $validated['code'],
            triggerSource: $validated['trigger_source'],
            locale: $locale,
            auditLogId: $auditLog->id
        );

        return response()->json([
            'message' => 'SMS queued successfully',
            'queued' => true,
        ], Response::HTTP_ACCEPTED);
    }

    public function sendEmail(Request $request): JsonResponse
    {
        // Validate request
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'sub' => 'nullable|string',
            'code' => 'nullable|string',
            'trigger_source' => 'required|string',
            'locale' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $validated = $validator->validated();

        // Extract identifier and sub
        $identifier = $validated['email'];
        $sub = $validated['sub'] ?? null;

        // Determine locale
        $locale = $validated['locale'] ?? 'fr-FR';

        // Hash identifier for RGPD compliance
        $identifierHash = $this->localeManager->hashIdentifier($identifier);

        // Mask PII in logs
        $maskedIdentifier = $this->maskIdentifier($identifier);
        Log::info('Cognito Email notification requested', [
            'identifier' => $maskedIdentifier,
            'sub' => $sub ? substr($sub, 0, 8).'...' : null,
            'trigger_source' => $validated['trigger_source'],
            'locale' => $locale,
        ]);

        // Create encrypted audit log
        $auditLog = CognitoAuditLog::createAudit(
            identifierHash: $identifierHash,
            type: 'email',
            triggerSource: $validated['trigger_source'],
            locale: $locale,
            payload: $validated,
            status: 'queued',
            sourceIp: $request->ip()
        );

        // Dispatch SendAuthEmailJob to queue
        SendAuthEmailJob::dispatch(
            email: $identifier,
            sub: $sub,
            code: $validated['code'],
            triggerSource: $validated['trigger_source'],
            locale: $locale,
            auditLogId: $auditLog->id
        );

        return response()->json([
            'message' => 'Email queued successfully',
            'queued' => true,
        ], Response::HTTP_ACCEPTED);
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
