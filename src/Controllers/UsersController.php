<?php

namespace App\Controllers;

use App\Database;
use PDOException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use PDO;

class UsersController
{
    public function __construct(private Database $database)
    {
    }

    public function register(
        ServerRequestInterface $request,
        ResponseInterface      $response
    ): ResponseInterface
    {

        $data = $request->getParsedBody();

        $name = $data['name'] ?? null;
        $phone = $data['phone'] ?? null;
        $email = $data['email'] ?? null;
        $password = $data['password'] ?? null;
        $isAdmin = isset($data['is_admin']) ? (int)$data['is_admin'] : 0;

        if (!$name || !$phone || !$email || !$password) {
            $response->getBody()->write(json_encode([
                'error' => 'Missing required fields'
            ]));
            return $response->withStatus(400)
                ->withHeader('Content-Type', 'application/json');
        }

        // Hashear password (nunca guardar plano)
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $this->database->prepare("
            INSERT INTO users (name, phone, email, password, is_admin)
            VALUES (:name, :phone, :email, :password, :is_admin)
        ");

        try {
            $stmt->execute([
                ':name' => $name,
                ':phone' => $phone,
                ':email' => $email,
                ':password' => $hashedPassword,
                ':is_admin' => $isAdmin
            ]);
        } catch (PDOException $e) {

            $response->getBody()->write(json_encode([
                'error' => 'Email already exists'
            ]));

            return $response->withStatus(409)
                ->withHeader('Content-Type', 'application/json');
        }

        $userId = $this->database->lastInsertId();

        $response->getBody()->write(json_encode([
            'id' => $userId,
            'name' => $name,
            'email' => $email,
            'is_admin' => $isAdmin,
            'is_deleted' => 0
        ]));

        return $response->withHeader('Content-Type', 'application/json')
            ->withStatus(201);
    }

    public function update(
        ServerRequestInterface $request,
        ResponseInterface      $response,
        array                  $args
    ): ResponseInterface
    {

        $id = $args['id'] ?? null;

        if (!$id) {
            $response->getBody()->write(json_encode([
                'error' => 'User id is required'
            ]));
            return $response->withStatus(400)
                ->withHeader('Content-Type', 'application/json');
        }

        $data = $request->getParsedBody();

        $name = $data['name'] ?? null;
        $phone = $data['phone'] ?? null;
        $email = $data['email'] ?? null;
        $isAdmin = isset($data['is_admin']) ? (int)$data['is_admin'] : null;
        $isDeleted = isset($data['is_deleted']) ? (int)$data['is_deleted'] : null;

        // Verificar si el usuario existe
        $stmt = $this->database->prepare("SELECT id FROM users WHERE id = :id");
        $stmt->execute([':id' => $id]);

        if (!$stmt->fetch()) {
            $response->getBody()->write(json_encode([
                'error' => 'User not found'
            ]));
            return $response->withStatus(404)
                ->withHeader('Content-Type', 'application/json');
        }

        $fields = [];
        $params = [':id' => $id];

        if ($name !== null) {
            $fields[] = "name = :name";
            $params[':name'] = $name;
        }

        if ($phone !== null) {
            $fields[] = "phone = :phone";
            $params[':phone'] = $phone;
        }

        if ($email !== null) {
            $fields[] = "email = :email";
            $params[':email'] = $email;
        }

        if ($isAdmin !== null) {
            $fields[] = "is_admin = :is_admin";
            $params[':is_admin'] = $isAdmin;
        }

        if ($isDeleted !== null) {
            $fields[] = "is_deleted = :is_deleted";
            $params[':is_deleted'] = $isDeleted;
        }

        $fields[] = "updated_at = CURRENT_TIMESTAMP";

        $sql = "UPDATE users SET " . implode(', ', $fields) . " WHERE id = :id";
        $updateStmt = $this->database->prepare($sql);
        $updateStmt->execute($params);

        $response->getBody()->write(json_encode([
            'message' => 'User updated successfully'
        ]));

        return $response->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    }

    public function delete(
        ServerRequestInterface $request,
        ResponseInterface      $response,
        array                  $args
    ): ResponseInterface
    {

        $id = $args['id'] ?? null;

        if (!$id) {
            $response->getBody()->write(json_encode([
                'error' => 'User id is required'
            ]));
            return $response->withStatus(400)
                ->withHeader('Content-Type', 'application/json');
        }

        // Verificar si existe y no está ya eliminado
        $stmt = $this->database->prepare("
        SELECT id, is_deleted FROM users WHERE id = :id
    ");
        $stmt->execute([':id' => $id]);

        $user = $stmt->fetch();

        if (!$user) {
            $response->getBody()->write(json_encode([
                'error' => 'User not found'
            ]));
            return $response->withStatus(404)
                ->withHeader('Content-Type', 'application/json');
        }

        if ((int)$user['is_deleted'] === 1) {
            $response->getBody()->write(json_encode([
                'message' => 'User already deleted'
            ]));
            return $response->withStatus(200)
                ->withHeader('Content-Type', 'application/json');
        }

        // Soft delete
        $update = $this->database->prepare("
        UPDATE users SET is_deleted = 1 WHERE id = :id
    ");

        $update->execute([':id' => $id]);

        $response->getBody()->write(json_encode([
            'message' => 'User soft deleted successfully'
        ]));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    }


    public function index(
        ServerRequestInterface $request,
        ResponseInterface      $response
    ): ResponseInterface
    {

        $stmt = $this->database->prepare(
            "SELECT id, name, phone, email, is_admin, is_deleted, created_at
             FROM users
             WHERE is_deleted = 0
             ORDER BY id DESC"
        );

        $stmt->execute();
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $response->getBody()->write(json_encode([
            'data' => $users,
            'count' => count($users)
        ]));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    }


    public function show(
        ServerRequestInterface $request,
        ResponseInterface      $response,
        array                  $args
    ): ResponseInterface
    {

        $id = $args['id'] ?? null;

        if (!$id) {
            $response->getBody()->write(json_encode([
                'error' => 'User id is required'
            ]));
            return $response->withStatus(400)
                ->withHeader('Content-Type', 'application/json');
        }

        $stmt = $this->database->prepare(
            "SELECT id, name, phone, email, is_admin, is_deleted, created_at
             FROM users
             WHERE id = :id"
        );

        $stmt->execute([':id' => $id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            $response->getBody()->write(json_encode([
                'error' => 'User not found'
            ]));
            return $response->withStatus(404)
                ->withHeader('Content-Type', 'application/json');
        }

        if ((int)$user['is_deleted'] === 1) {
            $response->getBody()->write(json_encode([
                'error' => 'User is deleted'
            ]));
            return $response->withStatus(404)
                ->withHeader('Content-Type', 'application/json');
        }

        $response->getBody()->write(json_encode([
            'data' => $user
        ]));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    }
}
