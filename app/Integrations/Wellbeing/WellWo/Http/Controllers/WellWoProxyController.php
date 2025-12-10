<?php

declare(strict_types=1);

namespace App\Integrations\Wellbeing\WellWo\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Integrations\Wellbeing\WellWo\Actions\GetClassesAction;
use App\Integrations\Wellbeing\WellWo\Actions\GetClassVideosAction;
use App\Integrations\Wellbeing\WellWo\Actions\GetProgramsAction;
use App\Integrations\Wellbeing\WellWo\Actions\GetProgramVideosAction;
use App\Integrations\Wellbeing\WellWo\Http\Resources\ClassResource;
use App\Integrations\Wellbeing\WellWo\Http\Resources\ClassVideoResource;
use App\Integrations\Wellbeing\WellWo\Http\Resources\ProgramResource;
use App\Integrations\Wellbeing\WellWo\Http\Resources\VideoResource;
use Config;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\App;

#[Group('Modules/Wellbeing/WellWo')]
class WellWoProxyController extends Controller
{
    public function __construct(
        private readonly GetProgramsAction $getProgramsAction,
        private readonly GetProgramVideosAction $getProgramVideosAction,
        private readonly GetClassesAction $getClassesAction,
        private readonly GetClassVideosAction $getClassVideosAction
    ) {}

