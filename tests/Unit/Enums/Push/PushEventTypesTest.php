<?php

namespace Tests\Unit\Enums\Push;

use App\Enums\PushEventTypes;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('push')]
#[Group('notification')]
class PushEventTypesTest extends TestCase
{
    #[Test]
    public function it_has_correct_event_type_values(): void
    {
        $this->assertEquals('sent', PushEventTypes::SENT);
        $this->assertEquals('delivered', PushEventTypes::DELIVERED);
        $this->assertEquals('opened', PushEventTypes::OPENED);
        $this->assertEquals('clicked', PushEventTypes::CLICKED);
        $this->assertEquals('dismissed', PushEventTypes::DISMISSED);
        $this->assertEquals('failed', PushEventTypes::FAILED);
    }

    #[Test]
    public function it_can_get_all_event_types(): void
    {
        $values = PushEventTypes::values();

        $this->assertIsArray($values);
        $this->assertCount(6, $values);
        $this->assertContains('sent', $values);
        $this->assertContains('delivered', $values);
        $this->assertContains('opened', $values);
        $this->assertContains('clicked', $values);
        $this->assertContains('dismissed', $values);
        $this->assertContains('failed', $values);
    }

    #[Test]
    public function it_can_create_from_value(): void
    {
        $sent = PushEventTypes::fromValue('sent');
        $this->assertEquals('sent', $sent->value);

        $delivered = PushEventTypes::fromValue('delivered');
        $this->assertEquals('delivered', $delivered->value);
    }

    #[Test]
    public function it_can_try_from_value(): void
    {
        $opened = PushEventTypes::coerce('opened');
        $this->assertInstanceOf(PushEventTypes::class, $opened);
        $this->assertEquals('opened', $opened->value);

        $invalid = PushEventTypes::coerce('invalid');
        $this->assertNull($invalid);
    }

    #[Test]
    public function it_has_label_method(): void
    {
        $this->assertEquals('Sent', PushEventTypes::SENT()->label());
        $this->assertEquals('Delivered', PushEventTypes::DELIVERED()->label());
        $this->assertEquals('Opened', PushEventTypes::OPENED()->label());
        $this->assertEquals('Clicked', PushEventTypes::CLICKED()->label());
        $this->assertEquals('Dismissed', PushEventTypes::DISMISSED()->label());
        $this->assertEquals('Failed', PushEventTypes::FAILED()->label());
    }

    #[Test]
    public function it_can_check_if_successful_event(): void
    {
        $this->assertTrue(PushEventTypes::SENT()->isSuccessful());
        $this->assertTrue(PushEventTypes::DELIVERED()->isSuccessful());
        $this->assertTrue(PushEventTypes::OPENED()->isSuccessful());
        $this->assertTrue(PushEventTypes::CLICKED()->isSuccessful());
        $this->assertFalse(PushEventTypes::DISMISSED()->isSuccessful());
        $this->assertFalse(PushEventTypes::FAILED()->isSuccessful());
    }

    #[Test]
    public function it_can_check_if_engagement_event(): void
    {
        $this->assertFalse(PushEventTypes::SENT()->isEngagement());
        $this->assertFalse(PushEventTypes::DELIVERED()->isEngagement());
        $this->assertTrue(PushEventTypes::OPENED()->isEngagement());
        $this->assertTrue(PushEventTypes::CLICKED()->isEngagement());
        $this->assertFalse(PushEventTypes::DISMISSED()->isEngagement());
        $this->assertFalse(PushEventTypes::FAILED()->isEngagement());
    }
}
