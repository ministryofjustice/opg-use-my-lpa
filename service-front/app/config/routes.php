<?php

declare(strict_types=1);

use Mezzio\Application;
use Mezzio\MiddlewareFactory;
use Psr\Container\ContainerInterface;

/**
 * Setup routes with a single request method:
 *
 * $app->get('/', App\Handler\HomePageHandler::class, 'home');
 * $app->post('/album', App\Handler\AlbumCreateHandler::class, 'album.create');
 * $app->put('/album/:id', App\Handler\AlbumUpdateHandler::class, 'album.put');
 * $app->patch('/album/:id', App\Handler\AlbumUpdateHandler::class, 'album.patch');
 * $app->delete('/album/:id', App\Handler\AlbumDeleteHandler::class, 'album.delete');
 *
 * Or with multiple request methods:
 *
 * $app->route('/contact', App\Handler\ContactHandler::class, ['GET', 'POST', ...], 'contact');
 *
 * Or handling all request methods:
 *
 * $app->route('/contact', App\Handler\ContactHandler::class)->setName('contact');
 *
 * or:
 *
 * $app->route(
 *     '/contact',
 *     App\Handler\ContactHandler::class,
 *     Mezzio\Router\Route::HTTP_METHOD_ANY,
 *     'contact'
 * );
 */

$viewerRoutes = function (Application $app, MiddlewareFactory $factory, ContainerInterface $container): void {
    $app->get('/healthcheck', Common\Handler\HealthcheckHandler::class, 'healthcheck');
    $app->route('/home', Viewer\Handler\EnterCodeHandler::class, ['GET', 'POST'], 'home');
    $app->route('/', Viewer\Handler\EnterCodeHandler::class, ['GET', 'POST'], 'home-trial');
    $app->get('/check-code', Viewer\Handler\CheckCodeHandler::class, 'check-code');
    $app->get('/view-lpa', Viewer\Handler\ViewLpaHandler::class, 'view-lpa');
    $app->get('/download-lpa', Viewer\Handler\DownloadLpaHandler::class, 'download-lpa');
    $app->get('/terms-of-use', Viewer\Handler\ViewerTermsOfUseHandler::class, 'viewer-terms-of-use');
    $app->get('/privacy-notice', Viewer\Handler\ViewerPrivacyNoticeHandler::class, 'viewer-privacy-notice');
    $app->get('/stats', Viewer\Handler\StatsPageHandler::class, 'viewer-stats');
    $app->get('/session-expired', Viewer\Handler\ViewerSessionExpiredHandler::class, 'session-expired');
    $app->route('/cookies', Common\Handler\CookiesPageHandler::class, ['GET', 'POST'], 'viewer-cookies');
};

