<?php

declare(strict_types=1);

namespace Tests\Unit\Exports;

use App\Enums\IDP\RoleDefaults;
use App\Enums\InvoiceType;
use App\Enums\Security\AuthorizationMode;
use App\Exports\InvoicesExport;
use App\Models\Division;
use App\Models\Financer;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Context;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Attributes\FlushTables;
use Tests\Helpers\Facades\ModelFactory;
use Tests\TestCase;

#[FlushTables(tables: ['invoices'], scope: 'test')]
#[Group('invoicing')]
class InvoicesExportTest extends TestCase
{
    public $team;

    use DatabaseTransactions;

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
        ModelFactory::createRole(['name' => RoleDefaults::DIVISION_ADMIN, 'team_id' => $this->team->id]);
        ModelFactory::createRole(['name' => RoleDefaults::FINANCER_ADMIN, 'team_id' => $this->team->id]);

        // Créer les données de test
        $this->divisionA = ModelFactory::createDivision(['name' => 'Division A']);
        $this->divisionB = ModelFactory::createDivision(['name' => 'Division B']);

        $this->financerA1 = ModelFactory::createFinancer(['division_id' => $this->divisionA->id]);
        $this->financerB1 = ModelFactory::createFinancer(['division_id' => $this->divisionB->id]);

        $this->invoiceHexekoToDivA = Invoice::factory()->create([
            'invoice_number' => 'HEXEKO-001',
            'invoice_type' => InvoiceType::HEXEKO_TO_DIVISION,
            'recipient_type' => 'App\\Models\\Division',
            'recipient_id' => $this->divisionA->id,
            'status' => 'confirmed',
        ]);

        $this->invoiceDivAToFinA1 = Invoice::factory()->create([
            'invoice_number' => 'DIVA-001',
            'invoice_type' => InvoiceType::DIVISION_TO_FINANCER,
            'issuer_type' => 'App\\Models\\Division',
            'issuer_id' => $this->divisionA->id,
            'recipient_type' => 'App\\Models\\Financer',
            'recipient_id' => $this->financerA1->id,
            'status' => 'confirmed',
        ]);

