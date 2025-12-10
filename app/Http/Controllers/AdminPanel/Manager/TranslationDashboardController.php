<?php

declare(strict_types=1);

namespace App\Http\Controllers\AdminPanel\Manager;

use App\Enums\Languages;
use App\Enums\OrigineInterfaces;
use App\Http\Controllers\Controller;
use App\Models\TranslationKey;
use App\Models\TranslationMigration;
use App\Models\TranslationValue;
use App\Settings\General\LocalizationSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Date;
use Illuminate\View\View;

class TranslationDashboardController extends Controller
{
    private const CACHE_PREFIX = 'translation-dashboard';

    private const CACHE_TTL_HOURS = 6;

    /**
     * Sections available for caching and manual refresh
     */
    private const SECTION_KEYS = [
        'stats',
        'coverage',
        'recent-activity',
        'missing',
    ];

    /**
     * Display the translation dashboard with statistics and overview
     */
    public function index(Request $request): View
    {
        $statsSection = $this->rememberSection('stats', fn (): array => $this->getTranslationStatistics());
        $coverageSection = $this->rememberSection('coverage', fn (): array => $this->getCoverageByInterface());
        $recentActivitySection = $this->rememberSection('recent-activity', fn (): array => $this->getRecentActivity());
        $missingSection = $this->rememberSection('missing', fn (): array => $this->getMissingTranslations());

        // Get localization settings for available locales management
        $localizationSettings = app(LocalizationSettings::class);
        $allLanguages = collect(Languages::asSelectObject())
            ->map(fn (array $lang): array => [
                'value' => $lang['value'],
                'label' => Languages::nativeName($lang['value']).' ('.$lang['value'].')',
            ])
            ->toArray();

        return view('admin-panel.manager.translations.dashboard', [
            'stats' => $statsSection['data'],
            'recentActivity' => $recentActivitySection['data'],
            'coverageByInterface' => $coverageSection['data'],
            'missingTranslations' => $missingSection['data'],
            'lastUpdated' => [
                'stats' => $this->formatCacheMetadata($statsSection['cached_at'] ?? null),
                'coverage' => $this->formatCacheMetadata($coverageSection['cached_at'] ?? null),
                'recent-activity' => $this->formatCacheMetadata($recentActivitySection['cached_at'] ?? null),
                'missing' => $this->formatCacheMetadata($missingSection['cached_at'] ?? null),
            ],
            'activeSection' => 'translations',
            'activePillar' => 'manager',
            'localizationSettings' => $localizationSettings,
            'allLanguages' => $allLanguages,
        ]);
    }

    /**
     * Refresh cached sections and redirect back to the dashboard
     */
    public function refresh(Request $request): RedirectResponse
    {
        $section = $request->string('section')->toString();
        $normalisedSection = $this->normaliseSection($section);

        if ($section === 'all') {
            $this->forgetSections(self::SECTION_KEYS);
        } elseif ($normalisedSection !== null) {
            $this->forgetSections([$normalisedSection]);
        } else {
            $request->validate([
                'section' => ['required', 'string', 'in:'.implode(',', array_merge(self::SECTION_KEYS, ['all']))],
            ]);
        }

        return redirect()->route('admin.manager.translations.index');
    }

    /**
     * Get translation statistics
     */
    private function getTranslationStatistics(): array
    {
        $totalKeys = TranslationKey::count();
        $totalValues = TranslationValue::count();
        $languages = Languages::getInstances();
        $interfaces = OrigineInterfaces::getInstances();

        // Calculate completion percentage
        $expectedValues = $totalKeys * count($languages) * count($interfaces);
        $completionPercentage = $expectedValues > 0 ? round(($totalValues / $expectedValues) * 100, 2) : 0;

        // Get pending migrations
        $pendingMigrations = TranslationMigration::where('status', 'pending')->count();

        // Get recent changes (last 24 hours)
        $recentChanges = TranslationValue::where('updated_at', '>=', Date::now()->subDay())->count();

        return [
            'total_keys' => $totalKeys,
            'total_values' => $totalValues,
            'total_languages' => count($languages),
            'total_interfaces' => count($interfaces),
            'completion_percentage' => $completionPercentage,
            'pending_migrations' => $pendingMigrations,
            'recent_changes' => $recentChanges,
        ];
    }

