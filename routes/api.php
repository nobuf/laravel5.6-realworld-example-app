<?php

use Illuminate\Http\Request;

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

Route::group([

    'middleware' => 'api',

], function ($router) {

    Route::post('/users', 'Api\UserController@store');
    Route::post('/users/login', 'Api\UserController@login');

});

Route::group([

    'middleware' => 'auth:api',

], function ($router) {

    Route::get('/user', 'Api\UserController@show');

});
