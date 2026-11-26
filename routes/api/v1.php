<?php

use App\Constants\Permissions;
use App\Http\Controllers\Api\Auth\LoginController;
use App\Http\Controllers\Api\Auth\LogoutController;
use App\Http\Controllers\Api\Auth\MeController;
use App\Http\Controllers\Api\Auth\ActivationController;
use App\Http\Controllers\Api\Auth\VerificationController;
use App\Http\Controllers\Api\Auth\ResidentMatchController;
use App\Http\Controllers\Api\Auth\ResidentRegistrationController;
use App\Http\Controllers\Api\Auth\ResidentLoginController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\SigningCertificateController;
use App\Http\Controllers\Api\OptionController;
use App\Http\Controllers\Api\BarangayController;
use App\Http\Controllers\Api\SitioPurokController;
use App\Http\Controllers\Api\EthnicityController;
use App\Http\Controllers\Api\DocumentTypeController;
use App\Http\Controllers\Api\DocumentTypeRequirementController;
use App\Http\Controllers\Api\DocumentTemplateController;
use App\Http\Controllers\Api\DocumentTypeFieldController;
use App\Http\Controllers\Api\HouseholdController;
use App\Http\Controllers\Api\ResidentController;
use App\Http\Controllers\Api\PSGCController;
use App\Http\Controllers\Api\DocumentApprovalController;
use App\Http\Controllers\Api\DocumentFileController;
use App\Http\Controllers\Api\DocumentGenerationController;
use App\Http\Controllers\Api\DocumentPaymentController;
use App\Http\Controllers\Api\DocumentReleaseController;
use App\Http\Controllers\Api\DocumentRequestController;
use App\Http\Controllers\Api\DocumentRequestAdminController;
use App\Http\Controllers\Api\DocumentPreviewController;
use App\Http\Controllers\Api\DocumentSignatureController; 
use App\Http\Controllers\Api\DocumentRequestAttachmentController;
use App\Http\Controllers\Api\DocumentRequestResidentReleaseController; 
use App\Http\Controllers\Api\DocumentVerificationController;
use App\Http\Controllers\Api\DocumentWalkInRequestController;
use App\Http\Controllers\Api\PayMongoWebhookController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\ComplaintController;
use App\Http\Controllers\Api\ComplaintResidentController;
use App\Http\Controllers\Api\ComplaintWorkflowController;
use App\Http\Controllers\Api\ComplaintHearingController;
use App\Http\Controllers\Api\ComplaintAttachmentController;
use App\Http\Controllers\Api\BarangayOfficialController;
use App\Http\Controllers\Api\OfficialPositionController;
use App\Http\Controllers\Api\BarangayOnboardingController;
use App\Http\Controllers\Api\BarangayOfficialAuditController;
use App\Http\Controllers\Api\PrenatalRecordController;
use App\Http\Controllers\Api\ChildNutritionMonitoringController;
use App\Http\Controllers\Api\ChildNutritionSettingController;
use App\Http\Controllers\Api\PaymentCollectionController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return "<center><h4>BOAConnect API</h4></center>";
});
Route::post('/login', LoginController::class);

Route::prefix('auth')->group(function () {

    /* Activation Route for Created User Accounts by Super Admin */
    Route::get('/activation/{token}', [ActivationController::class, 'verify']);
    Route::post('/activate', [ActivationController::class, 'activate']);

    /* Resident Authorization Routes */
    Route::prefix('verification')->group(function () {
        Route::post('/send', [VerificationController::class, 'send']);
        Route::post('/verify', [VerificationController::class, 'verify']);

    });

    Route::prefix('resident')->group(function () {
        Route::post('/match', ResidentMatchController::class);
        Route::post('/register', ResidentRegistrationController::class);
        Route::post('/login', ResidentLoginController::class);
    });

});

// Public registration form options
Route::get('/options', [OptionController::class, 'index']);
Route::get('/options/ethnicities', [OptionController::class, 'ethnicities']);
Route::get('/options/sectors', [OptionController::class, 'getSectors']);
Route::get('/options/registration/sitio-puroks', [OptionController::class, 'registrationSitioPuroks']);

