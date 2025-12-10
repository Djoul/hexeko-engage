<?php

namespace Tests\Feature\Http\Controllers\V1\User\InvitedUserController;

use App\Enums\IDP\PermissionDefaults;
use App\Enums\IDP\RoleDefaults;
use App\Enums\IDP\TeamTypes;
use App\Models\Financer;
use App\Models\Team;
use App\Services\CsvImportTrackerService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Mockery;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Facades\ModelFactory;
use Tests\ProtectedRouteTestCase;

#[Group('user')]
#[Group('csv')]
class CsvImportFlexibleColumnOrderTest extends ProtectedRouteTestCase
{
    use DatabaseTransactions;

    protected string $endpoint = '/api/v1/invited-users/import';

    protected Financer $financer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutExceptionHandling();
        // Use s3-local disk for testing (same as controller/action)
        Storage::fake('s3-local');

        // Mock CsvImportTrackerService to avoid Redis connection in CI
        $trackerMock = Mockery::mock(CsvImportTrackerService::class);
        $trackerMock->shouldReceive('initializeImport')->andReturn(null);
        $trackerMock->shouldReceive('storeImportMetadata')->andReturn(null);
        $this->app->instance(CsvImportTrackerService::class, $trackerMock);

        // Create test financer
        $division = ModelFactory::createDivision();
        $this->financer = ModelFactory::createFinancer([
            'division_id' => $division->id,
        ]);
        $team = Team::firstOrCreate(
            ['type' => TeamTypes::GLOBAL],
            ['name' => 'Global Team', 'slug' => 'global-team', 'type' => TeamTypes::GLOBAL]
        );
        ModelFactory::createRole(['name' => RoleDefaults::FINANCER_ADMIN, 'team_id' => $team->id]);
        // Create auth user with appropriate permissions
        $this->auth = ModelFactory::createUser([
            'financers' => [
                ['financer' => $this->financer, 'active' => true],
            ],
        ]);

        // Assign role with permissions

