<?php

declare(strict_types=1);

namespace App\Integrations\Wellbeing\WellWo\Documentation;

use App\Documentation\ThirdPartyApis\BaseApiDoc;

/**
 * Documentation for WellWo Wellbeing API
 * Based on official API documentation v1
 *
 * @see https://my.wellwo.net/api/v1/
 */
class WellWoApiDoc extends BaseApiDoc
{
    public static function getApiVersion(): string
    {
        return 'v1';
    }

    public static function getLastVerified(): string
    {
        return '2025-08-08';
    }

    public static function getProviderName(): string
    {
        return 'wellwo';
    }

    /**
     * Get List of Healthy Programs
     * Returns available wellbeing programs with images
     *
     * @return array<string, mixed>
     */
    public static function healthyProgramsGetList(): array
    {
        return [
            'description' => 'Get the list of healthy wellbeing programs',
            'endpoint' => 'POST https://my.wellwo.net/api/v1/',
            'documentation_url' => 'Internal WellWo API Documentation',
            'parameters' => [
                'authToken' => [
                    'type' => 'string',
                    'required' => true,
                    'description' => 'Authorization token provided by WellWo',
                ],
                'op' => [
                    'type' => 'string',
                    'required' => true,
                    'description' => 'Operation to perform',
                    'default' => 'healthyProgramsGetList',
                ],
                'lang' => [
                    'type' => 'string',
                    'required' => false,
                    'description' => 'Language for content (es, mx, ca, en, fr, it, pt)',
                    'default' => 'es',
                    'enum' => ['es', 'mx', 'ca', 'en', 'fr', 'it', 'pt'],
                ],
            ],
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'responses' => [
                '200' => [
                    'status' => 'OK',
                    '0' => [
                        'id' => 'kGc9MKOJOxBe',
                        'name' => 'Espalda sana',
                        'image' => 'https://cnt.wellwo.es/imgs/imagep/espalda-sana.jpg',
                    ],
                    '1' => [
                        'id' => 'or1YsUiKkd7B',
                        'name' => 'Cardiovascular',
                        'image' => 'https://cnt.wellwo.es/imgs/imagep/cardiovascular.jpg',
                    ],
                    '2' => [
                        'id' => 'DHhgvRnqHpJn',
                        'name' => 'Antiestrés',
                        'image' => 'https://cnt.wellwo.es/imgs/imagep/antiestres.jpg',
                    ],
                    '3' => [
                        'id' => 'Sqf1TukGaELR',
                        'name' => 'Movilidad',
                        'image' => 'https://cnt.wellwo.es/imgs/imagep/movilidad.jpg',
                    ],
                ],
                'error' => [
                    'status' => 'KO',
                    'message' => 'empty query',
                ],
            ],
            'example_call' => [
                'authToken' => 'xxxxxxxxxxxx',
                'op' => 'healthyProgramsGetList',
                'lang' => 'es',
            ],
            'notes' => [
                'Programs include wellness and fitness content',
                'Images are hosted on CDN for fast delivery',
                'Response uses numeric keys for array items',
                'Language affects program names and descriptions',
            ],
        ];
    }

