<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Enums\IDP\RoleDefaults;
use App\Enums\InvoiceType;
use App\Enums\Security\AuthorizationMode;
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
final class InvoiceAccessScopeTest extends TestCase
{
    public $team;

    use DatabaseTransactions;

    private Division $divisionA;

    private Division $divisionB;

    private Financer $financerA1;

    private Financer $financerA2;

    private Financer $financerB1;

    private Invoice $invoiceHexekoToDivA;

    private Invoice $invoiceHexekoToDivB;

    private Invoice $invoiceDivAToFinA1;

    private Invoice $invoiceDivAToFinA2;

    private Invoice $invoiceDivBToFinB1;

    protected function setUp(): void
    {
        parent::setUp();

        // Créer les rôles nécessaires
        $this->team = ModelFactory::createTeam();
        setPermissionsTeamId($this->team->id);

        ModelFactory::createRole(['name' => RoleDefaults::GOD, 'team_id' => $this->team->id]);
        ModelFactory::createRole(['name' => RoleDefaults::HEXEKO_SUPER_ADMIN, 'team_id' => $this->team->id]);
        ModelFactory::createRole(['name' => RoleDefaults::HEXEKO_ADMIN, 'team_id' => $this->team->id]);
        ModelFactory::createRole(['name' => RoleDefaults::DIVISION_SUPER_ADMIN, 'team_id' => $this->team->id]);
        ModelFactory::createRole(['name' => RoleDefaults::DIVISION_ADMIN, 'team_id' => $this->team->id]);
        ModelFactory::createRole(['name' => RoleDefaults::FINANCER_SUPER_ADMIN, 'team_id' => $this->team->id]);
        ModelFactory::createRole(['name' => RoleDefaults::FINANCER_ADMIN, 'team_id' => $this->team->id]);
        ModelFactory::createRole(['name' => RoleDefaults::BENEFICIARY, 'team_id' => $this->team->id]);

        // Créer la structure de test : 2 divisions avec financers
        $this->divisionA = ModelFactory::createDivision(['name' => 'Division A']);
        $this->divisionB = ModelFactory::createDivision(['name' => 'Division B']);

        $this->financerA1 = ModelFactory::createFinancer(['division_id' => $this->divisionA->id, 'name' => 'Financer A1']);
        $this->financerA2 = ModelFactory::createFinancer(['division_id' => $this->divisionA->id, 'name' => 'Financer A2']);
        $this->financerB1 = ModelFactory::createFinancer(['division_id' => $this->divisionB->id, 'name' => 'Financer B1']);

        // Set a default financer_id in Context for global scope queries during setup
        Context::add('financer_id', $this->financerA1->id);

        // Créer les factures de test
        // Type HEXEKO_TO_DIVISION
        $this->invoiceHexekoToDivA = Invoice::factory()->create([
            'invoice_type' => InvoiceType::HEXEKO_TO_DIVISION,
            'issuer_type' => 'App\Models\Division',
            'issuer_id' => null, // Hexeko n'a pas d'ID dans le système
            'recipient_type' => 'App\Models\Division',
            'recipient_id' => $this->divisionA->id,
        ]);

        $this->invoiceHexekoToDivB = Invoice::factory()->create([
            'invoice_type' => InvoiceType::HEXEKO_TO_DIVISION,
            'issuer_type' => 'App\Models\Division',
            'issuer_id' => null,
            'recipient_type' => 'App\Models\Division',
            'recipient_id' => $this->divisionB->id,
        ]);

        // Type DIVISION_TO_FINANCER
        $this->invoiceDivAToFinA1 = Invoice::factory()->create([
            'invoice_type' => InvoiceType::DIVISION_TO_FINANCER,
            'issuer_type' => 'App\Models\Division',
            'issuer_id' => $this->divisionA->id,
            'recipient_type' => 'App\Models\Financer',
            'recipient_id' => $this->financerA1->id,
        ]);

        $this->invoiceDivAToFinA2 = Invoice::factory()->create([
            'invoice_type' => InvoiceType::DIVISION_TO_FINANCER,
            'issuer_type' => 'App\Models\Division',
            'issuer_id' => $this->divisionA->id,
            'recipient_type' => 'App\Models\Financer',
            'recipient_id' => $this->financerA2->id,
        ]);

        $this->invoiceDivBToFinB1 = Invoice::factory()->create([
            'invoice_type' => InvoiceType::DIVISION_TO_FINANCER,
            'issuer_type' => 'App\Models\Division',
            'issuer_id' => $this->divisionB->id,
            'recipient_type' => 'App\Models\Financer',
            'recipient_id' => $this->financerB1->id,
        ]);
    }

    // ========== TESTS GOD/HEXEKO ==========

