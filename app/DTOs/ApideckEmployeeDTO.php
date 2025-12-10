<?php

namespace App\DTOs;

use App\Enums\IDP\RoleDefaults;
use App\Models\Team;
use DateTime;

class ApideckEmployeeDTO
{
    public ?DateTime $email_verified_at = null;

    public bool $force_change_email = false;

    public bool $enabled = true;

    public ?string $currency = null;

    public ?string $stripe_id = null;

    public ?DateTime $last_login = null;

    public bool $opt_in = false;

    public ?string $remember_token = null;

    public ?string $team_id = null;

    public ?string $sirh_id;

    public ?string $first_name;

    public ?string $last_name;

    public ?string $middle_name;

    public ?string $display_name;

    public ?string $preferred_name;

    public ?string $initials;

    public ?string $salutation;

    public ?string $job_title;

    public ?string $marital_status;

    public ?string $division;

    public ?string $department;

    public ?string $company_name;

    public ?string $hired_at;

    public ?string $employment_end_date;

    public ?string $leaving_reason;

    public ?string $employee_number;

    public ?string $employment_status;

    public ?string $ethnicity;

    public ?string $birthday;

    public ?string $gender;

    public ?string $pronouns;

    public ?string $preferred_language;

    /**
     * @var array<int, string>|null
     */
    public ?array $languages = null;

    /**
     * @var array<int, string>|null
     */
    public ?array $nationalities = null;

    public ?string $photo_url;

    public ?string $timezone;

    public ?string $source;

    public ?string $record_url;

    public ?bool $works_remote;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $addresses = null;

    public ?string $phone_number;

    public ?string $email;

    /**
     * @var array<int, array<string, mixed>>|null
     */
    public ?array $compensations = null;

    /**
     * @var array<int, array<string, mixed>>|null
     */
    public ?array $social_links = null;

    /**
     * @var array<int, array<string, mixed>>|null
     */
    public ?array $bank_accounts = null;

    public ?string $tax_code;

    public ?string $tax_id;

    public ?string $dietary_preference;

    /** @var array<int, string>|null */
    public ?array $food_allergies = null;

    /** @var array<int, string>|null */
    public ?array $tags = null;

    public ?bool $deleted;

    public ?string $updated_at;