    /**
     * Get Video List for a Healthy Program
     * Returns videos with metadata for a specific program
     */
    /**
     * @return array<string, mixed>
     */
    public static function healthyProgramsGetVideoList(): array
    {
        return [
            'description' => 'Get the list of videos for a healthy program',
            'endpoint' => 'POST https://my.wellwo.net/api/v1/',
            'documentation_url' => 'Internal WellWo API Documentation',
            'parameters' => [
                'authToken' => [
                    'type' => 'string',
                    'required' => true,
                    'description' => 'Authorization token provided by WellWo',
                ],
                'op' => [
                    'type' => 'string',
                    'required' => true,
                    'description' => 'Operation to perform',
                    'default' => 'healthyProgramsGetVideoList',
                ],
                'id' => [
                    'type' => 'string',
                    'required' => true,
                    'description' => 'Healthy Program Identifier (e.g., kGc9MKOJOxBe)',
                ],
                'lang' => [
                    'type' => 'string',
                    'required' => false,
                    'description' => 'Language for content',
                    'default' => 'es',
                    'enum' => ['es', 'mx', 'ca', 'en', 'fr', 'it', 'pt'],
                ],
            ],
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'responses' => [
                '200' => [
                    'status' => 'OK',
                    '0' => [
                        'id' => '32101',
                        'name' => 'Ejercicio para fortalecer la espalda',
                        'image' => 'https://cnt.wellwo.es/imgs/imagep/ES01.jpg',
                        'video' => 'https://cnt.wellwo.es/vidprg/1280x720/CAST_VFES01.mp4',
                        'length' => '17:16',
                    ],
                    '1' => [
                        'id' => '32102',
                        'name' => 'Ejercicio para fortalecer la espalda',
                        'image' => 'https://cnt.wellwo.es/imgs/imagep/ES02.jpg',
                        'video' => 'https://cnt.wellwo.es/vidprg/1280x720/CAST_VFES02.mp4',
                        'length' => '16:11',
                    ],
                ],
                'error' => [
                    'status' => 'KO',
                    'message' => 'empty query',
                ],
            ],
            'example_call' => [
                'authToken' => 'xxxxxxxxxxxx',
                'op' => 'healthyProgramsGetVideoList',
                'id' => 'kGc9MKOJOxBe',
                'lang' => 'es',
            ],
            'notes' => [
                'Videos are in MP4 format at 1280x720 resolution',
                'Length is formatted as MM:SS',
                'Each video has a thumbnail image',
                'Video URLs are direct links to CDN',
            ],
        ];
    }

    /**
     * Get List of Recorded Class Disciplines
     * Returns available fitness class disciplines
     */
    /**
     * @return array<string, mixed>
     */
    public static function recordedClassesGetDisciplines(): array
    {
        return [
            'description' => 'Get the list of disciplines of the recorded classes',
            'endpoint' => 'POST https://my.wellwo.net/api/v1/',
            'documentation_url' => 'Internal WellWo API Documentation',
            'parameters' => [
                'authToken' => [
                    'type' => 'string',
                    'required' => true,
                    'description' => 'Authorization token provided by WellWo',
                ],
                'op' => [
                    'type' => 'string',
                    'required' => true,
                    'description' => 'Operation to perform',
                    'default' => 'recordedClassesGetDisciplines',
                ],
                'lang' => [
                    'type' => 'string',
                    'required' => false,
                    'description' => 'Language for content',
                    'default' => 'es',
                    'enum' => ['es', 'mx', 'ca', 'en', 'fr', 'it', 'pt'],
                ],
            ],
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'responses' => [
                '200' => [
                    'status' => 'OK',
                    '0' => [
                        'id' => 'zFWctLyI0rVv',
                        'name' => 'Pilates',
                        'image' => 'https://my.wellwo.net/imgs/imgliv/wellwo/pilates.jpg',
                    ],
                    '1' => [
                        'id' => '4Ne3OXjikgHR',
                        'name' => 'TBC',
                        'image' => 'https://my.wellwo.net/imgs/imgliv/wellwo/tbc.jpg',
                    ],
                    '2' => [
                        'id' => 'mu3ejrhvE87x',
                        'name' => 'Spinning',
                        'image' => 'https://my.wellwo.net/imgs/imgliv/wellwo/spinning.jpg',
                    ],
                    '3' => [
                        'id' => 'jXsfRxweYWvQ',
                        'name' => 'Belly Dancing',
                        'image' => 'https://my.wellwo.net/imgs/imgliv/wellwo/vientre.jpg',
                    ],
                ],
                'error' => [
                    'status' => 'KO',
                    'message' => 'empty query',
                ],
            ],
            'example_call' => [
                'authToken' => 'xxxxxxxxxxxx',
                'op' => 'recordedClassesGetDisciplines',
                'lang' => 'en',
            ],
            'notes' => [
                'Disciplines include various fitness activities',
                'Each discipline has multiple video classes',
                'Images are representative of the discipline',
            ],
        ];
    }

