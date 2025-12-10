<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\User;

use App\Actions\User\InvitedUser\CreateInvitedUserAction;
use App\DTOs\User\CreateInvitedUserDTO;
use App\Mail\WelcomeEmail;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Attributes\FlushTables;
use Tests\Helpers\Facades\ModelFactory;
use Tests\TestCase;

#[FlushTables(tables: ['users', 'financers'], scope: 'class')]
#[Group('actions')]
#[Group('email')]
class CreateInvitedUserActionEmailTest extends TestCase
{
    #[Test]
    public function it_sends_email_by_default_when_creating_invited_user(): void
    {
        // Arrange
        Mail::fake();

        $financer = ModelFactory::createFinancer();

        $dto = CreateInvitedUserDTO::from([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john.doe@example.com',
            'financer_id' => (string) $financer->id,
            'intended_role' => 'beneficiary',
        ]);

        $action = new CreateInvitedUserAction($dto);

        // Act - Execute the action WITHOUT calling ->withoutEmail()
        $user = $action->execute();

        // Assert - User created
        $this->assertInstanceOf(User::class, $user);
        $this->assertEquals('john.doe@example.com', $user->email);
        $this->assertEquals('pending', $user->invitation_status);

        // Assert - Welcome email SHOULD be sent (default behavior)
        Mail::assertSent(WelcomeEmail::class, function (WelcomeEmail $mail): bool {
            return $mail->hasTo('john.doe@example.com');
        });
    }

    #[Test]
    public function it_does_not_send_email_when_explicitly_disabled(): void
    {
        // Arrange
        Mail::fake();

        $financer = ModelFactory::createFinancer();

        $dto = CreateInvitedUserDTO::from([
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'email' => 'jane.smith@example.com',
            'financer_id' => (string) $financer->id,
            'intended_role' => 'beneficiary',
        ]);

        $action = new CreateInvitedUserAction($dto);

        // Act - Call withoutEmail() to disable email sending
        $user = $action->withoutEmail()->execute();

        // Assert - User created
        $this->assertInstanceOf(User::class, $user);
        $this->assertEquals('jane.smith@example.com', $user->email);

        // Assert - Welcome email SHOULD NOT be sent
        Mail::assertNotSent(WelcomeEmail::class);
    }
}