    /**
     * Get recent translation activity
     */
    private function getRecentActivity(): array
    {
        return TranslationValue::with('key')
            ->orderByDesc('updated_at')
            ->limit(10)
            ->get()
            ->map(static function (TranslationValue $value): array {
                return [
                    'key' => $value->key?->key ?? 'Unknown',
                    'language' => $value->locale ?? 'n/a',
                    'interface' => $value->interface_origin ?? 'n/a',
                    'updated_at' => optional($value->updated_at)->diffForHumans() ?? 'n/a',
                    'updated_by' => 'System',
                ];
            })
            ->toArray();
    }

    /**
     * Get translation coverage by interface
     */
    private function getCoverageByInterface(): array
    {
        $coverage = [];
        $languages = Languages::getInstances();

        foreach (OrigineInterfaces::getInstances() as $interface) {
            $interfaceValue = $interface->value;

            $totalKeys = TranslationKey::where('interface_origin', $interfaceValue)->count();

            foreach ($languages as $language) {
                $languageValue = $language->value;

                $translatedCount = TranslationValue::where('locale', $languageValue)
                    ->whereHas('key', static function ($query) use ($interfaceValue): void {
                        $query->where('interface_origin', $interfaceValue);
                    })
                    ->count();

                $coverage[$interfaceValue][$languageValue] = [
                    'translated' => $translatedCount,
                    'total' => $totalKeys,
                    'percentage' => $totalKeys > 0 ? round(($translatedCount / max($totalKeys, 1)) * 100, 2) : 0,
                ];
            }
        }

        return $coverage;
    }

    /**
     * Get missing translations
     */
    private function getMissingTranslations(): array
    {
        $missing = [];
        $limit = 20; // Limit to prevent overwhelming the dashboard

        // Get all translation keys
        $keys = TranslationKey::limit($limit)->get();

        foreach ($keys as $key) {
            foreach (OrigineInterfaces::getValues() as $interface) {
                foreach (Languages::getValues() as $language) {
                    $exists = TranslationValue::where('translation_key_id', $key->id)
                        ->where('locale', $language)
                        ->exists();

                    if (! $exists) {
                        $missing[] = [
                            'key' => $key->key,
                            'interface' => $interface,
                            'language' => $language,
                        ];

                        if (count($missing) >= $limit) {
                            return $missing;
                        }
                    }
                }
            }
        }

        return $missing;
    }

    private function rememberSection(string $section, callable $callback): array
    {
        $key = $this->cacheKey($section);

        return Cache::remember(
            $key,
            Date::now()->addHours(self::CACHE_TTL_HOURS),
            static function () use ($callback): array {
                return [
                    'data' => $callback(),
                    'cached_at' => Date::now()->toIso8601String(),
                ];
            }
        );
    }

    private function cacheKey(string $section): string
    {
        return sprintf('%s:%s', self::CACHE_PREFIX, $section);
    }

    private function forgetSections(array $sections): void
    {
        foreach ($sections as $section) {
            Cache::forget($this->cacheKey($section));
        }
    }

    private function normaliseSection(?string $section): ?string
    {
        if ($section === null || $section === '') {
            return null;
        }

        $normalised = str_replace('_', '-', $section);

        return in_array($normalised, self::SECTION_KEYS, true) ? $normalised : null;
    }

    private function formatCacheMetadata(?string $cachedAt): array
    {
        if ($cachedAt === null) {
            return [
                'iso' => null,
                'label' => null,
            ];
        }

        $timestamp = Date::parse($cachedAt)->setTimezone((string) config('app.timezone', 'UTC'));

        return [
            'iso' => $cachedAt,
            'label' => $timestamp->format('d/m/Y H:i'),
        ];
    }
}
