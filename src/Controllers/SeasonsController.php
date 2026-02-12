<?php

namespace App\Controllers;

use App\Database;
use PDOException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use PDO;

class SeasonsController
{
    public function __construct(private Database $database)
    {
    }

    public function register(
        ServerRequestInterface $request,
        ResponseInterface      $response,
    ): ResponseInterface
    {
        $data = $request->getParsedBody();

        $name = $data['name'] ?? null;

        if (!$name) {
            $response->getBody()->write(json_encode([
                'error' => 'Season name is required'
            ]));

            return $response->withStatus(400)
                ->withHeader('Content-Type', 'application/json');
        }

        $stmt = $this->database->prepare("
            INSERT INTO seasons (name)
            VALUES (:name)
        ");

        try {
            $stmt->execute([
                ':name' => $name
            ]);
        } catch (PDOException $e) {

            $response->getBody()->write(json_encode([
                'error' => 'Unable to create Season'
            ]));

            return $response->withStatus(500)
                ->withHeader('Content-Type', 'application/json');
        }

        $seasonId = $this->database->lastInsertId();

        $response->getBody()->write(json_encode([
            'id' => $seasonId,
            'name' => $name
        ]));

        return $response
            ->withHeader('Content-Type', 'application/json')
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
                'error' => 'Season id is required'
            ]));

            return $response->withStatus(400)
                ->withHeader('Content-Type', 'application/json');
        }

        $data = $request->getParsedBody();
        $name = $data['name'] ?? null;

        if ($name === null) {
            $response->getBody()->write(json_encode([
                'error' => 'No fields to update'
            ]));

            return $response->withStatus(400)
                ->withHeader('Content-Type', 'application/json');
        }

        // Verificar si existe
        $stmt = $this->database->prepare("SELECT id FROM seasons WHERE id = :id");
        $stmt->execute([':id' => $id]);

        if (!$stmt->fetch()) {
            $response->getBody()->write(json_encode([
                'error' => 'Season not found'
            ]));

            return $response->withStatus(404)
                ->withHeader('Content-Type', 'application/json');
        }

        $updateStmt = $this->database->prepare(
            "UPDATE seasons SET name = :name, updated_at = CURRENT_TIMESTAMP WHERE id = :id"
        );

        $updateStmt->execute([
            ':name' => $name,
            ':id' => $id
        ]);

        $response->getBody()->write(json_encode([
            'message' => 'Season updated successfully'
        ]));

        return $response
            ->withHeader('Content-Type', 'application/json')
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
                'error' => 'Season id is required'
            ]));

            return $response->withStatus(400)
                ->withHeader('Content-Type', 'application/json');
        }

        // Verificar si existe
        $stmt = $this->database->prepare("SELECT id FROM seasons WHERE id = :id");
        $stmt->execute([':id' => $id]);

        if (!$stmt->fetch()) {
            $response->getBody()->write(json_encode([
                'error' => 'Season not found'
            ]));

            return $response->withStatus(404)
                ->withHeader('Content-Type', 'application/json');
        }

        // Soft delete
        $deleteStmt = $this->database->prepare(
            "UPDATE seasons SET is_deleted = 1, updated_at = CURRENT_TIMESTAMP WHERE id = :id"
        );

        $deleteStmt->execute([':id' => $id]);

        $response->getBody()->write(json_encode([
            'message' => 'Season deleted successfully'
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

        $stmt = $this->database->prepare("
        SELECT id, name, created_at, updated_at
        FROM seasons
        WHERE is_deleted = 0
        ORDER BY id ASC
    ");

        $stmt->execute();

        $weeks = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $response->getBody()->write(json_encode([
            'data' => $weeks
        ]));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    }

    public function show(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface
    {
        $id = $args['id'] ?? null;

        if (!$id) {
            $response->getBody()->write(json_encode([
                'error' => 'Season id is required'
            ]));

            return $response->withStatus(400)
                ->withHeader('Content-Type', 'application/json');
        }

        $stmt = $this->database->prepare(
            "SELECT id, name, created_at, updated_at
             FROM seasons
             WHERE id = :id AND is_deleted = 0"
        );

        $stmt->execute([':id' => $id]);

        $week = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$week) {
            $response->getBody()->write(json_encode([
                'error' => 'Season not found'
            ]));

            return $response->withStatus(404)
                ->withHeader('Content-Type', 'application/json');
        }

        $response->getBody()->write(json_encode([
            'data' => $week
        ]));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    }
}
