<?php

namespace App\Controllers;

use App\Database;
use PDO;
use PDOException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use DateTime;

class PicksController
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

        $userId = $data['user_id'] ?? null;
        $gameId = $data['game_id'] ?? null;
        $prediction = $data['prediction'] ?? null;

        if (!$userId || !$gameId || !$prediction) {
            return $this->error($response, 'All fields are required', 400);
        }

        if (!in_array($prediction, ['local', 'visit', 'draw'])) {
            return $this->error($response, 'Invalid prediction value', 400);
        }

        // 1️⃣ Validar que el juego exista
        $stmt = $this->database->prepare(
            "SELECT id, game_datetime FROM games WHERE id = :id"
        );
        $stmt->execute([':id' => $gameId]);
        $game = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$game) {
            return $this->error($response, 'Game not found', 404);
        }

        // 2️⃣ No permitir pick si el juego ya comenzó
        $now = new DateTime();
        $gameDate = new DateTime($game['game_datetime']);

        if ($now >= $gameDate) {
            return $this->error($response, 'Cannot make a pick after the game has started', 400);
        }

        // 3️⃣ No permitir pick si el juego ya tiene resultado
        $stmt = $this->database->prepare(
            "SELECT id FROM game_results WHERE game_id = :game_id"
        );
        $stmt->execute([':game_id' => $gameId]);

        if ($stmt->fetch(PDO::FETCH_ASSOC)) {
            return $this->error($response, 'Cannot make a pick for a finished game', 400);
        }

        // Evitar duplicado manualmente (aunque exista UNIQUE)
        $stmt = $this->database->prepare(
            "SELECT id FROM picks WHERE user_id = :user_id AND game_id = :game_id"
        );
        $stmt->execute([
            ':user_id' => $userId,
            ':game_id' => $gameId
        ]);

        if ($stmt->fetch(PDO::FETCH_ASSOC)) {
            return $this->error($response, 'User already made a pick for this game', 400);
        }

        $insert = $this->database->prepare(
            "INSERT INTO picks (user_id, game_id, prediction)
             VALUES (:user_id, :game_id, :prediction)"
        );

        try {
            $insert->execute([
                ':user_id' => $userId,
                ':game_id' => $gameId,
                ':prediction' => $prediction
            ]);
        } catch (PDOException $e) {
            return $this->error($response, 'Unable to create pick', 500);
        }

        $pickId = $this->database->lastInsertId();

        $response->getBody()->write(json_encode([
            'id' => $pickId,
            'message' => 'Pick created successfully'
        ]));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(201);
    }

    private function error(
        ResponseInterface $response,
        string            $message,
        int               $status
    ): ResponseInterface
    {

        $response->getBody()->write(json_encode([
            'error' => $message
        ]));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($status);
    }

    public function update(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface
    {
        $id = $args['id'] ?? null;

        if (!$id) {
            return $this->error($response, 'Pick id is required', 400);
        }

        $data = $request->getParsedBody();
        $prediction = $data['prediction'] ?? null;

        if (!$prediction) {
            return $this->error($response, 'Prediction is required', 400);
        }

        if (!in_array($prediction, ['local', 'visit', 'draw'])) {
            return $this->error($response, 'Invalid prediction value', 400);
        }

        // Validar que el pick exista
        $stmt = $this->database->prepare(
            "SELECT id, game_id FROM picks WHERE id = :id"
        );
        $stmt->execute([':id' => $id]);
        $pick = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$pick) {
            return $this->error($response, 'Pick not found', 404);
        }

        $gameId = $pick['game_id'];

        // 1️⃣ Validar que el juego exista
        $stmt = $this->database->prepare(
            "SELECT id, game_datetime FROM games WHERE id = :id"
        );
        $stmt->execute([':id' => $gameId]);
        $game = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$game) {
            return $this->error($response, 'Game not found', 404);
        }

        // 2️⃣ No permitir actualizar pick si el juego ya comenzó
        $now = new DateTime();
        $gameDate = new DateTime($game['game_datetime']);

        if ($now >= $gameDate) {
            return $this->error($response, 'Cannot update pick after the game has started', 400);
        }

        // 3️⃣ No permitir actualizar pick si el juego ya tiene resultado
        $stmt = $this->database->prepare(
            "SELECT id FROM game_results WHERE game_id = :game_id"
        );
        $stmt->execute([':game_id' => $gameId]);

        if ($stmt->fetch(PDO::FETCH_ASSOC)) {
            return $this->error($response, 'Cannot update pick for a finished game', 400);
        }

        $update = $this->database->prepare(
            "UPDATE picks SET prediction = :prediction, updated_at = CURRENT_TIMESTAMP WHERE id = :id"
        );

        try {
            $update->execute([
                ':prediction' => $prediction,
                ':id' => $id
            ]);
        } catch (PDOException $e) {
            return $this->error($response, 'Unable to update pick', 500);
        }

        $response->getBody()->write(json_encode([
            'message' => 'Pick updated successfully'
        ]));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    }
}
