<?php

use Illuminate\Support\Facades\Route;
use Modules\Commands\Http\Controllers\CommandsController;

Route::get('/health', [CommandsController::class, 'health']);