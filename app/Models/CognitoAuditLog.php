<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;

/**
 * Cognito Audit Log Model - RGPD-compliant encrypted audit trail
 *
 * Stores encrypted audit logs for Cognito SMS/Email notifications.
 * - Payload is encrypted using Laravel Crypt (PII protection)
 * - Identifiers are SHA256 hashed (RGPD compliant)
 * - 90-day automatic retention via PostgreSQL trigger
 * - No updated_at column (immutable logs)
 *
 * @property int $id
 * @property string $identifier_hash SHA256 hash of email/phone
 * @property string $type sms or email
 * @property string $trigger_source Cognito trigger source
 * @property string $locale Language code (e.g., 'fr-FR')
 * @property string $status queued, sent, failed, retrying
 * @property string $encrypted_payload Laravel Crypt encrypted JSON
 * @property string|null $error_message Error details if failed
 * @property string|null $source_ip IP address (IPv4/IPv6)
 * @property Carbon $created_at
 */
class CognitoAuditLog extends Model
{
    /**
     * Disable updated_at timestamp (immutable audit logs).
     */
    public const UPDATED_AT = null;

    protected $casts = [
        'created_at' => 'datetime',
    ];

    protected $guarded = [];

    /**
     * Create a new audit log with encrypted payload.
     *
     * @param  array<string, mixed>  $payload  Data to encrypt (email, phone, code, etc.)
     * @param  string|null  $errorMessage  Error details if status is failed
     */
    public static function createAudit(
        string $identifierHash,
        string $type,
        string $triggerSource,
        string $locale,
        array $payload,
        string $status,
        ?string $sourceIp = null,
        ?string $errorMessage = null
    ): self {
        // Encrypt payload using Laravel Crypt (PII protection)
        $jsonPayload = json_encode($payload);
        if ($jsonPayload === false) {
            $jsonPayload = '{}';
        }
        $encryptedPayload = Crypt::encryptString($jsonPayload);

        /** @var self $audit */
        $audit = self::create([
            'identifier_hash' => $identifierHash,
            'type' => $type,
            'trigger_source' => $triggerSource,
            'locale' => $locale,
            'status' => $status,
            'encrypted_payload' => $encryptedPayload,
            'source_ip' => $sourceIp,
            'error_message' => $errorMessage,
        ]);

        return $audit;
    }

    /**
     * Decrypt and return the payload as an array.
     *
     * @return array<string, mixed>
     */
    public function getDecryptedPayload(): array
    {
        $decrypted = Crypt::decryptString($this->encrypted_payload);

        $payload = json_decode($decrypted, true);

        if (! is_array($payload)) {
            return [];
        }

        /** @var array<string, mixed> $payload */
        return $payload;
    }

    /**
     * Override usesTimestamps to confirm only created_at is used.
     */
    public function usesTimestamps(): bool
    {
        return true; // We use created_at, but not updated_at
    }
}
