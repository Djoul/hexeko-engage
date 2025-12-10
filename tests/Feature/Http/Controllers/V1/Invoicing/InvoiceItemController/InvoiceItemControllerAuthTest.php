<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\V1\Invoicing\InvoiceItemController;

use App\Enums\IDP\RoleDefaults;
use App\Enums\InvoiceType;
use App\Models\Division;
use App\Models\Financer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\User;
use Illuminate\Support\Facades\Context;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Facades\ModelFactory;
use Tests\ProtectedRouteTestCase;

#[Group('invoicing')]
final class InvoiceItemControllerAuthTest extends ProtectedRouteTestCase
{
    public $team;

    private Division $divisionA;

    private Division $divisionB;

    private Financer $financerA1;

    private Financer $financerB1;

    private Invoice $invoiceDivAToFinA1;

    private Invoice $invoiceDivBToFinB1;

    private InvoiceItem $itemA;

    private InvoiceItem $itemB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->team = ModelFactory::createTeam();
        // Créer les rôles
        setPermissionsTeamId($this->team->id);

        ModelFactory::createRole(['name' => RoleDefaults::GOD, 'team_id' => $this->team->id]);
        ModelFactory::createRole(['name' => RoleDefaults::HEXEKO_ADMIN, 'team_id' => $this->team->id]);
        ModelFactory::createRole(['name' => RoleDefaults::DIVISION_ADMIN, 'team_id' => $this->team->id]);
        ModelFactory::createRole(['name' => RoleDefaults::FINANCER_ADMIN, 'team_id' => $this->team->id]);

        // Créer les données de test
        $this->divisionA = ModelFactory::createDivision(['name' => 'Division A']);
        $this->divisionB = ModelFactory::createDivision(['name' => 'Division B']);

        $this->financerA1 = ModelFactory::createFinancer(['division_id' => $this->divisionA->id]);
        $this->financerB1 = ModelFactory::createFinancer(['division_id' => $this->divisionB->id]);

        // Set a default financer_id in Context for global scope queries during setup
        Context::add('financer_id', $this->financerA1->id);

        $this->invoiceDivAToFinA1 = Invoice::factory()->create([
            'invoice_type' => InvoiceType::DIVISION_TO_FINANCER,
            'issuer_type' => 'App\\Models\\Division',
            'issuer_id' => $this->divisionA->id,
            'recipient_type' => 'App\\Models\\Financer',
            'recipient_id' => $this->financerA1->id,
        ]);

        $this->invoiceDivBToFinB1 = Invoice::factory()->create([
            'invoice_type' => InvoiceType::DIVISION_TO_FINANCER,
            'issuer_type' => 'App\\Models\\Division',
            'issuer_id' => $this->divisionB->id,
            'recipient_type' => 'App\\Models\\Financer',
            'recipient_id' => $this->financerB1->id,
        ]);