/* PSGC */
Route::prefix('psgc')->controller(PSGCController::class)->group(function () {
    Route::get('/regions', 'regions');
    Route::get('/regions/{region}/provinces', 'provinces');
    Route::get('/regions/{region}/cities', 'regionCities');
    Route::get('/provinces/{province}/cities', 'cities');
    Route::get('/cities/{city}/barangays', 'barangays');
});

/*
|--------------------------------------------------------------------------
| Authenticated Routes Available During Onboarding
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', MeController::class);

    Route::post('/logout', LogoutController::class);

    /* Options */
    Route::prefix('options')->controller(OptionController::class)->group(function () {
        // Route::get('/', 'index');
        Route::get('/roles', 'getRoles');
        Route::get('/barangays', 'barangays');
        Route::get('/sitio-puroks', 'sitioPuroks');
        // Route::get('/ethnicities', 'ethnicities');
        // Route::get('/sectors', 'getSectors');
        Route::get('/complaint-types', 'getComplaintTypes');
        Route::get('/official-positions', 'getOfficialPositions');
    });

    Route::prefix('onboarding')
    ->name('onboarding.')
    ->controller(BarangayOnboardingController::class)
    ->group(function () {
        Route::get('/', 'status')->name('status');
        Route::post('/complete', 'complete')->name('complete');
        Route::get('/sitio-puroks', 'sitioPuroks')->name('sitio-puroks');
    });

    Route::post(
        '/auth/resident/resubmit',
        [ResidentRegistrationController::class, 'resubmit']
    );
});

Route::middleware('auth:sanctum')
    ->prefix('onboarding')
    ->name('onboarding.')
    ->controller(BarangayOnboardingController::class)
    ->group(function () {
        Route::get('/', 'status')->name('status');
        Route::post('/complete', 'complete')->name('complete');
        Route::get('/sitio-puroks', 'sitioPuroks')->name('sitio-puroks');
    });

