<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Survey\Enums;

use App\Integrations\Survey\Enums\SurveyStatusEnum;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use Tests\TestCase;

#[Group('survey')]
#[Group('enum')]
class SurveyStatusEnumTest extends TestCase
{
    #[Test]
    public function it_has_correct_survey_status_values(): void
    {
        $this->assertEquals('draft', SurveyStatusEnum::DRAFT);
        $this->assertEquals('new', SurveyStatusEnum::NEW);
        $this->assertEquals('published', SurveyStatusEnum::PUBLISHED);
        $this->assertEquals('scheduled', SurveyStatusEnum::SCHEDULED);
        $this->assertEquals('active', SurveyStatusEnum::ACTIVE);
        $this->assertEquals('closed', SurveyStatusEnum::CLOSED);
        $this->assertEquals('archived', SurveyStatusEnum::ARCHIVED);
    }

    #[Test]
    public function it_can_get_all_survey_statuses(): void
    {
        $values = SurveyStatusEnum::getValues();

        $this->assertIsArray($values);
        $this->assertCount(7, $values);
        $this->assertContains('draft', $values);
        $this->assertContains('new', $values);
        $this->assertContains('published', $values);
        $this->assertContains('scheduled', $values);
        $this->assertContains('active', $values);
        $this->assertContains('closed', $values);
        $this->assertContains('archived', $values);
    }

    #[Test]
    public function it_can_get_static_values(): void
    {
        $staticValues = SurveyStatusEnum::getStaticValues();

        $this->assertIsArray($staticValues);
        $this->assertCount(3, $staticValues);
        $this->assertContains('draft', $staticValues);
        $this->assertContains('published', $staticValues);
        $this->assertContains('archived', $staticValues);
        $this->assertNotContains('new', $staticValues);
        $this->assertNotContains('scheduled', $staticValues);
        $this->assertNotContains('active', $staticValues);
        $this->assertNotContains('closed', $staticValues);
    }

    #[Test]
    public function it_can_get_dynamic_values(): void
    {
        $dynamicValues = SurveyStatusEnum::getDynamicValues();

        $this->assertIsArray($dynamicValues);
        $this->assertCount(4, $dynamicValues);
        $this->assertContains('new', $dynamicValues);
        $this->assertContains('scheduled', $dynamicValues);
        $this->assertContains('active', $dynamicValues);
        $this->assertContains('closed', $dynamicValues);
        $this->assertNotContains('draft', $dynamicValues);
        $this->assertNotContains('published', $dynamicValues);
        $this->assertNotContains('archived', $dynamicValues);
    }

    #[Test]
    public function it_can_get_all_values(): void
    {
        $allValues = SurveyStatusEnum::getAllValues();

        $this->assertIsArray($allValues);
        $this->assertCount(7, $allValues);
        $this->assertContains('draft', $allValues);
        $this->assertContains('new', $allValues);
        $this->assertContains('published', $allValues);
        $this->assertContains('scheduled', $allValues);
        $this->assertContains('active', $allValues);
        $this->assertContains('closed', $allValues);
        $this->assertContains('archived', $allValues);
    }

    #[Test]
    public function it_can_get_keys(): void
    {
        $keys = SurveyStatusEnum::getKeys();

        $this->assertIsArray($keys);
        $this->assertCount(7, $keys);
        $this->assertContains('DRAFT', $keys);
        $this->assertContains('NEW', $keys);
        $this->assertContains('PUBLISHED', $keys);
        $this->assertContains('SCHEDULED', $keys);
        $this->assertContains('ACTIVE', $keys);
        $this->assertContains('CLOSED', $keys);
        $this->assertContains('ARCHIVED', $keys);
    }

    #[Test]
    public function it_can_check_if_value_exists(): void
    {
        $this->assertTrue(SurveyStatusEnum::hasValue('draft'));
        $this->assertTrue(SurveyStatusEnum::hasValue('new'));
        $this->assertTrue(SurveyStatusEnum::hasValue('published'));
        $this->assertTrue(SurveyStatusEnum::hasValue('scheduled'));
        $this->assertTrue(SurveyStatusEnum::hasValue('active'));
        $this->assertTrue(SurveyStatusEnum::hasValue('closed'));
        $this->assertTrue(SurveyStatusEnum::hasValue('archived'));
        $this->assertFalse(SurveyStatusEnum::hasValue('invalid'));
    }

    #[Test]
    public function it_implements_localized_enum(): void
    {
        $reflection = new ReflectionClass(SurveyStatusEnum::class);
        $interfaces = $reflection->getInterfaceNames();

        $this->assertContains('BenSampo\Enum\Contracts\LocalizedEnum', $interfaces);
    }
}
