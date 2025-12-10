<?php

namespace App\Actions\User;

use App\Actions\User\InvitedUser\CreateInvitedUserAction;
use App\DTOs\User\CreateInvitedUserDTO;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Log;
use Throwable;

class CreateUserAction implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @var array<string, mixed>
     */
    public array $validatedData;

    /**
     * Store validated data to be used in queue
     *
     * @param  array<string, mixed>  $validatedData
     */
    public function __construct(array $validatedData)
    {
        $this->validatedData = $validatedData;
    }

    /**
     * Execute the job
     */
    public function handle(): User
    {
        try {
            // Create DTO from validated data
            $dto = CreateInvitedUserDTO::from($this->validatedData);

            // Dispatch the CreateInvitedUserAction with DTO
            $action = new CreateInvitedUserAction($dto);
            dispatch($action);

            // Note: The actual user creation will be handled by the Cognito webhook
            // when the user completes the registration process

            // Return a placeholder User object for backwards compatibility
            return new User([
                'email' => $this->validatedData['email'],
                'first_name' => $this->validatedData['first_name'],
                'last_name' => $this->validatedData['last_name'],
                'phone' => $this->validatedData['phone'] ?? null,
            ]);
        } catch (Throwable $e) {
            Log::error($e->getMessage(), ['trace' => $e->getTrace()]);
            throw $e;
        }
    }
}
