<?php

Route::group(['middleware' => ['web', 'auth'], 'namespace' => 'Modules\MFSEssentials\Http\Controllers'], function () {
    Route::post('/mfsessentials/reactions/toggle', ['uses' => 'ReactionsController@toggle'])->name('mfsessentials.reactions.toggle');
    Route::post('/mfsessentials/thread/convert', ['uses' => 'ConvertController@convert'])->name('mfsessentials.thread.convert');
    // 1.4.0: per-agent preferences (split view, divider, column widths, hidden statuses).
    Route::post('/mfsessentials/prefs', ['uses' => 'PrefsController@save'])->name('mfsessentials.prefs');
});

// Licence (card #194): admins only, same route shape as MSTeamsFS.
Route::group(['middleware' => ['web', 'auth', 'roles'], 'roles' => ['admin'], 'namespace' => 'Modules\MFSEssentials\Http\Controllers'], function () {
    Route::post('/admin/mfsessentials/license/manage', 'MFSEssentialsController@manageLicense')->name('mfsessentials.license.manage');
    Route::post('/admin/mfsessentials/module-license-action', 'MFSEssentialsController@handleModuleLicenseAction')->name('mfsessentials.module.license.action');
});
