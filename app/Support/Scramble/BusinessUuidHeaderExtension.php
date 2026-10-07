<?php

namespace App\Support\Scramble;

use Dedoc\Scramble\Extensions\OperationExtension;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\Parameter;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\RouteInfo;

class BusinessUuidHeaderExtension extends OperationExtension
{
    public function handle(Operation $operation, RouteInfo $routeInfo): void
    {
        // Only apply to protected API routes
        $uri = $routeInfo->route->uri();
        if (! str_starts_with($uri, 'api/v1/') || str_contains($uri, 'health') || str_contains($uri, 'docs')) {
            return;
        }

        // Avoid duplicate if already declared on method/controller
        foreach ($operation->parameters as $param) {
            if (strtolower($param->name) === 'x-business-uuid') {
                return;
            }
        }

        $headerParam = Parameter::make('X-Business-Uuid', 'header')
            ->description('Active business UUID for tenant context. Required if business_uuid is not provided in JWT token or query parameters.')
            ->required(false)
            ->setSchema(Schema::fromType((new StringType)->format('uuid')));

        $operation->addParameters([$headerParam]);
    }
}
