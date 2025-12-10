<?php

namespace App\Enums\IDP;

use BenSampo\Enum\Contracts\LocalizedEnum;
use BenSampo\Enum\Enum;
use Exception;

/**
 * @extends Enum<string>
 */
final class RoleDefaults extends Enum implements LocalizedEnum
{
    const GOD = 'god';

    const HEXEKO_SUPER_ADMIN = 'hexeko_super_admin';

    const HEXEKO_ADMIN = 'hexeko_admin';

    const DIVISION_SUPER_ADMIN = 'division_super_admin';

    const DIVISION_ADMIN = 'division_admin';

    const FINANCER_SUPER_ADMIN = 'financer_super_admin';

    const FINANCER_ADMIN = 'financer_admin';

    const BENEFICIARY = 'beneficiary';

    /*
     * Get permissions for a given role
     * @param string $role
     * @return array<int,string>
     # @throws \Exception
     # @phpstan-ignore missingType.iterableValue
     * */
    public static function getPermissionsByRole(string $role): array
    {
        return match ($role) {
            self::GOD => [
                ...PermissionDefaults::asArray(),
                PermissionDefaults::CREATE_ROLE,
                PermissionDefaults::UPDATE_ROLE,
                PermissionDefaults::DELETE_ROLE,
            ],
            self::HEXEKO_SUPER_ADMIN => [
                ...PermissionDefaults::asArray(),
                PermissionDefaults::MANAGE_DIVISION_MODULES,
            ],
            self::HEXEKO_ADMIN => [
                ...self::getPermissionsByRole(self::DIVISION_SUPER_ADMIN),

                PermissionDefaults::MANAGE_PROJECT,
                PermissionDefaults::SEND_EMAIL,
                PermissionDefaults::EXPORT_DATA,
                PermissionDefaults::MANAGE_SETTINGS,
                PermissionDefaults::APPROVE_REQUESTS,

                PermissionDefaults::CREATE_DIVISION,
                PermissionDefaults::DELETE_DIVISION,

                PermissionDefaults::CREATE_TEAM,
                PermissionDefaults::READ_TEAM,
                PermissionDefaults::UPDATE_TEAM,
                PermissionDefaults::DELETE_TEAM,

                PermissionDefaults::READ_PERMISSION,

                PermissionDefaults::CREATE_MODULE,
                PermissionDefaults::READ_MODULE,
                PermissionDefaults::UPDATE_MODULE,
                PermissionDefaults::DELETE_MODULE,

                PermissionDefaults::READ_TRADS,
                PermissionDefaults::CREATE_TRADS,
                PermissionDefaults::UPDATE_TRADS,
                PermissionDefaults::DELETE_TRADS,
                PermissionDefaults::SYNC_TRADS,

                PermissionDefaults::CREATE_INTEGRATION,
                PermissionDefaults::DELETE_INTEGRATION,

                PermissionDefaults::READ_ROLE,
                PermissionDefaults::READ_PERMISSION,

                PermissionDefaults::MANAGE_ANY_FINANCER,

                PermissionDefaults::CREATE_INVOICE_DIVISION,
                PermissionDefaults::UPDATE_INVOICE_DIVISION,
                PermissionDefaults::DELETE_INVOICE_DIVISION,
                PermissionDefaults::CONFIRM_INVOICE_DIVISION,
                PermissionDefaults::MARK_INVOICE_SENT_DIVISION,
                PermissionDefaults::MARK_INVOICE_PAID_DIVISION,
                PermissionDefaults::SEND_INVOICE_EMAIL_DIVISION,

            ],
            self::DIVISION_SUPER_ADMIN => [
                ...self::getPermissionsByRole(self::DIVISION_ADMIN),
                PermissionDefaults::DELETE_FINANCER,
                PermissionDefaults::MANAGE_FINANCER_MODULES,

            ],
            self::DIVISION_ADMIN => [
                ...self::getPermissionsByRole(self::FINANCER_SUPER_ADMIN),
                PermissionDefaults::ENABLE_PREVIEW_MODE,

                PermissionDefaults::UPDATE_DIVISION,

                PermissionDefaults::MANAGE_FINANCER,
                PermissionDefaults::READ_ANY_FINANCER,
                PermissionDefaults::CREATE_FINANCER,
                PermissionDefaults::UPDATE_FINANCER,

                PermissionDefaults::READ_INVOICE_DIVISION,
                PermissionDefaults::DOWNLOAD_INVOICE_PDF_DIVISION,

                PermissionDefaults::EXPORT_INVOICE_DIVISION,

                PermissionDefaults::MANAGE_INVOICE_ITEMS_DIVISION,

                PermissionDefaults::EXPORT_USER_BILLING_DIVISION,

                PermissionDefaults::CREATE_INVOICE_FINANCER,
                PermissionDefaults::UPDATE_INVOICE_FINANCER,
                PermissionDefaults::DELETE_INVOICE_FINANCER,
                PermissionDefaults::CONFIRM_INVOICE_FINANCER,
                PermissionDefaults::MARK_INVOICE_SENT_FINANCER,
                PermissionDefaults::MARK_INVOICE_PAID_FINANCER,
                PermissionDefaults::SEND_INVOICE_EMAIL_FINANCER,
                PermissionDefaults::MANAGE_INVOICE_ITEMS_FINANCER,

            ],
            self::FINANCER_SUPER_ADMIN => [
                ...self::getPermissionsByRole(self::FINANCER_ADMIN),
                PermissionDefaults::READ_INVOICE_FINANCER,
                PermissionDefaults::DOWNLOAD_INVOICE_PDF_FINANCER,
                PermissionDefaults::EXPORT_USER_BILLING_FINANCER,
                PermissionDefaults::EXPORT_INVOICE_FINANCER,

                PermissionDefaults::CREATE_USER,
                PermissionDefaults::UPDATE_USER,
                PermissionDefaults::DELETE_USER,

                PermissionDefaults::ASSIGN_ROLES,
                PermissionDefaults::REVOKE_ROLES,
                PermissionDefaults::MANAGE_USER_ROLES,

                PermissionDefaults::MANAGE_SETTINGS,
            ],
            self::FINANCER_ADMIN => [
                ...self::getPermissionsByRole(self::BENEFICIARY),

                PermissionDefaults::UPDATE_FINANCER,

                PermissionDefaults::VIEW_FINANCER_METRICS,

                PermissionDefaults::READ_USER,

                PermissionDefaults::READ_HRTOOLS,
                PermissionDefaults::CREATE_HRTOOLS,
                PermissionDefaults::UPDATE_HRTOOLS,
                PermissionDefaults::DELETE_HRTOOLS,

                PermissionDefaults::READ_ARTICLE,
                PermissionDefaults::CREATE_ARTICLE,
                PermissionDefaults::UPDATE_ARTICLE,
                PermissionDefaults::DELETE_ARTICLE,
                PermissionDefaults::VIEW_DRAFT_ARTICLE,

                PermissionDefaults::UPDATE_INTEGRATION,

                PermissionDefaults::VIEW_FINANCER_METRICS,

                PermissionDefaults::CREATE_DEPARTMENT,
                PermissionDefaults::UPDATE_DEPARTMENT,
                PermissionDefaults::DELETE_DEPARTMENT,

                PermissionDefaults::CREATE_SITE,
                PermissionDefaults::UPDATE_SITE,
                PermissionDefaults::DELETE_SITE,

                PermissionDefaults::CREATE_CONTRACT_TYPE,
                PermissionDefaults::UPDATE_CONTRACT_TYPE,
                PermissionDefaults::DELETE_CONTRACT_TYPE,

                PermissionDefaults::CREATE_TAG,
                PermissionDefaults::UPDATE_TAG,
                PermissionDefaults::DELETE_TAG,

                PermissionDefaults::CREATE_WORK_MODE,
                PermissionDefaults::UPDATE_WORK_MODE,
                PermissionDefaults::DELETE_WORK_MODE,

                PermissionDefaults::CREATE_JOB_TITLE,
                PermissionDefaults::UPDATE_JOB_TITLE,
                PermissionDefaults::DELETE_JOB_TITLE,

                PermissionDefaults::CREATE_JOB_LEVEL,
                PermissionDefaults::UPDATE_JOB_LEVEL,
                PermissionDefaults::DELETE_JOB_LEVEL,

                PermissionDefaults::CREATE_SEGMENT,
                PermissionDefaults::UPDATE_SEGMENT,
                PermissionDefaults::DELETE_SEGMENT,
                PermissionDefaults::READ_SEGMENT,

                // ENGAGEMENT
                PermissionDefaults::MANAGE_FINANCER_ANSWERS,
                PermissionDefaults::MANAGE_FINANCER_SUBMISSIONS,

                PermissionDefaults::CREATE_SURVEY,
                PermissionDefaults::UPDATE_SURVEY,
                PermissionDefaults::DELETE_SURVEY,

                PermissionDefaults::CREATE_QUESTION,
                PermissionDefaults::UPDATE_QUESTION,
                PermissionDefaults::DELETE_QUESTION,

                PermissionDefaults::READ_QUESTIONNAIRE,
                PermissionDefaults::CREATE_QUESTIONNAIRE,
                PermissionDefaults::UPDATE_QUESTIONNAIRE,
                PermissionDefaults::DELETE_QUESTIONNAIRE,

                PermissionDefaults::CREATE_THEME,
                PermissionDefaults::UPDATE_THEME,
                PermissionDefaults::DELETE_THEME,

                // INVOICES
                PermissionDefaults::READ_INVOICE_FINANCER,
                PermissionDefaults::DOWNLOAD_INVOICE_PDF_FINANCER,
                PermissionDefaults::EXPORT_USER_BILLING_FINANCER,
                PermissionDefaults::EXPORT_INVOICE_FINANCER,
            ],
            self::BENEFICIARY => [
                PermissionDefaults::READ_ARTICLE,

                PermissionDefaults::READ_OWN_FINANCER,

                PermissionDefaults::READ_HRTOOLS,

                PermissionDefaults::USE_INTEGRATION,

                PermissionDefaults::PIN_MODULE,

                PermissionDefaults::SELF_UPDATE_USER,

                PermissionDefaults::READ_MODULE,

                PermissionDefaults::CREATE_VOUCHER,
                PermissionDefaults::VIEW_VOUCHER_ORDERS,
                PermissionDefaults::RETRY_VOUCHER_ORDERS,

                PermissionDefaults::READ_DEPARTMENT,
                PermissionDefaults::READ_SITE,
                PermissionDefaults::READ_CONTRACT_TYPE,
                PermissionDefaults::READ_TAG,
                PermissionDefaults::READ_WORK_MODE,
                PermissionDefaults::READ_JOB_TITLE,
                PermissionDefaults::READ_JOB_LEVEL,

                // ENGAGEMENT
                PermissionDefaults::READ_ANSWER,
                PermissionDefaults::CREATE_ANSWER,
                PermissionDefaults::UPDATE_ANSWER,
                PermissionDefaults::DELETE_ANSWER,

                PermissionDefaults::READ_SURVEY,

                PermissionDefaults::READ_SUBMISSION,
                PermissionDefaults::CREATE_SUBMISSION,
                PermissionDefaults::UPDATE_SUBMISSION,
                PermissionDefaults::DELETE_SUBMISSION,

                PermissionDefaults::READ_QUESTION,

                PermissionDefaults::READ_THEME,
            ],

            default => throw new Exception('Role not found'),
        };
    }