    /**
     * Get available wellness programs.
     *
     * Retrieves a list of all available wellness programs from WellWo platform.
     * Returns program information including ID, name, image, description and video count.
     *
     * Language selection priority:
     * 1. If 'lang' parameter is provided, it will be used (accepts Languages enum format or WellWo codes)
     * 2. Otherwise, the authenticated user's locale will be automatically mapped to WellWo language
     * 3. Unsupported locales default to English ('en')
     *
     * @response AnonymousResourceCollection<ProgramResource>
     */
    #[QueryParameter(
        name: 'lang',
        description: 'Language code for content localization. Accepts Languages enum format (e.g. fr-FR, es-ES) or WellWo codes (es, en, fr, it, pt, ca, mx). If not provided, uses authenticated user\'s locale.',
        type: 'string',
        required: false,
        example: 'fr-FR'
    )]
    public function programs(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'lang' => ['sometimes', 'string', function ($attribute, $value, $fail): void {
                if (! $this->isValidLanguageInput($value)) {
                    $fail('The selected language is not supported.');
                }
            }],
        ]);

        $lang = $this->getLanguage($validated['lang'] ?? null);
        $programs = $this->getProgramsAction->execute($lang);

        return ProgramResource::collection($programs);
    }

    /**
     * Get videos for a specific program.
     *
     * Retrieves all video content associated with a specific wellness program.
     * Returns video details including ID, name, image URL, video URL and length.
     *
     * Language selection priority:
     * 1. If 'lang' parameter is provided, it will be used (accepts Languages enum format or WellWo codes)
     * 2. Otherwise, the authenticated user's locale will be automatically mapped to WellWo language
     * 3. Unsupported locales default to English ('en')
     *
     * @param  string  $id  The unique identifier of the program
     *
     * @response AnonymousResourceCollection<VideoResource>
     */
    #[QueryParameter(
        name: 'lang',
        description: 'Language code for content localization. Accepts Languages enum format (e.g. fr-FR, es-ES) or WellWo codes (es, en, fr, it, pt, ca, mx). If not provided, uses authenticated user\'s locale.',
        type: 'string',
        required: false,
        example: 'fr-FR'
    )]
    public function programVideos(Request $request, string $id): AnonymousResourceCollection|JsonResponse
    {
        $validated = $request->validate([
            'lang' => ['sometimes', 'string', function ($attribute, $value, $fail): void {
                if (! $this->isValidLanguageInput($value)) {
                    $fail('The selected language is not supported.');
                }
            }],
        ]);

        $lang = $this->getLanguage($validated['lang'] ?? null);
        $videos = $this->getProgramVideosAction->execute($id, $lang);

        if ($videos === null) {
            return response()->json(['data' => null], 404);
        }

        $videoItems = $videos['mediaItems'] ?? $videos;

        return VideoResource::collection($videoItems);

    }

    /**
     * Get available wellness classes.
     *
     * Retrieves a list of all available wellness classes from WellWo platform.
     * Returns class information including ID, name, image, description and video count.
     *
     * Language selection priority:
     * 1. If 'lang' parameter is provided, it will be used (accepts Languages enum format or WellWo codes)
     * 2. Otherwise, the authenticated user's locale will be automatically mapped to WellWo language
     * 3. Unsupported locales default to English ('en')
     *
     * @response AnonymousResourceCollection<ClassResource>
     */
    #[QueryParameter(
        name: 'lang',
        description: 'Language code for content localization. Accepts Languages enum format (e.g. fr-FR, es-ES) or WellWo codes (es, en, fr, it, pt, ca, mx). If not provided, uses authenticated user\'s locale.',
        type: 'string',
        required: false,
        example: 'fr-FR'
    )]
    public function classes(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'lang' => ['sometimes', 'string', function ($attribute, $value, $fail): void {
                if (! $this->isValidLanguageInput($value)) {
                    $fail('The selected language is not supported.');
                }
            }],
        ]);

        $lang = $this->getLanguage($validated['lang'] ?? null);
        $classes = $this->getClassesAction->execute($lang);

        return ClassResource::collection($classes);
    }

    /**
     * Get videos for a specific class.
     *
     * Retrieves all video content associated with a specific wellness class.
     * Returns video details including name, description, URL, level and image.
     *
     * Language selection priority:
     * 1. If 'lang' parameter is provided, it will be used (accepts Languages enum format or WellWo codes)
     * 2. Otherwise, the authenticated user's locale will be automatically mapped to WellWo language
     * 3. Unsupported locales default to English ('en')
     *
     * @param  string  $id  The unique identifier of the class
     *
     * @response AnonymousResourceCollection<ClassVideoResource>
     */
    #[QueryParameter(
        name: 'lang',
        description: 'Language code for content localization. Accepts Languages enum format (e.g. fr-FR, es-ES) or WellWo codes (es, en, fr, it, pt, ca, mx). If not provided, uses authenticated user\'s locale.',
        type: 'string',
        required: false,
        example: 'fr-FR'
    )]
    public function classVideos(Request $request, string $id): AnonymousResourceCollection|JsonResponse
    {
        $validated = $request->validate([
            'lang' => ['sometimes', 'string', function ($attribute, $value, $fail): void {
                if (! $this->isValidLanguageInput($value)) {
                    $fail('The selected language is not supported.');
                }
            }],
        ]);

        $lang = $this->getLanguage($validated['lang'] ?? null);

        $videos = $this->getClassVideosAction->execute($id, $lang);

        if ($videos === null) {
            return response()->json(['data' => null], 404);
        }

        return ClassVideoResource::collection($videos);
    }

    /**
     * Get language from request or use app locale with mapping
     */
    private function getLanguage(?string $requestLang): string
    {
        // If language is explicitly provided
        if ($requestLang !== null) {
            // Check if it's a Languages enum value (e.g., 'fr-FR')
            $mappingConfig = config('services.wellwo.language_mapping', []);
            $mapping = is_array($mappingConfig) ? $mappingConfig : [];
            if (array_key_exists($requestLang, $mapping) && is_scalar($mapping[$requestLang])) {
                return (string) $mapping[$requestLang];
            }

            // Otherwise, it's already a WellWo code (e.g., 'fr')
            return $requestLang;
        }

        // Otherwise, use app locale with mapping
        $appLocale = App::getLocale();
        $mappingConfig = config('services.wellwo.language_mapping', []);
        $mapping = is_array($mappingConfig) ? $mappingConfig : [];

        // Check if app locale is in the mapping
        if (array_key_exists($appLocale, $mapping) && is_scalar($mapping[$appLocale])) {
            return (string) $mapping[$appLocale];
        }

        // Default to configured default language
        $defaultLang = config('services.wellwo.default_language', 'en');

        return is_string($defaultLang) ? $defaultLang : 'en';
    }

    /**
     * Validate if the input is a valid language (either Languages enum or WellWo code)
     */
    private function isValidLanguageInput(string $value): bool
    {
        // Check if it's a valid Languages enum value in the mapping
        $mappingConfig = config('services.wellwo.language_mapping', []);
        $mapping = is_array($mappingConfig) ? $mappingConfig : [];
        if (array_key_exists($value, $mapping)) {
            return true;
        }

        // Check if it's a valid WellWo language code
        return in_array($value, Config::get('services.wellwo.supported_languages', []));
    }
}
