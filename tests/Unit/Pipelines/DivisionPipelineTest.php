<?php

namespace Tests\Unit\Pipelines;

use App;
use App\Models\Division;
use App\Pipelines\FilterPipelines\DivisionPipeline;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Group;
use Tests\Helpers\Attributes\FlushTables;
use Tests\TestCase;

#[FlushTables(tables: ['divisions', 'financers'], scope: 'test')]
#[Group('division')]
class DivisionPipelineTest extends TestCase
{
    protected DivisionPipeline $pipeline;

    protected function setUp(): void
    {

        parent::setUp();

        $this->pipeline = new DivisionPipeline;
    }

    public function test_name_filter(): void
    {

        // Arrange
        Division::factory()->create(['name' => 'Division A']);
        Division::factory()->create(['name' => 'Division B']);
        Division::factory()->create(['name' => 'Another Division']);

        // Act
        $request = Request::create('/', 'GET', ['name' => 'Division']);
        $this->app->instance('request', $request);

        $query = Division::query();
        $result = $this->pipeline->apply($query);

        // Assert
        $this->assertEquals(3, $result->count());
        $this->assertContains('Division A', $result->pluck('name')->toArray());
        $this->assertContains('Division B', $result->pluck('name')->toArray());
        $this->assertContains('Another Division', $result->pluck('name')->toArray());
    }

    public function test_remarks_filter(): void
    {
        // Arrange
        Division::factory()->create(['remarks' => 'This is a test remark']);
        Division::factory()->create(['remarks' => 'Another remark']);
        Division::factory()->create(['remarks' => 'No match here']);

        // Act
        $request = Request::create('/', 'GET', ['remarks' => 'test']);
        $this->app->instance('request', $request);

        $query = Division::query();
        $result = $this->pipeline->apply($query);

        // Assert
        $this->assertEquals(1, $result->count());
        $this->assertEquals('This is a test remark', $result->first()->remarks);
    }

    public function test_country_filter(): void
    {
        // Arrange
        Division::factory()->create(['country' => 'FR']);
        Division::factory()->create(['country' => 'US']);
        Division::factory()->create(['country' => 'UK']);

        // Act
        $request = Request::create('/', 'GET', ['country' => 'FR']);
        $this->app->instance('request', $request);

        $query = Division::query();
        $result = $this->pipeline->apply($query);

        // Assert
        $this->assertEquals(1, $result->count());
        $this->assertEquals('FR', $result->first()->country);
    }

    public function test_currency_filter(): void
    {
        // Arrange
        Division::factory()->create(['currency' => 'EUR']);
        Division::factory()->create(['currency' => 'USD']);
        Division::factory()->create(['currency' => 'GBP']);

        // Act
        $request = Request::create('/', 'GET', ['currency' => 'EUR']);
        $this->app->instance('request', $request);

        $query = Division::query();
        $result = $this->pipeline->apply($query);

        // Assert
        $this->assertEquals(1, $result->count());
        $this->assertEquals('EUR', $result->first()->currency);
    }

    public function test_timezone_filter(): void
    {
        // Arrange
        Division::factory()->create(['timezone' => 'Europe/Paris']);
        Division::factory()->create(['timezone' => 'America/New_York']);
        Division::factory()->create(['timezone' => 'Asia/Tokyo']);

        // Act
        $request = Request::create('/', 'GET', ['timezone' => 'Europe/Paris']);
        $this->app->instance('request', $request);

        $query = Division::query();
        $result = $this->pipeline->apply($query);

        // Assert
        $this->assertEquals(1, $result->count());
        $this->assertEquals('Europe/Paris', $result->first()->timezone);
    }

    public function test_language_filter(): void
    {
        // Arrange
        Division::factory()->create(['language' => 'fr']);
        Division::factory()->create(['language' => 'en']);
        Division::factory()->create(['language' => 'de']);

        // Act
        $request = Request::create('/', 'GET', ['language' => 'fr']);
        $this->app->instance('request', $request);

        $query = Division::query();
        $result = $this->pipeline->apply($query);

        // Assert
        $this->assertEquals(1, $result->count());
        $this->assertEquals('fr', $result->first()->language);
    }

