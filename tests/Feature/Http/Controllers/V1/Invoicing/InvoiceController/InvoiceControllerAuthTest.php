<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\V1\Invoicing\InvoiceController;

use App\Enums\IDP\RoleDefaults;
use App\Enums\InvoiceType;
use App\Enums\Security\AuthorizationMode;
use App\Models\Division;
use App\Models\Financer;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Support\Facades\Context;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Attributes\FlushTables;
use Tests\Helpers\Facades\ModelFactory;
use Tests\ProtectedRouteTestCase;

#[FlushTables(tables: ['invoices'], scope: 'class')]
#[Group('invoicing')]
final class InvoiceControllerAuthTest extends ProtectedRouteTestCase
{
    public $team;

    private Division $divisionA;

    private Division $divisionB;

    private Financer $financerA1;

    private Financer $financerB1;

    private Invoice $invoiceHexekoToDivA;

    private Invoice $invoiceDivAToFinA1;

    private Invoice $invoiceDivBToFinB1;

    protected function setUp(): void
    {
        parent::setUp();

        // Créer les rôles
        $this->team = ModelFactory::createTeam();
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

        $this->invoiceHexekoToDivA = Invoice::factory()->create([
            'invoice_type' => InvoiceType::HEXEKO_TO_DIVISION,
            'recipient_type' => 'App\Models\Division',
            'recipient_id' => $this->divisionA->id,
        ]);

        $this->invoiceDivAToFinA1 = Invoice::factory()->create([
            'invoice_type' => InvoiceType::DIVISION_TO_FINANCER,
            'issuer_type' => 'App\Models\Division',
            'issuer_id' => $this->divisionA->id,
            'recipient_type' => 'App\Models\Financer',
            'recipient_id' => $this->financerA1->id,
        ]);

        $this->invoiceDivBToFinB1 = Invoice::factory()->create([
            'invoice_type' => InvoiceType::DIVISION_TO_FINANCER,
            'issuer_type' => 'App\Models\Division',
            'issuer_id' => $this->divisionB->id,
            'recipient_type' => 'App\Models\Financer',
            'recipient_id' => $this->financerB1->id,
        ]);
    }

    // ========== TESTS INDEX ==========

    #[Test]
    public function index_returns_only_accessible_invoices_for_division_admin(): void
    {
        $divisionAdmin = $this->createUserWithRole(RoleDefaults::DIVISION_ADMIN, $this->financerA1);
        $this->setAccessibleContext($divisionAdmin);

        $response = $this->actingAs($divisionAdmin)->getJson('/api/v1/invoices');

        $response->assertOk();
        $response->assertJsonCount(2, 'data'); // invoiceHexekoToDivA + invoiceDivAToFinA1

        $invoiceIds = collect($response->json('data'))->pluck('id')->toArray();
        $this->assertContains($this->invoiceHexekoToDivA->id, $invoiceIds);
        $this->assertContains($this->invoiceDivAToFinA1->id, $invoiceIds);
        $this->assertNotContains($this->invoiceDivBToFinB1->id, $invoiceIds);
    }

    #[Test]
    public function index_returns_only_accessible_invoices_for_financer_admin(): void
    {
        $financerAdmin = $this->createUserWithRole(RoleDefaults::FINANCER_ADMIN, $this->financerA1);
        $this->setAccessibleContext($financerAdmin);

        $response = $this->actingAs($financerAdmin)->getJson('/api/v1/invoices');

        $response->assertOk();
        $response->assertJsonCount(1, 'data'); // invoiceDivAToFinA1 only

        $invoiceIds = collect($response->json('data'))->pluck('id')->toArray();
        $this->assertContains($this->invoiceDivAToFinA1->id, $invoiceIds);
        $this->assertNotContains($this->invoiceHexekoToDivA->id, $invoiceIds);
    }

    #[Test]
    public function index_returns_all_invoices_for_god_user(): void
    {
        $initialCount = Invoice::count();
        $godUser = $this->createUserWithRole(RoleDefaults::GOD);
        $this->setAccessibleContext($godUser);

        $response = $this->actingAs($godUser)->getJson('/api/v1/invoices');

        $response->assertOk();
        $response->assertJsonCount($initialCount, 'data'); // All invoices
    }