Route::middleware(['auth:sanctum', 'onboarding.complete'])->group(function () {

    Route::get('/dashboard', [
        DashboardController::class,
        'index',
    ]);

    /* Profile */
    Route::get('/profile', [
        ProfileController::class,
        'show',
    ])->name('profile.show');

    Route::put('/profile', [
        ProfileController::class,
        'update',
    ])->name('profile.update');

    Route::put('/profile/password', [
        ProfileController::class,
        'updatePassword',
    ])->name('profile.password.update');

    Route::post('/profile/signature', [
        ProfileController::class,
        'updateSignature',
    ])
    ->middleware('permission:document_requests.sign')
    ->name('profile.signature.update');

    Route::get('/profile/signature', [
        ProfileController::class,
        'signature',
    ])->name('profile.signature');

    Route::post('/signing-certificates', [SigningCertificateController::class, 'store']);
    Route::get('/signing-certificates', [SigningCertificateController::class, 'index']);

    Route::prefix('admin/signing-certificates')
        ->middleware('permission:' . Permissions::SIGNING_CERTIFICATE_MANAGE)
        ->group(function () {
            Route::get('/', [SigningCertificateController::class, 'adminIndex']);
            Route::get('/{certificate}', [SigningCertificateController::class, 'adminShow']);
            Route::post('/{certificate}/approve', [SigningCertificateController::class, 'approve']);
            Route::post('/{certificate}/reject', [SigningCertificateController::class, 'reject']);
        });

    /* User */
    Route::apiResource('users', UserController::class);
    Route::post('users/{user}/reset-password', [UserController::class, 'resetPassword']);
    Route::patch('users/{user}/status', [UserController::class, 'toggleStatus']);

    /* Barangay */
    Route::get('/barangays/options', [
        BarangayController::class,
        'options',
    ]);
    Route::apiResource('barangays', BarangayController::class);

    /* Sitio/Purok */
    Route::apiResource('sitio-puroks', SitioPurokController::class);

    /* Ethnicities */
    Route::apiResource('ethnicities', EthnicityController::class);

    Route::get('document-types/available', [DocumentTypeController::class, 'available']);
    // ->middleware('permission:' . Permissions::MY_REQUEST_CREATE)
    // ->name('document-types.available')

    /* Document Types */
    Route::apiResource('document-types', DocumentTypeController::class);

    Route::apiResource(
        'document-types/{documentType}/requirements',
        DocumentTypeRequirementController::class
    )
        ->except([
            'create',
            'edit',
        ]);

    Route::apiResource(
        'document-types/{documentType}/fields',
        DocumentTypeFieldController::class
    )
        ->except([
            'create',
            'edit',
        ])
        ->parameters([
            'fields' => 'documentTypeField',
        ]);


    /* Document Templates */

    Route::get(
        'document-templates/available',
        [DocumentTemplateController::class, 'available']
    );

    Route::apiResource(
        'document-templates',
        DocumentTemplateController::class
    );

    /* Household */
    Route::apiResource('households', HouseholdController::class);
    Route::get('/household-numbers', [HouseholdController::class, 'householdNumbers']);

    /* Residents */
    Route::get(
        '/residents/available-residents-for-user',
        [ResidentController::class, 'availableResidentsForUser']
    );
    Route::get(
        '/residents/resident-registrations',
        [ResidentController::class, 'residentRegistrations']
    );
    Route::put('/residents/update-household/{resident}', [ResidentController::class, 'updateResidentHousehold']);
    Route::put('/residents/reject-registration/{resident}', [ResidentController::class, 'rejectResidentRegistration']);

    Route::apiResource('residents', ResidentController::class);

    /* Official Positions */
    Route::apiResource('official-positions', OfficialPositionController::class);

    /* Barangay Officials */
    Route::get('barangay-officials/residents', [BarangayOfficialController::class, 'residents']);
    Route::get('barangay-officials/assignable', [BarangayOfficialController::class, 'assignable']);
    Route::get('barangay-officials/available-for-user', [BarangayOfficialController::class, 'availableForUser']);

    Route::get(
        'barangay-officials/{barangayOfficial}/audit-history',
        [BarangayOfficialAuditController::class, 'index']
    )
        ->middleware('permission:' . Permissions::AUDIT_LOG_VIEW)
        ->name('barangay-officials.audit-history');
    
    Route::apiResource('barangay-officials', BarangayOfficialController::class);

    /*
    |--------------------------------------------------------------------------
    | Document Requests
    |--------------------------------------------------------------------------
    */

    Route::prefix('admin/document-requests')
    ->middleware(
        'permission:' .
        Permissions::DOCUMENT_REQUEST_VIEW
    )
    ->group(function () {

        Route::get('/', [DocumentRequestAdminController::class, 'index',])->name(
            'admin.document-requests.index'
        );

        Route::post('/walk-in',
            [DocumentWalkInRequestController::class, 'store']
        );

        Route::get('/{id}', [DocumentRequestAdminController::class, 'show',])->name(
            'admin.document-requests.show'
        );

        Route::post(
                '/{documentRequest}/payment/cash',
                [DocumentPaymentController::class, 'recordCash']
            )
                ->middleware(
                    'permission:' . Permissions::DOCUMENT_REQUEST_PROCESS
                )
                ->name(
                    'document-requests.payment.cash'
                );

    });

    Route::get('/my-household/members', [
        HouseholdController::class,
        'members',
    ])
        // ->middleware('permission:' . Permissions::MY_REQUEST_CREATE)
        ->name('my-household.members');

    Route::prefix('document-requests')
        ->group(function () {

            /*
            |--------------------------------------------------------------------------
            | Resident / General Request Operations -> Resident Side
            |--------------------------------------------------------------------------
            */

            Route::get('/', [
                DocumentRequestController::class,
                'index',
            ])
                ->middleware('permission:' . Permissions::MY_REQUEST_VIEW)
                ->name('document-requests.index');

            Route::post('/', [
                DocumentRequestController::class,
                'store',
            ])
                ->middleware('permission:' . Permissions::MY_REQUEST_CREATE)
                ->name('document-requests.store');

            Route::post('/{documentRequest}/for-signature', [
                DocumentApprovalController::class,
                'forSignature',
            ])
                ->middleware(
                    'permission:' .
                    Permissions::DOCUMENT_REQUEST_PROCESS
                )
                ->name(
                    'document-requests.for-signature'
                );

            // For Previewing Document with For Signature status
            Route::get('/{documentRequest}/preview', [
                DocumentPreviewController::class,
                'show',
            ])
                ->middleware(
                    'permission:' .
                    Permissions::DOCUMENT_REQUEST_VIEW
                )
                ->name(
                    'document-requests.preview'
                );

            Route::post('/{documentRequest}/sign', [
                DocumentSignatureController::class,
                'store',
            ])
                ->middleware(
                    'permission:' .
                    Permissions::DOCUMENT_REQUEST_SIGN
                )
                ->name(
                    'document-requests.sign'
                );
            Route::post('/{documentRequest}/upload-signed-document', [
                DocumentSignatureController::class,
                'uploadSignedDocument',
            ])
                ->middleware(
                    'permission:' .
                    Permissions::DOCUMENT_REQUEST_SIGN
                )
                ->name('document-requests.upload-signed-document');

            Route::post('/{documentRequest}/release', [
                DocumentReleaseController::class,
                'store',
            ])
                ->middleware(
                    'permission:' .
                    Permissions::DOCUMENT_RELEASE
                )
                ->name(
                    'document-requests.release'
                );

            Route::post('/{documentRequest}/download/release', [
                DocumentRequestResidentReleaseController::class,
                'store',
            ])
                ->name(
                    'document-requests.download.release'
                );

            Route::get('/{documentRequest}', [
                DocumentRequestController::class,
                'show',
            ])
                ->middleware('permission:' . Permissions::MY_REQUEST_VIEW)
                ->name('document-requests.show');

            /*
            |--------------------------------------------------------------------------
            | Document Request Attachments
            |--------------------------------------------------------------------------
            */

            Route::get(
                '/{documentRequest}/attachments/{attachment:uuid}/view',
                [
                    DocumentRequestAttachmentController::class,
                    'view',
                ]
            )->name(
                'document-requests.attachments.view'
            );

            Route::get(
                '/{documentRequest}/attachments/{attachment}/download',
                [
                    DocumentRequestAttachmentController::class,
                    'download',
                ]
            )
                // ->middleware(
                //     'permission:' . Permissions::MY_REQUEST_VIEW
                // )
                ->name(
                    'document-requests.attachments.download'
                );

            Route::delete(
                '/{documentRequest}/attachments/{attachment}',
                [
                    DocumentRequestAttachmentController::class,
                    'destroy',
                ]
            )
                ->middleware(
                    'permission:' . Permissions::MY_REQUEST_CREATE
                )
                ->name(
                    'document-requests.attachments.destroy'
                );


            /*
            |--------------------------------------------------------------------------
            | Approval / Review
            |--------------------------------------------------------------------------
            */

            Route::post('/{documentRequest}/review', [
                DocumentApprovalController::class,
                'review',
            ])
                ->middleware('permission:' . Permissions::DOCUMENT_REQUEST_PROCESS)
                ->name('document-requests.review');

            Route::post('/{documentRequest}/approve', [
                DocumentApprovalController::class,
                'approve',
            ])
                ->middleware('permission:' . Permissions::DOCUMENT_REQUEST_APPROVE)
                ->name('document-requests.approve');

            Route::post('/{documentRequest}/reject', [
                DocumentApprovalController::class,
                'reject',
            ])
                ->middleware('permission:' . Permissions::DOCUMENT_REQUEST_REJECT)
                ->name('document-requests.reject');


            /*
            |--------------------------------------------------------------------------
            | Payment
            |--------------------------------------------------------------------------
            */

            Route::post('/{documentRequest}/payment', [
                DocumentPaymentController::class,
                'store',
            ])
                ->middleware('permission:' . Permissions::MY_REQUEST_CREATE)
                ->name('document-requests.payment.store');

            Route::get('/{documentRequest}/payment', [
                DocumentPaymentController::class,
                'show',
            ])
                ->middleware('permission:' . Permissions::MY_REQUEST_VIEW)
                ->name('document-requests.payment.show');

            Route::post(
                '/{documentRequest}/payment/confirm',
                [DocumentPaymentController::class, 'confirm']
            );

            Route::delete('/{documentRequest}/payment', [
                DocumentPaymentController::class,
                'cancel',
            ])
                ->middleware('permission:' . Permissions::MY_REQUEST_CREATE)
                ->name('document-requests.payment.cancel');


            /*
            |--------------------------------------------------------------------------
            | Document Generation
            |--------------------------------------------------------------------------
            */

            // Route::post('/{documentRequest}/generate', [
            //     DocumentGenerationController::class,
            //     'store',
            // ])
            //     ->middleware('permission:' . Permissions::DOCUMENT_GENERATE)
            //     ->name('document-requests.generate');


            /*
            |--------------------------------------------------------------------------
            | Document Release
            |--------------------------------------------------------------------------
            */

            Route::post('/{documentRequest}/release', [
                DocumentReleaseController::class,
                'store',
            ])
                ->middleware('permission:' . Permissions::DOCUMENT_RELEASE)
                ->name('document-requests.release');
        });


    /*
    |--------------------------------------------------------------------------
    | Secure Document Files
    |--------------------------------------------------------------------------
    */

    Route::prefix('document-files')
        ->group(function () {

            Route::get('/{uuid}/view', [
                DocumentFileController::class,
                'view',
            ])
                ->middleware('permission:' . Permissions::DOCUMENT_VIEW)
                ->name('document-files.view');

            Route::get('/{uuid}/download', [
                DocumentFileController::class,
                'download',
            ])
                ->middleware('permission:' . Permissions::DOCUMENT_DOWNLOAD)
                ->name('document-files.download');
        });

    Route::get('complaints-residents',
            [ComplaintController::class, 'residents']
        );

    Route::prefix('prenatal-records')->group(function () {

        // Resident lookup must come before the dynamic record route.
        Route::get('/residents', [
            PrenatalRecordController::class,
            'residents',
        ])->middleware('permission:prenatal_monitoring.create');

        Route::get('/', [
            PrenatalRecordController::class,
            'index',
        ])->middleware('permission:prenatal_monitoring.view');

        Route::post('/', [
            PrenatalRecordController::class,
            'store',
        ])->middleware('permission:prenatal_monitoring.create');

        Route::get('/{prenatalRecord}', [
            PrenatalRecordController::class,
            'show',
        ])->whereNumber('prenatalRecord')
          ->middleware('permission:prenatal_monitoring.view');

        Route::put('/{prenatalRecord}', [
            PrenatalRecordController::class,
            'update',
        ])->whereNumber('prenatalRecord')
          ->middleware('permission:prenatal_monitoring.edit');

        Route::delete('/{prenatalRecord}', [
            PrenatalRecordController::class,
            'destroy',
        ])->whereNumber('prenatalRecord')
          ->middleware('permission:prenatal_monitoring.delete');

        Route::post('/{prenatalRecord}/visits', [
            PrenatalRecordController::class,
            'storeVisit',
        ])->whereNumber('prenatalRecord')
          ->middleware('permission:prenatal_monitoring.create');

        Route::put('/{prenatalRecord}/visits/{visit}', [
            PrenatalRecordController::class,
            'updateVisit',
        ])->whereNumber('prenatalRecord')
          ->whereNumber('visit')
          ->middleware('permission:prenatal_monitoring.edit');

        Route::delete('/{prenatalRecord}/visits/{visit}', [
            PrenatalRecordController::class,
            'destroyVisit',
        ])->whereNumber('prenatalRecord')
          ->whereNumber('visit')
          ->middleware('permission:prenatal_monitoring.delete');
    });

    Route::prefix('child-nutrition-monitorings')->group(function () {

        // Resident lookup must come before the dynamic record route.
        Route::get('/residents', [
            ChildNutritionMonitoringController::class,
            'residents',
        ])->middleware('permission:child_nutrition_monitoring.create');

        Route::get('/', [
            ChildNutritionMonitoringController::class,
            'index',
        ])->middleware('permission:child_nutrition_monitoring.view');

        Route::post('/', [
            ChildNutritionMonitoringController::class,
            'store',
        ])->middleware('permission:child_nutrition_monitoring.create');

        Route::get('/report', [ChildNutritionMonitoringController::class, 'report'])
            ->middleware('permission:child_nutrition_monitoring.view');

        Route::get('/{id}', [
            ChildNutritionMonitoringController::class,
            'show',
        ])->whereNumber('id')
          ->middleware('permission:child_nutrition_monitoring.view');

        Route::put('/{id}', [
            ChildNutritionMonitoringController::class,
            'update',
        ])->whereNumber('id')
          ->middleware('permission:child_nutrition_monitoring.edit');

        Route::delete('/{id}', [
            ChildNutritionMonitoringController::class,
            'destroy',
        ])->whereNumber('id')
          ->middleware('permission:child_nutrition_monitoring.delete');

        // Monthly assessments
        Route::post('/{id}/assessments', [
            ChildNutritionMonitoringController::class,
            'storeAssessment',
        ])->whereNumber('id')
          ->middleware('permission:child_nutrition_monitoring.create');

        Route::put('/{id}/assessments/{assessmentId}', [
            ChildNutritionMonitoringController::class,
            'updateAssessment',
        ])->whereNumber('id')
          ->whereNumber('assessmentId')
          ->middleware('permission:child_nutrition_monitoring.edit');

        Route::delete('/{id}/assessments/{assessmentId}', [
            ChildNutritionMonitoringController::class,
            'destroyAssessment',
        ])->whereNumber('id')
          ->whereNumber('assessmentId')
          ->middleware('permission:child_nutrition_monitoring.delete');
    });

    Route::prefix('child-nutrition-settings')
    ->middleware('role:Super Administrator')
    ->group(function () {

        Route::get('/', [
            ChildNutritionSettingController::class,
            'show',
        ]);

        Route::put('/', [
            ChildNutritionSettingController::class,
            'update',
        ]);
    });

    Route::prefix('payment-collections')
        ->middleware('permission:payment_collection.view')
        ->controller(PaymentCollectionController::class)
        ->group(function () {
            Route::get('/', 'index');
            Route::get('/summary', 'summary');
            Route::get('/{id}', 'show');
        });

});

