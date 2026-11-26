<?php

namespace App\Constants;

class RolePermissions
{
    public static function matrix(): array
    {
        return [

            /*
            |--------------------------------------------------------------------------
            | Super Administrator
            |--------------------------------------------------------------------------
            */
            Roles::SUPER_ADMIN => Permissions::all(),

            /*
            |--------------------------------------------------------------------------
            | Administrator
            |--------------------------------------------------------------------------
            */
            Roles::ADMINISTRATOR => [

                Permissions::DASHBOARD_VIEW,

                Permissions::USER_VIEW,
                Permissions::USER_CREATE,
                Permissions::USER_EDIT,
                Permissions::USER_DELETE,
                Permissions::USER_RESET_PASSWORD,

                Permissions::RESIDENT_VIEW,
                Permissions::RESIDENT_CREATE,
                Permissions::RESIDENT_EDIT,
                Permissions::RESIDENT_DELETE,
                Permissions::RESIDENT_IMPORT,
                Permissions::RESIDENT_EXPORT,

                Permissions::HOUSEHOLD_VIEW,
                Permissions::HOUSEHOLD_CREATE,
                Permissions::HOUSEHOLD_EDIT,
                Permissions::HOUSEHOLD_DELETE,
                Permissions::HOUSEHOLD_IMPORT,
                Permissions::HOUSEHOLD_EXPORT,

                Permissions::DOCUMENT_TYPE_VIEW,
                Permissions::DOCUMENT_TYPE_CREATE,
                Permissions::DOCUMENT_TYPE_EDIT,
                Permissions::DOCUMENT_TYPE_DELETE,

                Permissions::DOCUMENT_REQUEST_VIEW,
                Permissions::DOCUMENT_REQUEST_CREATE,
                Permissions::DOCUMENT_REQUEST_EDIT,
                Permissions::DOCUMENT_REQUEST_DELETE,
                Permissions::DOCUMENT_REQUEST_APPROVE,
                Permissions::DOCUMENT_REQUEST_REJECT,
                Permissions::DOCUMENT_REQUEST_PROCESS,

                Permissions::DOCUMENT_VIEW,
                Permissions::DOCUMENT_GENERATE,
                Permissions::DOCUMENT_PRINT,
                Permissions::DOCUMENT_DOWNLOAD,
                Permissions::DOCUMENT_RELEASE,

                Permissions::COMPLAINT_VIEW,
                Permissions::COMPLAINT_CREATE,
                Permissions::COMPLAINT_EDIT,
                Permissions::COMPLAINT_DELETE,
                Permissions::COMPLAINT_REVIEW,
                Permissions::COMPLAINT_ASSIGN,
                Permissions::COMPLAINT_HEARING,
                Permissions::COMPLAINT_RESOLVE,
                Permissions::COMPLAINT_CLOSE,
                Permissions::COMPLAINT_REJECT,
                Permissions::COMPLAINT_CANCEL,
                Permissions::COMPLAINT_ENCODE_HISTORICAL,

                Permissions::REPORT_VIEW,
                Permissions::REPORT_EXPORT,

                Permissions::AUDIT_LOG_VIEW,

                Permissions::SIGNING_CERTIFICATE_MANAGE,
            ],

            /*
            |--------------------------------------------------------------------------
            | Chairperson
            |--------------------------------------------------------------------------
            */
            Roles::CHAIRPERSON => [

                Permissions::DASHBOARD_VIEW,

                Permissions::RESIDENT_VIEW,

                Permissions::HOUSEHOLD_VIEW,

                Permissions::DOCUMENT_REQUEST_VIEW,
                Permissions::DOCUMENT_REQUEST_APPROVE,
                Permissions::DOCUMENT_REQUEST_REJECT,
                Permissions::DOCUMENT_REQUEST_SIGN,

                Permissions::DOCUMENT_VIEW,
                Permissions::DOCUMENT_PRINT,
                Permissions::DOCUMENT_DOWNLOAD,

                Permissions::COMPLAINT_VIEW,
                Permissions::COMPLAINT_REVIEW,
                Permissions::COMPLAINT_CLOSE,

                Permissions::REPORT_VIEW,

                Permissions::SITIO_PUROK_VIEW,
            ],

            /*
            |--------------------------------------------------------------------------
            | Secretary
            |--------------------------------------------------------------------------
            */
            Roles::SECRETARY => [

                Permissions::DASHBOARD_VIEW,

                Permissions::RESIDENT_VIEW,
                Permissions::RESIDENT_CREATE,
                Permissions::RESIDENT_EDIT,

                Permissions::HOUSEHOLD_VIEW,
                Permissions::HOUSEHOLD_CREATE,
                Permissions::HOUSEHOLD_EDIT,

                Permissions::DOCUMENT_REQUEST_VIEW,
                Permissions::DOCUMENT_REQUEST_PROCESS,
                Permissions::DOCUMENT_REQUEST_APPROVE,
                Permissions::DOCUMENT_REQUEST_REJECT,

                Permissions::DOCUMENT_VIEW,
                Permissions::DOCUMENT_GENERATE,
                Permissions::DOCUMENT_PRINT,
                Permissions::DOCUMENT_DOWNLOAD,
                Permissions::DOCUMENT_RELEASE,

                Permissions::COMPLAINT_CREATE,
                Permissions::COMPLAINT_EDIT,
                Permissions::COMPLAINT_DELETE,
                Permissions::COMPLAINT_ASSIGN,
                Permissions::COMPLAINT_HEARING,
                Permissions::COMPLAINT_VIEW,
                Permissions::COMPLAINT_REVIEW,
                Permissions::COMPLAINT_RESOLVE,
                Permissions::COMPLAINT_REJECT,
                Permissions::COMPLAINT_CLOSE,

                Permissions::REPORT_VIEW,

                Permissions::SITIO_PUROK_VIEW,
                Permissions::SITIO_PUROK_CREATE,
                Permissions::SITIO_PUROK_EDIT,
                Permissions::SITIO_PUROK_DELETE,
            ],

            /*
            |--------------------------------------------------------------------------
            | Staff
            |--------------------------------------------------------------------------
            */
            Roles::STAFF => [

                Permissions::DASHBOARD_VIEW,

                Permissions::RESIDENT_VIEW,
                Permissions::RESIDENT_CREATE,
                Permissions::RESIDENT_EDIT,

                Permissions::HOUSEHOLD_VIEW,
                Permissions::HOUSEHOLD_CREATE,
                Permissions::HOUSEHOLD_EDIT,

                Permissions::DOCUMENT_REQUEST_VIEW,
                Permissions::DOCUMENT_REQUEST_PROCESS,
                Permissions::DOCUMENT_REQUEST_APPROVE,
                Permissions::DOCUMENT_REQUEST_REJECT,

                Permissions::DOCUMENT_VIEW,
                Permissions::DOCUMENT_GENERATE,
                Permissions::DOCUMENT_DOWNLOAD,
                Permissions::DOCUMENT_RELEASE,

                Permissions::COMPLAINT_CREATE,
                Permissions::COMPLAINT_EDIT,
                Permissions::COMPLAINT_DELETE,
                Permissions::COMPLAINT_ASSIGN,
                Permissions::COMPLAINT_HEARING,
                Permissions::COMPLAINT_VIEW,
                Permissions::COMPLAINT_REVIEW,
                Permissions::COMPLAINT_RESOLVE,
                Permissions::COMPLAINT_REJECT,
                Permissions::COMPLAINT_CLOSE,

                Permissions::SITIO_PUROK_VIEW,
                Permissions::SITIO_PUROK_CREATE,
                Permissions::SITIO_PUROK_EDIT,
            ],

            /*
            |--------------------------------------------------------------------------
            | Resident
            |--------------------------------------------------------------------------
            */
            Roles::RESIDENT => [

                Permissions::DASHBOARD_VIEW,

                Permissions::PROFILE_VIEW,
                Permissions::PROFILE_EDIT,

                Permissions::MY_REQUEST_VIEW,
                Permissions::MY_REQUEST_CREATE,

                Permissions::MY_COMPLAINT_VIEW,
                Permissions::MY_COMPLAINT_CREATE,

                Permissions::DOCUMENT_DOWNLOAD,

                Permissions::MY_PRENATAL_MONITORING_VIEW,
            ],

            /*
            |--------------------------------------------------------------------------
            | Treasurer
            |--------------------------------------------------------------------------
            */
            Roles::TREASURER => [

                Permissions::DASHBOARD_VIEW,

                Permissions::PAYMENT_COLLECTION_VIEW,
            ],

            /*
            |--------------------------------------------------------------------------
            | BHW/BNS
            |--------------------------------------------------------------------------
            */
            Roles::BHW_BNS => [
                Permissions::DASHBOARD_VIEW,

                Permissions::PRENATAL_MONITORING_VIEW,
                Permissions::PRENATAL_MONITORING_CREATE,
                Permissions::PRENATAL_MONITORING_EDIT,
                Permissions::PRENATAL_MONITORING_DELETE,

                Permissions::CHILD_NUTRITION_MONITORING_VIEW,
                Permissions::CHILD_NUTRITION_MONITORING_CREATE,
                Permissions::CHILD_NUTRITION_MONITORING_EDIT,
                Permissions::CHILD_NUTRITION_MONITORING_DELETE,
            ],

        ];
    }
}