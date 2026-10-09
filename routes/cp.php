<?php

use Illuminate\Support\Facades\Route;
use ThijsDeZoete\TinifyStatamic\Http\Controllers\AccountUsageController;
use ThijsDeZoete\TinifyStatamic\Http\Controllers\CompressLibraryController;

Route::group(['prefix' => 'tinify', 'as' => 'tinify.'], function () {
    Route::get('account-usage', AccountUsageController::class)->name('account-usage');
    Route::post('compress-library', CompressLibraryController::class)->name('compress-library');
});
