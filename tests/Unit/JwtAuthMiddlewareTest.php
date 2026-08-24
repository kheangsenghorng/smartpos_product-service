<?php

namespace Tests\Unit;

use App\Http\Middleware\JwtAuthMiddleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class JwtAuthMiddlewareTest extends TestCase
{
    private JwtAuthMiddleware $middleware;

    protected function setUp(): void
    {
        parent::setUp();
        $this->middleware = new JwtAuthMiddleware();
    }

    public function test_rejects_missing_authorization_header(): void
    {
        $request = Request::create('/api/products', 'GET');

        $response = $this->middleware->handle($request, function () {
            return response()->json(['success' => true]);
        });

        $this->assertEquals(401, $response->getStatusCode());
        $this->assertEquals('Unauthenticated.', json_decode($response->getContent(), true)['message']);
    }

    public function test_rejects_invalid_jwt_format(): void
    {
        $request = Request::create('/api/products', 'GET');
        $request->headers->set('Authorization', 'Bearer invalid-token');

        $response = $this->middleware->handle($request, function () {
            return response()->json(['success' => true]);
        });

        $this->assertEquals(401, $response->getStatusCode());
        $this->assertEquals('Invalid or expired token.', json_decode($response->getContent(), true)['message']);
    }

    public function test_rejects_expired_token(): void
    {
        $secret = config('jwt.secret');
        $header = rtrim(strtr(base64_encode(json_encode(['typ' => 'JWT', 'alg' => 'HS256'])), '+/', '-_'), '=');
        $payload = rtrim(strtr(base64_encode(json_encode([
            'iss' => 'smartpos-auth-service',
            'sub' => 'user-123',
            'exp' => time() - 3600,
        ])), '+/', '-_'), '=');
        $sig = rtrim(strtr(base64_encode(hash_hmac('sha256', "$header.$payload", $secret, true)), '+/', '-_'), '=');

        $token = "$header.$payload.$sig";

        $request = Request::create('/api/products', 'GET');
        $request->headers->set('Authorization', "Bearer $token");

        $response = $this->middleware->handle($request, function () {
            return response()->json(['success' => true]);
        });

        $this->assertEquals(401, $response->getStatusCode());
        $this->assertEquals('Invalid or expired token.', json_decode($response->getContent(), true)['message']);
    }

    public function test_accepts_valid_token_and_sets_attributes(): void
    {
        $secret = config('jwt.secret');
        $header = rtrim(strtr(base64_encode(json_encode(['typ' => 'JWT', 'alg' => 'HS256'])), '+/', '-_'), '=');
        $payloadData = [
            'iss' => 'smartpos-auth-service',
            'sub' => 'user-123',
            'user_uuid' => 'user-123',
            'business_uuid' => 'biz-456',
            'permissions' => ['products.read', 'products.write'],
            'roles' => ['manager'],
            'exp' => time() + 3600,
        ];
        $payload = rtrim(strtr(base64_encode(json_encode($payloadData)), '+/', '-_'), '=');
        $sig = rtrim(strtr(base64_encode(hash_hmac('sha256', "$header.$payload", $secret, true)), '+/', '-_'), '=');

        $token = "$header.$payload.$sig";

        $request = Request::create('/api/products', 'GET');
        $request->headers->set('Authorization', "Bearer $token");

        $nextCalled = false;
        $response = $this->middleware->handle($request, function (Request $req) use (&$nextCalled) {
            $nextCalled = true;
            $this->assertEquals('user-123', $req->attributes->get('user_uuid'));
            $this->assertEquals('user-123', $req->attributes->get('auth_user_uuid'));
            $this->assertEquals('biz-456', $req->attributes->get('business_uuid'));
            $this->assertEquals('biz-456', $req->attributes->get('auth_business_uuid'));
            $this->assertEquals(['products.read', 'products.write'], $req->attributes->get('jwt_permissions'));
            $this->assertEquals(['manager'], $req->attributes->get('jwt_roles'));
            return response()->json(['success' => true]);
        });

        $this->assertTrue($nextCalled);
        $this->assertEquals(200, $response->getStatusCode());
    }
}