$actorRoutes = function (Application $app, MiddlewareFactory $factory, ContainerInterface $container): void {
    $app->route('/home', Actor\Handler\ActorTriagePageHandler::class, ['GET', 'POST'], 'home');
    $app->route('/', Actor\Handler\ActorTriagePageHandler::class, ['GET', 'POST'], 'home-trial');
    $app->get('/healthcheck', Common\Handler\HealthcheckHandler::class, 'healthcheck');
    $app->get('/stats', Actor\Handler\StatsPageHandler::class, 'actor-stats');

    $app->route('/cookies', Common\Handler\CookiesPageHandler::class, ['GET', 'POST'], 'actor-cookies');
    $app->get('/terms-of-use', [Actor\Handler\ActorTermsOfUseHandler::class], 'actor-terms-of-use');
    $app->get('/privacy-notice', [Actor\Handler\ActorPrivacyNoticeHandler::class], 'actor-privacy-notice');

    // User creation
    $app->route('/create-account', Actor\Handler\CreateAccountHandler::class, ['GET', 'POST'], 'create-account');
    $app->get('/create-account-success', Actor\Handler\CreateAccountSuccessHandler::class, 'create-account-success');
    $app->get('/activate-account/{token}', Actor\Handler\ActivateAccountHandler::class, 'activate-account');

    // User auth
    $app->route('/login', Actor\Handler\LoginPageHandler::class, ['GET', 'POST'], 'login');
    $app->get('/logout', Actor\Handler\LogoutPageHandler::class, 'logout');
    $app->get('/session-expired', Actor\Handler\ActorSessionExpiredHandler::class, 'session-expired');

    // User management
    $app->route(
        '/forgot-password',
        Actor\Handler\PasswordResetRequestPageHandler::class,
        ['GET', 'POST'],
        'password-reset'
    );
    $app->route(
        '/forgot-password/{token}',
        Actor\Handler\PasswordResetPageHandler::class,
        ['GET', 'POST'],
        'password-reset-token'
    );
    $app->get('/verify-new-email/{token}', [
        Actor\Handler\CompleteChangeEmailHandler::class,
    ], 'verify-new-email');

    // User deletion
    $app->get('/confirm-delete-account', [
        Mezzio\Authentication\AuthenticationMiddleware::class,
        Actor\Handler\ConfirmDeleteAccountHandler::class], 'confirm-delete-account');
    $app->get('/delete-account', [
        Mezzio\Authentication\AuthenticationMiddleware::class,
        Actor\Handler\DeleteAccountHandler::class], 'delete-account');

    // User details
    $app->get('/your-details', [
        Mezzio\Authentication\AuthenticationMiddleware::class,
        Actor\Handler\YourDetailsHandler::class,
    ], 'your-details');
    $app->route('/change-password', [
        Mezzio\Authentication\AuthenticationMiddleware::class,
        Actor\Handler\ChangePasswordHandler::class
    ], ['GET','POST'], 'change-password');
    $app->route('/change-email', [
        Mezzio\Authentication\AuthenticationMiddleware::class,
        Actor\Handler\RequestChangeEmailHandler::class
    ], ['GET','POST'], 'change-email');
    $app->get('/lpa/change-details', [
        Mezzio\Authentication\AuthenticationMiddleware::class,
        Actor\Handler\ChangeDetailsHandler::class
    ], 'lpa.change-details');

    // LPA management
    $app->get('/lpa/dashboard', [
        Mezzio\Authentication\AuthenticationMiddleware::class,
        Actor\Handler\LpaDashboardHandler::class
    ], 'lpa.dashboard');
    $app->route('/lpa/add-details', [
        Mezzio\Authentication\AuthenticationMiddleware::class,
        Actor\Handler\LpaAddHandler::class
    ], ['GET', 'POST'], 'lpa.add');
    $app->route('/lpa/check', [
        Mezzio\Authentication\AuthenticationMiddleware::class,
        Actor\Handler\CheckLpaHandler::class
    ], ['GET', 'POST'], 'lpa.check');
    $app->get('/lpa/view-lpa', [
        Mezzio\Authentication\AuthenticationMiddleware::class,
        Actor\Handler\ViewLpaSummaryHandler::class
    ], 'lpa.view');
    $app->route('/lpa/code-make', [
        Mezzio\Authentication\AuthenticationMiddleware::class,
        Actor\Handler\CreateViewerCodeHandler::class
    ], ['GET', 'POST'], 'lpa.create-code');
    $app->route('/lpa/access-codes', [
        Mezzio\Authentication\AuthenticationMiddleware::class,
        Actor\Handler\CheckAccessCodesHandler::class
    ], ['GET', 'POST'], 'lpa.access-codes');
    $app->post('/lpa/confirm-cancel-code', [
        Mezzio\Authentication\AuthenticationMiddleware::class,
        Actor\Handler\ConfirmCancelCodeHandler::class
    ], 'lpa.confirm-cancel-code');
    $app->post('/lpa/cancel-code', [
        Mezzio\Authentication\AuthenticationMiddleware::class,
        Actor\Handler\CancelCodeHandler::class
    ], 'lpa.cancel-code');
    $app->get('/lpa/removed', [
        Mezzio\Authentication\AuthenticationMiddleware::class,
        Actor\Handler\LpaRemovedHandler::class
    ], 'lpa.removed');
    $app->get('/lpa/instructions-preferences', [
        Mezzio\Authentication\AuthenticationMiddleware::class,
        Actor\Handler\InstructionsPreferencesHandler::class
    ], 'lpa.instructions-preferences');
    $app->get('/lpa/death-notification', [
        Mezzio\Authentication\AuthenticationMiddleware::class,
        Actor\Handler\DeathNotificationHandler::class
    ], 'lpa.death-notification');
};

switch (getenv('CONTEXT')) {
    case 'viewer':
        return $viewerRoutes;
    case 'actor':
        return $actorRoutes;
    default:
        throw new Error('Unknown context');
}
