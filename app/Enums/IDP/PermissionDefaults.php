<?php

namespace App\Enums\IDP;

use BenSampo\Enum\Contracts\LocalizedEnum;
use BenSampo\Enum\Enum;

/*
 * @phpstan-ignore-next-line
 */
final class PermissionDefaults extends Enum implements LocalizedEnum
{
    const MANAGE_PROJECT = 'manage_project';

    const ENABLE_PREVIEW_MODE = 'enable_preview_mode';

    const SEND_EMAIL = 'send_email';

    const EXPORT_DATA = 'export_data';

    const MANAGE_SETTINGS = 'manage_settings';

    const APPROVE_REQUESTS = 'approve_requests';

    const CREATE_USER = 'create_user';

    const READ_USER = 'read_user';

    const UPDATE_USER = 'update_user';

    const SELF_UPDATE_USER = 'self_update_user';

    const DELETE_USER = 'delete_user';

    const MANAGE_ANY_FINANCER = 'manage_any_financer';

    // region Financer

    const MANAGE_FINANCER_MODULES = 'manage_financer_modules';

    const MANAGE_FINANCER = 'manage_financer';

    const CREATE_FINANCER = 'create_financer';

    const READ_ANY_FINANCER = 'read_any_financer';

    const READ_OWN_FINANCER = 'read_own_financer';

    const UPDATE_FINANCER = 'update_financer';

    const DELETE_FINANCER = 'delete_financer';

    const VIEW_FINANCER_METRICS = 'view_financer_metrics';
    // endregion

    // region Division
    const MANAGE_DIVISION_MODULES = 'manage_division_modules';

    const MANAGE_DIVISION = 'manage_division';

    const CREATE_DIVISION = 'create_division';

    const READ_DIVISION = 'read_division';

    const UPDATE_DIVISION = 'update_division';

    const DELETE_DIVISION = 'delete_division';
    // endregion

    // Team
    const CREATE_TEAM = 'create_team';

    const READ_TEAM = 'read_team';

    const UPDATE_TEAM = 'update_team';

    const DELETE_TEAM = 'delete_team';

    // Role
    const CREATE_ROLE = 'create_role';

    const READ_ROLE = 'read_role';

    const UPDATE_ROLE = 'update_role';

    const DELETE_ROLE = 'delete_role';

    const MANAGE_USER_ROLES = 'manage_user_roles';

    const ASSIGN_ROLES = 'assign_roles';

    const REVOKE_ROLES = 'revoke_roles';

    const ADD_PERMISSION_TO_ROLE = 'add_permission_to_role';

    const REMOVE_PERMISSION_FROM_ROLE = 'remove_permission_from_role';

    // Permission
    const CREATE_PERMISSION = 'create_permission';

    const READ_PERMISSION = 'read_permission';

    const UPDATE_PERMISSION = 'update_permission';

    const DELETE_PERMISSION = 'delete_permission';

    const MANAGE_PERMISSIONS = 'manage_permissions';

    // Module

    const CREATE_MODULE = 'create_module';

    const READ_MODULE = 'read_module';

    const UPDATE_MODULE = 'update_module';

    const PIN_MODULE = 'pin_module';

    const DELETE_MODULE = 'delete_module';

    // Integration

    const CREATE_INTEGRATION = 'create_integration';

    const USE_INTEGRATION = 'use_integration';

    const UPDATE_INTEGRATION = 'update_integration';

    const DELETE_INTEGRATION = 'delete_integration';

    // OUTILS RH
    const CREATE_HRTOOLS = 'create_hr_tools';

    const UPDATE_HRTOOLS = 'update_hr_tools';

    const READ_HRTOOLS = 'read_hr_tools';

    const DELETE_HRTOOLS = 'delete_hr_tools';

    /* Communication RH */
    const CREATE_ARTICLE = 'create_article';

    const READ_ARTICLE = 'read_article';

    const UPDATE_ARTICLE = 'update_article';

    const DELETE_ARTICLE = 'delete_article';

    const VIEW_DRAFT_ARTICLE = 'view_draft_article';

    /* Vouchers */

    const CREATE_VOUCHER = 'create_voucher';

    const VIEW_VOUCHER_ORDERS = 'vouchers_amilon_orders_view';

    const RETRY_VOUCHER_ORDERS = 'vouchers_amilon_orders_retry';

    const VIEW_VOUCHER_STATISTICS = 'vouchers_amilon_statistics_view';

    // Traductions
    const READ_TRADS = 'read.trads';

    const CREATE_TRADS = 'create.trads';

    const UPDATE_TRADS = 'update.trads';

    const DELETE_TRADS = 'delete.trads';

    const SYNC_TRADS = 'sync.trads';

    // ENGAGEMENT / ANSWERS
    const READ_ANSWER = 'read_answer';

    const CREATE_ANSWER = 'create_answer';

    const UPDATE_ANSWER = 'update_answer';

    const DELETE_ANSWER = 'delete_answer';

    const MANAGE_FINANCER_ANSWERS = 'manage_financer_answers';

    // ENGAGEMENT / SURVEYS
    const READ_SURVEY = 'read_survey';

    const CREATE_SURVEY = 'create_survey';

    const UPDATE_SURVEY = 'update_survey';

    const DELETE_SURVEY = 'delete_survey';

    // ENGAGEMENT / QUESTIONS
    const READ_QUESTION = 'read_question';

    const CREATE_QUESTION = 'create_question';