        $this->itemA = InvoiceItem::factory()->create(['invoice_id' => $this->invoiceDivAToFinA1->id]);
        $this->itemB = InvoiceItem::factory()->create(['invoice_id' => $this->invoiceDivBToFinB1->id]);
    }

    // ========== TESTS INDEX (View items) ==========

    #[Test]
    public function index_allows_god_user(): void
    {
        $godUser = $this->createUserWithRole(RoleDefaults::GOD);
        $this->setAccessibleContext($godUser);

        $response = $this->actingAs($godUser)->getJson("/api/v1/invoices/{$this->invoiceDivAToFinA1->id}/items");

        $response->assertOk();
    }

    #[Test]
    public function index_allows_division_admin_for_their_invoice(): void
    {
        $divisionAdmin = $this->createUserWithRole(RoleDefaults::DIVISION_ADMIN, $this->financerA1);
        $this->setAccessibleContext($divisionAdmin);

        $response = $this->actingAs($divisionAdmin)->getJson("/api/v1/invoices/{$this->invoiceDivAToFinA1->id}/items");

        $response->assertOk();
    }

    #[Test]
    public function index_forbids_division_admin_for_other_division_invoice(): void
    {
        $divisionAdmin = $this->createUserWithRole(RoleDefaults::DIVISION_ADMIN, $this->financerA1);
        $this->setAccessibleContext($divisionAdmin);

        $response = $this->actingAs($divisionAdmin)->getJson("/api/v1/invoices/{$this->invoiceDivBToFinB1->id}/items");

        $response->assertForbidden();
    }

    #[Test]
    public function index_allows_financer_admin_for_their_invoice(): void
    {
        $financerAdmin = $this->createUserWithRole(RoleDefaults::FINANCER_ADMIN, $this->financerA1);
        $this->setAccessibleContext($financerAdmin);

        $response = $this->actingAs($financerAdmin)->getJson("/api/v1/invoices/{$this->invoiceDivAToFinA1->id}/items");

        $response->assertOk();
    }

    // ========== TESTS STORE (Create item) ==========

    #[Test]
    public function store_allows_god_user(): void
    {
        $godUser = $this->createUserWithRole(RoleDefaults::GOD);
        $this->setAccessibleContext($godUser);

        $data = [
            'item_type' => 'other',
            'label' => ['en' => 'New Service'],
            'unit_price_htva' => 10000,
            'quantity' => 1,
        ];

        $response = $this->actingAs($godUser)->postJson("/api/v1/invoices/{$this->invoiceDivAToFinA1->id}/items", $data);

        $response->assertCreated();
    }

    #[Test]
    public function store_allows_division_admin_for_their_invoice(): void
    {
        $divisionAdmin = $this->createUserWithRole(RoleDefaults::DIVISION_ADMIN, $this->financerA1);
        $this->setAccessibleContext($divisionAdmin);

        $data = [
            'item_type' => 'other',
            'label' => ['en' => 'New Service'],
            'unit_price_htva' => 10000,
            'quantity' => 1,
        ];

        $response = $this->actingAs($divisionAdmin)->postJson("/api/v1/invoices/{$this->invoiceDivAToFinA1->id}/items", $data);

        $response->assertCreated();
    }

    #[Test]
    public function store_forbids_division_admin_for_other_division_invoice(): void
    {
        $divisionAdmin = $this->createUserWithRole(RoleDefaults::DIVISION_ADMIN, $this->financerA1);
        $this->setAccessibleContext($divisionAdmin);

        $data = [
            'item_type' => 'other',
            'label' => ['en' => 'New Service'],
            'unit_price_htva' => 10000,
            'quantity' => 1,
        ];

        $response = $this->actingAs($divisionAdmin)->postJson("/api/v1/invoices/{$this->invoiceDivBToFinB1->id}/items", $data);

        $response->assertForbidden();
    }

    #[Test]
    public function store_forbids_financer_admin(): void
    {
        $financerAdmin = $this->createUserWithRole(RoleDefaults::FINANCER_ADMIN, $this->financerA1);
        $this->setAccessibleContext($financerAdmin);

        $data = [
            'item_type' => 'other',
            'label' => ['en' => 'New Service'],
            'unit_price_htva' => 10000,
            'quantity' => 1,
        ];

        $response = $this->actingAs($financerAdmin)->postJson("/api/v1/invoices/{$this->invoiceDivAToFinA1->id}/items", $data);

        $response->assertForbidden();
    }

    // ========== TESTS UPDATE (Modify item) ==========

    #[Test]
    public function update_allows_god_user(): void
    {
        $godUser = $this->createUserWithRole(RoleDefaults::GOD);
        $this->setAccessibleContext($godUser);

        $data = [
            'label' => ['en' => 'Updated Label'],
            'quantity' => 2,
        ];

        $response = $this->actingAs($godUser)->putJson("/api/v1/invoices/{$this->invoiceDivAToFinA1->id}/items/{$this->itemA->id}", $data);

        $response->assertOk();
    }

    #[Test]
    public function update_allows_division_admin_for_their_invoice(): void
    {
        $divisionAdmin = $this->createUserWithRole(RoleDefaults::DIVISION_ADMIN, $this->financerA1);
        $this->setAccessibleContext($divisionAdmin);

        $data = [
            'label' => ['en' => 'Updated Label'],
            'quantity' => 2,
        ];

        $response = $this->actingAs($divisionAdmin)->putJson("/api/v1/invoices/{$this->invoiceDivAToFinA1->id}/items/{$this->itemA->id}", $data);

        $response->assertOk();
    }

    #[Test]
    public function update_forbids_division_admin_for_other_division_invoice(): void
    {
        $divisionAdmin = $this->createUserWithRole(RoleDefaults::DIVISION_ADMIN, $this->financerA1);
        $this->setAccessibleContext($divisionAdmin);

        $data = [
            'label' => ['en' => 'Updated Label'],
            'quantity' => 2,
        ];

        $response = $this->actingAs($divisionAdmin)->putJson("/api/v1/invoices/{$this->invoiceDivBToFinB1->id}/items/{$this->itemB->id}", $data);

        $response->assertForbidden();
    }

    #[Test]
    public function update_forbids_financer_admin(): void
    {
        $financerAdmin = $this->createUserWithRole(RoleDefaults::FINANCER_ADMIN, $this->financerA1);
        $this->setAccessibleContext($financerAdmin);

        $data = [
            'label' => ['en' => 'Updated Label'],
            'quantity' => 2,
        ];

        $response = $this->actingAs($financerAdmin)->putJson("/api/v1/invoices/{$this->invoiceDivAToFinA1->id}/items/{$this->itemA->id}", $data);

        $response->assertForbidden();
    }

    // ========== TESTS DESTROY (Delete item) ==========

    #[Test]
    public function destroy_allows_god_user(): void
    {
        $godUser = $this->createUserWithRole(RoleDefaults::GOD);
        $this->setAccessibleContext($godUser);

        $response = $this->actingAs($godUser)->deleteJson("/api/v1/invoices/{$this->invoiceDivAToFinA1->id}/items/{$this->itemA->id}");

        $response->assertNoContent();
    }

    #[Test]
    public function destroy_allows_division_admin_for_their_invoice(): void
    {
        $divisionAdmin = $this->createUserWithRole(RoleDefaults::DIVISION_ADMIN, $this->financerA1);
        $this->setAccessibleContext($divisionAdmin);

        $response = $this->actingAs($divisionAdmin)->deleteJson("/api/v1/invoices/{$this->invoiceDivAToFinA1->id}/items/{$this->itemA->id}");

        $response->assertNoContent();
    }

    #[Test]
    public function destroy_forbids_division_admin_for_other_division_invoice(): void
    {
        $divisionAdmin = $this->createUserWithRole(RoleDefaults::DIVISION_ADMIN, $this->financerA1);
        $this->setAccessibleContext($divisionAdmin);

        $response = $this->actingAs($divisionAdmin)->deleteJson("/api/v1/invoices/{$this->invoiceDivBToFinB1->id}/items/{$this->itemB->id}");

        $response->assertForbidden();
    }

    #[Test]
    public function destroy_forbids_financer_admin(): void
    {
        $financerAdmin = $this->createUserWithRole(RoleDefaults::FINANCER_ADMIN, $this->financerA1);
        $this->setAccessibleContext($financerAdmin);

        $response = $this->actingAs($financerAdmin)->deleteJson("/api/v1/invoices/{$this->invoiceDivAToFinA1->id}/items/{$this->itemA->id}");

        $response->assertForbidden();
    }

    // ========== HELPERS ==========

    private function createUserWithRole(string $role, ?Financer $financer = null): User
    {
        $financer ??= $this->financerA1;

        $this->createRoleAndPermissions($role, $this->team);

        $userData = [
            'team_id' => $this->team->id,
            'financers' => [
                ['financer' => $financer, 'role' => $role, 'active' => true],
            ],
        ];

        $user = ModelFactory::createUser($userData, true);

        setPermissionsTeamId($user->team_id);
        $user->assignRole($role);

        return $user->fresh(['financers', 'roles']);
    }

    private function setAccessibleContext(User $user): void
    {
        $this->hydrateAuthorizationContext($user);

        // Set the first financer as active financer in Context for global scopes
        $financerIds = $user->financers->pluck('id')->toArray();
        if (count($financerIds) > 0) {
            Context::add('financer_id', $financerIds[0]);
        }
    }
}
