<?php

Route::impersonate();
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Route;

//region OUTSIDE API CALLS
Route::any('/text-responses/inbound', 'ResponseConsoleController@inboundText')->name('pub-api.text-response-inbound')->middleware(null);
Route::any('/email-responses/inbound', 'ResponseConsoleController@inboundEmail')->name('pub-api.email-response-inbound')->middleware(null);
Route::any('/email-responses/log', 'ResponseConsoleController@logEmail')->name('pub-api.email-response-log')->middleware(null);
Route::any('/phone-responses/inbound', 'ResponseConsoleController@inboundPhone')->name('pub-api.phone-response-inbound')->middleware(null);
Route::any('/phone-responses/status', 'ResponseConsoleController@inboundPhoneStatus')->name('pub-api.phone-response-status')->middleware(null);

Route::any('/appointments/insert', 'AppointmentController@insert')->name('pub-api.appointment-insert')->middleware(null);
Route::any('/appointments/save', 'AppointmentController@save')->name('pub-api.appointment-save')->middleware(null);
Route::any('/appointments/get', 'AppointmentController@get')->name('pub-api.appointment-get')->middleware(null);
//endregion

//region AUTH
Route::get('login', 'Auth\LoginController@showLoginForm')->name('login');
Route::post('login', 'Auth\LoginController@login')->name('auth.authenticate');
Route::get('logout', 'Auth\LoginController@logout')->name('logout');
Route::post('logout', 'Auth\LoginController@logout')->name('logout');
Route::get('password/reset', 'Auth\ForgotPasswordController@showLinkRequestForm')->name('password.request');
Route::post('password/email', 'Auth\ForgotPasswordController@sendResetLinkEmail')->name('password.email');
Route::get('password/reset/{token}', 'Auth\ResetPasswordController@showResetForm')->name('password.reset');
Route::post('password/reset', 'Auth\ResetPasswordController@reset')->name('password.restore');
//endregion
//
//Route::group(['prefix' => 'new'], function () {
//    Route::get('campaign', function () {
//        return view('campaign.index', [
//            'companies' => [(object) [
//                'label' => 'asdfsaf',
//                'value' => 1
//                ]
//            ]
//        ]);
//    });
//});