    // ========== TESTS SHOW ==========

    #[Test]
    public function show_allows_division_admin_to_view_their_invoice(): void
    {
        $divisionAdmin = $this->createUserWithRole(RoleDefaults::DIVISION_ADMIN, $this->financerA1);
        $this->setAccessibleContext($divisionAdmin);

        $response = $this->actingAs($divisionAdmin)->getJson("/api/v1/invoices/{$this->invoiceHexekoToDivA->id}");

        $response->assertOk();
        $response->assertJson(['data' => ['id' => $this->invoiceHexekoToDivA->id]]);
    }

    #[Test]
    public function show_forbids_division_admin_to_view_other_division_invoice(): void
    {
        $divisionAdmin = $this->createUserWithRole(RoleDefaults::DIVISION_ADMIN, $this->financerA1);
        $this->setAccessibleContext($divisionAdmin);

        $response = $this->actingAs($divisionAdmin)->getJson("/api/v1/invoices/{$this->invoiceDivBToFinB1->id}");

        $response->assertForbidden();
    }

    #[Test]
    public function show_allows_financer_admin_to_view_their_invoice(): void
    {
        $financerAdmin = $this->createUserWithRole(RoleDefaults::FINANCER_ADMIN, $this->financerA1);
        $this->setAccessibleContext($financerAdmin);

        $response = $this->actingAs($financerAdmin)->getJson("/api/v1/invoices/{$this->invoiceDivAToFinA1->id}");

        $response->assertOk();
        $response->assertJson(['data' => ['id' => $this->invoiceDivAToFinA1->id]]);
    }

    #[Test]
    public function show_forbids_financer_admin_to_view_hexeko_to_division_invoice(): void
    {
        $financerAdmin = $this->createUserWithRole(RoleDefaults::FINANCER_ADMIN, $this->financerA1);
        $this->setAccessibleContext($financerAdmin);

        $response = $this->actingAs($financerAdmin)->getJson("/api/v1/invoices/{$this->invoiceHexekoToDivA->id}");

        $response->assertForbidden();
    }

    // ========== TESTS STORE ==========

    #[Test]
    public function store_allows_division_admin_to_create_invoice(): void
    {
        $divisionAdmin = $this->createUserWithRole(RoleDefaults::DIVISION_ADMIN, $this->financerA1);
        $this->setAccessibleContext($divisionAdmin);

        $data = [
            'recipient_type' => 'financer',
            'recipient_id' => $this->financerA1->id,
            'billing_period_start' => '2025-01-01',
            'billing_period_end' => '2025-01-31',
            'vat_rate' => '20.00',
            'currency' => 'EUR',
            'items' => [
                [
                    'item_type' => 'other',
                    'label' => ['en' => 'Test Item'],
                    'description' => ['en' => 'Test description'],
                    'unit_price_htva' => 10000,
                    'quantity' => 1,
                ],
            ],
        ];

        $response = $this->actingAs($divisionAdmin)->postJson('/api/v1/invoices', $data);

        $response->assertCreated();
    }

    #[Test]
    public function store_forbids_financer_admin_to_create_invoice(): void
    {
        $financerAdmin = $this->createUserWithRole(RoleDefaults::FINANCER_ADMIN, $this->financerA1);
        $this->setAccessibleContext($financerAdmin);

        $data = [
            'recipient_type' => 'financer',
            'recipient_id' => $this->financerA1->id,
            'billing_period_start' => '2025-01-01',
            'billing_period_end' => '2025-01-31',
            'vat_rate' => '20.00',
            'items' => [],
        ];

        $response = $this->actingAs($financerAdmin)->postJson('/api/v1/invoices', $data);

        $response->assertForbidden();
    }

    // ========== TESTS UPDATE ==========

    #[Test]
    public function update_allows_division_admin_to_update_their_issued_invoice(): void
    {
        $divisionAdmin = $this->createUserWithRole(RoleDefaults::DIVISION_ADMIN, $this->financerA1);
        $this->setAccessibleContext($divisionAdmin);

        $data = ['notes' => 'Updated notes'];

        $response = $this->actingAs($divisionAdmin)->patchJson("/api/v1/invoices/{$this->invoiceDivAToFinA1->id}", $data);

        $response->assertOk();
    }

