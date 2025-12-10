<?php

namespace Tests\Unit\Models;

use App\Models\DemoEntity;
use App\Models\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('demo')]
class DemoEntityTest extends TestCase
{
    #[Test]
    public function it_can_create_demo_entity(): void
    {
        $demoEntity = new DemoEntity([
            'entity_type' => User::class,
            'entity_id' => 'test-id-123',
        ]);

        $this->assertEquals(User::class, $demoEntity->entity_type);
        $this->assertEquals('test-id-123', $demoEntity->entity_id);
    }

    #[Test]
    public function it_has_fillable_attributes(): void
    {
        $demoEntity = new DemoEntity;
        $fillable = $demoEntity->getFillable();

        // DemoEntity doesn't define fillable, so it should be empty
        $this->assertIsArray($fillable);
        $this->assertEmpty($fillable);
    }
}
