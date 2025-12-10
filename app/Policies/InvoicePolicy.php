<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\IDP\PermissionDefaults;
use App\Enums\InvoiceType;
use App\Models\Division;
use App\Models\Invoice;
use App\Models\User;

final class InvoicePolicy
{
    /**
     * Déterminer si l'utilisateur peut lister les factures
     */
    public function viewAny(User $user): bool
    {
        if ($user->hasPermissionTo(PermissionDefaults::READ_INVOICE_DIVISION)) {
            return true;
        }

        return $user->hasPermissionTo(PermissionDefaults::READ_INVOICE_FINANCER);
    }

    /**
     * Déterminer si l'utilisateur peut voir une facture spécifique
     */
    public function view(User $user, Invoice $invoice): bool
    {
        // Division scope : check si la division est impliquée
        if ($user->hasPermissionTo(PermissionDefaults::READ_INVOICE_DIVISION)) {
            return $this->canAccessAsDivision($invoice);
        }

        // Financer scope : check si le financer est recipient
        if ($user->hasPermissionTo(PermissionDefaults::READ_INVOICE_FINANCER)) {
            return $this->canAccessAsFinancer($invoice);
        }

        return false;
    }

    /**
     * Déterminer si l'utilisateur peut créer des factures selon le type
     *
     * @param  InvoiceType::*  $invoiceType
     */
    public function create(User $user, string $invoiceType): bool
    {
        return match ($invoiceType) {
            InvoiceType::HEXEKO_TO_DIVISION => $user->hasPermissionTo(PermissionDefaults::CREATE_INVOICE_DIVISION),
            InvoiceType::DIVISION_TO_FINANCER => $user->hasPermissionTo(PermissionDefaults::CREATE_INVOICE_FINANCER),
            default => false,
        };
    }

    /**
     * Déterminer si l'utilisateur peut modifier une facture
     */
    public function update(User $user, Invoice $invoice): bool
    {
        return $this->canManageInvoice(
            $user,
            $invoice,
            PermissionDefaults::UPDATE_INVOICE_DIVISION,
            PermissionDefaults::UPDATE_INVOICE_FINANCER
        );
    }

    /**
     * Déterminer si l'utilisateur peut supprimer une facture
     */
    public function delete(User $user, Invoice $invoice): bool
    {
        return $this->canManageInvoice(
            $user,
            $invoice,
            PermissionDefaults::DELETE_INVOICE_DIVISION,
            PermissionDefaults::DELETE_INVOICE_FINANCER
        );
    }

    /**
     * Déterminer si l'utilisateur peut confirmer une facture
     */
    public function confirm(User $user, Invoice $invoice): bool
    {
        return $this->canManageInvoice(
            $user,
            $invoice,
            PermissionDefaults::CONFIRM_INVOICE_DIVISION,
            PermissionDefaults::CONFIRM_INVOICE_FINANCER
        );
    }

    /**
     * Déterminer si l'utilisateur peut marquer une facture comme envoyée
     */
    public function markSent(User $user, Invoice $invoice): bool
    {
        return $this->canManageInvoice(
            $user,
            $invoice,
            PermissionDefaults::MARK_INVOICE_SENT_DIVISION,
            PermissionDefaults::MARK_INVOICE_SENT_FINANCER
        );
    }

    /**
     * Déterminer si l'utilisateur peut marquer une facture comme payée
     */
    public function markPaid(User $user, Invoice $invoice): bool
    {
        return $this->canManageInvoice(
            $user,
            $invoice,
            PermissionDefaults::MARK_INVOICE_PAID_DIVISION,
            PermissionDefaults::MARK_INVOICE_PAID_FINANCER
        );
    }

    /**
     * Déterminer si l'utilisateur peut faire une mise à jour en masse
     *
     * @param  array<int>  $invoiceIds
     */
    public function bulkUpdateStatus(User $user, string $targetStatus, array $invoiceIds): bool
    {
        // Vérifier CHAQUE facture individuellement
        foreach ($invoiceIds as $invoiceId) {
            $invoice = Invoice::find($invoiceId);

            if (! $invoice instanceof Invoice) {
                return false; // Facture inexistante
            }

            // Vérifier l'autorisation selon le statut cible
            $authorized = match ($targetStatus) {
                'confirmed' => $this->confirm($user, $invoice),
                'sent' => $this->markSent($user, $invoice),
                'paid' => $this->markPaid($user, $invoice),
                'cancelled' => $this->delete($user, $invoice),
                default => false,
            };

            if (! $authorized) {
                return false; // UNE facture non autorisée = tout le bulk rejeté
            }
        }

        return true; // Toutes les factures sont autorisées
    }

    /**
     * Déterminer si l'utilisateur peut voir les items d'une facture
     */
    public function viewItems(User $user, Invoice $invoice): bool
    {
        return $this->view($user, $invoice);
    }

