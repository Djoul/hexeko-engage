<?php

declare(strict_types=1);

namespace Tests\Unit\Rules;

use App\Models\User;
use App\Rules\UniqueEmailPerActiveFinancer;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Facades\ModelFactory;
use Tests\TestCase;

/**
 * Tests for UniqueEmailPerActiveFinancer validation rule.
 *
 * This rule ensures email uniqueness per financer for active users only.
 * The same email can exist for:
 * - Different financers
 * - Inactive users within the same financer
 */
#[Group('validation')]
#[Group('rules')]
class UniqueEmailPerActiveFinancerTest extends TestCase
{
    use DatabaseTransactions;

    #[Test]
    public function it_passes_when_email_does_not_exist(): void
    {
        // Arrange
        $financer = ModelFactory::createFinancer();
        $email = 'new.user@example.com';

        $rule = new UniqueEmailPerActiveFinancer(
            financerId: (string) $financer->id
        );

        // Act - validation should pass
        $failed = false;
        $rule->validate('email', $email, function () use (&$failed): void {
            $failed = true;
        });

        // Assert
        $this->assertFalse($failed, 'Validation should pass for non-existent email');
    }

    #[Test]
    public function it_fails_when_email_exists_for_same_financer_with_active_user(): void
    {
        // Arrange
        $financer = ModelFactory::createFinancer();
        $email = 'existing@example.com';

        // Create existing active user with this email for this financer
        ModelFactory::createUser([
            'email' => $email,
            'financers' => [
                ['financer' => $financer, 'active' => true],
            ],
        ]);

        $rule = new UniqueEmailPerActiveFinancer(
            financerId: (string) $financer->id
        );

        // Act - validation should fail
        $failed = false;
        $errorMessage = '';
        $rule->validate('email', $email, function (string $message) use (&$failed, &$errorMessage): void {
            $failed = true;
            $errorMessage = $message;
        });

        // Assert
        $this->assertTrue($failed, 'Validation should fail for duplicate active email');
        $this->assertEquals('Email already exists for this financer', $errorMessage);
    }

    #[Test]
    public function it_passes_when_email_exists_for_different_financer(): void
    {
        // Arrange
        $financer1 = ModelFactory::createFinancer();
        $financer2 = ModelFactory::createFinancer();
        $email = 'shared@example.com';

        // Create active user with this email for financer1
        ModelFactory::createUser([
            'email' => $email,
            'financers' => [
                ['financer' => $financer1, 'active' => true],
            ],
        ]);

        // Check if same email can be used for financer2
        $rule = new UniqueEmailPerActiveFinancer(
            financerId: (string) $financer2->id
        );

        // Act - validation should pass
        $failed = false;
        $rule->validate('email', $email, function () use (&$failed): void {
            $failed = true;
        });

        // Assert
        $this->assertFalse($failed, 'Validation should pass when email exists for different financer');
    }

    #[Test]
    public function it_passes_when_email_exists_for_same_financer_but_inactive(): void
    {
        // Arrange
        $financer = ModelFactory::createFinancer();
        $email = 'inactive@example.com';

        // Create inactive user with this email for this financer
        ModelFactory::createUser([
            'email' => $email,
            'financers' => [
                ['financer' => $financer, 'active' => false],
            ],
        ]);

        $rule = new UniqueEmailPerActiveFinancer(
            financerId: (string) $financer->id
        );

        // Act - validation should pass
        $failed = false;
        $rule->validate('email', $email, function () use (&$failed): void {
            $failed = true;
        });

        // Assert
        $this->assertFalse($failed, 'Validation should pass when existing user is inactive');
    }

