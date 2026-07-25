<?php

use Illuminate\Support\Facades\Route;
use Modules\Enrollment\Http\Controllers\EnrollmentController;

Route::get('/health', [EnrollmentController::class, 'health']);