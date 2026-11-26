<?php

namespace App\Constants;

use ReflectionClass;

class Permissions
{
    public const USER_VIEW = 'users.view';
    public const USER_CREATE = 'users.create';
    public const USER_EDIT = 'users.edit';
    public const USER_DELETE = 'users.delete';
    public const USER_RESET_PASSWORD = 'users.reset_password';

    public const ROLE_VIEW = 'roles.view';
    public const ROLE_CREATE = 'roles.create';
    public const ROLE_EDIT = 'roles.edit';
    public const ROLE_DELETE = 'roles.delete';

    public const RESIDENT_VIEW = 'residents.view';
    public const RESIDENT_CREATE = 'residents.create';
    public const RESIDENT_EDIT = 'residents.edit';
    public const RESIDENT_DELETE = 'residents.delete';
    public const RESIDENT_IMPORT = 'residents.import';
    public const RESIDENT_EXPORT = 'residents.export';

    public const HOUSEHOLD_VIEW = 'households.view';
    public const HOUSEHOLD_CREATE = 'households.create';
    public const HOUSEHOLD_EDIT = 'households.edit';
    public const HOUSEHOLD_DELETE = 'households.delete';
    public const HOUSEHOLD_IMPORT = 'households.import';
    public const HOUSEHOLD_EXPORT = 'households.export';

    public const DOCUMENT_TYPE_VIEW = 'document_types.view';
    public const DOCUMENT_TYPE_CREATE = 'document_types.create';
    public const DOCUMENT_TYPE_EDIT = 'document_types.edit';
    public const DOCUMENT_TYPE_DELETE = 'document_types.delete';

    public const DOCUMENT_REQUEST_VIEW = 'document_requests.view';
    public const DOCUMENT_REQUEST_CREATE = 'document_requests.create';
    public const DOCUMENT_REQUEST_EDIT = 'document_requests.edit';
    public const DOCUMENT_REQUEST_DELETE = 'document_requests.delete';
    public const DOCUMENT_REQUEST_APPROVE = 'document_requests.approve';
    public const DOCUMENT_REQUEST_REJECT = 'document_requests.reject';
    public const DOCUMENT_REQUEST_PROCESS = 'document_requests.process';
    public const DOCUMENT_REQUEST_SIGN = 'document_requests.sign';

    public const DOCUMENT_VIEW = 'documents.view';
    public const DOCUMENT_GENERATE = 'documents.generate';
    public const DOCUMENT_PRINT = 'documents.print';
    public const DOCUMENT_DOWNLOAD = 'documents.download';
    public const DOCUMENT_RELEASE = 'documents.release';

    public const COMPLAINT_VIEW = 'complaints.view';
    public const COMPLAINT_CREATE = 'complaints.create';
    public const COMPLAINT_EDIT = 'complaints.edit';
    public const COMPLAINT_DELETE = 'complaints.delete';
    public const COMPLAINT_REVIEW = 'complaints.review';
    public const COMPLAINT_ASSIGN = 'complaints.assign';
    public const COMPLAINT_HEARING = 'complaints.hearing';
    public const COMPLAINT_RESOLVE = 'complaints.resolve';
    public const COMPLAINT_CLOSE = 'complaints.close';
    public const COMPLAINT_REJECT = 'complaints.reject';
    public const COMPLAINT_CANCEL = 'complaints.cancel';
    public const COMPLAINT_ENCODE_HISTORICAL = 'complaints.encode_historical';

    // Sitio / Purok Permissions
    public const SITIO_PUROK_VIEW = 'sitio_puroks.view';
    public const SITIO_PUROK_CREATE = 'sitio_puroks.create';
    public const SITIO_PUROK_EDIT = 'sitio_puroks.edit';
    public const SITIO_PUROK_DELETE = 'sitio_puroks.delete';

    public const REPORT_VIEW = 'reports.view';
    public const REPORT_EXPORT = 'reports.export';

    public const SETTING_VIEW = 'settings.view';
    public const SETTING_EDIT = 'settings.edit';

    public const AUDIT_LOG_VIEW = 'audit_logs.view';

    public const PROFILE_VIEW = 'profile.view';
    public const PROFILE_EDIT = 'profile.edit';

    public const MY_REQUEST_VIEW = 'my_requests.view';
    public const MY_REQUEST_CREATE = 'my_requests.create';

    public const MY_COMPLAINT_VIEW = 'my_complaints.view';
    public const MY_COMPLAINT_CREATE = 'my_complaints.create';

    public const MY_PRENATAL_MONITORING_VIEW = 'my_prenatal_monitoring.view';

    public const DASHBOARD_VIEW = 'dashboard.view';

    public const NOTIFICATION_VIEW = 'notifications.view';
    public const NOTIFICATION_SEND = 'notifications.send';

    public const SIGNING_CERTIFICATE_MANAGE = 'signing_certificates.manage';

    public const PAYMENT_COLLECTION_VIEW = 'payment_collection.view';

    public const PRENATAL_MONITORING_VIEW = 'prenatal_monitoring.view';
    public const PRENATAL_MONITORING_CREATE = 'prenatal_monitoring.create';
    public const PRENATAL_MONITORING_EDIT = 'prenatal_monitoring.edit';
    public const PRENATAL_MONITORING_DELETE = 'prenatal_monitoring.delete';

    public const CHILD_NUTRITION_MONITORING_VIEW = 'child_nutrition_monitoring.view';
    public const CHILD_NUTRITION_MONITORING_CREATE = 'child_nutrition_monitoring.create';
    public const CHILD_NUTRITION_MONITORING_EDIT = 'child_nutrition_monitoring.edit';
    public const CHILD_NUTRITION_MONITORING_DELETE = 'child_nutrition_monitoring.delete';

    public const CHILD_NUTRITION_MONITORING_SETTINGS = 'child_nutrition_monitoring.settings';

    public static function all(): array
    {
        return array_values(
            (new ReflectionClass(self::class))
                ->getConstants()
        );
    }
}