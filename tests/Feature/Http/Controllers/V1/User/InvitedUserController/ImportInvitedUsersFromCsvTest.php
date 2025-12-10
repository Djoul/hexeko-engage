<?php

namespace Tests\Feature\Http\Controllers\V1\User\InvitedUserController;

use App\Enums\IDP\RoleDefaults;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Facades\ModelFactory;
use Tests\ProtectedRouteTestCase;

#[Group('user')]
class ImportInvitedUsersFromCsvTest extends ProtectedRouteTestCase
{
    const URI = '/api/v1/invited-users/import';

    private User $user;

    #[Test]
    public function it_can_import_users_from_csv_file(): void
    {
        // Create a financer for the test
        $financer = ModelFactory::createFinancer();
        $this->actingAs($this->user);

        // Note: DatabaseTransactions trait handles cleanup automatically
        // No need to manually delete existing records

        // Create a CSV file with test data
        $csvContent = "first_name,last_name,email\n";
        $csvContent .= "John,Doe,john.doe@example.com\n";
        $csvContent .= "Jane,Smith,jane.smith@example.com\n";

        $csvFile = UploadedFile::fake()->createWithContent(
            'users.csv',
            $csvContent
        );

        // Make a POST request to the import endpoint
        $response = $this->postJson(self::URI, [
            'file' => $csvFile,
            'financer_id' => $financer->id,
        ]);

        // Assert that the response is accepted (queued)
        $response->assertStatus(202);

        // Assert that the response contains the expected data
        $response->assertJsonStructure([
            'data' => [
                'message',
                'import_id',
            ],
            'message',
        ]);

        // Assert that import_id is returned
        $this->assertNotNull($response->json('data.import_id'));

        // Note: Since the import is now asynchronous, we can't immediately check the database
        // The actual import happens in the background via queue
    }

    #[Test]
    public function it_validates_the_csv_file(): void
    {
        // Create a financer for the test
        $financer = ModelFactory::createFinancer();
        $this->actingAs($this->user);

        // Make a POST request without a CSV file
        $response = $this->postJson(self::URI, [
            'financer_id' => $financer->id,
        ]);

        // Assert that the response is a validation error
        $response->assertStatus(422);

        // Assert that the response contains the expected validation error
        $response->assertJsonValidationErrors(['file']);
    }

    #[Test]
    public function it_validates_the_financer_id(): void
    {
        $this->actingAs($this->user);

        // Create a CSV file with test data
        $csvContent = "first_name,last_name,email\n";
        $csvContent .= "John,Doe,john.doe@example.com\n";

        $csvFile = UploadedFile::fake()->createWithContent(
            'users.csv',
            $csvContent
        );

        // Make a POST request without a financer_id
        $response = $this->postJson(self::URI, [
            'file' => $csvFile,
        ]);

        // Assert that the response is a validation error
        $response->assertStatus(422);

        // Assert that the response contains the expected validation error
        $response->assertJsonValidationErrors(['financer_id']);
    }

    #[Test]
    public function it_handles_invalid_csv_format(): void
    {
        // Create a financer for the test
        $financer = ModelFactory::createFinancer();
        $this->actingAs($this->user);

        // Create an invalid CSV file (missing required headers)
        $csvContent = "name,email\n"; // Missing last_name
        $csvContent .= "John,john.doe@example.com\n";

        $csvFile = UploadedFile::fake()->createWithContent(
            'invalid.csv',
            $csvContent
        );

        // Make a POST request with the invalid CSV
        $response = $this->postJson(self::URI, [
            'file' => $csvFile,
            'financer_id' => $financer->id,
        ]);

        // Assert that the response is accepted (queued)
        $response->assertStatus(400);

        // Assert that the response indicates job was queued
        $response->assertJson([
            'message' => 'Invalid file structure',
            'error' => 'Missing required headers: first_name, last_name',
            'missing_headers' => [
                0 => 'first_name',
                1 => 'last_name',
            ],
            'found_headers' => [
                0 => 'name',
                1 => 'email',
            ],
        ]);

        // Note: Since the import is now asynchronous, we can't immediately check the database
        // The actual import validation happens in the background via queue
    }

    protected function setUp(): void
    {
        parent::setUp();
        // Configure s3-local disk for file storage (same as controller/action)
        Storage::fake('s3-local');
        $this->user = $this->createAuthUser(RoleDefaults::FINANCER_ADMIN);
        Mail::fake();
    }
}
