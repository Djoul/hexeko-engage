<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Enums\IDP\PermissionDefaults;
use App\Enums\InvoiceType;
use App\Enums\Security\AuthorizationMode;
use App\Models\Division;
use App\Models\Financer;
use App\Models\Invoice;
use App\Models\Permission;
use App\Models\Team;
use App\Models\User;
use App\Policies\InvoicePolicy;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Mockery;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Facades\ModelFactory;
use Tests\TestCase;

#[Group('invoicing')]
#[Group('policy')]
final class InvoicePolicyTest extends TestCase
{
    use DatabaseTransactions;

    private InvoicePolicy $policy;

    private Team $team;

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

        $this->policy = new InvoicePolicy;

        // Create team for permissions
        $this->team = ModelFactory::createTeam();
        setPermissionsTeamId($this->team->id);

        // Create all invoice permissions
        $this->createInvoicePermissions();

        // Create test data
        $this->divisionA = ModelFactory::createDivision();
        $this->divisionB = ModelFactory::createDivision();

        $this->financerA1 = ModelFactory::createFinancer(['division_id' => $this->divisionA->id]);
        $this->financerB1 = ModelFactory::createFinancer(['division_id' => $this->divisionB->id]);

        // Invoice HEXEKO → Division A
        $this->invoiceHexekoToDivA = Invoice::factory()->create([
            'invoice_type' => InvoiceType::HEXEKO_TO_DIVISION,
            'issuer_id' => null,
            'recipient_type' => Division::class,
            'recipient_id' => $this->divisionA->id,
        ]);

        // Invoice Division A → Financer A1
        $this->invoiceDivAToFinA1 = Invoice::factory()->create([
            'invoice_type' => InvoiceType::DIVISION_TO_FINANCER,
            'issuer_id' => $this->divisionA->id,
            'recipient_type' => Financer::class,
            'recipient_id' => $this->financerA1->id,
        ]);

        // Invoice Division B → Financer B1
        $this->invoiceDivBToFinB1 = Invoice::factory()->create([
            'invoice_type' => InvoiceType::DIVISION_TO_FINANCER,
            'issuer_id' => $this->divisionB->id,
            'recipient_type' => Financer::class,
            'recipient_id' => $this->financerB1->id,
        ]);

