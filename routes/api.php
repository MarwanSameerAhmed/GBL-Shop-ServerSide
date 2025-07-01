<?php

use App\Http\Controllers\CategoriesController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ItemsController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::get('/Categories/GetMainCategories', CategoriesController::class . '@GetApiMainCategories');
Route::get('/Categories/GetSubCategories', CategoriesController::class . '@GetApiSubCategories');
Route::post('/Categories/CreateCategory', CategoriesController::class . '@CreateCategory');
Route::post('/Items/CreateItem', ItemsController::class . '@CreateItem');
Route::post('/Items/DeleteItem', ItemsController::class . '@DeleteItem');
Route::post('/Categories/DeleteCategory', CategoriesController::class . '@DeleteCategory');