Route::prefix('complaints')
    ->middleware(['auth:sanctum','onboarding.complete'])
    ->group(function () {

        Route::get('/', [
            ComplaintController::class,
            'index',
        ])
            ->middleware('permission:complaints.view')
            ->name('complaints.index');

        Route::post('/', [
            ComplaintController::class,
            'store',
        ])
            ->middleware('permission:complaints.create')
            ->name('complaints.store');

        Route::post('/historical', [
            ComplaintController::class,
            'storeHistorical',
        ])
            ->middleware(
                'permission:complaints.encode_historical'
            );

        Route::get(
            '/residents/{resident}/complaint-history',
            [
                ComplaintController::class,
                'respondentHistory',
            ]
        )
            ->name(
                'complaints.resident-history'
            );

        Route::post(
            '/{complaint}/hearings/{hearing}/complete',
            [
                ComplaintHearingController::class,
                'complete',
            ]
        )
            ->middleware(
                'permission:complaints.hearing'
            )
            ->name('complaints.hearings.complete');

        Route::post(
            '/{complaint}/hearings/{hearing}/cancel',
            [
                ComplaintHearingController::class,
                'cancel',
            ]
        )
            ->middleware(
                'permission:complaints.hearing'
            )
            ->name('complaints.hearings.cancel');

        Route::post(
            '/{complaint}/hearings/{hearing}/reschedule',
            [
                ComplaintHearingController::class,
                'reschedule',
            ]
        )
            ->middleware(
                'permission:complaints.hearing'
            )
            ->name('complaints.hearings.reschedule');

        Route::post(
            '/{complaint}/hearings/historical',
            [
                ComplaintHearingController::class,
                'storeHistorical',
            ]
        )
            ->middleware(
                'permission:complaints.encode_historical'
            )
            ->name('complaints.hearings.historical.store');

        Route::get('/{complaint}', [
            ComplaintController::class,
            'show',
        ])
            ->name('complaints.show');

        Route::put('/{complaint}', [
            ComplaintController::class,
            'update',
        ])
            ->middleware('permission:complaints.edit')
            ->name('complaints.edit');

        Route::delete('/{complaint}', [
            ComplaintController::class,
            'destroy',
        ])
            ->middleware('permission:complaints.delete')
            ->name('complaints.destroy');

        Route::post(
            '/{complaint}/review',
            [
                ComplaintWorkflowController::class,
                'review',
            ]
        )
            ->middleware(
                'permission:complaints.review'
            )
            ->name('complaints.review');

        Route::post(
            '/{complaint}/assign',
            [
                ComplaintWorkflowController::class,
                'assign',
            ]
        )
            ->middleware(
                'permission:complaints.assign'
            )
            ->name('complaints.assign');

        Route::post(
            '/{complaint}/hearing/schedule',
            [
                ComplaintWorkflowController::class,
                'schedule',
            ]
        )
            ->middleware(
                'permission:complaints.hearing'
            )
            ->name('complaints.hearing.schedule');

        Route::post(
            '/{complaint}/hearing/start',
            [
                ComplaintWorkflowController::class,
                'hearing',
            ]
        )
            ->middleware(
                'permission:complaints.hearing'
            )
            ->name('complaints.hearing.start');

        Route::post(
            '/{complaint}/resolve',
            [
                ComplaintWorkflowController::class,
                'resolve',
            ]
        )
            ->middleware(
                'permission:complaints.resolve'
            )
            ->name('complaints.resolve');

        Route::post(
            '/{complaint}/close',
            [
                ComplaintWorkflowController::class,
                'close',
            ]
        )
            ->middleware(
                'permission:complaints.close'
            )
            ->name('complaints.close');

        Route::post(
            '/{complaint}/reject',
            [
                ComplaintWorkflowController::class,
                'reject',
            ]
        )
            ->middleware(
                'permission:complaints.reject'
            )
            ->name('complaints.reject');

        Route::post(
            '/{complaint}/cancel',
            [
                ComplaintWorkflowController::class,
                'cancel',
            ]
        )
            ->middleware(
                'permission:complaints.cancel'
            )
            ->name('complaints.cancel');

        Route::post(
            '/{complaint}/attachments',
            [
                ComplaintAttachmentController::class,
                'store',
            ]
        )
            ->middleware(
                'permission:complaints.edit'
            )
            ->name('complaints.attachments.store');

        Route::get(
            '/{complaint}/attachments/{attachment:uuid}/view',
            [
                ComplaintAttachmentController::class,
                'view',
            ]
        )
            ->name('complaints.attachments.view');

        Route::get(
            '/{complaint}/attachments/{attachment:uuid}/download',
            [
                ComplaintAttachmentController::class,
                'download',
            ]
        )
            ->name('complaints.attachments.download');

        Route::delete(
            '/{complaint}/attachments/{attachment:uuid}',
            [
                ComplaintAttachmentController::class,
                'destroy',
            ]
        )
            ->middleware(
                'permission:complaints.delete'
            )
            ->name('complaints.attachments.destroy');
    });