    public ?string $created_at;

    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(array $data)
    {
        $this->sirh_id = array_key_exists('id', $data) && is_scalar($data['id']) ? (string) $data['id'] : null;
        $this->first_name = array_key_exists('first_name', $data) && is_scalar($data['first_name']) ? (string) $data['first_name'] : null;
        $this->last_name = array_key_exists('last_name', $data) && is_scalar($data['last_name']) ? (string) $data['last_name'] : null;
        $this->middle_name = array_key_exists('middle_name', $data) && is_scalar($data['middle_name']) ? (string) $data['middle_name'] : null;
        $this->display_name = array_key_exists('display_name', $data) && is_scalar($data['display_name']) ? (string) $data['display_name'] : null;
        $this->preferred_name = array_key_exists('preferred_name', $data) && is_scalar($data['preferred_name']) ? (string) $data['preferred_name'] : null;
        $this->initials = array_key_exists('initials', $data) && is_scalar($data['initials']) ? (string) $data['initials'] : null;
        $this->salutation = array_key_exists('salutation', $data) && is_scalar($data['salutation']) ? (string) $data['salutation'] : null;
        $this->job_title = array_key_exists('title', $data) && is_scalar($data['title']) ? (string) $data['title'] : null;
        $this->marital_status = array_key_exists('marital_status', $data) && is_scalar($data['marital_status']) ? (string) $data['marital_status'] : null;
        $this->division = array_key_exists('division', $data) && is_scalar($data['division']) ? (string) $data['division'] : null;
        $this->department = array_key_exists('department', $data) && is_scalar($data['department']) ? (string) $data['department'] : null;
        $this->company_name = array_key_exists('company_name', $data) && is_scalar($data['company_name']) ? (string) $data['company_name'] : null;
        $this->hired_at = array_key_exists('employment_start_date', $data) && is_scalar($data['employment_start_date']) ? (string) $data['employment_start_date'] : null;
        $this->employment_end_date = array_key_exists('employment_end_date', $data) && is_scalar($data['employment_end_date']) ? (string) $data['employment_end_date'] : null;
        $this->leaving_reason = array_key_exists('leaving_reason', $data) && is_scalar($data['leaving_reason']) ? (string) $data['leaving_reason'] : null;
        $this->employee_number = array_key_exists('employee_number', $data) && is_scalar($data['employee_number']) ? (string) $data['employee_number'] : null;
        $this->employment_status = array_key_exists('employment_status', $data) && is_scalar($data['employment_status']) ? (string) $data['employment_status'] : null;
        $this->ethnicity = array_key_exists('ethnicity', $data) && is_scalar($data['ethnicity']) ? (string) $data['ethnicity'] : null;
        $this->birthday = array_key_exists('birthday', $data) && is_scalar($data['birthday']) ? (string) $data['birthday'] : null;
        $this->gender = array_key_exists('gender', $data) && is_scalar($data['gender']) ? (string) $data['gender'] : null;
        $this->pronouns = array_key_exists('pronouns', $data) && is_scalar($data['pronouns']) ? (string) $data['pronouns'] : null;
        $this->preferred_language = array_key_exists('preferred_language', $data) && is_scalar($data['preferred_language']) ? (string) $data['preferred_language'] : null;
        $this->languages = array_key_exists('languages', $data) && is_array($data['languages'])
            ? array_map(fn ($item): string => is_scalar($item) ? (string) $item : '', $data['languages'])
            : [];
        $this->nationalities = array_key_exists('nationalities', $data) && is_array($data['nationalities'])
            ? array_map(fn ($item): string => is_scalar($item) ? (string) $item : '', $data['nationalities'])
            : [];
        $this->photo_url = array_key_exists('photo_url', $data) && is_scalar($data['photo_url']) ? (string) $data['photo_url'] : null;
        $this->timezone = array_key_exists('timezone', $data) && is_scalar($data['timezone']) ? (string) $data['timezone'] : null;
        $this->source = array_key_exists('source', $data) && is_scalar($data['source']) ? (string) $data['source'] : null;
        $this->record_url = array_key_exists('record_url', $data) && is_scalar($data['record_url']) ? (string) $data['record_url'] : null;
        $this->works_remote = array_key_exists('works_remote', $data) ? (bool) $data['works_remote'] : null;

        // Traitement des tableaux complexes
        /** @var array<int, array<string, mixed>> $addresses */
        $addresses = array_key_exists('addresses', $data) && is_array($data['addresses'])
            ? $this->ensureTypedArray($data['addresses'])
            : [];
        $this->addresses = $this->extractAddresses($addresses);

        /** @var array<int, array<string, mixed>> $phoneNumbers */
        $phoneNumbers = array_key_exists('phone_numbers', $data) && is_array($data['phone_numbers'])
            ? $this->ensureTypedArray($data['phone_numbers'])
            : [];
        $this->phone_number = $this->extractPrimaryPhoneNumber($phoneNumbers);

        /** @var array<int, array<string, mixed>> $emails */
        $emails = array_key_exists('emails', $data) && is_array($data['emails'])
            ? $this->ensureTypedArray($data['emails'])
            : [];
        $this->email = $this->extractPrimaryEmail($emails);

        /** @var array<int, array<string, mixed>> $compensations */
        $compensations = array_key_exists('compensations', $data) && is_array($data['compensations'])
            ? $this->ensureTypedArray($data['compensations'])
            : [];
        $this->compensations = $compensations;

        /** @var array<int, array<string, mixed>> $socialLinks */
        $socialLinks = array_key_exists('social_links', $data) && is_array($data['social_links'])
            ? $this->ensureTypedArray($data['social_links'])
            : [];
        $this->social_links = $socialLinks;

        /** @var array<int, array<string, mixed>> $bankAccounts */
        $bankAccounts = array_key_exists('bank_accounts', $data) && is_array($data['bank_accounts'])
            ? $this->ensureTypedArray($data['bank_accounts'])
            : [];
        $this->bank_accounts = $bankAccounts;

        $this->tax_code = array_key_exists('tax_code', $data) && is_scalar($data['tax_code']) ? (string) $data['tax_code'] : null;
        $this->tax_id = array_key_exists('tax_id', $data) && is_scalar($data['tax_id']) ? (string) $data['tax_id'] : null;
        $this->dietary_preference = array_key_exists('dietary_preference', $data) && is_scalar($data['dietary_preference']) ? (string) $data['dietary_preference'] : null;

        // Conversion explicite des tableaux pour les propriétés avec des types spécifiques
        $this->food_allergies = array_key_exists('food_allergies', $data) && is_array($data['food_allergies'])
            ? array_map(fn ($item): string => is_scalar($item) ? (string) $item : '', $data['food_allergies'])
            : [];

        $this->tags = array_key_exists('tags', $data) && is_array($data['tags'])
            ? array_map(fn ($item): string => is_scalar($item) ? (string) $item : '', $data['tags'])
            : [];

        $this->deleted = array_key_exists('deleted', $data) && (bool) $data['deleted'];
        $this->updated_at = array_key_exists('updated_at', $data) && is_scalar($data['updated_at']) ? (string) $data['updated_at'] : null;
        $this->created_at = array_key_exists('created_at', $data) && is_scalar($data['created_at']) ? (string) $data['created_at'] : null;
    }

    /**
     * S'assure que le tableau est correctement typé pour les méthodes extract
     *
     * @param  array<mixed>  $array
     * @return array<int, array<string, mixed>>
     */
    private function ensureTypedArray(array $array): array
    {
        return array_map(function ($item): array {
            if (! is_array($item)) {
                return ['value' => $item];
            }

            return $item;
        }, $array);
    }

