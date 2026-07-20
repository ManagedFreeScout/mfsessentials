<?php

Route::group(['middleware' => ['web', 'auth'], 'namespace' => 'Modules\MFSEssentials\Http\Controllers'], function () {
    Route::post('/mfsessentials/reactions/toggle', ['uses' => 'ReactionsController@toggle'])->name('mfsessentials.reactions.toggle');
});
