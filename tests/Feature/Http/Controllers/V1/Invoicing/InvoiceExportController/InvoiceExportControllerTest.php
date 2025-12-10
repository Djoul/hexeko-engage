<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\V1\Invoicing\InvoiceExportController;

use App\Actions\Invoicing\ExportInvoicesExcelAction;
use App\Actions\Invoicing\ExportUserBillingExcelAction;
use App\Actions\Invoicing\GenerateInvoicePdfAction;
use App\Actions\Invoicing\SendInvoiceEmailAction;
use App\Enums\IDP\RoleDefaults;
use App\Enums\InvoiceType;
use App\Enums\Security\AuthorizationMode;
use App\Models\Division;
use App\Models\Financer;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Support\Facades\Context;
use Mockery;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\Helpers\Facades\ModelFactory;
use Tests\ProtectedRouteTestCase;

#[Group('invoicing')]
#[Group('feature')]
class InvoiceExportControllerTest extends ProtectedRouteTestCase
{
    protected bool $checkAuth = false;

    #[Test]
    public function it_streams_excel_export(): void
    {
        $user = $this->createAuthUser(RoleDefaults::GOD);

        $mock = Mockery::mock(ExportInvoicesExcelAction::class);
        $mock->shouldReceive('execute')->once()->with(Mockery::type('array'), $user)
            ->andReturn(response()->streamDownload(static function (): void {
                echo 'excel-content';
            }, 'invoices.xlsx'));

        $this->app->instance(ExportInvoicesExcelAction::class, $mock);

        $this->grantGlobalAccess($user);

        $response = $this->actingAs($user)->get('/api/v1/invoices/export/excel?date_start=2025-01-01');

        $response->assertOk();
        $this->assertInstanceOf(StreamedResponse::class, $response->baseResponse);
        $this->assertStringContainsString('invoices.xlsx', (string) $response->headers->get('content-disposition'));
    }

    #[Test]
    public function it_streams_invoice_pdf_download(): void
    {
        $user = $this->createAuthUser(RoleDefaults::GOD);
        $division = ModelFactory::createDivision();
        $invoice = Invoice::factory()->create([
            'invoice_type' => InvoiceType::HEXEKO_TO_DIVISION,
            'recipient_type' => Division::class,
            'recipient_id' => $division->id,
        ]);
        $this->grantGlobalAccess($user);

        $mock = Mockery::mock(GenerateInvoicePdfAction::class);
        $mock->shouldReceive('execute')->once()->with($invoice->id, false)
            ->andReturn(response()->streamDownload(static function (): void {
                echo 'pdf-content';
            }, 'invoice.pdf'));

        $this->app->instance(GenerateInvoicePdfAction::class, $mock);

        $response = $this->actingAs($user)->get("/api/v1/invoices/{$invoice->id}/pdf");

        $response->assertOk();
        $this->assertInstanceOf(StreamedResponse::class, $response->baseResponse);
    }

    #[Test]
    public function it_sends_invoice_email(): void
    {
        $user = $this->createAuthUser(RoleDefaults::GOD);
        $division = ModelFactory::createDivision();
        $invoice = Invoice::factory()->create([
            'invoice_type' => InvoiceType::HEXEKO_TO_DIVISION,
            'recipient_type' => Division::class,
            'recipient_id' => $division->id,
        ]);
        $this->grantGlobalAccess($user);

        $mock = Mockery::mock(SendInvoiceEmailAction::class);
        $mock->shouldReceive('execute')->once()->with($invoice->id, 'billing@example.com', ['finance@example.com'])
            ->andReturnNull();

        $this->app->instance(SendInvoiceEmailAction::class, $mock);

        $response = $this->actingAs($user)->postJson("/api/v1/invoices/{$invoice->id}/send-email", [
            'email' => 'billing@example.com',
            'cc' => ['finance@example.com'],
        ]);

        $response->assertOk()->assertJson(['message' => 'Invoice email queued']);
    }

    #[Test]
    public function it_exports_user_billing_details_excel(): void
    {
        $user = $this->createAuthUser(RoleDefaults::GOD);
        $division = ModelFactory::createDivision();
        $invoice = Invoice::factory()->create([
            'invoice_type' => InvoiceType::HEXEKO_TO_DIVISION,
            'recipient_type' => Division::class,
            'recipient_id' => $division->id,
        ]);
        $this->grantGlobalAccess($user);

        $mock = Mockery::mock(ExportUserBillingExcelAction::class);
        $mock->shouldReceive('execute')->once()->with($invoice->id)
            ->andReturn(response()->streamDownload(static function (): void {
                echo 'user-billing-excel-content';
            }, 'user-billing-details.xlsx'));

        $this->app->instance(ExportUserBillingExcelAction::class, $mock);

        $response = $this->actingAs($user)->get("/api/v1/invoices/{$invoice->id}/export/user-billing");

        $response->assertOk();
        $this->assertInstanceOf(StreamedResponse::class, $response->baseResponse);
        $this->assertStringContainsString('user-billing', (string) $response->headers->get('content-disposition'));
    }

    private function grantGlobalAccess(User $user): void
    {
        $divisions = Division::pluck('id')->toArray();
        $financers = Financer::pluck('id')->toArray();

        Context::add('accessible_divisions', $divisions);
        Context::add('accessible_financers', $financers);

        authorizationContext()->hydrate(
            AuthorizationMode::GLOBAL,
            $financers,
            $divisions,
            $user->roles->pluck('name')->toArray(),
            null
        );
    }
}