    /**
     * Get Video List for a Recorded Class Discipline
     * Returns detailed video information for a specific discipline
     */
    /**
     * @return array<string, mixed>
     */
    public static function recordedClassesGetVideoList(): array
    {
        return [
            'description' => 'Gets the list of videos of a discipline of recorded classes',
            'endpoint' => 'POST https://my.wellwo.net/api/v1/',
            'documentation_url' => 'Internal WellWo API Documentation',
            'parameters' => [
                'authToken' => [
                    'type' => 'string',
                    'required' => true,
                    'description' => 'Authorization token provided by WellWo',
                ],
                'op' => [
                    'type' => 'string',
                    'required' => true,
                    'description' => 'Operation to perform',
                    'default' => 'recordedClassesGetVideoList',
                ],
                'id' => [
                    'type' => 'string',
                    'required' => true,
                    'description' => 'Discipline identifier (e.g., mu3ejrhvE87x)',
                ],
                'lang' => [
                    'type' => 'string',
                    'required' => false,
                    'description' => 'Language for content',
                    'default' => 'es',
                    'enum' => ['es', 'mx', 'ca', 'en', 'fr', 'it', 'pt'],
                ],
            ],
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'responses' => [
                '200' => [
                    'name' => 'Spinning',
                    'language' => 'en',
                    'image' => 'https://my.wellwo.net/imgs/imgliv/wellwo/spinning.jpg',
                    'mediaItems' => [
                        [
                            'name' => 'Extensive Interval',
                            'description' => 'This spinning session features four blocks of extensive intervals...',
                            'url' => 'https://player.vimeo.com/video/1043621601',
                            'level' => 'Advanced level',
                            'image' => 'https://my.wellwo.net/imgs/imgliv/iih4reonnguq.jpg',
                        ],
                        [
                            'name' => 'Hill Training',
                            'description' => 'Focus on climbing techniques and resistance...',
                            'url' => 'https://player.vimeo.com/video/1043621602',
                            'level' => 'Intermediate level',
                            'image' => 'https://my.wellwo.net/imgs/imgliv/xyz123.jpg',
                        ],
                    ],
                ],
                'error' => [
                    'status' => 'KO',
                    'message' => 'empty query',
                ],
            ],
            'example_call' => [
                'authToken' => 'xxxxxxxxxxxx',
                'op' => 'recordedClassesGetVideoList',
                'id' => 'mu3ejrhvE87x',
                'lang' => 'en',
            ],
            'notes' => [
                'Videos are hosted on Vimeo platform',
                'Each video includes difficulty level',
                'Descriptions provide workout details',
                'Response structure differs from other endpoints',
            ],
        ];
    }

    /**
     * Get User Information
     * Returns user profile and activity data
     */
    /**
     * @return array<string, mixed>
     */
    public static function userInfo(): array
    {
        return [
            'description' => 'Get information about a specific user',
            'endpoint' => 'POST https://my.wellwo.net/api/v1/',
            'documentation_url' => 'Internal WellWo API Documentation',
            'parameters' => [
                'authToken' => [
                    'type' => 'string',
                    'required' => true,
                    'description' => 'Authorization token provided by WellWo',
                ],
                'op' => [
                    'type' => 'string',
                    'required' => true,
                    'description' => 'Operation to perform',
                    'default' => 'userInfo',
                ],
                'user' => [
                    'type' => 'string',
                    'required' => true,
                    'description' => 'Email address of the user',
                ],
            ],
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'responses' => [
                '200' => [
                    'status' => 'OK',
                    'name' => 'John Doe',
                    'segmentation1' => 'Department A',
                    'segmentation2' => 'Location X',
                    'segmentation3' => 'Team Y',
                    'points' => '12345',
                    'deleted' => false,
                ],
                'error' => [
                    'status' => 'KO',
                    'message' => 'user not found',
                ],
            ],
            'example_call' => [
                'authToken' => 'xxxxxxxxxxxx',
                'op' => 'userInfo',
                'user' => 'user@domain.com',
            ],
            'notes' => [
                'Segmentation fields are customizable per company',
                'Points represent user engagement score',
                'Deleted flag indicates soft-deleted users',
            ],
        ];
    }