        $this->resetAuthorizationContext();
    }

    protected function tearDown(): void
    {
        $this->resetAuthorizationContext();
        Mockery::close();
        parent::tearDown();
    }

    private function createInvoicePermissions(): void
    {
        $permissions = [
            PermissionDefaults::READ_INVOICE_DIVISION,
            PermissionDefaults::CREATE_INVOICE_DIVISION,
            PermissionDefaults::UPDATE_INVOICE_DIVISION,
            PermissionDefaults::DELETE_INVOICE_DIVISION,
            PermissionDefaults::CONFIRM_INVOICE_DIVISION,
            PermissionDefaults::MARK_INVOICE_SENT_DIVISION,
            PermissionDefaults::MARK_INVOICE_PAID_DIVISION,
            PermissionDefaults::SEND_INVOICE_EMAIL_DIVISION,
            PermissionDefaults::DOWNLOAD_INVOICE_PDF_DIVISION,
            PermissionDefaults::EXPORT_INVOICE_DIVISION,
            PermissionDefaults::MANAGE_INVOICE_ITEMS_DIVISION,
            PermissionDefaults::EXPORT_USER_BILLING_DIVISION,
            PermissionDefaults::READ_INVOICE_FINANCER,
            PermissionDefaults::DOWNLOAD_INVOICE_PDF_FINANCER,
            PermissionDefaults::EXPORT_USER_BILLING_FINANCER,
            PermissionDefaults::CREATE_INVOICE_FINANCER,
            PermissionDefaults::UPDATE_INVOICE_FINANCER,
            PermissionDefaults::DELETE_INVOICE_FINANCER,
            PermissionDefaults::CONFIRM_INVOICE_FINANCER,
            PermissionDefaults::MARK_INVOICE_SENT_FINANCER,
            PermissionDefaults::MARK_INVOICE_PAID_FINANCER,
            PermissionDefaults::SEND_INVOICE_EMAIL_FINANCER,
            PermissionDefaults::MANAGE_INVOICE_ITEMS_FINANCER,
            PermissionDefaults::EXPORT_INVOICE_FINANCER,
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'api',
            ]);
        }
    }

    // ==================== Tests viewAny() ====================

    #[Test]
    public function user_without_permissions_cannot_view_any_invoices(): void
    {
        $user = $this->createDivisionUser($this->financerA1, []);

        $this->assertFalse($this->policy->viewAny($user));
    }

    #[Test]
    public function division_user_with_read_permission_can_view_any(): void
    {
        $user = $this->createDivisionUser($this->financerA1, [
            PermissionDefaults::READ_INVOICE_DIVISION,
        ]);

        $this->assertTrue($this->policy->viewAny($user));
    }

    #[Test]
    public function financer_user_with_read_permission_can_view_any(): void
    {
        $user = $this->createFinancerUser($this->financerA1, [
            PermissionDefaults::READ_INVOICE_FINANCER,
        ]);

        $this->assertTrue($this->policy->viewAny($user));
    }

    // ==================== Tests view() - HEXEKO_TO_DIVISION ====================

    #[Test]
    public function division_user_can_view_hexeko_invoice_to_their_division(): void
    {
        $user = $this->createDivisionUser($this->financerA1, [
            PermissionDefaults::READ_INVOICE_DIVISION,
        ]);

        $this->assertTrue($this->policy->view($user, $this->invoiceHexekoToDivA));
    }

    #[Test]
    public function division_user_cannot_view_hexeko_invoice_to_other_division(): void
    {
        $user = $this->createDivisionUser($this->financerA1, [
            PermissionDefaults::READ_INVOICE_DIVISION,
        ]);

        $invoiceHexekoToDivB = Invoice::factory()->create([
            'invoice_type' => InvoiceType::HEXEKO_TO_DIVISION,
            'recipient_type' => Division::class,
            'recipient_id' => $this->divisionB->id,
        ]);

        $this->assertFalse($this->policy->view($user, $invoiceHexekoToDivB));
    }

    #[Test]
    public function financer_user_cannot_view_hexeko_to_division_invoices(): void
    {
        $user = $this->createFinancerUser($this->financerA1, [
            PermissionDefaults::READ_INVOICE_FINANCER,
        ]);

        $this->assertFalse($this->policy->view($user, $this->invoiceHexekoToDivA));
    }

    #[Test]
    public function division_access_uses_recipient_division_relation(): void
    {
        $user = $this->createDivisionUser($this->financerA1, [
            PermissionDefaults::READ_INVOICE_DIVISION,
        ]);

        /** @var Invoice $invoice */
        $invoice = Mockery::mock(Invoice::class)->makePartial();
        $invoice->invoice_type = InvoiceType::HEXEKO_TO_DIVISION;
        $invoice->recipient_id = $this->divisionB->id; // would normally be denied
        $invoice->issuer_id = null;
        $invoice->shouldReceive('recipientDivision')->once()->andReturn($this->divisionA);
        $invoice->shouldReceive('issuerDivision')->andReturn(null);
        $invoice->recipient_type = Division::class;

        $this->assertTrue($this->policy->view($user, $invoice));
    }

    #[Test]
    public function division_access_uses_issuer_division_relation_for_division_to_financer(): void
    {
        $user = $this->createDivisionUser($this->financerA1, [
            PermissionDefaults::READ_INVOICE_DIVISION,
        ]);

        /** @var Invoice $invoice */
        $invoice = Mockery::mock(Invoice::class)->makePartial();
        $invoice->invoice_type = InvoiceType::DIVISION_TO_FINANCER;
        $invoice->issuer_id = $this->divisionB->id; // would normally be denied
        $invoice->recipient_id = $this->financerA1->id;
        $invoice->recipient_type = Financer::class;
        $invoice->shouldReceive('issuerDivision')->once()->andReturn($this->divisionA);
        $invoice->shouldReceive('recipientDivision')->andReturn(null);

        $this->assertTrue($this->policy->view($user, $invoice));
    }

    // ==================== Tests view() - DIVISION_TO_FINANCER ====================

    #[Test]
    public function division_user_can_view_invoice_issued_by_their_division(): void
    {
        $user = $this->createDivisionUser($this->financerA1, [
            PermissionDefaults::READ_INVOICE_DIVISION,
        ]);

        $this->assertTrue($this->policy->view($user, $this->invoiceDivAToFinA1));
    }

    #[Test]
    public function financer_user_can_view_invoice_for_their_financer(): void
    {
        $user = $this->createFinancerUser($this->financerA1, [
            PermissionDefaults::READ_INVOICE_FINANCER,
        ]);

        $this->assertTrue($this->policy->view($user, $this->invoiceDivAToFinA1));
    }

    #[Test]
    public function financer_user_cannot_view_invoice_for_other_financer(): void
    {
        $user = $this->createFinancerUser($this->financerA1, [
            PermissionDefaults::READ_INVOICE_FINANCER,
        ]);

        $this->assertFalse($this->policy->view($user, $this->invoiceDivBToFinB1));
    }

    // ==================== Tests create() ====================

    #[Test]
    public function division_user_with_financer_create_permission_can_create_division_to_financer_invoices(): void
    {
        $user = $this->createDivisionUser($this->financerA1, [
            PermissionDefaults::CREATE_INVOICE_FINANCER,
        ]);

        $this->assertTrue($this->policy->create($user, InvoiceType::DIVISION_TO_FINANCER));
    }

    #[Test]
    public function hexeko_user_with_division_create_permission_can_create_hexeko_invoices(): void
    {
        $user = $this->createHexekoUser([
            PermissionDefaults::CREATE_INVOICE_DIVISION,
        ]);

        $this->assertTrue($this->policy->create($user, InvoiceType::HEXEKO_TO_DIVISION));
    }

    #[Test]
    public function financer_user_cannot_create_invoices(): void
    {
        $user = $this->createFinancerUser($this->financerA1, [
            PermissionDefaults::READ_INVOICE_FINANCER,
        ]);

        $this->assertFalse($this->policy->create($user, InvoiceType::DIVISION_TO_FINANCER));
    }

    #[Test]
    public function user_without_create_permission_cannot_create_invoices(): void
    {
        $user = $this->createDivisionUser($this->financerA1, [
            PermissionDefaults::READ_INVOICE_DIVISION,
        ]);

        $this->assertFalse($this->policy->create($user, InvoiceType::DIVISION_TO_FINANCER));
    }

    // ==================== Tests update() ====================

    #[Test]
    public function division_user_can_update_their_division_invoices(): void
    {
        $user = $this->createDivisionUser($this->financerA1, [
            PermissionDefaults::UPDATE_INVOICE_FINANCER,
        ]);

        $this->assertTrue($this->policy->update($user, $this->invoiceDivAToFinA1));
    }

    #[Test]
    public function division_user_cannot_update_other_division_invoices(): void
    {
        $user = $this->createDivisionUser($this->financerA1, [
            PermissionDefaults::UPDATE_INVOICE_FINANCER,
        ]);

        $this->assertFalse($this->policy->update($user, $this->invoiceDivBToFinB1));
    }

    #[Test]
    public function hexeko_user_can_update_hexeko_invoices(): void
    {
        $user = $this->createHexekoUser([
            PermissionDefaults::UPDATE_INVOICE_DIVISION,
        ]);

        $this->assertTrue($this->policy->update($user, $this->invoiceHexekoToDivA));
    }

    #[Test]
    public function financer_user_cannot_update_invoices(): void
    {
        $user = $this->createFinancerUser($this->financerA1, [
            PermissionDefaults::READ_INVOICE_FINANCER,
        ]);

        $this->assertFalse($this->policy->update($user, $this->invoiceDivAToFinA1));
    }

    // ==================== Tests confirm() ====================

    #[Test]
    public function division_user_can_confirm_if_issuer(): void
    {
        $user = $this->createDivisionUser($this->financerA1, [
            PermissionDefaults::CONFIRM_INVOICE_FINANCER,
        ]);

        $this->assertTrue($this->policy->confirm($user, $this->invoiceDivAToFinA1));
    }

    #[Test]
    public function division_user_cannot_confirm_if_recipient_only(): void
    {
        $user = $this->createDivisionUser($this->financerA1, [
            PermissionDefaults::CONFIRM_INVOICE_FINANCER,
        ]);

        $this->assertFalse($this->policy->confirm($user, $this->invoiceHexekoToDivA));
    }

    #[Test]
    public function financer_user_cannot_confirm_invoices(): void
    {
        $user = $this->createFinancerUser($this->financerA1, [
            PermissionDefaults::READ_INVOICE_FINANCER,
        ]);

        $this->assertFalse($this->policy->confirm($user, $this->invoiceDivAToFinA1));
    }

    // ==================== Tests bulkUpdateStatus() ====================

    #[Test]
    public function bulk_update_succeeds_if_all_invoices_authorized(): void
    {
        $user = $this->createDivisionUser($this->financerA1, [
            PermissionDefaults::CONFIRM_INVOICE_FINANCER,
        ]);

        $invoice2 = Invoice::factory()->create([
            'invoice_type' => InvoiceType::DIVISION_TO_FINANCER,
            'issuer_id' => $this->divisionA->id,
            'recipient_id' => $this->financerA1->id,
        ]);

        $result = $this->policy->bulkUpdateStatus(
            $user,
            'confirmed',
            [$this->invoiceDivAToFinA1->id, $invoice2->id]
        );

        $this->assertTrue($result);
    }

    #[Test]
    public function bulk_update_fails_if_one_invoice_unauthorized(): void
    {
        $user = $this->createDivisionUser($this->financerA1, [
            PermissionDefaults::CONFIRM_INVOICE_FINANCER,
        ]);

        $result = $this->policy->bulkUpdateStatus(
            $user,
            'confirmed',
            [$this->invoiceDivAToFinA1->id, $this->invoiceDivBToFinB1->id]
        );

        $this->assertFalse($result);
    }

    #[Test]
    public function bulk_update_fails_with_invalid_status(): void
    {
        $user = $this->createDivisionUser($this->financerA1, [
            PermissionDefaults::CONFIRM_INVOICE_FINANCER,
        ]);

        $result = $this->policy->bulkUpdateStatus(
            $user,
            'invalid_status',
            [$this->invoiceDivAToFinA1->id]
        );

        $this->assertFalse($result);
    }

    // ==================== Tests items ====================

    #[Test]
    public function view_items_requires_view_invoice(): void
    {
        $user = $this->createFinancerUser($this->financerA1, [
            PermissionDefaults::READ_INVOICE_FINANCER,
        ]);

        $resultCanView = $this->policy->viewItems($user, $this->invoiceDivAToFinA1);
        $resultCannotView = $this->policy->viewItems($user, $this->invoiceDivBToFinB1);

        $this->assertTrue($resultCanView);
        $this->assertFalse($resultCannotView);
    }

    #[Test]
    public function create_item_requires_manage_items_permission(): void
    {
        $userWithPermission = $this->createDivisionUser($this->financerA1, [
            PermissionDefaults::MANAGE_INVOICE_ITEMS_FINANCER,
        ]);

        $userWithoutPermission = $this->createDivisionUser($this->financerA1, [
            PermissionDefaults::READ_INVOICE_DIVISION,
        ]);

        $this->assertTrue($this->policy->createItem($userWithPermission, $this->invoiceDivAToFinA1));
        $this->assertFalse($this->policy->createItem($userWithoutPermission, $this->invoiceDivAToFinA1));
    }

    // ==================== Tests exports ====================

    #[Test]
    public function user_with_download_pdf_permission_can_download_visible_invoice(): void
    {
        $user = $this->createFinancerUser($this->financerA1, [
            PermissionDefaults::DOWNLOAD_INVOICE_PDF_FINANCER,
        ]);

        $this->assertTrue($this->policy->downloadPdf($user, $this->invoiceDivAToFinA1));
    }

    #[Test]
    public function user_cannot_download_pdf_of_invisible_invoice(): void
    {
        $user = $this->createFinancerUser($this->financerA1, [
            PermissionDefaults::DOWNLOAD_INVOICE_PDF_FINANCER,
        ]);

        $this->assertFalse($this->policy->downloadPdf($user, $this->invoiceDivBToFinB1));
    }

    #[Test]
    public function division_user_with_export_permission_can_export_excel(): void
    {
        $user = $this->createDivisionUser($this->financerA1, [
            PermissionDefaults::EXPORT_INVOICE_DIVISION,
        ]);

        $this->assertTrue($this->policy->exportExcel($user));
    }

    #[Test]
    public function financer_user_with_export_permission_can_export_excel(): void
    {
        $user = $this->createFinancerUser($this->financerA1, [
            PermissionDefaults::EXPORT_INVOICE_FINANCER,
        ]);

        $this->assertTrue($this->policy->exportExcel($user));
    }

    #[Test]
    public function financer_user_cannot_export_excel_globally(): void
    {
        $user = $this->createFinancerUser($this->financerA1, [
            PermissionDefaults::READ_INVOICE_FINANCER,
        ]);

        $this->assertFalse($this->policy->exportExcel($user));
    }

    #[Test]
    public function send_email_requires_issuer_permission(): void
    {
        $user = $this->createDivisionUser($this->financerA1, [
            PermissionDefaults::SEND_INVOICE_EMAIL_FINANCER,
        ]);

        $resultCan = $this->policy->sendEmail($user, $this->invoiceDivAToFinA1);
        $resultCannot = $this->policy->sendEmail($user, $this->invoiceHexekoToDivA);

        $this->assertTrue($resultCan);
        $this->assertFalse($resultCannot);
    }

    // ==================== Helpers ====================

    /**
     * Create an Hexeko-level user with access to every division/financer
     *
     * @param  array<string>  $permissions
     */
    private function createHexekoUser(array $permissions): User
    {
        $user = User::factory()->create(['team_id' => $this->team->id]);

        setPermissionsTeamId($this->team->id);

        foreach ($permissions as $permission) {
            $user->givePermissionTo($permission);
        }

        $this->hydrateAuthorizationContext(
            [$this->divisionA->id, $this->divisionB->id],
            [$this->financerA1->id, $this->financerB1->id]
        );

        return $user->fresh();
    }

    /**
     * Create a division user with specific permissions
     *
     * @param  array<string>  $permissions
     */
    private function createDivisionUser(Financer $financer, array $permissions): User
    {
        $user = User::factory()->create(['team_id' => $this->team->id]);

        setPermissionsTeamId($this->team->id);

        foreach ($permissions as $permission) {
            $user->givePermissionTo($permission);
        }

        // Set context for accessible divisions/financers
        $this->hydrateAuthorizationContext(
            [$financer->division_id],
            [$financer->id]
        );

        return $user->fresh();
    }

    /**
     * Create a financer user with specific permissions
     *
     * @param  array<string>  $permissions
     */
    private function createFinancerUser(Financer $financer, array $permissions): User
    {
        $user = User::factory()->create(['team_id' => $this->team->id]);

        setPermissionsTeamId($this->team->id);

        foreach ($permissions as $permission) {
            $user->givePermissionTo($permission);
        }

        $this->hydrateAuthorizationContext([], [$financer->id]);

        return $user->fresh();
    }

    private function hydrateAuthorizationContext(array $divisionIds, array $financerIds): void
    {
        authorizationContext()->hydrate(
            AuthorizationMode::SELF,
            $financerIds,
            $divisionIds,
            [],
            $financerIds[0] ?? null
        );
    }

    private function resetAuthorizationContext(): void
    {
        authorizationContext()->hydrate(
            AuthorizationMode::SELF,
            [],
            [],
            [],
            null
        );
    }
}