    const UPDATE_QUESTION = 'update_question';

    const DELETE_QUESTION = 'delete_question';

    // ENGAGEMENT / QUESTIONNAIRES
    const READ_QUESTIONNAIRE = 'read_questionnaire';

    const CREATE_QUESTIONNAIRE = 'create_questionnaire';

    const UPDATE_QUESTIONNAIRE = 'update_questionnaire';

    const DELETE_QUESTIONNAIRE = 'delete_questionnaire';

    // ENGAGEMENT / SUBMISSIONS
    const READ_SUBMISSION = 'read_submission';

    const CREATE_SUBMISSION = 'create_submission';

    const UPDATE_SUBMISSION = 'update_submission';

    const DELETE_SUBMISSION = 'delete_submission';

    const MANAGE_FINANCER_SUBMISSIONS = 'manage_financer_submissions';

    // ENGAGEMENT / THEMES
    const READ_THEME = 'read_theme';

    const CREATE_THEME = 'create_theme';

    const UPDATE_THEME = 'update_theme';

    const DELETE_THEME = 'delete_theme';

    // TRANSLATIONS
    const MANAGE_TRANSLATIONS = 'manage.translations';

    // DEPARTMENTS
    const READ_DEPARTMENT = 'read_department';

    const CREATE_DEPARTMENT = 'create_department';

    const UPDATE_DEPARTMENT = 'update_department';

    const DELETE_DEPARTMENT = 'delete_department';

    // SITES
    const READ_SITE = 'read_site';

    const CREATE_SITE = 'create_site';

    const UPDATE_SITE = 'update_site';

    const DELETE_SITE = 'delete_site';

    // CONTRACT TYPES
    const READ_CONTRACT_TYPE = 'read_contract_type';

    const CREATE_CONTRACT_TYPE = 'create_contract_type';

    const UPDATE_CONTRACT_TYPE = 'update_contract_type';

    const DELETE_CONTRACT_TYPE = 'delete_contract_type';

    // TAGS
    const READ_TAG = 'read_tag';

    const CREATE_TAG = 'create_tag';

    const UPDATE_TAG = 'update_tag';

    const DELETE_TAG = 'delete_tag';

    // WORK MODES
    const READ_WORK_MODE = 'read_work_mode';

    const CREATE_WORK_MODE = 'create_work_mode';

    const UPDATE_WORK_MODE = 'update_work_mode';

    const DELETE_WORK_MODE = 'delete_work_mode';

    // JOB TITLES
    const READ_JOB_TITLE = 'read_job_title';

    const CREATE_JOB_TITLE = 'create_job_title';

    const UPDATE_JOB_TITLE = 'update_job_title';

    const DELETE_JOB_TITLE = 'delete_job_title';

    // JOB LEVELS
    const READ_JOB_LEVEL = 'read_job_level';

    const CREATE_JOB_LEVEL = 'create_job_level';

    const UPDATE_JOB_LEVEL = 'update_job_level';

    const DELETE_JOB_LEVEL = 'delete_job_level';

    // SEGMENTS
    const READ_SEGMENT = 'read_segment';

    const CREATE_SEGMENT = 'create_segment';

    const UPDATE_SEGMENT = 'update_segment';

    const DELETE_SEGMENT = 'delete_segment';

    // INVOICES - Division scope (issuer/creator)
    const READ_INVOICE_DIVISION = 'read_invoice_division';

    const CREATE_INVOICE_DIVISION = 'create_invoice_division';

    const UPDATE_INVOICE_DIVISION = 'update_invoice_division';

    const DELETE_INVOICE_DIVISION = 'delete_invoice_division';

    const CONFIRM_INVOICE_DIVISION = 'confirm_invoice_division';

    const MARK_INVOICE_SENT_DIVISION = 'mark_invoice_sent_division';

    const MARK_INVOICE_PAID_DIVISION = 'mark_invoice_paid_division';

    const SEND_INVOICE_EMAIL_DIVISION = 'send_invoice_email_division';

    const DOWNLOAD_INVOICE_PDF_DIVISION = 'download_invoice_pdf_division';

    const EXPORT_INVOICE_DIVISION = 'export_invoice_division';

    const MANAGE_INVOICE_ITEMS_DIVISION = 'manage_invoice_items_division';

    const EXPORT_USER_BILLING_DIVISION = 'export_user_billing_division';

    // INVOICES - Financer scope (recipient/viewer)
    const READ_INVOICE_FINANCER = 'read_invoice_financer';

    const DOWNLOAD_INVOICE_PDF_FINANCER = 'download_invoice_pdf_financer';

    const EXPORT_USER_BILLING_FINANCER = 'export_user_billing_financer';

    const CREATE_INVOICE_FINANCER = 'create_invoice_financer';

    const UPDATE_INVOICE_FINANCER = 'update_invoice_financer';

    const DELETE_INVOICE_FINANCER = 'delete_invoice_financer';

    const CONFIRM_INVOICE_FINANCER = 'confirm_invoice_financer';

    const MARK_INVOICE_SENT_FINANCER = 'mark_invoice_sent_financer';

    const MARK_INVOICE_PAID_FINANCER = 'mark_invoice_paid_financer';

    const SEND_INVOICE_EMAIL_FINANCER = 'send_invoice_email_financer';

    const EXPORT_INVOICE_FINANCER = 'export_invoice_financer';

    const MANAGE_INVOICE_ITEMS_FINANCER = 'manage_invoice_items_financer';
}