    #[Test]
    public function it_ignores_specified_user_id_during_update(): void
    {
        // Arrange
        $financer = ModelFactory::createFinancer();
        $email = 'user@example.com';

        // Create existing active user
        $existingUser = ModelFactory::createUser([
            'email' => $email,
            'financers' => [
                ['financer' => $financer, 'active' => true],
            ],
        ]);

        // Rule with ignoreUserId - simulating an update to the same user
        $rule = new UniqueEmailPerActiveFinancer(
            financerId: (string) $financer->id,
            ignoreUserId: (string) $existingUser->id
        );

        // Act - validation should pass (updating own email)
        $failed = false;
        $rule->validate('email', $email, function () use (&$failed): void {
            $failed = true;
        });

        // Assert
        $this->assertFalse($failed, 'Validation should pass when updating same user');
    }

    #[Test]
    public function it_fails_when_another_active_user_has_email_during_update(): void
    {
        // Arrange
        $financer = ModelFactory::createFinancer();
        $email = 'taken@example.com';

        // Create first active user
        ModelFactory::createUser([
            'email' => $email,
            'financers' => [
                ['financer' => $financer, 'active' => true],
            ],
        ]);

        // Create second user (different email)
        $user2 = ModelFactory::createUser([
            'email' => 'other@example.com',
            'financers' => [
                ['financer' => $financer, 'active' => true],
            ],
        ]);

        // Try to update user2's email to user1's email
        $rule = new UniqueEmailPerActiveFinancer(
            financerId: (string) $financer->id,
            ignoreUserId: (string) $user2->id
        );

        // Act - validation should fail
        $failed = false;
        $errorMessage = '';
        $rule->validate('email', $email, function (string $message) use (&$failed, &$errorMessage): void {
            $failed = true;
            $errorMessage = $message;
        });

        // Assert
        $this->assertTrue($failed, 'Validation should fail when another user already has the email');
        $this->assertEquals('Email already exists for this financer', $errorMessage);
    }

    #[Test]
    public function it_handles_non_string_values_gracefully(): void
    {
        // Arrange
        $financer = ModelFactory::createFinancer();

        $rule = new UniqueEmailPerActiveFinancer(
            financerId: (string) $financer->id
        );

        // Act - validation should pass (early return for non-string)
        $failed = false;
        $rule->validate('email', null, function () use (&$failed): void {
            $failed = true;
        });

        // Assert
        $this->assertFalse($failed, 'Validation should pass for null value');
    }

    #[Test]
    public function it_checks_multiple_financers_correctly(): void
    {
        // Arrange
        $financer1 = ModelFactory::createFinancer();
        $financer2 = ModelFactory::createFinancer();
        $financer3 = ModelFactory::createFinancer();
        $email = 'multi-financer@example.com';

        // Create user attached to financer1 (active) and financer2 (inactive)
        $user = ModelFactory::createUser([
            'email' => $email,
        ]);
        $user->financers()->attach($financer1->id, ['active' => true]);
        $user->financers()->attach($financer2->id, ['active' => false]);

        // Test 1: Should fail for financer1 (active)
        $rule1 = new UniqueEmailPerActiveFinancer(financerId: (string) $financer1->id);
        $failed1 = false;
        $rule1->validate('email', $email, function () use (&$failed1): void {
            $failed1 = true;
        });
        $this->assertTrue($failed1, 'Should fail for financer1 where user is active');

        // Test 2: Should pass for financer2 (inactive)
        $rule2 = new UniqueEmailPerActiveFinancer(financerId: (string) $financer2->id);
        $failed2 = false;
        $rule2->validate('email', $email, function () use (&$failed2): void {
            $failed2 = true;
        });
        $this->assertFalse($failed2, 'Should pass for financer2 where user is inactive');

        // Test 3: Should pass for financer3 (not attached)
        $rule3 = new UniqueEmailPerActiveFinancer(financerId: (string) $financer3->id);
        $failed3 = false;
        $rule3->validate('email', $email, function () use (&$failed3): void {
            $failed3 = true;
        });
        $this->assertFalse($failed3, 'Should pass for financer3 where user is not attached');
    }
}
