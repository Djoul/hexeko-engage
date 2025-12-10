<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\V1\Invoicing\InvoiceItemController;

use App\Enums\IDP\RoleDefaults;
use App\Enums\InvoiceType;
use App\Enums\Security\AuthorizationMode;
use App\Models\Division;
use App\Models\Financer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Module;
use App\Models\User;
use Illuminate\Support\Facades\Context;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Facades\ModelFactory;
use Tests\ProtectedRouteTestCase;

#[Group('invoicing')]
#[Group('feature')]
class InvoiceItemControllerTest extends ProtectedRouteTestCase
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
    public function it_lists_invoice_items(): void
    {
        $division = ModelFactory::createDivision();
        $invoice = Invoice::factory()
            ->has(InvoiceItem::factory()->count(3), 'items')
            ->create([
                'invoice_type' => InvoiceType::HEXEKO_TO_DIVISION,
                'recipient_type' => Division::class,
                'recipient_id' => $division->id,
            ]);
        $this->grantGlobalAccess();

        $response = $this->getJson("/api/v1/invoices/{$invoice->id}/items");

        $response->assertOk()->assertJsonCount(3, 'data');
    }

    #[Test]
    public function it_creates_invoice_item(): void
    {
        $division = ModelFactory::createDivision();
        $invoice = Invoice::factory()->create([
            'invoice_type' => InvoiceType::HEXEKO_TO_DIVISION,
            'recipient_type' => Division::class,
            'recipient_id' => $division->id,
            'vat_rate' => '21.00',
        ]);
        $module = Module::factory()->create([
            'name' => ['fr' => 'Test Module'],
            'active' => true,
        ]);
        $this->grantGlobalAccess();

        $payload = [
            'item_type' => 'module',
            'module_id' => $module->id,
            'unit_price_htva' => 1500,
            'quantity' => 2,
            'label' => ['fr' => 'Module'],
            'description' => ['fr' => 'Description module'],
        ];

        $response = $this->postJson("/api/v1/invoices/{$invoice->id}/items", $payload);

        $response->assertCreated();

        $this->assertDatabaseHas('invoice_items', [
            'invoice_id' => $invoice->id,
            'module_id' => $module->id,
            'subtotal_htva' => 3000,
        ]);
    }

    #[Test]
    public function it_updates_invoice_item(): void
    {
        $division = ModelFactory::createDivision();
        $invoice = Invoice::factory()->create([
            'invoice_type' => InvoiceType::HEXEKO_TO_DIVISION,
            'recipient_type' => Division::class,
            'recipient_id' => $division->id,
            'vat_rate' => '21.00',
        ]);
        $item = InvoiceItem::factory()->for($invoice)->create([
            'unit_price_htva' => 1000,
            'quantity' => 1,
        ]);
        $this->grantGlobalAccess();

        $response = $this->putJson("/api/v1/invoices/{$invoice->id}/items/{$item->id}", [
            'quantity' => 3,
            'unit_price_htva' => 800,
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('invoice_items', [
            'id' => $item->id,
            'quantity' => 3,
            'subtotal_htva' => 2400,
        ]);
    }

    #[Test]
    public function it_deletes_invoice_item(): void
    {
        $division = ModelFactory::createDivision();
        $invoice = Invoice::factory()->create([
            'invoice_type' => InvoiceType::HEXEKO_TO_DIVISION,
            'recipient_type' => Division::class,
            'recipient_id' => $division->id,
        ]);
        $item = InvoiceItem::factory()->for($invoice)->create();
        $this->grantGlobalAccess();

        $response = $this->deleteJson("/api/v1/invoices/{$invoice->id}/items/{$item->id}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('invoice_items', ['id' => $item->id]);
    }

    private function grantGlobalAccess(): void
    {
        $divisions = Division::pluck('id')->toArray();
        $financers = Financer::pluck('id')->toArray();

        Context::add('accessible_divisions', $divisions);
        Context::add('accessible_financers', $financers);

        authorizationContext()->hydrate(
            AuthorizationMode::GLOBAL,
            $financers,
            $divisions,
            $this->godUser->roles->pluck('name')->toArray(),
            null
        );
    }
}
