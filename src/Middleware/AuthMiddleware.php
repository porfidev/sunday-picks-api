<?php

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;

class AuthMiddleware implements MiddlewareInterface
{
    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface
    {
        $token = $this->extractBearerToken($request->getHeaderLine('Authorization'));
        if ($token === null) {
            return $this->jsonResponse(401, [
                'error' => 'Unauthorized',
                'code' => 'missing_token',
                'message' => 'Access token is required'
            ]);
        }

        $validation = $this->validateJwt($token);
        if (!$validation['valid']) {
            if ($validation['reason'] === 'expired') {
                return $this->jsonResponse(401, [
                    'error' => 'Unauthorized',
                    'code' => 'token_expired',
                    'message' => 'Access token has expired'
                ]);
            }

            return $this->jsonResponse(401, [
                'error' => 'Unauthorized',
                'code' => 'invalid_token',
                'message' => 'Access token is invalid'
            ]);
        }

        $request = $request->withAttribute('auth', $validation['payload']);
        return $handler->handle($request);
    }

    private function extractBearerToken(string $header): ?string
    {
        if ($header === '') {
            return null;
        }

        if (!preg_match('/^Bearer\s+(.+)$/i', $header, $matches)) {
            return null;
        }

        $token = trim($matches[1]);
        return $token === '' ? null : $token;
    }

    private function validateJwt(string $token): array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return ['valid' => false, 'reason' => 'invalid', 'payload' => null];
        }

        [$headerEncoded, $payloadEncoded, $signatureEncoded] = $parts;
        $headerJson = $this->base64UrlDecode($headerEncoded);
        $payloadJson = $this->base64UrlDecode($payloadEncoded);

        if ($headerJson === false || $payloadJson === false) {
            return ['valid' => false, 'reason' => 'invalid', 'payload' => null];
        }

        $header = json_decode($headerJson, true);
        $payload = json_decode($payloadJson, true);

        if (!is_array($header) || !is_array($payload)) {
            return ['valid' => false, 'reason' => 'invalid', 'payload' => null];
        }

        if (($header['alg'] ?? null) !== 'HS256') {
            return ['valid' => false, 'reason' => 'invalid', 'payload' => null];
        }

        $expectedSignature = $this->base64UrlEncode(hash_hmac(
            'sha256',
            $headerEncoded . '.' . $payloadEncoded,
            $this->env('JWT_SECRET', 'local-dev-secret'),
            true
        ));

        if (!hash_equals($expectedSignature, $signatureEncoded)) {
            return ['valid' => false, 'reason' => 'invalid', 'payload' => null];
        }

        $exp = $payload['exp'] ?? null;
        if (!is_numeric($exp)) {
            return ['valid' => false, 'reason' => 'invalid', 'payload' => null];
        }

        if ((int)$exp < time()) {
            return ['valid' => false, 'reason' => 'expired', 'payload' => null];
        }

        $expectedIssuer = $this->env('JWT_ISSUER', 'sunday-picks-api');
        if (($payload['iss'] ?? null) !== $expectedIssuer) {
            return ['valid' => false, 'reason' => 'invalid', 'payload' => null];
        }

        return ['valid' => true, 'reason' => null, 'payload' => $payload];
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $data): string|false
    {
        $padded = str_pad(
            strtr($data, '-_', '+/'),
            strlen($data) + (4 - strlen($data) % 4) % 4,
            '=',
            STR_PAD_RIGHT
        );

        return base64_decode($padded, true);
    }

    private function env(string $key, string $default): string
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

        if ($value === false || $value === null || $value === '') {
            return $default;
        }

        return (string)$value;
    }

    private function jsonResponse(int $status, array $payload): ResponseInterface
    {
        $response = new Response($status);
        $response->getBody()->write(json_encode($payload));
        return $response->withHeader('Content-Type', 'application/json');
    }
}
