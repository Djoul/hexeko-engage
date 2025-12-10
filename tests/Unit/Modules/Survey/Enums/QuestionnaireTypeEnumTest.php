<?php

namespace Tests\Unit\Modules\Survey\Enums;

use App\Integrations\Survey\Enums\QuestionnaireTypeEnum;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use Tests\TestCase;

#[Group('survey')]
#[Group('questionnaire')]
#[Group('enum')]
class QuestionnaireTypeEnumTest extends TestCase
{
    #[Test]
    public function it_has_correct_questionnaire_type_values(): void
    {
        $this->assertEquals('nps', QuestionnaireTypeEnum::NPS);
        $this->assertEquals('satisfaction', QuestionnaireTypeEnum::SATISFACTION);
        $this->assertEquals('custom', QuestionnaireTypeEnum::CUSTOM);
    }

    #[Test]
    public function it_can_get_all_questionnaire_types(): void
    {
        $values = QuestionnaireTypeEnum::getValues();

        $this->assertIsArray($values);
        $this->assertCount(3, $values);
        $this->assertContains('nps', $values);
        $this->assertContains('satisfaction', $values);
        $this->assertContains('custom', $values);
    }

    #[Test]
    public function it_can_get_keys(): void
    {
        $keys = QuestionnaireTypeEnum::getKeys();

        $this->assertIsArray($keys);
        $this->assertCount(3, $keys);
        $this->assertContains('NPS', $keys);
        $this->assertContains('SATISFACTION', $keys);
        $this->assertContains('CUSTOM', $keys);
    }

    #[Test]
    public function it_can_check_if_value_exists(): void
    {
        $this->assertTrue(QuestionnaireTypeEnum::hasValue('nps'));
        $this->assertTrue(QuestionnaireTypeEnum::hasValue('satisfaction'));
        $this->assertTrue(QuestionnaireTypeEnum::hasValue('custom'));
        $this->assertFalse(QuestionnaireTypeEnum::hasValue('invalid'));
    }

    #[Test]
    public function it_implements_localized_enum(): void
    {
        $reflection = new ReflectionClass(QuestionnaireTypeEnum::class);
        $interfaces = $reflection->getInterfaceNames();

        $this->assertContains('BenSampo\Enum\Contracts\LocalizedEnum', $interfaces);
    }
}
