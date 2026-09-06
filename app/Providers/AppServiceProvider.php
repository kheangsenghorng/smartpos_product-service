<?php

namespace App\Providers;

use Dedoc\Scramble\Scramble;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // 📋 Custom Failed Job Provider: atomically inserts tenant metadata & error summary
        $this->app->extend('queue.failer', function ($service, $app) {
            $config = $app['config']['queue.failed'] ?? [];

            return new class(
                $app['db'],
                $config['database'] ?? null,
                $config['table'] ?? 'failed_jobs'
            ) extends \Illuminate\Queue\Failed\DatabaseUuidFailedJobProvider {
                public function log($connection, $queue, $payload, $exception)
                {
                    $data = json_decode($payload, true);
                    $displayName = $data['displayName'] ?? null;
                    $command = isset($data['data']['command']) ? @unserialize($data['data']['command']) : null;
                    $businessUuid = $command->businessUuid ?? ($command->report->business_uuid ?? null);
                    $errorSummary = \Illuminate\Support\Str::limit($exception->getMessage(), 500);

                    $id = (string) \Illuminate\Support\Str::uuid();

                    $this->getTable()->insert([
                        'uuid' => $id,
                        'connection' => $connection,
                        'queue' => $queue,
                        'business_uuid' => $businessUuid,
                        'job_name' => $displayName,
                        'payload' => $payload,
                        'exception' => (string) $exception,
                        'error_summary' => $errorSummary,
                        'failed_at' => \Illuminate\Support\Facades\Date::now(),
                    ]);

                    return $id;
                }
            };
        });
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

        Gate::define('viewApiDocs', function ($user = null) {
            return true;
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

        // 🛡️ Security Rate Limiters to protect against DoS, brute-force & API flooding
        \Illuminate\Support\Facades\RateLimiter::for('api', function (Request $request) {
            $identifier = $request->attributes->get('auth_user_uuid')
                ?: $request->attributes->get('auth_business_uuid')
                ?: $request->ip();

            return \Illuminate\Cache\RateLimiting\Limit::perMinute(120)->by($identifier)->response(function () {
                return response()->json([
                    'success' => false,
                    'message' => 'Too many requests. Please slow down.',
                ], 429);
            });
        });

        \Illuminate\Support\Facades\RateLimiter::for('heavy-ops', function (Request $request) {
            $identifier = $request->attributes->get('auth_user_uuid')
                ?: $request->attributes->get('auth_business_uuid')
                ?: $request->ip();

            return \Illuminate\Cache\RateLimiting\Limit::perMinute(30)->by($identifier)->response(function () {
                return response()->json([
                    'success' => false,
                    'message' => 'Rate limit exceeded for resource-intensive operations. Please try again later.',
                ], 429);
            });
        });

        // 🔄 Observers for POS cache invalidation
        \App\Models\Product::observe(\App\Observers\ProductObserver::class);
    }
}
