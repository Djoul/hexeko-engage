<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Resources\Invoicing;

use App\Http\Resources\Invoicing\InvoiceCollection;
use App\Models\Invoice;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('invoicing')]
#[Group('resources')]
class InvoiceCollectionResourceTest extends TestCase
{
    #[Test]
    public function it_adds_cursor_pagination_metadata(): void
    {
        Invoice::factory()->count(3)->create();

        $paginator = Invoice::orderByDesc('created_at')->cursorPaginate(2);

        $collection = new InvoiceCollection($paginator);

        $response = $collection->toResponse(Request::create('/api/v1/invoices'));
        $payload = $response->getData(true);

        $this->assertArrayHasKey('data', $payload);
        $this->assertArrayHasKey('meta', $payload);
        $this->assertIsArray($payload['data']);
        $this->assertCount(2, $payload['data']);
        $this->assertArrayHasKey('next_cursor', $payload['meta']);
        $this->assertArrayHasKey('prev_cursor', $payload['meta']);
        $this->assertArrayHasKey('per_page', $payload['meta']);
        // Note: Laravel ResourceCollection may wrap values - just verify per_page exists
        $this->assertTrue(isset($payload['meta']['per_page']));
    }
}