    #[Test]
    public function update_forbids_division_admin_to_update_hexeko_to_division_invoice(): void
    {
        $divisionAdmin = $this->createUserWithRole(RoleDefaults::DIVISION_ADMIN, $this->financerA1);
        $this->setAccessibleContext($divisionAdmin);

        $data = ['notes' => 'Updated notes'];

        $response = $this->actingAs($divisionAdmin)->patchJson("/api/v1/invoices/{$this->invoiceHexekoToDivA->id}", $data);

        $response->assertForbidden();
    }

    #[Test]
    public function update_forbids_financer_admin_to_update_invoice(): void
    {
        $financerAdmin = $this->createUserWithRole(RoleDefaults::FINANCER_ADMIN, $this->financerA1);
        $this->setAccessibleContext($financerAdmin);

        $data = ['notes' => 'Updated notes'];

        $response = $this->actingAs($financerAdmin)->patchJson("/api/v1/invoices/{$this->invoiceDivAToFinA1->id}", $data);

        $response->assertForbidden();
    }

    // ========== TESTS DESTROY ==========

    #[Test]
    public function destroy_allows_division_admin_to_delete_their_issued_invoice(): void
    {
        $divisionAdmin = $this->createUserWithRole(RoleDefaults::DIVISION_ADMIN, $this->financerA1);
        $this->setAccessibleContext($divisionAdmin);

        $response = $this->actingAs($divisionAdmin)->deleteJson("/api/v1/invoices/{$this->invoiceDivAToFinA1->id}");

        $response->assertNoContent();
    }

    #[Test]
    public function destroy_forbids_financer_admin_to_delete_invoice(): void
    {
        $financerAdmin = $this->createUserWithRole(RoleDefaults::FINANCER_ADMIN, $this->financerA1);
        $this->setAccessibleContext($financerAdmin);

        $response = $this->actingAs($financerAdmin)->deleteJson("/api/v1/invoices/{$this->invoiceDivAToFinA1->id}");

        $response->assertForbidden();
    }

    // ========== TESTS CONFIRM ==========

    #[Test]
    public function confirm_allows_division_admin_to_confirm_their_issued_invoice(): void
    {
        $divisionAdmin = $this->createUserWithRole(RoleDefaults::DIVISION_ADMIN, $this->financerA1);
        $this->setAccessibleContext($divisionAdmin);

        $response = $this->actingAs($divisionAdmin)->postJson("/api/v1/invoices/{$this->invoiceDivAToFinA1->id}/confirm");

        $response->assertOk();
    }

    #[Test]
    public function confirm_forbids_financer_admin_to_confirm_invoice(): void
    {
        $financerAdmin = $this->createUserWithRole(RoleDefaults::FINANCER_ADMIN, $this->financerA1);
        $this->setAccessibleContext($financerAdmin);

        $response = $this->actingAs($financerAdmin)->postJson("/api/v1/invoices/{$this->invoiceDivAToFinA1->id}/confirm");

        $response->assertForbidden();
    }

    // ========== TESTS MARK_SENT ==========

    #[Test]
    public function mark_sent_allows_division_admin_to_mark_their_invoice_as_sent(): void
    {
        $divisionAdmin = $this->createUserWithRole(RoleDefaults::DIVISION_ADMIN, $this->financerA1);
        $this->setAccessibleContext($divisionAdmin);

        // Confirm the invoice first (business rule requirement)
        $this->invoiceDivAToFinA1->update(['status' => 'confirmed', 'confirmed_at' => now()]);

        $response = $this->actingAs($divisionAdmin)->postJson("/api/v1/invoices/{$this->invoiceDivAToFinA1->id}/mark-sent");

        $response->assertOk();
    }

    #[Test]
    public function mark_sent_forbids_financer_admin(): void
    {
        $financerAdmin = $this->createUserWithRole(RoleDefaults::FINANCER_ADMIN, $this->financerA1);
        $this->setAccessibleContext($financerAdmin);

        $response = $this->actingAs($financerAdmin)->postJson("/api/v1/invoices/{$this->invoiceDivAToFinA1->id}/mark-sent");

        $response->assertForbidden();
    }

