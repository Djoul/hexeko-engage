<?php

declare(strict_types=1);

namespace Tests\E2E\Invoicing;

use App\Enums\IDP\RoleDefaults;
use App\Enums\InvoiceType;
use App\Models\Division;
use App\Models\Financer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\User;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Facades\ModelFactory;
use Tests\ProtectedRouteTestCase;

/**
 * Tests E2E complets du système d'autorisation des factures.
 *
 * Ces tests valident des workflows réalistes de bout en bout :
 * - Cycle de vie complet d'une facture (création → consultation → modification → confirmation → export)
 * - Scénarios multi-rôles avec isolation appropriée
 * - Gestion des items de facture dans le contexte d'autorisation parent
 */
#[Group('invoicing')]
final class InvoiceAuthorizationE2ETest extends ProtectedRouteTestCase
{
    public $team;

    private Division $divisionA;

    private Division $divisionB;

    private Financer $financerA1;

    private Financer $financerB1;

    private User $godUser;

    private User $divisionAdminA;

    private User $divisionAdminB;

    private User $financerAdminA1;

    protected function setUp(): void
    {
        parent::setUp();

        // Fake storage for PDF/Excel exports (avoid Minio connection in tests)
        Storage::fake('s3');
        Storage::fake('s3-local');

        // Créer les rôles
        $this->team = ModelFactory::createTeam();
        setPermissionsTeamId($this->team->id);

        ModelFactory::createRole(['name' => RoleDefaults::GOD, 'team_id' => $this->team->id]);
        ModelFactory::createRole(['name' => RoleDefaults::DIVISION_ADMIN, 'team_id' => $this->team->id]);
        ModelFactory::createRole(['name' => RoleDefaults::FINANCER_ADMIN, 'team_id' => $this->team->id]);

        // Créer la structure organisationnelle
        $this->divisionA = ModelFactory::createDivision(['name' => 'Division A']);
        $this->divisionB = ModelFactory::createDivision(['name' => 'Division B']);

        $this->financerA1 = ModelFactory::createFinancer(['division_id' => $this->divisionA->id, 'name' => 'Financer A1']);
        $this->financerB1 = ModelFactory::createFinancer(['division_id' => $this->divisionB->id, 'name' => 'Financer B1']);

        // Créer les utilisateurs
        $this->godUser = $this->createUserWithRole(RoleDefaults::GOD);
        $this->divisionAdminA = $this->createUserWithRole(RoleDefaults::DIVISION_ADMIN, $this->financerA1);
        $this->divisionAdminB = $this->createUserWithRole(RoleDefaults::DIVISION_ADMIN, $this->financerB1);
        $this->financerAdminA1 = $this->createUserWithRole(RoleDefaults::FINANCER_ADMIN, $this->financerA1);
    }

    #[Test]
    public function cross_division_isolation_prevents_unauthorized_access(): void
    {
        $this->setAccessibleContext($this->divisionAdminA);

        // Division A crée une facture
        $invoiceA = Invoice::factory()->create([
            'invoice_type' => InvoiceType::DIVISION_TO_FINANCER,
            'issuer_type' => 'App\\Models\\Division',
            'issuer_id' => $this->divisionA->id,
            'recipient_type' => 'App\\Models\\Financer',
            'recipient_id' => $this->financerA1->id,
            'status' => 'confirmed',
        ]);

        $this->setAccessibleContext($this->divisionAdminB);

        // Division B ne peut PAS voir la facture de Division A
        $listResponse = $this->actingAs($this->divisionAdminB)
            ->getJson('/api/v1/invoices');

        $listResponse->assertOk();
        $invoiceIds = collect($listResponse->json('data'))->pluck('id')->toArray();
        $this->assertNotContains($invoiceA->id, $invoiceIds);

        // Division B ne peut PAS consulter la facture de Division A
        $viewResponse = $this->actingAs($this->divisionAdminB)
            ->getJson("/api/v1/invoices/{$invoiceA->id}");

        $viewResponse->assertForbidden();

        // Division B ne peut PAS modifier la facture de Division A
        $updateResponse = $this->actingAs($this->divisionAdminB)
            ->putJson("/api/v1/invoices/{$invoiceA->id}", [
                'due_date' => '2025-12-31',
            ]);

        $updateResponse->assertForbidden();

        // Division B ne peut PAS télécharger le PDF de Division A
        $pdfResponse = $this->actingAs($this->divisionAdminB)
            ->get("/api/v1/invoices/{$invoiceA->id}/pdf");

        $pdfResponse->assertForbidden();
    }

