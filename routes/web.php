<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CategoriesController;
use App\Http\Controllers\ItemsController;
use App\Models\Item;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

Route::get('/Categories', CategoriesController::class . '@GetCategories');
Route::get('/Categories/{key}', CategoriesController::class . '@GetSubCategories');
Route::get('/Items/Search/', ItemsController::class . '@Search');
Route::get('/Items/{key}', ItemsController::class . '@GetItems');
Route::get('/Item/{key}', ItemsController::class . '@GetItem');

