<?php

namespace App\Controllers;

use App\Database;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use PDO;

class AuthController
{
    public function __construct(private Database $database)
    {
    }

    public function login(
        ServerRequestInterface $request,
        ResponseInterface      $response
    ): ResponseInterface
    {
        $data = $request->getParsedBody();
        $email = $data['email'] ?? null;
        $password = $data['password'] ?? null;

        if (!$email || !$password) {
            return $this->json($response, [
                'error' => 'Email and password are required'
            ], 400);
        }

        $stmt = $this->database->prepare("
            SELECT id, name, email, password, is_admin
            FROM users
            WHERE email = :email AND is_deleted = 0
            LIMIT 1
        ");

        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || !password_verify($password, $user['password'])) {
            return $this->json($response, [
                'error' => 'Invalid credentials'
            ], 401);
        }

        $accessTokenTtl = (int)$this->env('JWT_EXPIRES_IN', '900');
        $refreshTokenTtl = (int)$this->env('REFRESH_TOKEN_EXPIRES_IN', '2592000');
        $now = time();

        $accessToken = $this->createJwt([
            'sub' => (int)$user['id'],
            'email' => $user['email'],
            'is_admin' => (int)$user['is_admin'],
            'iat' => $now,
            'exp' => $now + $accessTokenTtl,
            'iss' => $this->env('JWT_ISSUER', 'sunday-picks-api')
        ]);

        $refreshToken = bin2hex(random_bytes(48));
        $refreshTokenHash = hash('sha256', $refreshToken);
        $refreshExpiresAt = date('Y-m-d H:i:s', $now + $refreshTokenTtl);

        $insert = $this->database->prepare("
            INSERT INTO refresh_tokens (user_id, token_hash, expires_at)
            VALUES (:user_id, :token_hash, :expires_at)
        ");
        $insert->execute([
            ':user_id' => $user['id'],
            ':token_hash' => $refreshTokenHash,
            ':expires_at' => $refreshExpiresAt
        ]);

        return $this->json($response, [
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'token_type' => 'Bearer',
            'expires_in' => $accessTokenTtl,
            'refresh_expires_in' => $refreshTokenTtl,
            'user' => [
                'id' => (int)$user['id'],
                'name' => $user['name'],
                'email' => $user['email'],
                'is_admin' => (int)$user['is_admin']
            ]
        ]);
    }

    public function logout(
        ServerRequestInterface $request,
        ResponseInterface      $response
    ): ResponseInterface
    {
        $auth = $request->getAttribute('auth');
        $userId = is_array($auth) ? (int)($auth['sub'] ?? 0) : 0;
        if ($userId <= 0) {
            return $this->json($response, [
                'error' => 'Invalid access token payload'
            ], 401);
        }

        $stmt = $this->database->prepare("
            UPDATE refresh_tokens
            SET revoked_at = CURRENT_TIMESTAMP
            WHERE user_id = :user_id
              AND revoked_at IS NULL
        ");
        $stmt->execute([
            ':user_id' => $userId
        ]);

        return $this->json($response, [
            'message' => 'Logout successful'
        ]);
    }

    public function refresh(
        ServerRequestInterface $request,
        ResponseInterface      $response
    ): ResponseInterface
    {
        $data = $request->getParsedBody();
        $refreshToken = $data['refresh_token'] ?? null;

        if (!$refreshToken) {
            return $this->json($response, [
                'error' => 'refresh_token is required'
            ], 400);
        }

        $refreshTokenHash = hash('sha256', $refreshToken);
        $stmt = $this->database->prepare("
            SELECT
                rt.id AS refresh_token_id,
                u.id,
                u.name,
                u.email,
                u.is_admin
            FROM refresh_tokens rt
            INNER JOIN users u ON u.id = rt.user_id
            WHERE rt.token_hash = :token_hash
              AND rt.revoked_at IS NULL
              AND rt.expires_at > CURRENT_TIMESTAMP
              AND u.is_deleted = 0
            LIMIT 1
        ");
        $stmt->execute([':token_hash' => $refreshTokenHash]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            return $this->json($response, [
                'error' => 'Invalid or expired refresh token'
            ], 401);
        }

        $revoke = $this->database->prepare("
            UPDATE refresh_tokens
            SET revoked_at = CURRENT_TIMESTAMP
            WHERE id = :id AND revoked_at IS NULL
        ");
        $revoke->execute([':id' => $user['refresh_token_id']]);

        $accessTokenTtl = (int)$this->env('JWT_EXPIRES_IN', '900');
        $refreshTokenTtl = (int)$this->env('REFRESH_TOKEN_EXPIRES_IN', '2592000');
        $now = time();

        $accessToken = $this->createJwt([
            'sub' => (int)$user['id'],
            'email' => $user['email'],
            'is_admin' => (int)$user['is_admin'],
            'iat' => $now,
            'exp' => $now + $accessTokenTtl,
            'iss' => $this->env('JWT_ISSUER', 'sunday-picks-api')
        ]);

        $newRefreshToken = bin2hex(random_bytes(48));
        $newRefreshTokenHash = hash('sha256', $newRefreshToken);
        $refreshExpiresAt = date('Y-m-d H:i:s', $now + $refreshTokenTtl);

        $insert = $this->database->prepare("
            INSERT INTO refresh_tokens (user_id, token_hash, expires_at)
            VALUES (:user_id, :token_hash, :expires_at)
        ");
        $insert->execute([
            ':user_id' => $user['id'],
            ':token_hash' => $newRefreshTokenHash,
            ':expires_at' => $refreshExpiresAt
        ]);

        return $this->json($response, [
            'access_token' => $accessToken,
            'refresh_token' => $newRefreshToken,
            'token_type' => 'Bearer',
            'expires_in' => $accessTokenTtl,
            'refresh_expires_in' => $refreshTokenTtl,
            'user' => [
                'id' => (int)$user['id'],
                'name' => $user['name'],
                'email' => $user['email'],
                'is_admin' => (int)$user['is_admin']
            ]
        ]);
    }

    public function changePassword(
        ServerRequestInterface $request,
        ResponseInterface      $response
    ): ResponseInterface
    {
        $auth = $request->getAttribute('auth');
        $userId = is_array($auth) ? (int)($auth['sub'] ?? 0) : 0;
        if ($userId <= 0) {
            return $this->json($response, [
                'error' => 'Invalid access token payload'
            ], 401);
        }

        $data = $request->getParsedBody() ?? [];
        $currentPassword = $data['current_password'] ?? null;
        $newPassword = $data['new_password'] ?? null;
        $newPasswordConfirmation = $data['new_password_confirmation'] ?? null;

        if (!$currentPassword || !$newPassword || !$newPasswordConfirmation) {
            return $this->json($response, [
                'error' => 'current_password, new_password and new_password_confirmation are required'
            ], 400);
        }

        if ($newPassword !== $newPasswordConfirmation) {
            return $this->json($response, [
                'error' => 'new_password and new_password_confirmation must match'
            ], 400);
        }

        if (strlen($newPassword) < 8) {
            return $this->json($response, [
                'error' => 'new_password must be at least 8 characters'
            ], 400);
        }

        $userStmt = $this->database->prepare("
            SELECT id, password
            FROM users
            WHERE id = :id AND is_deleted = 0
            LIMIT 1
        ");
        $userStmt->execute([':id' => $userId]);
        $user = $userStmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            return $this->json($response, [
                'error' => 'User not found'
            ], 404);
        }

        if (!password_verify($currentPassword, $user['password'])) {
            return $this->json($response, [
                'error' => 'Current password is incorrect'
            ], 401);
        }

        if (password_verify($newPassword, $user['password'])) {
            return $this->json($response, [
                'error' => 'New password must be different from current password'
            ], 400);
        }

        $newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);

        $updateUser = $this->database->prepare("
            UPDATE users
            SET password = :password, updated_at = CURRENT_TIMESTAMP
            WHERE id = :id
        ");
        $updateUser->execute([
            ':password' => $newPasswordHash,
            ':id' => $userId
        ]);

        $revokeRefreshTokens = $this->database->prepare("
            UPDATE refresh_tokens
            SET revoked_at = CURRENT_TIMESTAMP
            WHERE user_id = :user_id
              AND revoked_at IS NULL
        ");
        $revokeRefreshTokens->execute([
            ':user_id' => $userId
        ]);

        return $this->json($response, [
            'message' => 'Password updated successfully'
        ]);
    }

    private function createJwt(array $payload): string
    {
        $header = ['alg' => 'HS256', 'typ' => 'JWT'];
        $headerEncoded = $this->base64UrlEncode(json_encode($header));
        $payloadEncoded = $this->base64UrlEncode(json_encode($payload));
        $signature = hash_hmac(
            'sha256',
            $headerEncoded . '.' . $payloadEncoded,
            $this->env('JWT_SECRET', 'local-dev-secret'),
            true
        );
        $signatureEncoded = $this->base64UrlEncode($signature);

        return $headerEncoded . '.' . $payloadEncoded . '.' . $signatureEncoded;
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

    private function json(
        ResponseInterface $response,
        array $payload,
        int $status = 200
    ): ResponseInterface
    {
        $response->getBody()->write(json_encode($payload));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($status);
    }
}