    /**
     * Déterminer si l'utilisateur peut créer un item sur une facture
     */
    public function createItem(User $user, Invoice $invoice): bool
    {
        return $this->canManageInvoice(
            $user,
            $invoice,
            PermissionDefaults::MANAGE_INVOICE_ITEMS_DIVISION,
            PermissionDefaults::MANAGE_INVOICE_ITEMS_FINANCER
        );
    }

    /**
     * Déterminer si l'utilisateur peut modifier un item d'une facture
     */
    public function updateItem(User $user, Invoice $invoice): bool
    {
        return $this->createItem($user, $invoice);
    }

    /**
     * Déterminer si l'utilisateur peut supprimer un item d'une facture
     */
    public function deleteItem(User $user, Invoice $invoice): bool
    {
        return $this->createItem($user, $invoice);
    }

    /**
     * Déterminer si l'utilisateur peut télécharger le PDF d'une facture
     */
    public function downloadPdf(User $user, Invoice $invoice): bool
    {
        // Division scope
        if ($user->hasPermissionTo(PermissionDefaults::DOWNLOAD_INVOICE_PDF_DIVISION)) {
            return $this->canAccessAsDivision($invoice);
        }

        // Financer scope
        if ($user->hasPermissionTo(PermissionDefaults::DOWNLOAD_INVOICE_PDF_FINANCER)) {
            return $this->canAccessAsFinancer($invoice);
        }

        return false;
    }

    /**
     * Déterminer si l'utilisateur peut exporter des factures en Excel
     */
    public function exportExcel(User $user): bool
    {
        if ($user->hasPermissionTo(PermissionDefaults::EXPORT_INVOICE_DIVISION)) {
            return true;
        }

        return $user->hasPermissionTo(PermissionDefaults::EXPORT_INVOICE_FINANCER);
    }

    /**
     * Déterminer si l'utilisateur peut envoyer une facture par email
     */
    public function sendEmail(User $user, Invoice $invoice): bool
    {
        return $this->canManageInvoice(
            $user,
            $invoice,
            PermissionDefaults::SEND_INVOICE_EMAIL_DIVISION,
            PermissionDefaults::SEND_INVOICE_EMAIL_FINANCER
        );
    }

    /**
     * Déterminer si l'utilisateur peut exporter les détails de facturation utilisateur
     */
    public function exportUserBilling(User $user, Invoice $invoice): bool
    {
        // Division scope
        if ($user->hasPermissionTo(PermissionDefaults::EXPORT_USER_BILLING_DIVISION)) {
            return $this->canAccessAsDivision($invoice);
        }

        // Financer scope
        if ($user->hasPermissionTo(PermissionDefaults::EXPORT_USER_BILLING_FINANCER)) {
            return $this->canAccessAsFinancer($invoice);
        }

        return false;
    }

    // ========== HELPERS PRIVÉS ==========

    /**
     * Vérifier si l'utilisateur Division peut accéder à la facture
     */
    private function canAccessAsDivision(Invoice $invoice): bool
    {
        $accessibleDivisions = authorizationContext()->divisionIds();

        $division = match ($invoice->invoice_type) {
            InvoiceType::HEXEKO_TO_DIVISION => $invoice->recipientDivision(),
            InvoiceType::DIVISION_TO_FINANCER => $invoice->issuerDivision(),
            default => null,
        };

        if (! $division instanceof Division) {
            return false;
        }

        return in_array($division->id, $accessibleDivisions, true);
    }

    /**
     * Vérifier si l'utilisateur Financer peut accéder à la facture
     */
    private function canAccessAsFinancer(Invoice $invoice): bool
    {
        $accessibleFinancers = authorizationContext()->financerIds();

        // Seules les factures DIVISION_TO_FINANCER sont accessibles
        if ($invoice->invoice_type === InvoiceType::DIVISION_TO_FINANCER) {
            return in_array($invoice->recipient_id, $accessibleFinancers, true);
        }

        // Les factures HEXEKO_TO_DIVISION ne concernent pas les financers
        return false;
    }

    private function canManageInvoice(User $user, Invoice $invoice, string $divisionPermission, string $financerPermission): bool
    {
        if ($invoice->invoice_type === InvoiceType::HEXEKO_TO_DIVISION) {
            return $user->hasPermissionTo($divisionPermission) && $this->canAccessAsDivision($invoice);
        }

        if ($invoice->invoice_type === InvoiceType::DIVISION_TO_FINANCER) {
            return $user->hasPermissionTo($financerPermission) && $this->isIssuedByUserDivision($invoice);
        }

        return false;
    }

    /**
     * Vérifier si la facture est émise par une division de l'utilisateur
     */
    private function isIssuedByUserDivision(Invoice $invoice): bool
    {
        $accessibleDivisions = authorizationContext()->divisionIds();

        if ($invoice->invoice_type !== InvoiceType::DIVISION_TO_FINANCER) {
            return false;
        }

        $issuerDivision = $invoice->issuerDivision();

        if (! $issuerDivision instanceof Division) {
            return false;
        }

        return in_array($issuerDivision->id, $accessibleDivisions, true);
    }
}