    public function test_date_filters(): void
    {
        // Arrange
        $today = Carbon::now();
        $yesterday = Carbon::yesterday();
        $tomorrow = Carbon::tomorrow();

        // Force specific created_at and updated_at dates
        DB::table('divisions')->insert([
            'id' => Str::uuid()->toString(),
            'name' => 'Division Today',
            'country' => 'FR',
            'currency' => 'EUR',
            'timezone' => 'Europe/Paris',
            'language' => App::currentLocale(),
            'created_at' => $today,
            'updated_at' => $yesterday, // Mise à jour hier
        ]);

        DB::table('divisions')->insert([
            'id' => Str::uuid()->toString(),
            'name' => 'Division Yesterday',
            'country' => 'FR',
            'currency' => 'EUR',
            'timezone' => 'Europe/Paris',
            'language' => App::currentLocale(),
            'created_at' => $yesterday,
            'updated_at' => $today, // Mise à jour aujourd'hui
        ]);

        // Test DateFromFilter sur created_at (par défaut)
        $request = Request::create('/', 'GET', ['date_from' => $today->toDateString()]);
        $this->app->instance('request', $request);

        $query = Division::query();
        $result = $this->pipeline->apply($query);

        // Assert
        $this->assertEquals(1, $result->count());
        $this->assertEquals('Division Today', $result->first()->name);

        // Test DateToFilter sur created_at (par défaut)
        $request = Request::create('/', 'GET', ['date_to' => $yesterday->toDateString()]);
        $this->app->instance('request', $request);

        $query = Division::query();
        $result = $this->pipeline->apply($query);

        // Assert
        $this->assertEquals(1, $result->count());
        $this->assertEquals('Division Yesterday', $result->first()->name);

        // Test DateFromFilter sur updated_at
        $request = Request::create('/', 'GET', [
            'date_from' => $today->toDateString(),
            'date_from_fields' => 'updated_at',
        ]);
        $this->app->instance('request', $request);

        $query = Division::query();
        $result = $this->pipeline->apply($query);

        // Assert
        $this->assertEquals(1, $result->count());
        $this->assertEquals('Division Yesterday', $result->first()->name);

        // Test DateToFilter sur updated_at
        $request = Request::create('/', 'GET', [
            'date_to' => $yesterday->toDateString(),
            'date_to_fields' => 'updated_at',
        ]);
        $this->app->instance('request', $request);

        $query = Division::query();
        $result = $this->pipeline->apply($query);

        // Assert
        $this->assertEquals(1, $result->count());
        $this->assertEquals('Division Today', $result->first()->name);

        // Test combiné sur created_at (par défaut)
        $request = Request::create('/', 'GET', [
            'date_from' => $yesterday->toDateString(),
            'date_to' => $tomorrow->toDateString(),
        ]);
        $this->app->instance('request', $request);

        $query = Division::query();
        $result = $this->pipeline->apply($query);

        // Assert
        $this->assertEquals(2, $result->count());
    }

    public function test_multiple_filters(): void
    {
        // Arrange
        Division::factory()->create([
            'name' => 'Division France',
            'country' => 'FR',
            'currency' => 'EUR',
        ]);
        Division::factory()->create([
            'name' => 'Another France Division',
            'country' => 'FR',
            'currency' => 'EUR',
        ]);
        Division::factory()->create([
            'name' => 'Division USA',
            'country' => 'US',
            'currency' => 'USD',
        ]);

        // Act
        $request = Request::create('/', 'GET', [
            'name' => 'Division',
            'country' => 'FR',
        ]);
        $this->app->instance('request', $request);

        $query = Division::query();
        $result = $this->pipeline->apply($query);

        // Assert
        $this->assertEquals(2, $result->count());
        $this->assertContains('Division France', $result->pluck('name')->toArray());
        $this->assertContains('Another France Division', $result->pluck('name')->toArray());
    }

    public function test_invalid_filter_values(): void
    {
        // Arrange
        Division::factory()->create(['name' => 'Division A']);
        Division::factory()->create(['name' => 'Division B']);

        // Act - Test with array value which should be ignored
        $request = Request::create('/', 'GET', ['name' => ['invalid', 'array']]);
        $this->app->instance('request', $request);

        $query = Division::query();
        $result = $this->pipeline->apply($query);

        // Assert - Should return all divisions as filter is ignored
        $this->assertEquals(2, $result->count());
    }
}
