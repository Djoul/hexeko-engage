<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\V1\Invoicing\InvoiceController;

use App\Enums\IDP\RoleDefaults;
use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Enums\Security\AuthorizationMode;
use App\Models\Division;
use App\Models\Financer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Date;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Facades\ModelFactory;
use Tests\ProtectedRouteTestCase;

#[Group('invoicing')]
class InvoiceControllerTest extends ProtectedRouteTestCase
{
    private User $godUser;

    protected function setUp(): void
    {
        parent::setUp();

        // Utiliser GOD pour bypasser toutes les autorisations
        // et tester uniquement la logique métier (pas les permissions)
        $this->godUser = $this->createAuthUser(RoleDefaults::GOD);
        $this->actingAs($this->godUser);
    }

    #[Test]
    public function it_lists_invoices_with_pagination(): void
    {
        Invoice::factory()->count(25)->create();
        $this->grantGlobalAccess();

        $response = $this->getJson('/api/v1/invoices?per_page=10&page=1');

        $response->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'invoice_number', 'status', 'amounts'],
                ],
                'meta' => ['current_page', 'from', 'last_page', 'links'],
            ]);
    }

    #[Test]
    public function it_filters_invoices_by_status(): void
    {
        Invoice::factory()->count(3)->create(['status' => InvoiceStatus::DRAFT]);
        Invoice::factory()->count(2)->create(['status' => InvoiceStatus::PAID]);
        $this->grantGlobalAccess();

        $response = $this->getJson('/api/v1/invoices?status=paid');

        $response->assertOk();
        $this->assertSame(2, count($response->json('data')));
        $this->assertTrue(collect($response->json('data'))->every(fn ($invoice): bool => $invoice['status'] === InvoiceStatus::PAID));
    }

    #[Test]
    public function it_shows_single_invoice_with_items(): void
    {
        $division = ModelFactory::createDivision();
        $invoice = Invoice::factory()
            ->has(InvoiceItem::factory()->count(2), 'items')
            ->create([
                'invoice_type' => InvoiceType::HEXEKO_TO_DIVISION,
                'recipient_type' => Division::class,
                'recipient_id' => $division->id,
            ]);
        $this->grantGlobalAccess();

        $response = $this->getJson("/api/v1/invoices/{$invoice->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $invoice->id)
            ->assertJsonCount(2, 'data.items');
    }

    #[Test]
    public function it_creates_invoice_with_items(): void
    {
        $division = ModelFactory::createDivision(['vat_rate' => '21.00']);
        $this->grantGlobalAccess();

        $payload = [
            'recipient_type' => 'division',
            'recipient_id' => $division->id,
            'billing_period_start' => Date::now()->startOfMonth()->toDateString(),
            'billing_period_end' => Date::now()->endOfMonth()->toDateString(),
            'vat_rate' => '21.00',
            'currency' => 'EUR',
            'items' => [
                [
                    'item_type' => 'core_package',
                    'module_id' => null,
                    'unit_price_htva' => 1000,
                    'quantity' => 2,
                    'label' => ['fr' => 'Forfait'],
                    'description' => ['fr' => 'Forfait mensuel'],
                ],
            ],
        ];

        $initialItems = InvoiceItem::count();
        $response = $this->postJson('/api/v1/invoices', $payload);

        $response->assertCreated();

        $this->assertDatabaseHas('invoices', [
            'recipient_id' => $division->id,
            'subtotal_htva' => 2000,
            'vat_amount' => 420,
            'total_ttc' => 2420,
        ]);

        $this->assertSame($initialItems + 1, InvoiceItem::count());
    }

    #[Test]
    public function it_updates_invoice_notes_and_due_date(): void
    {
        $division = ModelFactory::createDivision();
        $invoice = Invoice::factory()->create([
            'invoice_type' => InvoiceType::HEXEKO_TO_DIVISION,
            'recipient_type' => Division::class,
            'recipient_id' => $division->id,
        ]);
        $this->grantGlobalAccess();

        $dueDate = Date::now()->addDays(10)->toDateString();

        $response = $this->putJson("/api/v1/invoices/{$invoice->id}", [
            'notes' => 'Updated notes',
            'due_date' => $dueDate,
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'notes' => 'Updated notes',
            'due_date' => $dueDate,
        ]);
    }

    #[Test]
    public function it_soft_deletes_invoice(): void
    {
        $division = ModelFactory::createDivision();
        $invoice = Invoice::factory()->create([
            'invoice_type' => InvoiceType::HEXEKO_TO_DIVISION,
            'recipient_type' => Division::class,
            'recipient_id' => $division->id,
        ]);
        $this->grantGlobalAccess();

        $response = $this->deleteJson("/api/v1/invoices/{$invoice->id}");

        $response->assertNoContent();
        $this->assertSoftDeleted('invoices', ['id' => $invoice->id]);
    }

    #[Test]
    public function it_confirms_invoice(): void
    {
        $division = ModelFactory::createDivision();
        $invoice = Invoice::factory()->create([
            'invoice_type' => InvoiceType::HEXEKO_TO_DIVISION,
            'recipient_type' => Division::class,
            'recipient_id' => $division->id,
            'status' => InvoiceStatus::DRAFT,
        ]);
        $this->grantGlobalAccess();

        $response = $this->postJson("/api/v1/invoices/{$invoice->id}/confirm");

        $response->assertOk();
        $invoice->refresh();
        $this->assertSame(InvoiceStatus::CONFIRMED, $invoice->status);
        $this->assertNotNull($invoice->confirmed_at);
    }

    #[Test]
    public function it_marks_invoice_as_sent(): void
    {
        $division = ModelFactory::createDivision();
        $invoice = Invoice::factory()->create([
            'invoice_type' => InvoiceType::HEXEKO_TO_DIVISION,
            'recipient_type' => Division::class,
            'recipient_id' => $division->id,
            'status' => InvoiceStatus::CONFIRMED,
            'confirmed_at' => Carbon::now(),
        ]);
        $this->grantGlobalAccess();

        $response = $this->postJson("/api/v1/invoices/{$invoice->id}/mark-sent");

        $response->assertOk();
        $invoice->refresh();
        $this->assertNotNull($invoice->sent_at);
    }

    #[Test]
    public function it_marks_invoice_as_paid(): void
    {
        $division = ModelFactory::createDivision();
        $invoice = Invoice::factory()->create([
            'invoice_type' => InvoiceType::HEXEKO_TO_DIVISION,
            'recipient_type' => Division::class,
            'recipient_id' => $division->id,
            'total_ttc' => 5000,
            'status' => InvoiceStatus::CONFIRMED,
        ]);
        $this->grantGlobalAccess();

        $response = $this->postJson("/api/v1/invoices/{$invoice->id}/mark-paid", [
            'amount_paid' => 5000,
        ]);

        $response->assertOk();
        $invoice->refresh();
        $this->assertSame(InvoiceStatus::PAID, $invoice->status);
        $this->assertNotNull($invoice->paid_at);
    }

    #[Test]
    public function it_bulk_updates_invoice_statuses(): void
    {
        $division = ModelFactory::createDivision();
        $invoices = Invoice::factory()->count(2)->create([
            'invoice_type' => InvoiceType::HEXEKO_TO_DIVISION,
            'recipient_type' => Division::class,
            'recipient_id' => $division->id,
        ]);
        $this->grantGlobalAccess();

        $response = $this->postJson('/api/v1/invoices/bulk/update-status', [
            'invoice_ids' => $invoices->pluck('id')->all(),
            'status' => InvoiceStatus::CONFIRMED,
        ]);

        $response->assertOk()->assertJsonPath('data.updated', 2);

        $this->assertDatabaseHas('invoices', [
            'id' => $invoices->first()->id,
            'status' => InvoiceStatus::CONFIRMED,
        ]);
    }

    private function grantGlobalAccess(?User $user = null): void
    {
        $user ??= $this->godUser;

        $divisions = Division::pluck('id')->toArray();
        $financers = Financer::pluck('id')->toArray();

        Context::add('accessible_divisions', $divisions);
        Context::add('accessible_financers', $financers);

        authorizationContext()->hydrate(
            AuthorizationMode::GLOBAL,
            $financers,
            $divisions,
            $user?->roles->pluck('name')->toArray() ?? [],
            null
        );
    }
}