// TODO: remove me after testing
Route::get('/pusher-test', function () {

    $labelCounts = App\Models\Recipient::withResponses(20)
        ->selectRaw("sum(interested) as interested, sum(not_interested) as not_interested,
                sum(appointment) as appointment, sum(service) as service, sum(wrong_number) as wrong_number,
                sum(car_sold) as car_sold, sum(heat) as heat_case, sum(callback) as callback,
                sum(case when (interested = 0 and not_interested = 0 and appointment = 0 and service = 0 and
                wrong_number = 0 and car_sold = 0 and heat = 0) then 1 else 0 end) as not_labelled")
        ->first();

    $data = [
        'labelCounts' => array_map('intval', $labelCounts->toArray()),
    ];

    // $data['message'] = 'hello world ' . time();

    $pusher = new Pusher\Pusher(
        env('PUSHER_APP_KEY'),
        env('PUSHER_APP_SECRET'),
        env('PUSHER_APP_ID'),
        [
            'cluster' => env('PUSHER_CLUSTER'),
            'useTLS'  => true,
        ]
    );

    $pusher->trigger("private-campaign.20", 'counts.updated', $data);
    // $pusher->trigger("campaign.20", 'counts.updated', $data);

    return ['success' => true];
});

Route::get('/layout', function () {
    return view('layouts.base');
});
Route::get('/new-dashboard', function () {
    return view('dashboard.index');
});
Route::get('/campaign-dashboard', function () {
    return view('campaign.index');
});
Route::get('/campaign-view', function () {
    return view('campaign.view');
});
Route::get('/user-dashboard', function () {
    return view('user.index');
});
Route::get('/user-view', function () {
    return view('user.view');
});
Route::get('/new-response-console', function () {
    return view('campaign.console');
});

// TODO: remove me when original route is done
Route::get('/new-response-console', function () {
    return view('campaign.console');
});

//region AUTHENTICATED REQUESTS ONLY
Route::group(['middleware' => 'auth'], function () {

    // TODO: move to better location
    Route::get('/current-user', function (){
        return Auth::user();
    });

    Route::get('/dashboard', 'HomeController@index')->middleware('check.active.company')->name('dashboard');
    Route::get('/dashboard', 'HomeController@index')->middleware('check.active.company')->name('dashboard');
    Route::get('/', function () {
        return redirect()->route('dashboard');
    });

    Route::post('/appointment/{appointment}/update-called-status', 'AppointmentController@updateCalledStatus')->name('appointment.update-called-status')->middleware('can:change-console');
    Route::post('/callback/{appointment}/update-called-status', 'AppointmentController@updateCalledStatus')->name('callback.update-called-status')->middleware('can:change-console');

    //region PROFILE
    Route::group(['prefix' => 'profile'], function () {
        Route::get('', 'ProfileController@index')->name('profile.index');
        Route::post('', 'ProfileController@update')->name('profile.update');
        Route::post('password', 'ProfileController@updatePassword')->name('profile.update-password');
        Route::post('/company-data', 'ProfileController@updateCompanyData')->name('profile.update-company-data');
    });
    //endregion

    //region ADMIN
    Route::group(['prefix' => 'admin'], function () {
        Route::get('/resend-invitation', 'AdminController@resendInvitation')->name('admin.resend-invitation')->middleware('can:resend-invitation,App\Models\User');
        Route::get('/impersonate/leave', 'AdminController@impersonateLeave')->name('admin.impersonate-leave');
        Route::get('/impersonate/{user}', 'AdminController@impersonateUser')->name('admin.impersonate')->middleware('can:impersonate,App\Models\User');
    });
    //endregion

    //region APPOINTMENT
    Route::group(['prefix' => 'appointment'], function () {
        Route::get('for-calendar-display', 'AppointmentController@getForCalendarDisplay')->name('appointment.for-calendar-display');
    });
    //endregion

    //region DROP
    Route::group(['prefix' => 'drop'], function () {
        Route::get('for-calendar-display', 'DropController@getForCalendarDisplay')->name('drop.for-calendar-display');
    });
    //endregion

    //region SELECTOR
    Route::group(['prefix' => 'selector'], function () {
        Route::get('', 'SelectorController@show')->name('selector.select-active-company');
        Route::post('', 'SelectorController@updateActiveCompany')->name('selector.update-active-company');
    });
    //endregion

    //region USER
    Route::group(['prefix' => 'user'], function () {
        Route::get('', 'UserController@index')->name('user.index')->middleware(['check.active.company', 'can:list,App\Models\User']);
        Route::get('for-user-display', 'UserController@getForUserDisplay')->middleware(['check.active.company', 'can:list,App\Models\User'])->name('user.for-user-display');
        Route::get('/create', 'UserController@create')->name('user.create')->middleware(['check.active.company', 'can:create-user,App\Models\User']);
        Route::get('{user}', 'UserController@view')->name('user.view')->middleware(['check.active.company', 'can:list,App\Models\User']);
        Route::get('/{user}/edit', 'UserController@edit')->name('user.edit')->middleware(['check.active.company', 'can:edit-user,user']);
        Route::get('/{user}/activate', 'UserController@activate')->name('user.activate')->middleware(['check.active.company', 'can:edit-user,user']);
        Route::get('/{user}/deactivate', 'UserController@deactivate')->name('user.deactivate')->middleware(['check.active.company', 'can:edit-user,user']);
        Route::post('', 'UserController@store')->name('user.store')->middleware(['check.active.company', 'can:create-user,App\Models\User']);
        Route::post('{user}', 'UserController@update')->name('user.update')->middleware(['check.active.company', 'can:create-user,App\Models\User']);
        Route::post('{user}/avatar', 'UserController@updateAvatar')->name('user.update-avatar')->middleware(['check.active.company', 'can:create-user,App\Models\User']);
        Route::post('{user}/company-data', 'UserController@updateCompanyData')->name('user.update-company-data')->middleware(['check.active.company', 'can:create-user,App\Models\User']);
        Route::delete('/{user}', 'UserController@delete')->name('user.delete')->middleware(['check.active.company', 'can:delete-user,App\Models\User']);
    });
    //endregion

    //region TEMPLATES
    Route::get('/templates', 'TemplateController@index')->name('template.index')->middleware('can:view-templates');
    Route::get('/templates/for-user-display', 'TemplateController@getForUserDisplay')->name('template.for-user-display')->middleware('can:view-templates');
    Route::group(['prefix' => 'template'], function () {
        Route::get('', 'TemplateController@index')->name('template.index')->middleware('can:view-templates');
        Route::get('/create-form', 'TemplateController@createForm')->name('template.create-form')->middleware('can:change-templates');
        Route::post('/create', 'TemplateController@create')->name('template.create')->middleware('can:change-templates');
        Route::group(['prefix' => '{template}', 'middleware' => 'can:view-templates'], function () {
            Route::get('/', 'TemplateController@show')->name('template.show');
            Route::post('/json', 'TemplateController@showJson')->name('template.show-json');
            Route::get('/edit', 'TemplateController@editForm')->name('template.edit')->middleware('can:change-templates');
            Route::post('/update', 'TemplateController@update')->name('template.update')->middleware('can:change-templates');
            Route::delete('/delete', 'TemplateController@delete')->name('template.delete')->middleware('can:change-templates');
        });
    });
    //endregion

    //region TEMPLATE BUILDER
    Route::group(['prefix' => 'template-builder', 'middleware' => 'can:admin-only'], function () {
        Route::get('', 'TemplateBuildController@index')->name('template-builder.index');
        Route::get('editor', 'TemplateBuildController@showEditor')->name('template-builder.show-editor');
        Route::get('templates/{template}/{templateName}', 'TemplateBuildController@getTemplate')->name('template-builder.get-template');
        Route::get('upload', 'TemplateBuildController@getImageList')->name('template-builder.get-image-list');
        Route::post('upload', 'TemplateBuildController@uploadImage')->name('template-builder.upload-image');
        Route::get('img', 'TemplateBuildController@getImage')->name('template-builder.get-image');
        Route::get('templates/{template}/edres/{file}', 'TemplateBuildController@getEdresFile')->name('template-builder.get-edres-file');
        Route::post('dl', 'TemplateBuildController@download')->name('template-builder.download-post');
        Route::get('dl', 'TemplateBuildController@download')->name('template-builder.download-get');
        Route::post('create', 'TemplateBuildController@createTemplate')->name('template-builder.store');
    });
    //endregion

    //region CAMPAIGN
    Route::get('/campaigns', 'CampaignController@index')->name('campaign.index')->middleware('can:view-campaigns');
    Route::get('/campaigns/for-user-display', 'CampaignController@getForUserDisplay')->name('campaign.for-user-display');
    Route::get('/campaigns/user/{user}', 'CampaignController@getUserCampaigns')->name('campaign.user.show')->middleware('can:view-campaigns');
    Route::get('/campaigns/new', 'CampaignController@createNew')->middleware('can:change-campaigns');
    Route::post('/campaigns/create', 'CampaignController@create')->middleware('can:change-campaigns');
    Route::group(['prefix' => '/campaign/{campaign}', 'middleware' => ['check.active.company','can:view,campaign']], function () {
        Route::get('/', 'CampaignController@show')->name('campaign.view');
        Route::delete('/', 'CampaignController@delete');
        Route::get('/details', 'CampaignController@details');
        Route::get('/edit', 'CampaignController@edit')->name('campaign.edit');
        Route::get('/delete', 'CampaignController@delete')->name('campaign.delete');
        Route::post('/update', 'CampaignController@update');
        // Recipient list pages
        Route::get('/recipients', 'RecipientController@show')->name('campaign.recipient.index');
        Route::get('/recipients/for-user-display', 'ResponseConsoleController@getRecipientsForUserDisplay')->name('campaign.recipient.for-user-display');
        Route::post('/recipient-list/upload', 'RecipientController@uploadFile')->name('recipient-list.upload');
        Route::get('/recipient-list/{id}', 'RecipientController@showRecipientList')->name('recipient-list.show');
        Route::get('/recipient-list/delete/{list}', 'RecipientController@deleteRecipientList')->name('recipient-list.delete');
        Route::post('/recipient-list/{list}/delete-stats', 'RecipientController@recipientListDeleteStats')->name('recipient-list.delete-stats');
        Route::post('/recipient-list', 'RecipientController@fromCampaign');
        Route::post('/add-recipient', 'RecipientController@add');
        Route::put('/update-recipient', 'RecipientController@update');
        Route::delete('/remove-recipient', 'RecipientController@delete')->name('recipient.delete');
        Route::get('/recipients/partialByField', 'RecipientController@getPartialRecipientsByField');
        Route::post('/recipients/deletePartialByField', 'RecipientController@deletePartialRecipientsByField');
        Route::get('/recipients/search', 'RecipientController@searchForDeployment');
        Route::post('/recipients/upload', 'RecipientController@createRecipientList');
        Route::any('/recipients/finalize_upload', 'RecipientController@finishUpload');
        Route::get('/recipients/download', 'RecipientController@download');
        Route::get('/recipients/delete-all', 'RecipientController@deleteAll');
        // End of Recipient list pages
        Route::get('phone-list', 'PhoneController@fromCampaign');
        Route::get('phone-list-json', 'PhoneController@fromCampaignAsJson')->middleware('can:view-campaigns');
        Route::post('phone/{phone}/edit', 'PhoneController@edit')->middleware('can:change-campaigns')->name('phone-number.edit');
        Route::post('phone/{phone}/release', 'PhoneController@release')->middleware('can:change-campaigns')->name('phone-number.release');
        Route::get('/drops', 'DeploymentController@forCampaign')->middleware('can:view-campaigns');
        Route::get('/drops', 'DeploymentController@forCampaign')->name('campaign.drop.index');
        Route::get('/drop/{drop}', 'DeploymentController@show');
        Route::post('/drop/{deployment}/update', 'DeploymentController@update');
        Route::get('/drops/new', 'DeploymentController@createNew')->name('campaign.drop.create');
        Route::post('/drops/create', 'DeploymentController@create');
        Route::post('/drops/add-groups', 'DeploymentController@saveGroups');
        Route::post('/drop/{drop}/send-sms/{recipient}', 'DeploymentController@deploySms');
        Route::get('/drop/{drop}/edit', 'DeploymentController@updateForm');
        Route::get('/responses', 'ResponseController@getCampaignResponses');
        Route::get('/responses/export-responders', 'ResponseController@getAllResponders');
        Route::get('/responses/export-nonresponders', 'ResponseController@getNonResponders');
        Route::any('/get-responses-hash', 'ResponseController@getResponsesHash');
        Route::any('/responses/{recipient}/get-text-hash', 'ResponseController@getTextHash');
        Route::any('/responses/{recipient}/add-appointment', 'AppointmentController@addAppointmentFromConsole')->middleware('can:view-console')->name('add-appointment');
        Route::any('/responses/{recipient}/get-email-hash', 'ResponseController@getEmailHash');
        Route::any('/responses/{recipient}/get-text-thread', 'ResponseController@getTextThread');
        Route::any('/responses/{recipient}/get-email-thread', 'ResponseController@getEmailThread');
        Route::any('/get-response-list', 'ResponseController@getResponseList');
        // TODO: old route
        // Route::get('/response/{recipient}', 'ResponseController@getResponse');
        Route::get('/response/{recipient}', 'ResponseController@getResponseJson');
        Route::post('/text-response/{recipient}', 'ResponseConsoleController@smsReply');
        Route::post('/email-response/{recipient}', 'ResponseConsoleController@emailReply')->middleware('can:respond-console');
        Route::get('/response-console', 'ResponseConsoleController@show')->name('campaign.response-console.index');
        Route::get('/response-console/unread', 'ResponseConsoleController@showUnread')->name('campaign.response-console.index.unread');
        Route::get('/response-console/idle', 'ResponseConsoleController@showIdle')->name('campaign.response-console.index.idle');
        Route::get('/response-console/archived', 'ResponseConsoleController@showArchived')->name('campaign.response-console.index.archived');
        Route::get('/response-console/labelled/{label}', 'ResponseConsoleController@showLabelled')->name('campaign.response-console.index.labelled');
        Route::get('/response-console/calls', 'ResponseConsoleController@showCalls')->name('campaign.response-console.index.call');
        Route::get('/response-console/sms', 'ResponseConsoleController@showTexts')->name('campaign.response-console.index.sms');
        Route::get('/response-console/email', 'ResponseConsoleController@showEmails')->name('campaign.response-console.index.email');
    });
    //endregion

    //region RECIPIENT
    Route::group(['prefix' => '/recipient/{recipient}', 'middleware' => ['check.active.company','can:update,recipient']], function () {
        Route::post('/add-label', 'RecipientController@addLabel')->name('recipient.add-label');
        Route::post('/remove-label', 'RecipientController@removeLabel')->name('recipient.remove-label');
        Route::post('/update-notes', 'RecipientController@updateNotes')->name('recipient.update-notes');
        Route::get('/get-responses-by-recipient', 'RecipientController@fetchResponsesByRecipient')->name('recipient.get-responses');
    });
    //endregion

    //region DEPLOYMENT
    /* TODO: change nomenclature to Drops */
    Route::group(['prefix' => '/drop/{deployment}', 'middleware' => 'can:change-campaigns'], function () {
        Route::get('/pause', 'DeploymentController@pause')->name('deployment.pause');
        Route::get('/resume', 'DeploymentController@resume')->name('deployment.resume');
        Route::post('/delete', 'DeploymentController@delete')->name('deployment.delete');
    });
    //endregion

    //region PHONES
    Route::group(['prefix' => '/phones', 'middleware' => 'can:change-campaigns'], function () {
        Route::post('search', 'PhoneController@searchAvailable')->name('phone.search');
        Route::post('provision', 'PhoneController@provision')->name('phone.provision');
        // Route::get('list-unused', 'PhoneController@showUnused'); // Future improvement
        // Route::post('release', 'PhoneController@releaseNumber'); // Future improvement
    });
    //endregion

    //region RESPONSE
    Route::post('/response/{response}/update-read-status', 'ResponseController@updateReadStatus')->name('response.update-read-status')->middleware('can:change-console');
    //endregion

    //region SYSTEM
    Route::group(['prefix' => 'system', 'middleware' => 'can:admin-only'], function () {
        Route::get('drops', 'SystemController@index')->name('system.drop.index');
        Route::get('reports', 'SystemController@index')->name('system.report.index');
    });
    //endregion

    //region COMPANIES
    Route::group(['prefix' => 'companies'], function () {
        Route::get('', 'CompanyController@index')->middleware('can:create')->name('company.index');
        Route::get('for-dropdown', 'CompanyController@getForDropdown')->name('company.for-dropdown');
        Route::get('for-user-display', 'CompanyController@getForUserDisplay')->name('company.for-user-display');
        Route::get('/create', 'CompanyController@create')->middleware('can:create,App\Models\Company')->name('company.create');
        Route::get('/{company}/edit', 'CompanyController@edit')->middleware('can:edit,company')->name('company.edit');
        Route::post('/{company}', 'CompanyController@update')->middleware('can:edit,company')->name('company.update');
        Route::post('/', 'CompanyController@store')->middleware('can:create,App\Models\Company')->name('company.store');
        Route::get('/{company}/campaign', 'CompanyController@campaignIndex')->middleware('can:viewForPreferences,company')->name('company.campaign.index');
        Route::get('/{company}/edit', 'CompanyController@edit')->middleware('can:manage,company')->name('company.edit');
        Route::delete('/{company}', 'CompanyController@delete')->middleware('can:manage,company')->name('company.delete');

        Route::group(['prefix' => '/{company}/user'], function () {
            Route::get('', 'CompanyController@userIndex')->middleware('can:manage,company')->name('company.user.index');
            Route::get('/create', 'CompanyController@userCreate')->middleware('can:manage,company')->name('company.user.create');
            Route::post('', 'CompanyController@userStore')->middleware('can:manage,company')->name('company.user.store');
            Route::get('/{user}/edit', 'CompanyController@userEdit')->middleware('can:manage,company')->name('company.user.edit');
            Route::post('/{user}', 'CompanyController@userUpdate')->middleware('can:manage,company')->name('company.user.update');
        });
    });
    //endregion
});
//endregion

//region NEW ROUTES
Route::group(['prefix' => 'registration'], function () {
    Route::get('/complete', 'Auth\CompleteController@show')->middleware('signed', 'justinvited')->name('registration.complete.show');
    Route::post('/complete', 'Auth\CompleteController@set')->middleware('signed')->name('registration.complete.store');
});
//endregion
