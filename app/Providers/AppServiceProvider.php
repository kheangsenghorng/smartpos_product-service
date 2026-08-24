<?php

namespace App\Providers;

use Dedoc\Scramble\Scramble;
use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Request::macro('businessUuid', function () {
            /** @var Request $this */
            return $this->attributes->get('auth_business_uuid')
                ?? $this->attributes->get('business_uuid')
                ?? $this->header('X-Business-Uuid')
                ?? $this->input('business_uuid');
        });

        Request::macro('userUuid', function () {
            /** @var Request $this */
            return $this->attributes->get('auth_user_uuid')
                ?? $this->attributes->get('user_uuid');
        });

        Request::macro('isGlobalAdmin', function () {
            /** @var Request $this */
            $roles = (array) $this->attributes->get('auth_roles', []);
            $payload = (array) $this->attributes->get('jwt_payload', []);

            return in_array('admin', $roles, true)
                || in_array('super_admin', $roles, true)
                || in_array('superadmin', $roles, true)
                || !empty($payload['is_admin']);
        });

        Scramble::configure()
            ->expose(
                ui: '/docs/products',
                document: '/docs/products.json',
            );

        Scramble::afterOpenApiGenerated(function (\Dedoc\Scramble\Support\Generator\OpenApi $openApi) {
            $openApi->secure(
                \Dedoc\Scramble\Support\Generator\SecurityScheme::http('bearer', 'JWT')
            );
        });
    }
}
