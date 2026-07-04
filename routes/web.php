<?php

use App\Http\Controllers\Auth\InvitationController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\Portal\DashboardController as PortalDashboardController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\Settings\MemberController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\WorkspaceController;
use App\Http\Controllers\TimeEntryController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('login'));

// Dev-only shortcut
if (app()->isLocal()) {
    Route::get('/dev-login', function () {
        $user = \App\Models\User::where('email', 'dev@billr.test')->firstOrFail();
        auth()->login($user);
        return redirect()->route('dashboard');
    })->name('dev-login');
}

// Guest routes
Route::middleware('guest')->group(function () {
    Route::get('/register', [RegisterController::class, 'show'])->name('register');
    Route::post('/register', [RegisterController::class, 'store']);

    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'store']);

    Route::get('/invitations/{token}', [InvitationController::class, 'show'])->name('invitations.show');
    Route::post('/invitations/{token}', [InvitationController::class, 'store'])->name('invitations.accept');
});

// Authenticated routes
Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    // Freelancer app
    Route::middleware('can:access-workspace')->group(function () {
        Route::get('/dashboard', DashboardController::class)->name('dashboard');

        // Clients
        Route::get('/clients', [ClientController::class, 'index'])->name('clients.index');
        Route::post('/clients', [ClientController::class, 'store'])->name('clients.store');
        Route::put('/clients/{client}', [ClientController::class, 'update'])->name('clients.update');
        Route::delete('/clients/{client}', [ClientController::class, 'destroy'])->name('clients.destroy');

        // Projects
        Route::get('/projects', [ProjectController::class, 'index'])->name('projects.index');
        Route::post('/projects', [ProjectController::class, 'store'])->name('projects.store');
        Route::put('/projects/{project}', [ProjectController::class, 'update'])->name('projects.update');
        Route::delete('/projects/{project}', [ProjectController::class, 'destroy'])->name('projects.destroy');

        // Time entries
        Route::get('/time', [TimeEntryController::class, 'index'])->name('time.index');
        Route::post('/time', [TimeEntryController::class, 'store'])->name('time.store');
        Route::post('/time/start/{project}', [TimeEntryController::class, 'start'])->name('time.start');
        Route::post('/time/{entry}/stop', [TimeEntryController::class, 'stop'])->name('time.stop');
        Route::put('/time/{entry}', [TimeEntryController::class, 'update'])->name('time.update');
        Route::delete('/time/{entry}', [TimeEntryController::class, 'destroy'])->name('time.destroy');

        // Invoices
        Route::get('/invoices', [InvoiceController::class, 'index'])->name('invoices.index');
        Route::get('/invoices/create', [InvoiceController::class, 'create'])->name('invoices.create');
        Route::post('/invoices', [InvoiceController::class, 'store'])->name('invoices.store');
        Route::get('/invoices/{invoice}', [InvoiceController::class, 'show'])->name('invoices.show');
        Route::post('/invoices/{invoice}/sent', [InvoiceController::class, 'markSent'])->name('invoices.sent');
        Route::post('/invoices/{invoice}/paid', [InvoiceController::class, 'markPaid'])->name('invoices.paid');
        Route::delete('/invoices/{invoice}', [InvoiceController::class, 'destroy'])->name('invoices.destroy');
        Route::get('/invoices/unbilled-entries', [InvoiceController::class, 'unbilledEntries'])->name('invoices.unbilled-entries');

        // Settings
        Route::prefix('settings')->name('settings.')->group(function () {
            Route::get('/profile', [ProfileController::class, 'show'])->name('profile');
            Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
            Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');

            Route::get('/workspace', [WorkspaceController::class, 'show'])->name('workspace');
            Route::put('/workspace', [WorkspaceController::class, 'update'])->name('workspace.update');

            Route::get('/members', [MemberController::class, 'show'])->name('members');
            Route::post('/members/invite', [MemberController::class, 'invite'])->name('members.invite');
            Route::delete('/members/{user}', [MemberController::class, 'remove'])->name('members.remove');
        });
    });

    // Client portal
    Route::prefix('portal')->name('portal.')->middleware('can:access-portal')->group(function () {
        Route::get('/dashboard', PortalDashboardController::class)->name('dashboard');
        Route::get('/invoices/{invoice}', [\App\Http\Controllers\Portal\InvoiceController::class, 'show'])->name('invoices.show');
    });
});