    /**
     * Get User Global Status
     * Returns comprehensive user activity and progress data
     */
    /**
     * @return array<string, mixed>
     */
    public static function userGlobalStatus(): array
    {
        return [
            'description' => 'Get global status including programs progress and achievements',
            'endpoint' => 'POST https://my.wellwo.net/api/v1/',
            'documentation_url' => 'Internal WellWo API Documentation',
            'parameters' => [
                'authToken' => [
                    'type' => 'string',
                    'required' => true,
                    'description' => 'Authorization token provided by WellWo',
                ],
                'op' => [
                    'type' => 'string',
                    'required' => true,
                    'description' => 'Operation to perform',
                    'default' => 'userGlobalStatus',
                ],
                'user' => [
                    'type' => 'string',
                    'required' => true,
                    'description' => 'Email address of the user',
                ],
            ],
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'responses' => [
                '200' => [
                    'status' => 'OK',
                    'name' => 'Juanjo',
                    'classification' => [
                        'points' => '598857',
                        'position' => 7,
                        'pointsClassif1' => '803328',
                        'pointsClassif2' => '798119',
                        'pointsClassif3' => '784076',
                    ],
                    'dose' => [
                        'motivationalQuote' => 'Las personas geniales empiezan grandes obras...',
                        'motivationalImage' => 'https://cnt.wellwo.es/imgs/imagem/1phoq4cZGQV6.jpg',
                    ],
                    'brainTrain' => [
                        'name' => 'Agudeza Visual',
                        'title' => 'El reto de las caras',
                    ],
                    'programs' => [
                        [
                            'cidprg' => 'or1YsUiKkd7B',
                            'titulo' => 'Cardiovascular',
                            'porcentaje' => 5,
                        ],
                        [
                            'cidprg' => 'kGc9MKOJOxBe',
                            'titulo' => 'Espalda sana',
                            'porcentaje' => 5,
                        ],
                        [
                            'cidprg' => 'bGu6IG0jDbjA',
                            'titulo' => 'Dormir bien',
                            'porcentaje' => 86,
                        ],
                    ],
                ],
                'error' => [
                    'status' => 'KO',
                    'message' => 'user not found',
                ],
            ],
            'example_call' => [
                'authToken' => 'xxxxxxxxxxxx',
                'op' => 'userGlobalStatus',
                'user' => 'user@domain.com',
            ],
            'notes' => [
                'Classification shows ranking among users',
                'Programs show completion percentage',
                'Dose provides daily motivational content',
                'BrainTrain suggests cognitive exercises',
            ],
        ];
    }

    /**
     * API Authentication and General Information
     */
    /**
     * @return array<string, mixed>
     */
    public static function apiOverview(): array
    {
        return [
            'base_url' => 'https://my.wellwo.net/api/v1/',
            'authentication' => [
                'type' => 'Token-based',
                'description' => 'All requests require an authToken parameter',
                'token_header' => false,
                'token_parameter' => 'authToken',
            ],
            'request_format' => [
                'method' => 'POST',
                'content_type' => 'application/json',
                'required_fields' => [
                    'authToken' => 'Authorization token provided by WellWo',
                    'op' => 'Operation to perform',
                ],
            ],
            'response_format' => [
                'content_type' => 'application/json',
                'success_indicator' => 'status: OK',
                'error_indicator' => 'status: KO',
                'structure' => 'Numeric keys for array items, named keys for objects',
            ],
            'supported_languages' => [
                'es' => 'Spanish (Spain)',
                'mx' => 'Spanish (LATAM)',
                'ca' => 'Catalan',
                'en' => 'English',
                'fr' => 'French',
                'it' => 'Italian',
                'pt' => 'Portuguese',
            ],
            'rate_limiting' => [
                'enabled' => true,
                'description' => 'Rate limits apply per authToken',
                'limits' => 'Contact WellWo for specific limits',
            ],
            'sandbox_environment' => [
                'available' => false,
                'description' => 'Production API only, test with caution',
            ],
            'notes' => [
                'All endpoints use POST method',
                'Operation specified via "op" parameter',
                'Responses use numeric keys for arrays',
                'Language parameter affects content localization',
                'Contact account manager for authToken',
            ],
        ];
    }

