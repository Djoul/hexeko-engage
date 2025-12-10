<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\V1\Invoicing\InvoiceExportController;

use App\Enums\IDP\RoleDefaults;
use App\Enums\InvoiceType;
use App\Enums\Security\AuthorizationMode;
use App\Models\Division;
use App\Models\Financer;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Attributes\FlushTables;
use Tests\Helpers\Facades\ModelFactory;
use Tests\ProtectedRouteTestCase;

#[FlushTables(tables: ['invoices'], scope: 'class')]
#[Group('invoicing')]
final class InvoiceExportControllerAuthTest extends ProtectedRouteTestCase
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

        // Fake storage for PDF exports
        Storage::fake('s3-local');
        Storage::fake('local');

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

    // ========== TESTS EXPORT EXCEL ==========

    #[Test]
    public function excel_export_allows_god_user(): void
    {
        $godUser = $this->createUserWithRole(RoleDefaults::GOD);
        $this->setAccessibleContext($godUser);

        $response = $this->actingAs($godUser)->getJson('/api/v1/invoices/export/excel');

        $response->assertOk();
    }

    #[Test]
    public function excel_export_allows_division_admin(): void
    {
        $divisionAdmin = $this->createUserWithRole(RoleDefaults::DIVISION_ADMIN, $this->financerA1);
        $this->setAccessibleContext($divisionAdmin);

        $response = $this->actingAs($divisionAdmin)->getJson('/api/v1/invoices/export/excel');

        $response->assertOk();
    }

    #[Test]
    public function excel_export_allows_financer_admin(): void
    {
        $financerAdmin = $this->createUserWithRole(RoleDefaults::FINANCER_ADMIN, $this->financerA1);
        $this->setAccessibleContext($financerAdmin);

        $response = $this->actingAs($financerAdmin)->getJson('/api/v1/invoices/export/excel');

        $response->assertOk();
    }

    // ========== TESTS DOWNLOAD PDF ==========

    #[Test]
    public function pdf_download_allows_division_admin_for_their_invoice(): void
    {
        $divisionAdmin = $this->createUserWithRole(RoleDefaults::DIVISION_ADMIN, $this->financerA1);
        $this->setAccessibleContext($divisionAdmin);

        $response = $this->actingAs($divisionAdmin)->getJson("/api/v1/invoices/{$this->invoiceHexekoToDivA->id}/pdf");

        $response->assertOk();
    }

    #[Test]
    public function pdf_download_forbids_division_admin_for_other_division_invoice(): void
    {
        $divisionAdmin = $this->createUserWithRole(RoleDefaults::DIVISION_ADMIN, $this->financerA1);
        $this->setAccessibleContext($divisionAdmin);

        $response = $this->actingAs($divisionAdmin)->getJson("/api/v1/invoices/{$this->invoiceDivBToFinB1->id}/pdf");

        $response->assertForbidden();
    }

    #[Test]
    public function pdf_download_allows_financer_admin_for_their_invoice(): void
    {
        $financerAdmin = $this->createUserWithRole(RoleDefaults::FINANCER_ADMIN, $this->financerA1);
        $this->setAccessibleContext($financerAdmin);

        $response = $this->actingAs($financerAdmin)->getJson("/api/v1/invoices/{$this->invoiceDivAToFinA1->id}/pdf");

        $response->assertOk();
    }

    #[Test]
    public function pdf_download_forbids_financer_admin_for_hexeko_to_division_invoice(): void
    {
        $financerAdmin = $this->createUserWithRole(RoleDefaults::FINANCER_ADMIN, $this->financerA1);
        $this->setAccessibleContext($financerAdmin);

        $response = $this->actingAs($financerAdmin)->getJson("/api/v1/invoices/{$this->invoiceHexekoToDivA->id}/pdf");

        $response->assertForbidden();
    }

    #[Test]
    public function pdf_download_allows_god_user(): void
    {
        $godUser = $this->createUserWithRole(RoleDefaults::GOD);
        $this->setAccessibleContext($godUser);

        $response = $this->actingAs($godUser)->getJson("/api/v1/invoices/{$this->invoiceHexekoToDivA->id}/pdf");

        $response->assertOk();
    }

    // ========== TESTS SEND EMAIL ==========

    #[Test]
    public function send_email_allows_division_admin_for_their_issued_invoice(): void
    {
        Queue::fake();

        $divisionAdmin = $this->createUserWithRole(RoleDefaults::DIVISION_ADMIN, $this->financerA1);
        $this->setAccessibleContext($divisionAdmin);

        $data = ['email' => 'test@example.com'];

        $response = $this->actingAs($divisionAdmin)->postJson("/api/v1/invoices/{$this->invoiceDivAToFinA1->id}/send-email", $data);

        $response->assertOk();
    }

    #[Test]
    public function send_email_forbids_division_admin_for_hexeko_to_division_invoice(): void
    {
        $divisionAdmin = $this->createUserWithRole(RoleDefaults::DIVISION_ADMIN, $this->financerA1);
        $this->setAccessibleContext($divisionAdmin);

        $data = ['email' => 'test@example.com'];

        $response = $this->actingAs($divisionAdmin)->postJson("/api/v1/invoices/{$this->invoiceHexekoToDivA->id}/send-email", $data);

        $response->assertForbidden();
    }

    #[Test]
    public function send_email_forbids_financer_admin(): void
    {
        $financerAdmin = $this->createUserWithRole(RoleDefaults::FINANCER_ADMIN, $this->financerA1);
        $this->setAccessibleContext($financerAdmin);

        $data = ['email' => 'test@example.com'];

        $response = $this->actingAs($financerAdmin)->postJson("/api/v1/invoices/{$this->invoiceDivAToFinA1->id}/send-email", $data);

        $response->assertForbidden();
    }

    #[Test]
    public function send_email_allows_god_user(): void
    {
        Queue::fake();

        $godUser = $this->createUserWithRole(RoleDefaults::GOD);
        $this->setAccessibleContext($godUser);

        $data = ['email' => 'test@example.com'];

        $response = $this->actingAs($godUser)->postJson("/api/v1/invoices/{$this->invoiceHexekoToDivA->id}/send-email", $data);

        $response->assertOk();
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
