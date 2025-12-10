<?php

declare(strict_types=1);

namespace Tests\Unit\Contracts;

use App\Contracts\Searchable;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('search')]
class SearchableInterfaceTest extends TestCase
{
    #[Test]
    public function it_requires_searchable_fields_method(): void
    {
        $mock = $this->createMock(Searchable::class);

        $this->assertTrue(method_exists($mock, 'getSearchableFields'));
    }

    #[Test]
    public function it_requires_searchable_relations_method(): void
    {
        $mock = $this->createMock(Searchable::class);

        $this->assertTrue(method_exists($mock, 'getSearchableRelations'));
    }

    #[Test]
    public function it_validates_searchable_fields_return_type(): void
    {
        $mock = $this->createMock(Searchable::class);
        $mock->method('getSearchableFields')->willReturn(['field1', 'field2']);

        $result = $mock->getSearchableFields();

        $this->assertIsArray($result);
        $this->assertContainsOnly('string', $result);
    }

    #[Test]
    public function it_validates_searchable_relations_return_type(): void
    {
        $mock = $this->createMock(Searchable::class);
        $mock->method('getSearchableRelations')->willReturn([
            'relation1' => ['field1', 'field2'],
            'relation2' => ['field3'],
        ]);

        $result = $mock->getSearchableRelations();

        $this->assertIsArray($result);
        foreach ($result as $relation => $fields) {
            $this->assertIsString($relation);
            $this->assertIsArray($fields);
            $this->assertContainsOnly('string', $fields);
        }
    }
}