    /**
     * Calendar Operations Documentation
     */
    /**
     * @return array<string, mixed>
     */
    public static function calendarOperations(): array
    {
        return [
            'description' => 'Calendar management endpoints for events and scheduling',
            'operations' => [
                'calendarGetList' => [
                    'description' => 'Gets the list of published calendars',
                    'parameters' => [],
                    'response' => 'Array of calendar objects with id and name',
                ],
                'calendarGetEvents' => [
                    'description' => 'Gets events from a calendar filtered by dates',
                    'parameters' => [
                        'id' => 'Calendar identifier',
                        'from' => 'Start date/time in UTC',
                        'to' => 'End date/time in UTC',
                    ],
                    'response' => 'Array of event objects',
                ],
                'calendarInsertEvent' => [
                    'description' => 'Insert an event into a calendar',
                    'parameters' => [
                        'calendarId' => 'Calendar identifier',
                        'title' => 'Event title',
                        'description' => 'Event description',
                        'location' => 'Event location',
                        'timestampFrom' => 'Start date/time in UTC',
                        'timestampTo' => 'End date/time in UTC',
                    ],
                    'response' => 'Event ID on success',
                ],
                'calendarDeleteEvent' => [
                    'description' => 'Deletes an event from a calendar',
                    'parameters' => [
                        'calendarId' => 'Calendar identifier',
                        'eventId' => 'Event ID',
                    ],
                    'response' => 'Success status',
                ],
            ],
            'notes' => [
                'All timestamps are in UTC format',
                'Calendar operations require appropriate permissions',
                'Events support title, description, and location fields',
            ],
        ];
    }

    /**
     * User Management Operations Documentation
     */
    /**
     * @return array<string, mixed>
     */
    public static function userManagementOperations(): array
    {
        return [
            'description' => 'User account management and administration endpoints',
            'operations' => [
                'userDeactivate' => [
                    'description' => 'Deactivate a user account (soft delete)',
                    'parameters' => [
                        'user' => 'Email address of the user',
                    ],
                    'notes' => 'Users marked for deletion are permanently deleted after 15 days',
                ],
                'userReactivate' => [
                    'description' => 'Reactivate a previously deactivated user',
                    'parameters' => [
                        'user' => 'Email address of the user',
                    ],
                ],
                'userUpdateProperty' => [
                    'description' => 'Update a specific user property',
                    'parameters' => [
                        'user' => 'Email address of the user',
                        'property' => 'Property to modify',
                        'value' => 'New value',
                    ],
                ],
                'emailChecklistGet' => [
                    'description' => 'Get checklist of allowed emails for self-registration',
                    'parameters' => [
                        'filter' => '(optional) Filter string',
                    ],
                ],
                'emailChecklistInsert' => [
                    'description' => 'Add email to self-registration whitelist',
                    'parameters' => [
                        'email' => 'Email to add',
                        'segme1-4' => '(optional) Segmentation values',
                    ],
                ],
                'emailChecklistDelete' => [
                    'description' => 'Remove email from self-registration whitelist',
                    'parameters' => [
                        'email' => 'Email to remove',
                    ],
                ],
            ],
            'notes' => [
                'User deactivation is reversible within 15 days',
                'Email checklist controls self-registration access',
                'Segmentation values allow user categorization',
            ],
        ];
    }
}
