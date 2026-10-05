<?php

use Illuminate\Support\Facades\Route;
use Tinify\Statamic\Http\Controllers\AccountUsageController;
use Tinify\Statamic\Http\Controllers\CompressLibraryController;

Route::group(['prefix' => 'tinify', 'as' => 'tinify.'], function () {
    Route::get('account-usage', AccountUsageController::class)->name('account-usage');
    Route::post('compress-library', CompressLibraryController::class)->name('compress-library');
});