    /**
     * Récupère l'adresse principale si disponible
     *
     * @param  array<int, array<string, mixed>>  $addresses
     * @return array<string, mixed>
     */
    private function extractAddresses(array $addresses): array
    {
        if ($addresses === []) {
            return [];
        }

        $firstAddress = collect($addresses)->first();

        return is_array($firstAddress) ? $firstAddress : [];
    }

    /**
     * @param  array<int, array<string, mixed>>  $phoneNumbers
     */
    private function extractPrimaryPhoneNumber(array $phoneNumbers): ?string
    {
        /** @var string|null */
        return collect($phoneNumbers)
            ->pluck('number')
            ->first();
    }

    /**
     * @param  array<int, array<string, mixed>>  $emails
     */
    private function extractPrimaryEmail(array $emails): ?string
    {
        /** @var string|null */
        return collect($emails)
            ->pluck('email')
            ->first();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function example(array $data = []): array
    {
        return [
            'id' => '12345',
            'sirh_id' => '12345',
            'first_name' => $data['first_name'] ?? 'Elon',
            'last_name' => $data['last_name'] ?? 'Musk',
            'middle_name' => 'D.',
            'display_name' => 'Technoking',
            'preferred_name' => 'Elon Musk',
            'initials' => 'EM',
            'salutation' => 'Mr',
            'job_title' => 'CEO',
            'marital_status' => 'married',
            'division' => 'Europe',
            'department' => 'R&D',
            'company_name' => 'SpaceX',
            'hired_at' => '2021-10-26',
            'employment_end_date' => '2028-10-26',
            'leaving_reason' => 'resigned',
            'employee_number' => '123456-AB',
            'employment_status' => 'active',
            'ethnicity' => 'African American',
            'birthday' => '2000-08-12',
            'gender' => 'male',
            'pronouns' => 'he/him',
            'preferred_language' => 'EN',
            'languages' => ['EN'],
            'nationalities' => ['US'],
            'photo_url' => 'https://unavatar.io/elon-musk',
            'timezone' => 'Europe/London',
            'source' => 'lever',
            'record_url' => 'https://app.intercom.io/contacts/12345',
            'works_remote' => true,
            'phone_number' => '111-111-1111',
            'emails' => [
                ['email' => $data['email'] ?? 'elon@musk.com'],
            ],
            'compensations' => [],
            'social_links' => [],
            'bank_accounts' => [],
            'tax_code' => '1111',
            'tax_id' => '234-32-0000',
            'deleted' => false,
            'updated_at' => '2020-09-30T07:43:32.000Z',
            'created_at' => '2020-09-30T07:43:32.000Z',
        ];
    }

    /**
     * Convertit le DTO en tableau pour le modèle User
     *
     * @return array<string, mixed>
     */
    public function toUserModelArray(): array
    {
        return [
            'email' => $this->email ?? null,
            'cognito_id' => null,
            'first_name' => $this->first_name ?? null,
            'last_name' => $this->last_name ?? null,
            'force_change_email' => $this->force_change_email ?? false,
            'birthdate' => $this->birthday ?? null,
            'terms_confirmed' => false,
            'enabled' => $this->enabled ?? true,
            'locale' => $this->preferred_language[0] ?? 'fr-FR',
            'currency' => $this->currency ?? 'EUR',
            'timezone' => $this->timezone ?? 'Europe/Paris',
            'stripe_id' => $this->stripe_id ?? null,
            'sirh_id' => $this->sirh_id,
            'last_login' => $this->last_login ?? null,
            'opt_in' => $this->opt_in ?? false,
            'phone' => $this->phone_number ?? null,
            'remember_token' => $this->remember_token ?? null,
            'team_id' => $this->team_id ?? (Team::first() ? Team::first()->id : null),
            'gender' => $this->gender ?? null,
        ];
    }

    /**
     * Convertit le DTO en tableau pour le modèle InvitedUser
     *
     * @return array<string, mixed>
     */
    public function toInvitedUserModelArray(): array
    {
        return [
            'first_name' => $this->first_name ?? '',
            'last_name' => $this->last_name ?? '',
            'email' => $this->email ?? '',
            'sirh_id' => $this->sirh_id,
            'extra_data' => [
                'role' => RoleDefaults::BENEFICIARY,
                'middle_name' => $this->middle_name,
                'display_name' => $this->display_name,
                'preferred_name' => $this->preferred_name,
                'job_title' => $this->job_title,
                'department' => $this->department,
                'phone_number' => $this->phone_number,
                'birthday' => $this->birthday,
                'timezone' => $this->timezone ?? 'Europe/Paris',
                'preferred_language' => $this->preferred_language ?? 'fr-FR',
            ],
        ];
    }
}