        $this->invoiceDivBToFinB1 = Invoice::factory()->create([
            'invoice_number' => 'DIVB-001',
            'invoice_type' => InvoiceType::DIVISION_TO_FINANCER,
            'issuer_type' => 'App\\Models\\Division',
            'issuer_id' => $this->divisionB->id,
            'recipient_type' => 'App\\Models\\Financer',
            'recipient_id' => $this->financerB1->id,
            'status' => 'confirmed',
        ]);
    }

    #[Test]
    public function it_filters_by_recipient_type_and_id(): void
    {
        // Create test recipients
        $division1 = Division::factory()->create();
        $division2 = Division::factory()->create();
        $financer = Financer::factory()->create();

        // Create test invoices with different recipients
        Invoice::factory()->create([
            'recipient_type' => 'App\\Models\\Division',
            'recipient_id' => $division1->id,
            'invoice_number' => 'INV-DIV-001',
        ]);

        Invoice::factory()->create([
            'recipient_type' => 'App\\Models\\Financer',
            'recipient_id' => $financer->id,
            'invoice_number' => 'INV-FIN-001',
        ]);

        // Another division invoice with different ID
        Invoice::factory()->create([
            'recipient_type' => 'App\\Models\\Division',
            'recipient_id' => $division2->id,
            'invoice_number' => 'INV-DIV-002',
        ]);

        // Export with recipient_type + recipient_id filters
        $export = new InvoicesExport([
            'recipient_type' => 'App\\Models\\Division',
            'recipient_id' => $division1->id,
        ]);

        $results = $export->query()->get();

        // Should only return the specific division invoice
        $this->assertCount(1, $results);
        $this->assertEquals('INV-DIV-001', $results->first()->invoice_number);
    }

    #[Test]
    public function it_filters_by_status(): void
    {
        Invoice::factory()->create(['status' => 'draft']);
        Invoice::factory()->create(['status' => 'confirmed']);
        Invoice::factory()->create(['status' => 'confirmed']);

        $export = new InvoicesExport(['status' => 'confirmed']);
        $results = $export->query()->get();

        // 3 invoices 'confirmed' dans setUp + 2 créées ici = 5 total
        $this->assertCount(5, $results);
        $this->assertTrue($results->every(fn ($invoice): bool => $invoice->status === 'confirmed'));

        // Vérifier qu'il y a bien au moins une invoice 'draft' en DB
        $this->assertGreaterThanOrEqual(1, Invoice::where('status', 'draft')->count());
    }

    #[Test]
    public function it_filters_by_issuer_type_and_id(): void
    {
        // Create test issuers
        $division = Division::factory()->create();
        $financer = Financer::factory()->create();

        Invoice::factory()->create([
            'issuer_type' => 'App\\Models\\Division',
            'issuer_id' => $division->id,
            'invoice_number' => 'INV-ISSUED-001',
        ]);

        Invoice::factory()->create([
            'issuer_type' => 'App\\Models\\Financer',
            'issuer_id' => $financer->id,
            'invoice_number' => 'INV-ISSUED-002',
        ]);

        $export = new InvoicesExport([
            'issuer_type' => 'App\\Models\\Division',
            'issuer_id' => $division->id,
        ]);

        $results = $export->query()->get();

        $this->assertCount(1, $results);
        $this->assertEquals('INV-ISSUED-001', $results->first()->invoice_number);
    }

    #[Test]
    public function it_filters_by_date_range(): void
    {
        Invoice::factory()->create([
            'billing_period_start' => '2025-01-01',
            'billing_period_end' => '2025-01-31',
        ]);

        Invoice::factory()->create([
            'billing_period_start' => '2025-02-01',
            'billing_period_end' => '2025-02-28',
        ]);

        Invoice::factory()->create([
            'billing_period_start' => '2025-03-01',
            'billing_period_end' => '2025-03-31',
        ]);

        $export = new InvoicesExport([
            'date_start' => '2025-02-01',
            'date_end' => '2025-02-28',
        ]);

        $results = $export->query()->get();

        $this->assertCount(1, $results);
        $this->assertEquals('2025-02-01', $results->first()->billing_period_start->format('Y-m-d'));
    }

    #[Test]
    public function it_combines_multiple_filters(): void
    {
        // Create test entities
        $division = Division::factory()->create();
        Financer::factory()->create();

        // Matching invoice
        Invoice::factory()->create([
            'recipient_type' => 'App\\Models\\Division',
            'recipient_id' => $division->id,
            'status' => 'confirmed',
            'billing_period_start' => '2025-02-01',
            'invoice_number' => 'MATCH-001',
        ]);

        // Non-matching: wrong recipient_type
        Invoice::factory()->create([
            'recipient_type' => 'App\\Models\\Financer',
            'recipient_id' => $division->id, // Same ID but wrong type
            'status' => 'confirmed',
            'billing_period_start' => '2025-02-01',
        ]);

        // Non-matching: wrong status
        Invoice::factory()->create([
            'recipient_type' => 'App\\Models\\Division',
            'recipient_id' => $division->id,
            'status' => 'draft',
            'billing_period_start' => '2025-02-01',
        ]);

        // Non-matching: wrong date
        Invoice::factory()->create([
            'recipient_type' => 'App\\Models\\Division',
            'recipient_id' => $division->id,
            'status' => 'confirmed',
            'billing_period_start' => '2025-01-01',
        ]);

        $export = new InvoicesExport([
            'recipient_type' => 'App\\Models\\Division',
            'recipient_id' => $division->id,
            'status' => 'confirmed',
            'date_start' => '2025-02-01',
        ]);

        $results = $export->query()->get();

        $this->assertCount(1, $results);
        $this->assertEquals('MATCH-001', $results->first()->invoice_number);
    }

    #[Test]
    public function it_returns_correct_headings(): void
    {
        $export = new InvoicesExport;
        $headings = $export->headings();

        $expectedHeadings = [
            'Invoice Number',
            'Recipient',
            'Status',
            'Subtotal (HTVA)',
            'VAT Amount',
            'Total (TTC)',
            'Billing Period Start',
            'Billing Period End',
            'Due Date',
        ];

        $this->assertEquals($expectedHeadings, $headings);
    }

    #[Test]
    public function it_maps_invoice_data_correctly(): void
    {
        $invoice = Invoice::factory()->create([
            'invoice_number' => 'INV-2025-001',
            'status' => 'confirmed',
            'subtotal_htva' => 100000, // €1000.00
            'vat_amount' => 21000, // €210.00
            'total_ttc' => 121000, // €1210.00
            'billing_period_start' => '2025-01-01',
            'billing_period_end' => '2025-01-31',
            'due_date' => '2025-02-15',
        ]);

        $export = new InvoicesExport;
        $mapped = $export->map($invoice);

        $this->assertEquals('INV-2025-001', $mapped[0]);
        $this->assertEquals('confirmed', $mapped[2]);
        $this->assertEquals('1000.00', $mapped[3]); // Subtotal
        $this->assertEquals('210.00', $mapped[4]); // VAT
        $this->assertEquals('1210.00', $mapped[5]); // Total
        $this->assertEquals('2025-01-01', $mapped[6]);
        $this->assertEquals('2025-01-31', $mapped[7]);
        $this->assertEquals('2025-02-15', $mapped[8]);
    }

    #[Test]
    public function export_for_god_user_includes_all_invoices(): void
    {
        $godUser = $this->createUserWithRole(RoleDefaults::GOD);
        $this->setAccessibleContext($godUser);

        $export = new InvoicesExport([], $godUser);
        $invoices = $export->query()->get();

        // GOD devrait voir les 3 factures
        $this->assertCount(3, $invoices);
        $this->assertTrue($invoices->contains('id', $this->invoiceHexekoToDivA->id));
        $this->assertTrue($invoices->contains('id', $this->invoiceDivAToFinA1->id));
        $this->assertTrue($invoices->contains('id', $this->invoiceDivBToFinB1->id));
    }

    #[Test]
    public function export_for_division_admin_includes_only_their_invoices(): void
    {
        $divisionAdmin = $this->createUserWithRole(RoleDefaults::DIVISION_ADMIN, $this->financerA1);
        $this->setAccessibleContext($divisionAdmin);

        $export = new InvoicesExport([], $divisionAdmin);
        $invoices = $export->query()->get();

        // Division A admin devrait voir HEXEKO-001 (recipient) et DIVA-001 (issuer)
        $this->assertCount(2, $invoices);
        $this->assertTrue($invoices->contains('id', $this->invoiceHexekoToDivA->id));
        $this->assertTrue($invoices->contains('id', $this->invoiceDivAToFinA1->id));

        // Mais PAS DIVB-001 (autre division)
        $this->assertFalse($invoices->contains('id', $this->invoiceDivBToFinB1->id));
    }

    #[Test]
    public function export_for_financer_admin_includes_only_their_invoices(): void
    {
        $financerAdmin = $this->createUserWithRole(RoleDefaults::FINANCER_ADMIN, $this->financerA1);
        $this->setAccessibleContext($financerAdmin);

        $export = new InvoicesExport([], $financerAdmin);
        $invoices = $export->query()->get();

        // Financer admin devrait voir DIVA-001 (recipient)
        $this->assertCount(1, $invoices);
        $this->assertTrue($invoices->contains('id', $this->invoiceDivAToFinA1->id));

        // Mais PAS HEXEKO-001 ni DIVB-001
        $this->assertFalse($invoices->contains('id', $this->invoiceHexekoToDivA->id));
        $this->assertFalse($invoices->contains('id', $this->invoiceDivBToFinB1->id));
    }

    #[Test]
    public function export_respects_status_filter_with_user_scope(): void
    {
        // Créer une facture DRAFT et une CONFIRMED pour DivisionA
        $draftInvoice = Invoice::factory()->create([
            'invoice_number' => 'DRAFT-001',
            'invoice_type' => InvoiceType::DIVISION_TO_FINANCER,
            'issuer_type' => 'App\\Models\\Division',
            'issuer_id' => $this->divisionA->id,
            'recipient_type' => 'App\\Models\\Financer',
            'recipient_id' => $this->financerA1->id,
            'status' => 'draft',
        ]);

        // DIVA-001 déjà confirmé dans setUp

        $divisionAdmin = $this->createUserWithRole(RoleDefaults::DIVISION_ADMIN, $this->financerA1);
        $this->setAccessibleContext($divisionAdmin);

        $export = new InvoicesExport(['status' => 'draft'], $divisionAdmin);
        $invoices = $export->query()->get();

        // Devrait voir DRAFT-001 seulement (dans ses factures accessibles)
        $this->assertCount(1, $invoices);
        $this->assertTrue($invoices->contains('id', $draftInvoice->id));
        $this->assertFalse($invoices->contains('id', $this->invoiceDivAToFinA1->id));
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
        $user = ModelFactory::createUser($userData);

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

            Context::add('accessible_divisions', $accessibleDivisions);
            Context::add('accessible_financers', $accessibleFinancers);

            // Set the first financer as active financer in Context for global scopes
            if (count($accessibleFinancers) > 0) {
                Context::add('financer_id', $accessibleFinancers[0]);
            }
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

            Context::add('accessible_divisions', $accessibleDivisions);
            Context::add('accessible_financers', $accessibleFinancers);

            // Set the first financer as active financer in Context for global scopes
            if (count($accessibleFinancers) > 0) {
                Context::add('financer_id', $accessibleFinancers[0]);
            }
        }
    }
}