Route::prefix('resident/complaints')
    ->middleware(['auth:sanctum','onboarding.complete'])
    ->group(function () {
        Route::get(
            '/my-complaint-history',
            [
                ComplaintResidentController::class,
                'myComplaintHistory',
            ]
        )
            ->name(
                'resident.complaints.my-history'
            );

        Route::get('/types', [
            ComplaintResidentController::class,
            'complaintTypes',
        ])->name('resident.complaints.types');

        Route::get('/respondents', [
            ComplaintResidentController::class,
            'respondents',
        ])->name('resident.complaints.respondents');

        Route::get('/', [
            ComplaintController::class,
            'index',
        ])->name('resident.complaints.index');

        Route::post('/', [
            ComplaintResidentController::class,
            'store',
        ])->name('resident.complaints.store');

        Route::post(
            '/{complaint}/attachments',
            [
                ComplaintAttachmentController::class,
                'store',
            ]
        )->name(
            'resident.complaints.attachments.store'
        );

        Route::get('/{complaint}', [
            ComplaintController::class,
            'show',
        ])->name('resident.complaints.show');
    });

// Document Verification
Route::get(
    '/document-verification/{uuid}',
    [
        DocumentVerificationController::class,
        'show',
    ]
)->name('document-verification.show');

// PayMongo Webhook
Route::post('/payments/paymongo/webhook', [PayMongoWebhookController::class, 'handle']);