    /*
     * Get role that a given role may assign
     * @param string $role
     * @return array<int,string>
     * # @throws \Exception
     * # @phpstan-ignore missingType.iterableValue
    */
    public static function canManageRole(array $authRoles, string $newRole): bool
    {
        foreach ($authRoles as $role) {
            if (in_array($newRole, self::getAssignableRoles($role), true)) {
                return true;
            }
        }

        return false;
    }

    /*
     * Get permissions for a given role
     * @param array<int,string> $authRoles
     * @param string $newRole
     * @return bool
     *      # @phpstan-ignore missingType.iterableValue
     */
    public static function getAssignableRoles(string $role): array
    {
        return match ($role) {
            self::GOD => [
                ...self::getAssignableRoles(self::HEXEKO_SUPER_ADMIN),
                self::HEXEKO_SUPER_ADMIN,
            ],
            self::HEXEKO_SUPER_ADMIN => [
                ...self::getAssignableRoles(self::HEXEKO_ADMIN),
                self::HEXEKO_ADMIN,
            ],
            self::HEXEKO_ADMIN => [
                ...self::getAssignableRoles(self::DIVISION_SUPER_ADMIN),
                self::DIVISION_SUPER_ADMIN,
            ],
            self::DIVISION_SUPER_ADMIN => [
                ...self::getAssignableRoles(self::DIVISION_ADMIN),
                self::DIVISION_ADMIN,
            ],
            self::DIVISION_ADMIN => [
                ...self::getAssignableRoles(self::FINANCER_SUPER_ADMIN),
                self::FINANCER_SUPER_ADMIN,
            ],
            self::FINANCER_SUPER_ADMIN => [
                ...self::getAssignableRoles(self::FINANCER_ADMIN),
                self::FINANCER_ADMIN,
            ],
            self::FINANCER_ADMIN => [
                self::BENEFICIARY,
            ],
            self::BENEFICIARY => [
            ],
            default => throw new Exception('Role not found'),
        };
    }
}