    #[Test]
    public function financer_admin_can_view_but_not_modify_their_invoices(): void
    {
        $this->setAccessibleContext($this->divisionAdminA);

        // Division A crée une facture pour Financer A1
        $invoice = Invoice::factory()->create([
            'invoice_type' => InvoiceType::DIVISION_TO_FINANCER,
            'issuer_type' => 'App\\Models\\Division',
            'issuer_id' => $this->divisionA->id,
            'recipient_type' => 'App\\Models\\Financer',
            'recipient_id' => $this->financerA1->id,
            'status' => 'confirmed',
        ]);

        $item = InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'quantity' => 1,
            'unit_price_htva' => 5000,
        ]);

        $this->setAccessibleContext($this->financerAdminA1);

        // Financer Admin PEUT consulter la facture
        $viewResponse = $this->actingAs($this->financerAdminA1)
            ->getJson("/api/v1/invoices/{$invoice->id}");

        $viewResponse->assertOk()
            ->assertJsonPath('data.id', $invoice->id);

        // Financer Admin PEUT voir les items
        $itemsResponse = $this->actingAs($this->financerAdminA1)
            ->getJson("/api/v1/invoices/{$invoice->id}/items");

        $itemsResponse->assertOk()
            ->assertJsonCount(1, 'data');

        // Financer Admin PEUT télécharger le PDF
        $pdfResponse = $this->actingAs($this->financerAdminA1)
            ->get("/api/v1/invoices/{$invoice->id}/pdf");

        $pdfResponse->assertOk();

        // Financer Admin NE PEUT PAS modifier la facture
        $updateResponse = $this->actingAs($this->financerAdminA1)
            ->putJson("/api/v1/invoices/{$invoice->id}", [
                'due_date' => '2025-12-31',
            ]);

        $updateResponse->assertForbidden();

        // Financer Admin NE PEUT PAS ajouter d'items
        $addItemResponse = $this->actingAs($this->financerAdminA1)
            ->postJson("/api/v1/invoices/{$invoice->id}/items", [
                'item_type' => 'other',
                'label' => ['en' => 'New item', 'fr' => 'Nouvel item'],
                'description' => ['en' => 'New item', 'fr' => 'Nouvel item'],
                'quantity' => 1,
                'unit_price_htva' => 1000,
            ]);

        $addItemResponse->assertForbidden();

        // Financer Admin NE PEUT PAS modifier d'items
        $updateItemResponse = $this->actingAs($this->financerAdminA1)
            ->putJson("/api/v1/invoices/{$invoice->id}/items/{$item->id}", [
                'quantity' => 2,
            ]);

        $updateItemResponse->assertForbidden();

        // Financer Admin NE PEUT PAS exporter en Excel
        $excelResponse = $this->actingAs($this->financerAdminA1)
            ->get('/api/v1/invoices/export/excel');

        $excelResponse->assertForbidden();
    }

    #[Test]
    public function god_user_has_full_access_to_all_invoices_and_operations(): void
    {
        $this->setAccessibleContext($this->godUser);

        // Créer des factures pour différentes divisions
        $invoiceA = Invoice::factory()->create([
            'invoice_type' => InvoiceType::DIVISION_TO_FINANCER,
            'issuer_type' => 'App\\Models\\Division',
            'issuer_id' => $this->divisionA->id,
            'recipient_type' => 'App\\Models\\Financer',
            'recipient_id' => $this->financerA1->id,
            'status' => 'confirmed',
        ]);

        $invoiceB = Invoice::factory()->create([
            'invoice_type' => InvoiceType::DIVISION_TO_FINANCER,
            'issuer_type' => 'App\\Models\\Division',
            'issuer_id' => $this->divisionB->id,
            'recipient_type' => 'App\\Models\\Financer',
            'recipient_id' => $this->financerB1->id,
            'status' => 'draft',
        ]);

        // GOD peut lister TOUTES les factures
        $listResponse = $this->actingAs($this->godUser)
            ->getJson('/api/v1/invoices');

        $listResponse->assertOk();
        $invoiceIds = collect($listResponse->json('data'))->pluck('id')->toArray();
        $this->assertContains($invoiceA->id, $invoiceIds);
        $this->assertContains($invoiceB->id, $invoiceIds);

        // GOD peut consulter n'importe quelle facture
        $viewResponseA = $this->actingAs($this->godUser)
            ->getJson("/api/v1/invoices/{$invoiceA->id}");

        $viewResponseA->assertOk();

        $viewResponseB = $this->actingAs($this->godUser)
            ->getJson("/api/v1/invoices/{$invoiceB->id}");

        $viewResponseB->assertOk();

        // GOD peut modifier n'importe quelle facture
        $updateResponse = $this->actingAs($this->godUser)
            ->putJson("/api/v1/invoices/{$invoiceB->id}", [
                'status' => 'confirmed',
            ]);

        $updateResponse->assertOk();

        // GOD peut télécharger n'importe quel PDF
        $pdfResponse = $this->actingAs($this->godUser)
            ->get("/api/v1/invoices/{$invoiceA->id}/pdf");

        $pdfResponse->assertOk();

        // GOD peut exporter en Excel
        $excelResponse = $this->actingAs($this->godUser)
            ->get('/api/v1/invoices/export/excel');

        $excelResponse->assertOk();
    }

    #[Test]
    public function bulk_operations_respect_individual_invoice_authorizations(): void
    {
        $this->setAccessibleContext($this->divisionAdminA);

        // Division A crée deux factures
        $invoiceA1 = Invoice::factory()->create([
            'invoice_type' => InvoiceType::DIVISION_TO_FINANCER,
            'issuer_type' => 'App\\Models\\Division',
            'issuer_id' => $this->divisionA->id,
            'recipient_type' => 'App\\Models\\Financer',
            'recipient_id' => $this->financerA1->id,
            'status' => 'draft',
        ]);

        $invoiceA2 = Invoice::factory()->create([
            'invoice_type' => InvoiceType::DIVISION_TO_FINANCER,
            'issuer_type' => 'App\\Models\\Division',
            'issuer_id' => $this->divisionA->id,
            'recipient_type' => 'App\\Models\\Financer',
            'recipient_id' => $this->financerA1->id,
            'status' => 'draft',
        ]);

        $this->setAccessibleContext($this->divisionAdminB);

        // Division B crée une facture
        $invoiceB1 = Invoice::factory()->create([
            'invoice_type' => InvoiceType::DIVISION_TO_FINANCER,
            'issuer_type' => 'App\\Models\\Division',
            'issuer_id' => $this->divisionB->id,
            'recipient_type' => 'App\\Models\\Financer',
            'recipient_id' => $this->financerB1->id,
            'status' => 'draft',
        ]);

        $this->setAccessibleContext($this->divisionAdminA);

        // Division A peut mettre à jour ses propres factures en bulk
        $validBulkResponse = $this->actingAs($this->divisionAdminA)
            ->postJson('/api/v1/invoices/bulk/update-status', [
                'invoice_ids' => [$invoiceA1->id, $invoiceA2->id],
                'status' => 'confirmed',
            ]);

        $validBulkResponse->assertOk();

        // Division A NE PEUT PAS inclure des factures d'autres divisions
        $invalidBulkResponse = $this->actingAs($this->divisionAdminA)
            ->postJson('/api/v1/invoices/bulk/update-status', [
                'invoice_ids' => [$invoiceA1->id, $invoiceB1->id], // invoiceB1 non autorisée
                'status' => 'sent',
            ]);

        $invalidBulkResponse->assertForbidden();
    }

    #[Test]
    public function export_filters_respect_user_scope_in_realistic_scenario(): void
    {
        // Division A crée 3 factures : 2 confirmed, 1 draft
        $this->setAccessibleContext($this->divisionAdminA);

        Invoice::factory()->create([
            'invoice_type' => InvoiceType::DIVISION_TO_FINANCER,
            'issuer_type' => 'App\\Models\\Division',
            'issuer_id' => $this->divisionA->id,
            'recipient_type' => 'App\\Models\\Financer',
            'recipient_id' => $this->financerA1->id,
            'status' => 'confirmed',
            'billing_period_start' => '2025-01-01',
        ]);

        Invoice::factory()->create([
            'invoice_type' => InvoiceType::DIVISION_TO_FINANCER,
            'issuer_type' => 'App\\Models\\Division',
            'issuer_id' => $this->divisionA->id,
            'recipient_type' => 'App\\Models\\Financer',
            'recipient_id' => $this->financerA1->id,
            'status' => 'confirmed',
            'billing_period_start' => '2025-02-01',
        ]);

        Invoice::factory()->create([
            'invoice_type' => InvoiceType::DIVISION_TO_FINANCER,
            'issuer_type' => 'App\\Models\\Division',
            'issuer_id' => $this->divisionA->id,
            'recipient_type' => 'App\\Models\\Financer',
            'recipient_id' => $this->financerA1->id,
            'status' => 'draft',
            'billing_period_start' => '2025-03-01',
        ]);

        // Division B crée 2 factures confirmed
        $this->setAccessibleContext($this->divisionAdminB);

        Invoice::factory()->create([
            'invoice_type' => InvoiceType::DIVISION_TO_FINANCER,
            'issuer_type' => 'App\\Models\\Division',
            'issuer_id' => $this->divisionB->id,
            'recipient_type' => 'App\\Models\\Financer',
            'recipient_id' => $this->financerB1->id,
            'status' => 'confirmed',
            'billing_period_start' => '2025-01-01',
        ]);

        Invoice::factory()->create([
            'invoice_type' => InvoiceType::DIVISION_TO_FINANCER,
            'issuer_type' => 'App\\Models\\Division',
            'issuer_id' => $this->divisionB->id,
            'recipient_type' => 'App\\Models\\Financer',
            'recipient_id' => $this->financerB1->id,
            'status' => 'confirmed',
            'billing_period_start' => '2025-02-01',
        ]);

        $this->setAccessibleContext($this->divisionAdminA);

        // Division A exporte avec filtre status=confirmed
        // Devrait voir 2 factures (ses confirmed), pas les 2 de Division B
        $exportResponse = $this->actingAs($this->divisionAdminA)
            ->get('/api/v1/invoices/export/excel?status=confirmed');

        $exportResponse->assertOk();

        // Vérifier via l'API de liste avec filtre
        $listResponse = $this->actingAs($this->divisionAdminA)
            ->getJson('/api/v1/invoices?status=confirmed');

        $listResponse->assertOk();
        $this->assertCount(2, $listResponse->json('data'));
    }

    // ========== HELPERS ==========

    private function createUserWithRole(string $role, ?Financer $financer = null): User
    {

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
            Context::add('accessible_divisions', Division::pluck('id')->toArray());
            Context::add('accessible_financers', Financer::pluck('id')->toArray());
        } else {
            $accessibleDivisions = $user->financers->pluck('division_id')->unique()->toArray();
            $accessibleFinancers = $user->financers->pluck('id')->toArray();

            Context::add('accessible_divisions', $accessibleDivisions);
            Context::add('accessible_financers', $accessibleFinancers);
        }
    }
}