    #[Test]
    public function god_user_can_see_all_invoices(): void
    {
        $godUser = $this->createUserWithRole(RoleDefaults::GOD);
        $this->setAccessibleContext($godUser);

        $accessibleInvoices = Invoice::query()->accessibleByUser($godUser)->get();

        $this->assertCount(5, $accessibleInvoices);
        $this->assertTrue($accessibleInvoices->contains($this->invoiceHexekoToDivA));
        $this->assertTrue($accessibleInvoices->contains($this->invoiceHexekoToDivB));
        $this->assertTrue($accessibleInvoices->contains($this->invoiceDivAToFinA1));
        $this->assertTrue($accessibleInvoices->contains($this->invoiceDivAToFinA2));
        $this->assertTrue($accessibleInvoices->contains($this->invoiceDivBToFinB1));
    }

    #[Test]
    public function hexeko_super_admin_can_see_all_invoices(): void
    {
        $hexekoAdmin = $this->createUserWithRole(RoleDefaults::HEXEKO_SUPER_ADMIN);
        $this->setAccessibleContext($hexekoAdmin);

        $accessibleInvoices = Invoice::query()->accessibleByUser($hexekoAdmin)->get();

        $this->assertCount(5, $accessibleInvoices);
    }

    #[Test]
    public function hexeko_admin_can_see_all_invoices(): void
    {
        $hexekoAdmin = $this->createUserWithRole(RoleDefaults::HEXEKO_ADMIN);
        $this->setAccessibleContext($hexekoAdmin);

        $accessibleInvoices = Invoice::query()->accessibleByUser($hexekoAdmin)->get();

        $this->assertCount(5, $accessibleInvoices);
    }

    // ========== TESTS DIVISION ADMIN ==========

    #[Test]
    public function division_admin_can_see_hexeko_to_division_invoices_for_their_division(): void
    {
        $divisionAdmin = $this->createUserWithRole(RoleDefaults::DIVISION_ADMIN, $this->financerA1);
        $this->setAccessibleContext($divisionAdmin);

        $accessibleInvoices = Invoice::query()->accessibleByUser($divisionAdmin)->get();

        // Doit voir : invoiceHexekoToDivA (recipient), invoiceDivAToFinA1 (issuer), invoiceDivAToFinA2 (issuer)
        $this->assertCount(3, $accessibleInvoices);
        $this->assertTrue($accessibleInvoices->contains($this->invoiceHexekoToDivA));
        $this->assertTrue($accessibleInvoices->contains($this->invoiceDivAToFinA1));
        $this->assertTrue($accessibleInvoices->contains($this->invoiceDivAToFinA2));
    }

    #[Test]
    public function division_admin_can_see_division_to_financer_invoices_issued_by_their_division(): void
    {
        $divisionAdmin = $this->createUserWithRole(RoleDefaults::DIVISION_ADMIN, $this->financerA1);
        $this->setAccessibleContext($divisionAdmin);

        $accessibleInvoices = Invoice::query()->accessibleByUser($divisionAdmin)->get();

        // Doit voir les factures émises par Division A
        $this->assertTrue($accessibleInvoices->contains($this->invoiceDivAToFinA1));
        $this->assertTrue($accessibleInvoices->contains($this->invoiceDivAToFinA2));
    }

    #[Test]
    public function division_admin_cannot_see_invoices_from_other_divisions(): void
    {
        $divisionAdminA = $this->createUserWithRole(RoleDefaults::DIVISION_ADMIN, $this->financerA1);
        $this->setAccessibleContext($divisionAdminA);

        $accessibleInvoices = Invoice::query()->accessibleByUser($divisionAdminA)->get();

        // Ne doit PAS voir les factures de Division B
        $this->assertFalse($accessibleInvoices->contains($this->invoiceHexekoToDivB));
        $this->assertFalse($accessibleInvoices->contains($this->invoiceDivBToFinB1));
    }

    #[Test]
    public function division_super_admin_has_same_access_as_division_admin(): void
    {
        $divisionSuperAdmin = $this->createUserWithRole(RoleDefaults::DIVISION_SUPER_ADMIN, $this->financerA1);
        $this->setAccessibleContext($divisionSuperAdmin);

        $accessibleInvoices = Invoice::query()->accessibleByUser($divisionSuperAdmin)->get();

        // Même accès que DIVISION_ADMIN
        $this->assertCount(3, $accessibleInvoices);
        $this->assertTrue($accessibleInvoices->contains($this->invoiceHexekoToDivA));
        $this->assertTrue($accessibleInvoices->contains($this->invoiceDivAToFinA1));
        $this->assertTrue($accessibleInvoices->contains($this->invoiceDivAToFinA2));
    }

    // ========== TESTS FINANCER ROLES ==========

    #[Test]
    public function financer_admin_can_see_division_to_financer_invoices_for_their_financer(): void
    {
        $financerAdmin = $this->createUserWithRole(RoleDefaults::FINANCER_ADMIN, $this->financerA1);
        $this->setAccessibleContext($financerAdmin);

        $accessibleInvoices = Invoice::query()->accessibleByUser($financerAdmin)->get();

        // Doit voir uniquement invoiceDivAToFinA1
        $this->assertCount(1, $accessibleInvoices);
        $this->assertTrue($accessibleInvoices->contains($this->invoiceDivAToFinA1));
    }

