<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Survey\Exceptions;

use App\Exceptions\ApplicationException;
use App\Integrations\Survey\Exceptions\ThemeException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('survey')]
#[Group('theme')]
#[Group('exception')]
class ThemeExceptionTest extends TestCase
{
    #[Test]
    public function it_creates_has_questions_exception(): void
    {
        $exception = ThemeException::hasQuestions();

        $this->assertEquals('Theme has associated questions and cannot be deleted', $exception->getMessage());
        $this->assertEquals(422, $exception->getHttpStatusCode());

        $context = $exception->getContext();
        $this->assertEquals('ThemeHasQuestions', $context['error']);
    }

    #[Test]
    public function it_creates_already_archived_exception(): void
    {
        $exception = ThemeException::alreadyArchived();

        $this->assertEquals('Theme is already archived', $exception->getMessage());
        $this->assertEquals(422, $exception->getHttpStatusCode());

        $context = $exception->getContext();
        $this->assertEquals('ThemeAlreadyArchived', $context['error']);
    }

    #[Test]
    public function it_creates_not_archived_exception(): void
    {
        $exception = ThemeException::notArchived();

        $this->assertEquals('Theme is not archived', $exception->getMessage());
        $this->assertEquals(422, $exception->getHttpStatusCode());

        $context = $exception->getContext();
        $this->assertEquals('ThemeNotArchived', $context['error']);
    }

    #[Test]
    public function it_creates_is_default_exception(): void
    {
        $exception = ThemeException::isDefault();

        $this->assertEquals('Default themes cannot be modified or deleted', $exception->getMessage());
        $this->assertEquals(422, $exception->getHttpStatusCode());

        $context = $exception->getContext();
        $this->assertEquals('ThemeIsDefault', $context['error']);
    }

    #[Test]
    public function it_creates_cannot_delete_exception(): void
    {
        $exception = ThemeException::cannotDelete();

        $this->assertEquals('Theme cannot be deleted', $exception->getMessage());
        $this->assertEquals(422, $exception->getHttpStatusCode());

        $context = $exception->getContext();
        $this->assertEquals('ThemeCannotDelete', $context['error']);
    }

    #[Test]
    public function it_creates_duplicate_name_exception(): void
    {
        $name = 'Duplicate Theme Name';

        $exception = ThemeException::duplicateName($name);

        $this->assertEquals('A theme with this name already exists', $exception->getMessage());
        $this->assertEquals(409, $exception->getHttpStatusCode());

        $context = $exception->getContext();
        $this->assertEquals('ThemeDuplicateName', $context['error']);
        $this->assertEquals($name, $context['name']);
    }

    #[Test]
    public function it_extends_application_exception(): void
    {
        $exception = ThemeException::hasQuestions();

        $this->assertInstanceOf(ApplicationException::class, $exception);
    }
}