    // ========== TESTS MARK_PAID ==========

    #[Test]
    public function mark_paid_allows_division_admin_to_mark_their_invoice_as_paid(): void
    {
        $divisionAdmin = $this->createUserWithRole(RoleDefaults::DIVISION_ADMIN, $this->financerA1);
        $this->setAccessibleContext($divisionAdmin);

        $data = ['amount_paid' => 12000];

        $response = $this->actingAs($divisionAdmin)->postJson("/api/v1/invoices/{$this->invoiceDivAToFinA1->id}/mark-paid", $data);

        $response->assertOk();
    }

    #[Test]
    public function mark_paid_forbids_financer_admin(): void
    {
        $financerAdmin = $this->createUserWithRole(RoleDefaults::FINANCER_ADMIN, $this->financerA1);
        $this->setAccessibleContext($financerAdmin);

        $data = ['amount_paid' => 12000];

        $response = $this->actingAs($financerAdmin)->postJson("/api/v1/invoices/{$this->invoiceDivAToFinA1->id}/mark-paid", $data);

        $response->assertForbidden();
    }

    // ========== TESTS BULK_UPDATE_STATUS ==========

    #[Test]
    public function bulk_update_status_allows_division_admin_for_their_invoices(): void
    {
        $divisionAdmin = $this->createUserWithRole(RoleDefaults::DIVISION_ADMIN, $this->financerA1);
        $this->setAccessibleContext($divisionAdmin);

        $data = [
            'invoice_ids' => [$this->invoiceDivAToFinA1->id],
            'status' => 'confirmed',
        ];

        $response = $this->actingAs($divisionAdmin)->postJson('/api/v1/invoices/bulk/update-status', $data);

        $response->assertOk();
    }

    #[Test]
    public function bulk_update_status_forbids_when_one_invoice_is_not_authorized(): void
    {
        $divisionAdmin = $this->createUserWithRole(RoleDefaults::DIVISION_ADMIN, $this->financerA1);
        $this->setAccessibleContext($divisionAdmin);

        $data = [
            'invoice_ids' => [$this->invoiceDivAToFinA1->id, $this->invoiceDivBToFinB1->id],
            'status' => 'confirmed',
        ];

        $response = $this->actingAs($divisionAdmin)->postJson('/api/v1/invoices/bulk/update-status', $data);

        $response->assertForbidden();
    }

    #[Test]
    public function bulk_update_status_forbids_financer_admin(): void
    {
        $financerAdmin = $this->createUserWithRole(RoleDefaults::FINANCER_ADMIN, $this->financerA1);
        $this->setAccessibleContext($financerAdmin);

        $data = [
            'invoice_ids' => [$this->invoiceDivAToFinA1->id],
            'status' => 'confirmed',
        ];

        $response = $this->actingAs($financerAdmin)->postJson('/api/v1/invoices/bulk/update-status', $data);

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
        if ($user->hasAnyRole([RoleDefaults::GOD, RoleDefaults::HEXEKO_ADMIN])) {
            $accessibleDivisions = Division::pluck('id')->toArray();
            $accessibleFinancers = Financer::pluck('id')->toArray();

            authorizationContext()->hydrate(
                AuthorizationMode::GLOBAL,
                $accessibleFinancers,
                $accessibleDivisions,
                [],
                $accessibleFinancers[0] ?? null
            );
        } else {
            $accessibleDivisions = $user->financers->pluck('division_id')->unique()->toArray();
            $accessibleFinancers = $user->financers->pluck('id')->toArray();

            authorizationContext()->hydrate(
                AuthorizationMode::SELF,
                $accessibleFinancers,
                $accessibleDivisions,
                [],
                $accessibleFinancers[0] ?? null
            );
        }

        // Set the first financer as active financer in Context for global scopes
        $financerIds = $user->financers->pluck('id')->toArray();
        if (count($financerIds) > 0) {
            Context::add('financer_id', $financerIds[0]);
        }
    }
}