    #[Test]
    public function financer_admin_cannot_see_hexeko_to_division_invoices(): void
    {
        $financerAdmin = $this->createUserWithRole(RoleDefaults::FINANCER_ADMIN, $this->financerA1);
        $this->setAccessibleContext($financerAdmin);

        $accessibleInvoices = Invoice::query()->accessibleByUser($financerAdmin)->get();

        // Ne doit PAS voir les factures HEXEKO_TO_DIVISION
        $this->assertFalse($accessibleInvoices->contains($this->invoiceHexekoToDivA));
        $this->assertFalse($accessibleInvoices->contains($this->invoiceHexekoToDivB));
    }

    #[Test]
    public function financer_admin_cannot_see_invoices_for_other_financers(): void
    {
        $financerAdmin = $this->createUserWithRole(RoleDefaults::FINANCER_ADMIN, $this->financerA1);
        $this->setAccessibleContext($financerAdmin);

        $accessibleInvoices = Invoice::query()->accessibleByUser($financerAdmin)->get();

        // Ne doit PAS voir les factures pour d'autres financers
        $this->assertFalse($accessibleInvoices->contains($this->invoiceDivAToFinA2));
        $this->assertFalse($accessibleInvoices->contains($this->invoiceDivBToFinB1));
    }

    #[Test]
    public function financer_super_admin_has_same_access_as_financer_admin(): void
    {
        $financerSuperAdmin = $this->createUserWithRole(RoleDefaults::FINANCER_SUPER_ADMIN, $this->financerA1);
        $this->setAccessibleContext($financerSuperAdmin);

        $accessibleInvoices = Invoice::query()->accessibleByUser($financerSuperAdmin)->get();

        // Même accès que FINANCER_ADMIN
        $this->assertCount(1, $accessibleInvoices);
        $this->assertTrue($accessibleInvoices->contains($this->invoiceDivAToFinA1));
    }

    #[Test]
    public function beneficiary_has_same_access_as_financer_admin(): void
    {
        $beneficiary = $this->createUserWithRole(RoleDefaults::BENEFICIARY, $this->financerA1);
        $this->setAccessibleContext($beneficiary);

        $accessibleInvoices = Invoice::query()->accessibleByUser($beneficiary)->get();

        // Même accès que FINANCER_ADMIN
        $this->assertCount(1, $accessibleInvoices);
        $this->assertTrue($accessibleInvoices->contains($this->invoiceDivAToFinA1));
    }

    // ========== TESTS EDGE CASES ==========

    #[Test]
    public function scope_returns_empty_collection_when_no_accessible_divisions_or_financers(): void
    {
        $user = $this->createUserWithRole(RoleDefaults::DIVISION_ADMIN, $this->financerA1);

        // Simuler un contexte vide (pas d'accès)
        authorizationContext()->hydrate(
            AuthorizationMode::SELF,
            [],
            [],
            [],
            null
        );

        $accessibleInvoices = Invoice::query()->accessibleByUser($user)->get();

        $this->assertCount(0, $accessibleInvoices);
    }

    #[Test]
    public function scope_handles_user_with_multiple_financers_in_same_division(): void
    {
        // Créer un utilisateur avec accès à 2 financers de la même division
        $userData = [
            'team_id' => $this->team->id,
            'financers' => [
                ['financer' => $this->financerA1, 'role' => RoleDefaults::FINANCER_ADMIN, 'active' => true],
                ['financer' => $this->financerA2, 'role' => RoleDefaults::FINANCER_ADMIN, 'active' => true],
            ],
        ];
        $user = ModelFactory::createUser($userData, true);
        setPermissionsTeamId($user->team_id);
        $user->assignRole(RoleDefaults::FINANCER_ADMIN);
        $user = $user->fresh(['financers', 'roles']);

        $this->setAccessibleContext($user);

        $accessibleInvoices = Invoice::query()->accessibleByUser($user)->get();

        // Doit voir les 2 factures (une par financer)
        $this->assertCount(2, $accessibleInvoices);
        $this->assertTrue($accessibleInvoices->contains($this->invoiceDivAToFinA1));
        $this->assertTrue($accessibleInvoices->contains($this->invoiceDivAToFinA2));
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
        if ($user->hasAnyRole([
            RoleDefaults::GOD,
            RoleDefaults::HEXEKO_SUPER_ADMIN,
            RoleDefaults::HEXEKO_ADMIN,
        ])) {
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

        // Set financer_id in Context for global scopes
        $financerIds = $user->financers->pluck('id')->toArray();
        if (count($financerIds) > 0) {
            Context::add('financer_id', $financerIds[0]);
        }
    }
}
