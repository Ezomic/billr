<?php

use App\Http\Controllers\Auth\InvitationController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\ClientPortalController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\Portal\DashboardController as PortalDashboardController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\Settings\MemberController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\WorkspaceController as SettingsWorkspaceController;
use App\Http\Controllers\StripeWebhookController;
use App\Http\Controllers\TimeEntryController;
use App\Http\Controllers\WorkspaceController;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('login'));

// Stripe webhook — exempt from CSRF
Route::post('/stripe/webhook', [StripeWebhookController::class, 'handle'])
    ->name('stripe.webhook')
    ->withoutMiddleware([VerifyCsrfToken::class]);

// Dev-only shortcut
if (app()->isLocal()) {
    Route::get('/dev-login', function () {
        $user = User::where('email', 'dev@billr.test')->firstOrFail();
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

// Client timesheet approval portal — token-based, no login required
Route::get('/client-portal/{token}', [ClientPortalController::class, 'show'])->name('client-portal.show');
Route::post('/client-portal/{token}/approve', [ClientPortalController::class, 'approve'])->name('client-portal.approve');

// Authenticated routes
Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    // Workspace management
    Route::middleware('can:access-workspace')->group(function () {
        Route::post('/workspaces', [WorkspaceController::class, 'store'])->name('workspaces.store');
        Route::post('/workspaces/{workspace}/switch', [WorkspaceController::class, 'switch'])->name('workspaces.switch');
    });

    // Freelancer app
    Route::middleware('can:access-workspace')->group(function () {
        Route::get('/dashboard', DashboardController::class)->name('dashboard');

        // Clients
        Route::get('/clients', [ClientController::class, 'index'])->name('clients.index');
        Route::post('/clients', [ClientController::class, 'store'])->name('clients.store');
        Route::get('/clients/{client}', [ClientController::class, 'show'])->name('clients.show');
        Route::put('/clients/{client}', [ClientController::class, 'update'])->name('clients.update');
        Route::delete('/clients/{client}', [ClientController::class, 'destroy'])->name('clients.destroy');
        Route::post('/clients/{client}/portal-access', [ClientPortalController::class, 'sendAccess'])->name('clients.portal-access');

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
        Route::post('/invoices/{invoice}/send', [InvoiceController::class, 'send'])->name('invoices.send');
        Route::post('/invoices/{invoice}/payment-link', [InvoiceController::class, 'generatePaymentLink'])->name('invoices.payment-link');
        Route::post('/invoices/{invoice}/sent', [InvoiceController::class, 'markSent'])->name('invoices.sent');
        Route::post('/invoices/{invoice}/paid', [InvoiceController::class, 'markPaid'])->name('invoices.paid');
        Route::delete('/invoices/{invoice}', [InvoiceController::class, 'destroy'])->name('invoices.destroy');
        Route::get('/invoices/unbilled-entries', [InvoiceController::class, 'unbilledEntries'])->name('invoices.unbilled-entries');

        // Settings
        Route::prefix('settings')->name('settings.')->group(function () {
            Route::get('/profile', [ProfileController::class, 'show'])->name('profile');
            Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
            Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');

            Route::get('/workspace', [SettingsWorkspaceController::class, 'show'])->name('workspace');
            Route::put('/workspace', [SettingsWorkspaceController::class, 'update'])->name('workspace.update');

            Route::get('/members', [MemberController::class, 'show'])->name('members');
            Route::post('/members/invite', [MemberController::class, 'invite'])->name('members.invite');
            Route::delete('/members/{user}', [MemberController::class, 'remove'])->name('members.remove');
        });
    });

    // Client portal
    Route::prefix('portal')->name('portal.')->middleware('can:access-portal')->group(function () {
        Route::get('/dashboard', PortalDashboardController::class)->name('dashboard');
        Route::get('/invoices/{invoice}', [App\Http\Controllers\Portal\InvoiceController::class, 'show'])->name('invoices.show');
    });
});
