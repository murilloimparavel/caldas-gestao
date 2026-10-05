<?php

namespace App\Providers;

use App\Http\Controllers\Integrations\Passport\AccessTokenController;
use App\Http\Controllers\Integrations\Passport\ApproveAuthorizationController;
use App\Http\Controllers\Integrations\Passport\AuthorizationController;
use App\Http\Controllers\Integrations\Passport\DenyAuthorizationController;
use App\Http\Middleware\RequireIntegrationOAuthEnabled;
use App\Http\Middleware\RequireOAuthAuthorizationContext;
use App\Http\Middleware\RequirePasskeyStepUp;
use App\Support\Integrations\OAuthContextBinding;
use App\Support\Integrations\Passport\AccessTokenRepository;
use App\Support\Integrations\Passport\AuthCodeRepository;
use App\Support\Integrations\Passport\RefreshTokenRepository;
use Carbon\CarbonInterval;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Bridge\AccessTokenRepository as PassportAccessTokenRepository;
use Laravel\Passport\Bridge\AuthCodeRepository as PassportAuthCodeRepository;
use Laravel\Passport\Bridge\RefreshTokenRepository as PassportRefreshTokenRepository;
use Laravel\Passport\Contracts\AuthorizationViewResponse;
use Laravel\Passport\Passport;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\HttpFoundation\Response;

class IntegrationPassportServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        Passport::$deviceCodeGrantEnabled = false;
        $this->app->scoped(OAuthContextBinding::class, fn (): OAuthContextBinding => new OAuthContextBinding);
        $this->app->bind(PassportAccessTokenRepository::class, AccessTokenRepository::class);
        $this->app->bind(PassportAuthCodeRepository::class, AuthCodeRepository::class);
        $this->app->bind(PassportRefreshTokenRepository::class, RefreshTokenRepository::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        Passport::tokensCan([
            'context:read' => 'Read the authenticated integration context.',
            'operations:propose' => 'Propose an operation for review by an administrator.',
            'catalog:read' => 'Read approved catalog and setup fields for the bound unit.',
            'setup:read' => 'Read minimized setup and booking readiness indicators for the bound unit.',
            'mcp:use' => 'Use the approved MCP integration tools.',
        ]);
        Passport::tokensExpireIn(CarbonInterval::minutes((int) config('integration.oauth.access_token_ttl_minutes', 15)));
        Passport::refreshTokensExpireIn(CarbonInterval::days((int) config('integration.oauth.refresh_token_ttl_days', 30)));
        Passport::personalAccessTokensExpireIn(CarbonInterval::days(30));

        $this->app->booted(fn (): null => $this->hardenPassportRoutes());
    }

    private function hardenPassportRoutes(): void
    {
        $routes = Route::getRoutes();

        foreach ([
            'passport.authorizations.authorize',
            'passport.authorizations.approve',
            'passport.authorizations.deny',
            'passport.token',
            'passport.token.refresh',
        ] as $name) {
            $route = $routes->getByName($name);

            if ($route !== null) {
                $route->middleware(RequireIntegrationOAuthEnabled::class);
            }
        }

        $authorize = $routes->getByName('passport.authorizations.authorize');
        $approve = $routes->getByName('passport.authorizations.approve');
        $deny = $routes->getByName('passport.authorizations.deny');
        $token = $routes->getByName('passport.token');
        $refresh = $routes->getByName('passport.token.refresh');
        $webAuthMiddleware = ['web', config('passport.guard') ? 'auth:'.config('passport.guard') : 'auth'];

        if ($authorize !== null) {
            $authorize->setAction(['as' => $authorize->getName(), 'uses' => function (
                ServerRequestInterface $psrRequest,
                Request $request,
                ResponseInterface $psrResponse,
                AuthorizationViewResponse $viewResponse,
            ): Response|AuthorizationViewResponse {
                return app(AuthorizationController::class)->authorize($psrRequest, $request, $psrResponse, $viewResponse);
            }, 'middleware' => ['web', RequireIntegrationOAuthEnabled::class, RequireOAuthAuthorizationContext::class]]);
        }

        if ($approve !== null) {
            $approve->setAction(['as' => $approve->getName(), 'uses' => function (
                Request $request,
                ResponseInterface $psrResponse,
            ): Response {
                return app(ApproveAuthorizationController::class)->approve($request, $psrResponse);
            }, 'middleware' => array_merge($webAuthMiddleware, [
                RequireIntegrationOAuthEnabled::class,
                RequireOAuthAuthorizationContext::class,
                RequirePasskeyStepUp::class.':oauth.consent',
            ])]);
        }

        if ($deny !== null) {
            $deny->setAction(['as' => $deny->getName(), 'uses' => function (
                Request $request,
                ResponseInterface $psrResponse,
            ): Response {
                return app(DenyAuthorizationController::class)->deny($request, $psrResponse);
            }, 'middleware' => array_merge($webAuthMiddleware, [RequireIntegrationOAuthEnabled::class])]);
        }

        foreach ([$token, $refresh] as $route) {
            if ($route !== null) {
                $route->setAction(['as' => $route->getName(), 'uses' => function (
                    ServerRequestInterface $psrRequest,
                    ResponseInterface $psrResponse,
                ): Response {
                    return app(AccessTokenController::class)->issueToken($psrRequest, $psrResponse);
                }, 'middleware' => $route === $token
                    ? ['throttle', RequireIntegrationOAuthEnabled::class]
                    : array_merge($webAuthMiddleware, [RequireIntegrationOAuthEnabled::class])]);
            }
        }
    }
}
