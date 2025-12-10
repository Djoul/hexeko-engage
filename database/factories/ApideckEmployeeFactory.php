<?php

namespace Database\Factories;

use Faker\Factory as Faker;

class ApideckEmployeeFactory
{
    public static function make(array $overrides = []): array
    {
        $faker = Faker::create();

        return array_merge([
            'first_name' => $faker->firstName,
            'last_name' => $faker->lastName,
            'middle_name' => $faker->optional()->firstName,
            'display_name' => $faker->name,
            'preferred_name' => $faker->firstName,
            'initials' => strtoupper($faker->lexify('??')),
            'salutation' => $faker->title,
            'title' => $faker->jobTitle,
            'marital_status' => $faker->randomElement(['single', 'married', 'divorced', 'widowed']),
            'division' => $faker->company,
            'department' => $faker->word,
            'company_name' => $faker->company,
            'employment_start_date' => $faker->date,
            'employment_end_date' => $faker->optional()->date,
            'leaving_reason' => $faker->optional()->sentence,
            'employee_number' => $faker->randomNumber(6),
            'employment_status' => $faker->randomElement(['active', 'terminated', 'on leave']),
            'ethnicity' => $faker->word,
            'birthday' => $faker->date,
            'gender' => $faker->randomElement(['male', 'female', 'non-binary']),
            'pronouns' => $faker->randomElement(['he/him', 'she/her', 'they/them']),
            'preferred_language' => $faker->languageCode,
            'languages' => [$faker->languageCode, $faker->languageCode],
            'nationalities' => [$faker->country, $faker->country],
            'photo_url' => $faker->imageUrl,
            'timezone' => $faker->timezone,
            'source' => 'Apideck',
            'record_url' => $faker->url,
            'works_remote' => $faker->boolean,
            'addresses' => [
                [
                    'street' => $faker->streetAddress,
                    'city' => $faker->city,
                    'state' => $faker->state,
                    'zip' => $faker->postcode,
                    'country' => $faker->country,
                ],
            ],
            'phone_numbers' => [
                ['number' => $faker->phoneNumber, 'type' => 'mobile'],
            ],
            'emails' => [
                ['email' => $faker->email, 'type' => 'work'],
            ],
            'compensations' => [
                ['amount' => $faker->randomFloat(2, 3000, 10000), 'currency' => 'USD', 'type' => 'salary'],
            ],
            'social_links' => [
                ['platform' => 'LinkedIn', 'url' => $faker->url],
            ],
            'bank_accounts' => [
                ['account_number' => $faker->bankAccountNumber, 'bank_name' => $faker->company],
            ],
            'tax_code' => $faker->randomNumber(5),
            'tax_id' => $faker->randomNumber(9),
            'dietary_preference' => $faker->optional()->word,
            'food_allergies' => [$faker->optional()->word, $faker->optional()->word],
            'tags' => [$faker->word, $faker->word],
            'deleted' => false,
            'updated_at' => now(),
            'created_at' => now(),
        ], $overrides);
    }

    public static function makeMany(int $count, array $overrides = []): array
    {
        return array_map(fn (): array => self::make($overrides), range(1, $count));
    }
}
