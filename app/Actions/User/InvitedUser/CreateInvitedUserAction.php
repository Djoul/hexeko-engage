<?php

declare(strict_types=1);

namespace App\Actions\User\InvitedUser;

use App\DTOs\User\CreateInvitedUserDTO;
use App\Events\InvitationCreated;
use App\Exceptions\RoleManagement\UnauthorizedRoleAssignmentException;
use App\Mail\WelcomeEmail;
use App\Models\User;
use App\Services\Models\InvitedUserService;
use App\Services\RoleManagementService;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use stdClass;
use Throwable;

/**
 * Unified action for creating invited users.
 *
 * This action consolidates all invitation creation logic and supports multiple execution modes:
 * - Synchronous execution via execute()
 * - Asynchronous via dispatch() (implements ShouldQueue)
 *
 * Configuration options:
 * - Role validation: Ensure inviter can assign intended role
 * - Email sending: Send welcome email with registration link
 * - Event dispatching: Dispatch InvitationCreated event
 *
 * @example Synchronous with role validation
 * app(CreateInvitedUserAction::class, ['dto' => $dto])
 *     ->withRoleValidation($inviter)
 *     ->execute();
 * @example Asynchronous without email (bulk import)
 * dispatch(
 *     app(CreateInvitedUserAction::class, ['dto' => $dto])
 *         ->withoutEmail()
 *         ->withoutEvent()
 * );
 */
