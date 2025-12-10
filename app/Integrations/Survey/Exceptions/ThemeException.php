<?php

declare(strict_types=1);

namespace App\Integrations\Survey\Exceptions;

use App\Exceptions\ApplicationException;

class ThemeException extends ApplicationException
{
    public static function hasQuestions(): self
    {
        return new self(
            'Theme has associated questions and cannot be deleted',
            ['error' => 'ThemeHasQuestions'],
            422
        );
    }

    public static function alreadyArchived(): self
    {
        return new self(
            'Theme is already archived',
            ['error' => 'ThemeAlreadyArchived'],
            422
        );
    }

    public static function notArchived(): self
    {
        return new self(
            'Theme is not archived',
            ['error' => 'ThemeNotArchived'],
            422
        );
    }

    public static function isDefault(): self
    {
        return new self(
            'Default themes cannot be modified or deleted',
            ['error' => 'ThemeIsDefault'],
            422
        );
    }

    public static function cannotDelete(): self
    {
        return new self(
            'Theme cannot be deleted',
            ['error' => 'ThemeCannotDelete'],
            422
        );
    }

    public static function duplicateName(string $name): self
    {
        return new self(
            'A theme with this name already exists',
            ['error' => 'ThemeDuplicateName', 'name' => $name],
            409
        );
    }
}
