<?php

namespace Tests\Unit\Modules\Survey\Enums;

use App\Integrations\Survey\Enums\QuestionTypeEnum;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use Tests\TestCase;

#[Group('survey')]
#[Group('question')]
#[Group('enum')]
class QuestionTypeEnumTest extends TestCase
{
    #[Test]
    public function it_has_correct_question_type_values(): void
    {
        $this->assertEquals('scale', QuestionTypeEnum::SCALE);
        $this->assertEquals('text', QuestionTypeEnum::TEXT);
        $this->assertEquals('single_choice', QuestionTypeEnum::SINGLE_CHOICE);
        $this->assertEquals('multiple_choice', QuestionTypeEnum::MULTIPLE_CHOICE);
    }

    #[Test]
    public function it_can_get_all_question_types(): void
    {
        $values = QuestionTypeEnum::getValues();

        $this->assertIsArray($values);
        $this->assertCount(4, $values);
        $this->assertContains('scale', $values);
        $this->assertContains('text', $values);
        $this->assertContains('single_choice', $values);
        $this->assertContains('multiple_choice', $values);
    }

    #[Test]
    public function it_can_get_keys(): void
    {
        $keys = QuestionTypeEnum::getKeys();

        $this->assertIsArray($keys);
        $this->assertCount(4, $keys);
        $this->assertContains('SCALE', $keys);
        $this->assertContains('TEXT', $keys);
        $this->assertContains('SINGLE_CHOICE', $keys);
        $this->assertContains('MULTIPLE_CHOICE', $keys);
    }

    #[Test]
    public function it_can_check_if_value_exists(): void
    {
        $this->assertTrue(QuestionTypeEnum::hasValue('scale'));
        $this->assertTrue(QuestionTypeEnum::hasValue('text'));
        $this->assertTrue(QuestionTypeEnum::hasValue('single_choice'));
        $this->assertTrue(QuestionTypeEnum::hasValue('multiple_choice'));
        $this->assertFalse(QuestionTypeEnum::hasValue('invalid'));
    }

    #[Test]
    public function it_can_check_if_type_requires_options(): void
    {
        // Types that require options
        $this->assertTrue(QuestionTypeEnum::requiresOptions(QuestionTypeEnum::SCALE));
        $this->assertTrue(QuestionTypeEnum::requiresOptions(QuestionTypeEnum::SINGLE_CHOICE));
        $this->assertTrue(QuestionTypeEnum::requiresOptions(QuestionTypeEnum::MULTIPLE_CHOICE));

        // Type that doesn't require options
        $this->assertFalse(QuestionTypeEnum::requiresOptions(QuestionTypeEnum::TEXT));
    }

    #[Test]
    public function it_returns_correct_type_for_question_types(): void
    {
        // Multiple choice returns array
        $this->assertEquals('array', QuestionTypeEnum::type(QuestionTypeEnum::MULTIPLE_CHOICE));

        // All other types return string
        $this->assertEquals('string', QuestionTypeEnum::type(QuestionTypeEnum::SCALE));
        $this->assertEquals('string', QuestionTypeEnum::type(QuestionTypeEnum::TEXT));
        $this->assertEquals('string', QuestionTypeEnum::type(QuestionTypeEnum::SINGLE_CHOICE));
    }

    #[Test]
    public function it_implements_localized_enum(): void
    {
        $reflection = new ReflectionClass(QuestionTypeEnum::class);
        $interfaces = $reflection->getInterfaceNames();

        $this->assertContains('BenSampo\Enum\Contracts\LocalizedEnum', $interfaces);
    }
}
