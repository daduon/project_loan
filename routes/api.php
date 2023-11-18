<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CompanyProfileController;
use App\Http\Controllers\BorrowMasterController;
use App\Http\Controllers\BorrowScheduleController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\CustomerTypeController;
use App\Http\Controllers\SummaryController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::post('login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('user', [AuthController::class, 'user']);
    Route::post('logout', [AuthController::class, 'logout']);

    //Customer Type
    Route::resource('customer_types', CustomerTypeController::class);
    // Customer
    Route::resource('customers', CustomerController::class);
    // Notification
    Route::get('notifications', [CustomerController::class, 'notifications']);
    // List Customer For paying by monthly
    Route::get('customers_monthly/{month}', [CustomerController::class, 'customersMonthly']);
});

Route::get('allcustomers', [CustomerController::class, 'getallcustomer']);

//CompanyProfile
Route::get('CompanyProfile/{id}', [CompanyProfileController::class, 'GetById']);
Route::get('CompanyProfile', [CompanyProfileController::class, 'GetAll']);
Route::post('CompanyProfile', [CompanyProfileController::class, 'Add']);
Route::put('CompanyProfile/{id}', [CompanyProfileController::class, 'Update']);
Route::delete('CompanyProfile/{id}', [CompanyProfileController::class, 'Delete']);
Route::put('CompanyProfileChangeImage/{id}', [CompanyProfileController::class, 'ChangeImage']);
Route::put('CompanyProfileChangeName/{id}', [CompanyProfileController::class, 'ChangeName']);

// borrow route
Route::get('retriveborrower', [BorrowMasterController::class, 'retrieveListBorrower']);
Route::post('createborrower', [BorrowMasterController::class, 'createBorrower']);
Route::post('updateborrower/{id}', [BorrowMasterController::class, 'updateBorrower']);
Route::get('retriveborrower/{id}/{lId}', [BorrowMasterController::class, 'retrieveSchedulePayment']);
Route::get('test', [BorrowMasterController::class, 'test']);
//Route::get('test', [BorrowMasterController::class, 'CreateLoan']);
Route::get('payloanbyid/{id}/{brid}/{seq}', [BorrowScheduleController::class, 'updatePaidLoan']);
Route::put('unpayloanbyid/{id}', [BorrowScheduleController::class, 'updateUnpaidLoan']);

//Summary
Route::get('getsummarybycus/{id}', [SummaryController::class, 'GetSummaryByCusId']);
Route::get('getpaidsummarybycus/{id}', [SummaryController::class, 'GetPaidSummaryByCusId']);
Route::get('getunpaidsummarybycus/{id}', [SummaryController::class, 'GetUnpaidSummaryByCusId']);
Route::get('getborrowidbycus/{id}', [SummaryController::class, 'GetBorrowIdByCusId']);
Route::get('getsummarybycusbyborrow/{id}/{bid}', [SummaryController::class, 'GetSummaryByCusIdByBorrowId']);
Route::get('getsummaryprint/{id}/{bid}', [SummaryController::class, 'GetSummaryPrintByCusIdByBorrowId']);
Route::get('getpaidsummarybycusbyborrow/{id}/{bid}', [SummaryController::class, 'GetPaidSummaryByCusIdByBorrowId']);
Route::get('getunpaidsummarybycusbyborrow/{id}/{bid}', [SummaryController::class, 'GetUnpaidSummaryByCusIdByBorrowId']);