        $permission = ModelFactory::createPermission(['name' => PermissionDefaults::CREATE_USER]);
        $this->auth->givePermissionTo($permission);
    }

    #[Test]
    public function it_accepts_csv_with_standard_column_order(): void
    {
        // Create CSV with standard order: first_name, last_name, email
        $csvContent = "first_name,last_name,email,phone,external_id\n";
        $csvContent .= "John,Doe,john.doe@example.com,+1234567890,EXT001\n";
        $csvContent .= "Jane,Smith,jane.smith@example.com,+0987654321,EXT002\n";

        $file = UploadedFile::fake()->createWithContent('users.csv', $csvContent);

        $response = $this->actingAs($this->auth)
            ->postJson($this->endpoint, [
                'file' => $file,
                'financer_id' => $this->financer->id,
            ]);

        $response->assertAccepted()
            ->assertJsonStructure([
                'data' => [
                    'import_id',
                    'file_path',
                    'financer_id',
                ],
            ]);
    }

    #[Test]
    public function it_accepts_csv_with_email_first_column_order(): void
    {
        // Create CSV with email first: email, first_name, last_name
        $csvContent = "email,first_name,last_name,phone,external_id\n";
        $csvContent .= "alice.brown@example.com,Alice,Brown,+1112223333,EXT003\n";
        $csvContent .= "bob.jones@example.com,Bob,Jones,+4445556666,EXT004\n";

        $file = UploadedFile::fake()->createWithContent('users.csv', $csvContent);

        $response = $this->actingAs($this->auth)
            ->postJson($this->endpoint, [
                'file' => $file,
                'financer_id' => $this->financer->id,
            ]);

        $response->assertAccepted()
            ->assertJsonStructure([
                'data' => [
                    'import_id',
                    'file_path',
                    'financer_id',
                ],
            ]);
    }

    #[Test]
    public function it_accepts_csv_with_reversed_column_order(): void
    {
        // Create CSV with completely reversed order
        $csvContent = "external_id,phone,email,last_name,first_name\n";
        $csvContent .= "EXT005,+7778889999,charlie.wilson@example.com,Wilson,Charlie\n";
        $csvContent .= "EXT006,+1010101010,diana.taylor@example.com,Taylor,Diana\n";

        $file = UploadedFile::fake()->createWithContent('users.csv', $csvContent);

        $response = $this->actingAs($this->auth)
            ->postJson($this->endpoint, [
                'file' => $file,
                'financer_id' => $this->financer->id,
            ]);

        $response->assertAccepted()
            ->assertJsonStructure([
                'data' => [
                    'import_id',
                    'file_path',
                    'financer_id',
                ],
            ]);
    }

    #[Test]
    public function it_accepts_csv_with_only_required_columns_in_any_order(): void
    {
        // Create CSV with only required columns in random order
        $csvContent = "last_name,email,first_name\n";
        $csvContent .= "Martinez,emma.martinez@example.com,Emma\n";
        $csvContent .= "Rodriguez,frank.rodriguez@example.com,Frank\n";

        $file = UploadedFile::fake()->createWithContent('users.csv', $csvContent);

        $response = $this->actingAs($this->auth)
            ->postJson($this->endpoint, [
                'file' => $file,
                'financer_id' => $this->financer->id,
            ]);

        $response->assertAccepted()
            ->assertJsonStructure([
                'data' => [
                    'import_id',
                    'file_path',
                    'financer_id',
                ],
            ]);
    }

    #[Test]
    public function it_accepts_csv_with_mixed_optional_columns_order(): void
    {
        // Create CSV with optional columns interspersed
        $csvContent = "phone,first_name,external_id,email,last_name\n";
        $csvContent .= "+1234567890,Grace,EXT007,grace.lee@example.com,Lee\n";
        $csvContent .= "+0987654321,Henry,EXT008,henry.kim@example.com,Kim\n";

        $file = UploadedFile::fake()->createWithContent('users.csv', $csvContent);

        $response = $this->actingAs($this->auth)
            ->postJson($this->endpoint, [
                'file' => $file,
                'financer_id' => $this->financer->id,
            ]);

        $response->assertAccepted()
            ->assertJsonStructure([
                'data' => [
                    'import_id',
                    'file_path',
                    'financer_id',
                ],
            ]);
    }

    #[Test]
    public function it_rejects_csv_missing_required_column_first_name(): void
    {
        // Create CSV missing first_name column
        $csvContent = "last_name,email,phone\n";
        $csvContent .= "Johnson,missing.firstname@example.com,+1234567890\n";

        $file = UploadedFile::fake()->createWithContent('users.csv', $csvContent);

        $response = $this->actingAs($this->auth)
            ->postJson($this->endpoint, [
                'file' => $file,
                'financer_id' => $this->financer->id,
            ]);

        $response->assertBadRequest()
            ->assertJsonPath('error', 'Missing required headers: first_name')
            ->assertJsonPath('missing_headers', ['first_name']);
    }

    #[Test]
    public function it_rejects_csv_missing_required_column_email(): void
    {
        // Create CSV missing email column
        $csvContent = "first_name,last_name,phone\n";
        $csvContent .= "Isabella,White,+1234567890\n";

        $file = UploadedFile::fake()->createWithContent('users.csv', $csvContent);

        $response = $this->actingAs($this->auth)
            ->postJson($this->endpoint, [
                'file' => $file,
                'financer_id' => $this->financer->id,
            ]);

        $response->assertBadRequest()
            ->assertJsonPath('error', 'Missing required headers: email')
            ->assertJsonPath('missing_headers', ['email']);
    }

    #[Test]
    public function it_rejects_csv_missing_multiple_required_columns(): void
    {
        // Create CSV missing multiple required columns
        $csvContent = "phone,external_id\n";
        $csvContent .= "+1234567890,EXT009\n";

        $file = UploadedFile::fake()->createWithContent('users.csv', $csvContent);

        $response = $this->actingAs($this->auth)
            ->postJson($this->endpoint, [
                'file' => $file,
                'financer_id' => $this->financer->id,
            ]);

        $response->assertBadRequest()
            ->assertJson([
                'message' => 'Invalid file structure',
            ])
            ->assertJsonCount(3, 'missing_headers');
    }

    #[Test]
    public function it_accepts_csv_with_semicolon_delimiter_any_order(): void
    {
        // Create CSV with semicolon delimiter
        $csvContent = "email;last_name;first_name;phone\n";
        $csvContent .= "jack.black@example.com;Black;Jack;+1234567890\n";
        $csvContent .= "kate.green@example.com;Green;Kate;+0987654321\n";

        $file = UploadedFile::fake()->createWithContent('users.csv', $csvContent);

        $response = $this->actingAs($this->auth)
            ->postJson($this->endpoint, [
                'file' => $file,
                'financer_id' => $this->financer->id,
            ]);

        $response->assertAccepted()
            ->assertJsonStructure([
                'data' => [
                    'import_id',
                    'file_path',
                    'financer_id',
                ],
            ]);
    }

    #[Test]
    public function it_accepts_csv_with_tab_delimiter_any_order(): void
    {
        // Create CSV with tab delimiter
        $csvContent = "last_name\temail\tfirst_name\texternal_id\n";
        $csvContent .= "Brown\tliam.brown@example.com\tLiam\tEXT010\n";
        $csvContent .= "Davis\tmia.davis@example.com\tMia\tEXT011\n";

        $file = UploadedFile::fake()->createWithContent('users.csv', $csvContent);

        $response = $this->actingAs($this->auth)
            ->postJson($this->endpoint, [
                'file' => $file,
                'financer_id' => $this->financer->id,
            ]);

        $response->assertAccepted()
            ->assertJsonStructure([
                'data' => [
                    'import_id',
                    'file_path',
                    'financer_id',
                ],
            ]);
    }

    #[Test]
    public function it_handles_csv_with_extra_unknown_columns(): void
    {
        // Create CSV with extra columns that should be ignored
        $csvContent = "first_name,unknown_column,last_name,another_unknown,email,phone\n";
        $csvContent .= "Noah,ignored,Wilson,also_ignored,noah.wilson@example.com,+1234567890\n";
        $csvContent .= "Olivia,ignored,Moore,also_ignored,olivia.moore@example.com,+0987654321\n";

        $file = UploadedFile::fake()->createWithContent('users.csv', $csvContent);

        $response = $this->actingAs($this->auth)
            ->postJson($this->endpoint, [
                'file' => $file,
                'financer_id' => $this->financer->id,
            ]);

        $response->assertAccepted()
            ->assertJsonStructure([
                'data' => [
                    'import_id',
                    'file_path',
                    'financer_id',
                ],
            ]);
    }
}
