<?php

namespace Tests\Unit\Enums\Push;

use App\Enums\NotificationTypes;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('push')]
#[Group('notification')]
class NotificationTypesTest extends TestCase
{
    #[Test]
    public function it_has_correct_notification_type_values(): void
    {
        $this->assertEquals('transaction', NotificationTypes::TRANSACTION);
        $this->assertEquals('marketing', NotificationTypes::MARKETING);
        $this->assertEquals('system', NotificationTypes::SYSTEM);
        $this->assertEquals('reminder', NotificationTypes::REMINDER);
        $this->assertEquals('alert', NotificationTypes::ALERT);
    }

    #[Test]
    public function it_can_get_all_notification_types(): void
    {
        $values = NotificationTypes::values();

        $this->assertIsArray($values);
        $this->assertCount(5, $values);
        $this->assertContains('transaction', $values);
        $this->assertContains('marketing', $values);
        $this->assertContains('system', $values);
        $this->assertContains('reminder', $values);
        $this->assertContains('alert', $values);
    }

    #[Test]
    public function it_can_create_from_value(): void
    {
        $transaction = NotificationTypes::fromValue('transaction');
        $this->assertEquals('transaction', $transaction->value);

        $marketing = NotificationTypes::fromValue('marketing');
        $this->assertEquals('marketing', $marketing->value);
    }

    #[Test]
    public function it_can_try_from_value(): void
    {
        $system = NotificationTypes::coerce('system');
        $this->assertInstanceOf(NotificationTypes::class, $system);
        $this->assertEquals('system', $system->value);

        $invalid = NotificationTypes::coerce('invalid');
        $this->assertNull($invalid);
    }

    #[Test]
    public function it_has_label_method(): void
    {
        $this->assertEquals('Transaction Update', NotificationTypes::TRANSACTION()->label());
        $this->assertEquals('Marketing Campaign', NotificationTypes::MARKETING()->label());
        $this->assertEquals('System Notification', NotificationTypes::SYSTEM()->label());
        $this->assertEquals('Reminder', NotificationTypes::REMINDER()->label());
        $this->assertEquals('Alert', NotificationTypes::ALERT()->label());
    }

    #[Test]
    public function it_has_priority_method(): void
    {
        $this->assertEquals('high', NotificationTypes::TRANSACTION()->priority());
        $this->assertEquals('low', NotificationTypes::MARKETING()->priority());
        $this->assertEquals('urgent', NotificationTypes::SYSTEM()->priority());
        $this->assertEquals('normal', NotificationTypes::REMINDER()->priority());
        $this->assertEquals('urgent', NotificationTypes::ALERT()->priority());
    }

    #[Test]
    public function it_can_check_if_critical(): void
    {
        $this->assertFalse(NotificationTypes::TRANSACTION()->isCritical());
        $this->assertFalse(NotificationTypes::MARKETING()->isCritical());
        $this->assertTrue(NotificationTypes::SYSTEM()->isCritical());
        $this->assertFalse(NotificationTypes::REMINDER()->isCritical());
        $this->assertTrue(NotificationTypes::ALERT()->isCritical());
    }

    #[Test]
    public function it_can_check_if_requires_opt_in(): void
    {
        $this->assertFalse(NotificationTypes::TRANSACTION()->requiresOptIn());
        $this->assertTrue(NotificationTypes::MARKETING()->requiresOptIn());
        $this->assertFalse(NotificationTypes::SYSTEM()->requiresOptIn());
        $this->assertTrue(NotificationTypes::REMINDER()->requiresOptIn());
        $this->assertFalse(NotificationTypes::ALERT()->requiresOptIn());
    }
}