class CreateInvitedUserAction implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Configuration flags
     */
    private bool $validateRole = false;

    private bool $sendEmail = true;

    private bool $dispatchEvent = true;

    private ?User $inviter = null;

    public function __construct(
        public readonly CreateInvitedUserDTO $dto,
        private readonly ?InvitedUserService $invitedUserService = null,
        private readonly ?RoleManagementService $roleManagementService = null
    ) {
        // Services can be injected for testing or will be resolved via container
    }

    /**
     * Main execution method - coordinates all invitation creation logic.
     *
     * @throws UnauthorizedRoleAssignmentException if role validation fails
     * @throws InvalidArgumentException if required data is missing
     * @throws Exception if creation fails
     */
    public function execute(): User
    {
        Log::info('Creating invited user', [
            'email' => $this->dto->email,
            'financer_id' => $this->dto->financer_id,
            'validateRole' => $this->validateRole,
        ]);

        try {
            return DB::transaction(function (): User {
                // 1. Validate role assignment if required
                if ($this->validateRole) {
                    $this->validateRoleAssignment();
                }

                // 2. Check for duplicate pending invitation
                $this->checkDuplicateInvitation();

                // 3. Create invited user
                $invitedUser = $this->createUser();

                // 4. Attach financer if provided
                if (! in_array($this->dto->financer_id, [null, '', '0'], true)) {
                    $this->attachFinancer($invitedUser);
                }

                // 5. Send welcome email if configured
                if ($this->sendEmail) {
                    $this->sendWelcomeEmail($invitedUser);
                }

                // 6. Dispatch event if configured
                if ($this->dispatchEvent && $this->inviter instanceof User) {
                    event(new InvitationCreated($invitedUser, $this->inviter, $this->dto->financer_id ?? null));
                }

                return $invitedUser->refresh();
            });
        } catch (ValidationException $e) {
            // Re-throw validation exceptions directly so Laravel converts them to 422
            throw $e;
        } catch (Throwable $e) {
            Log::error('Failed to create invited user', [
                'email' => $this->dto->email,
                'error' => $e->getMessage(),
                'trace' => $e->getTrace(),
            ]);
            throw $e;
        }
    }

    /**
     * Laravel queue handler - calls execute()
     */
    public function handle(): User
    {
        return $this->execute();
    }

    /**
     * Fluent configuration: Enable role validation with inviter context.
     */
    public function withRoleValidation(User $inviter): self
    {
        $this->validateRole = true;
        $this->inviter = $inviter;

        return $this;
    }

    /**
     * Fluent configuration: Disable welcome email sending.
     * Useful for bulk imports to avoid spam.
     */
    public function withoutEmail(): self
    {
        $this->sendEmail = false;

        return $this;
    }

    /**
     * Fluent configuration: Disable InvitationCreated event dispatching.
     */
    public function withoutEvent(): self
    {
        $this->dispatchEvent = false;

        return $this;
    }

    /**
     * Validate that the inviter can assign the intended role.
     *
     * @throws UnauthorizedRoleAssignmentException
     */
    private function validateRoleAssignment(): void
    {
        if (! $this->dto->intended_role) {
            return; // No role specified, nothing to validate
        }

        if (! $this->inviter instanceof User) {
            throw new InvalidArgumentException('Inviter must be provided when role validation is enabled');
        }

        $roleService = $this->roleManagementService ?? app(RoleManagementService::class);

        if (! $roleService->canManageRole($this->inviter, $this->dto->intended_role)) {
            throw new UnauthorizedRoleAssignmentException(
                "You are not authorized to assign the role: {$this->dto->intended_role}"
            );
        }
    }

    /**
     * Check if a pending invitation already exists for this email and financer.
     *
     * Now checks per financer instead of globally, aligning with the new business rule:
     * "Email must be unique per financer for active users"
     *
     * @throws ValidationException if duplicate found
     */
    private function checkDuplicateInvitation(): void
    {
        // Skip check if no financer specified
        if (in_array($this->dto->financer_id, [null, '', '0'], true)) {
            return;
        }

        // Check if email already exists for this financer with pending invitation
        $existingPendingInvitation = User::where('email', $this->dto->email)
            ->where('invitation_status', 'pending')
            ->whereHas('financers', function ($query): void {
                $query->where('financer_user.financer_id', $this->dto->financer_id);
            })
            ->first();

        if ($existingPendingInvitation instanceof User) {
            throw ValidationException::withMessages([
                'email' => ['A pending invitation for this email already exists for this financer'],
            ]);
        }
    }

    /**
     * Create the invited user record.
     */
    private function createUser(): User
    {
        $service = $this->invitedUserService ?? app(InvitedUserService::class);

        // Prepare data for User creation
        $userData = [
            'first_name' => $this->dto->first_name,
            'last_name' => $this->dto->last_name,
            'email' => $this->dto->email,
            'phone' => $this->dto->phone,
            'invitation_metadata' => $this->buildInvitationMetadata(),
            'invitation_status' => 'pending',
            'invitation_token' => $this->generateToken(),
            'invitation_expires_at' => $this->dto->expires_at ?? now()->addDays($this->dto->expiration_days),
            'invited_at' => now(),
            'invited_by' => $this->dto->invited_by ?? ($this->inviter?->id !== null ? (string) $this->inviter->id : null),
            'team_id' => $this->dto->team_id,
            'enabled' => false,
            'cognito_id' => null,
        ];

        return $service->create($userData);
    }

    /**
     * Build invitation_metadata JSON field.
     *
     * @return array<string, mixed>
     */
    private function buildInvitationMetadata(): array
    {
        // Default role to 'beneficiary' if not specified
        $intendedRole = in_array($this->dto->intended_role, [null, '', '0'], true)
            ? 'beneficiary'
            : $this->dto->intended_role;

        return array_merge(
            $this->dto->metadata ?? [],
            $this->dto->extra_data ?? [],
            array_filter([
                'financer_id' => $this->dto->financer_id,
                'sirh_id' => $this->dto->sirh_id,
                'external_id' => $this->dto->external_id,
                'intended_role' => $intendedRole,
            ], fn (?string $value): bool => ! in_array($value, [null, '', '0'], true))
        );
    }

    /**
     * Attach financer to the invited user.
     */
    private function attachFinancer(User $invitedUser): void
    {
        if (in_array($this->dto->financer_id, [null, '', '0'], true)) {
            return;
        }

        // Determine role for single-role system
        $role = in_array($this->dto->intended_role, [null, '', '0'], true)
            ? 'beneficiary'
            : $this->dto->intended_role;

        $invitedUser->financers()->attach($this->dto->financer_id, [
            'active' => false, // Inactive until invitation is accepted
            'sirh_id' => $this->dto->sirh_id ?? '',
            'from' => now()->toDateString(),
            'role' => $role, // Single role system
        ]);
    }

    /**
     * Send welcome email with registration link.
     */
    private function sendWelcomeEmail(User $invitedUser): void
    {
        try {
            // Create a temporary user object to pass to the WelcomeEmail
            // This is a temporary solution until we update the WelcomeEmail class
            $tempUser = new stdClass;
            $tempUser->email = $invitedUser->email;
            $tempUser->first_name = $invitedUser->first_name;
            $tempUser->last_name = $invitedUser->last_name;
            $tempUser->id = $invitedUser->id;

            Mail::to($invitedUser->email)
                ->send(new WelcomeEmail($tempUser, (string) $invitedUser->id));

            Log::info('Welcome email sent successfully', ['email' => $invitedUser->email]);
        } catch (Throwable $e) {
            Log::error('Failed to send welcome email', [
                'email' => $invitedUser->email,
                'error' => $e->getMessage(),
                'trace' => $e->getTrace(),
            ]);
            // Do not throw - email failure should not fail invitation creation
        }
    }

    /**
     * Generate a secure invitation token.
     */
    private function generateToken(): string
    {
        return bin2hex(random_bytes(32)); // 64 characters hex string
    }
}
