<?php

namespace App\Controllers;

use App\Database;
use PDO;
use PDOException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class GameResultsController
{
    public function __construct(private Database $database)
    {
    }

    public function register(
        ServerRequestInterface $request,
        ResponseInterface $response
    ): ResponseInterface {
        $data = $request->getParsedBody();

        $gameId = $data['game_id'] ?? null;
        $localScore = $data['local_score'] ?? null;
        $visitScore = $data['visit_score'] ?? null;

        if ($gameId === null || $localScore === null || $visitScore === null) {
            return $this->errorResponse($response, 'All fields are required', 400);
        }

        if (!is_numeric($localScore) || !is_numeric($visitScore) || $localScore < 0 || $visitScore < 0) {
            return $this->errorResponse($response, 'Scores must be non-negative numbers', 400);
        }

        $stmt = $this->database->prepare("SELECT id FROM games WHERE id = :id");
        $stmt->execute([':id' => $gameId]);

        if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
            return $this->errorResponse($response, 'Game not found', 404);
        }

        $stmt = $this->database->prepare("SELECT id FROM game_results WHERE game_id = :game_id");
        $stmt->execute([':game_id' => $gameId]);

        if ($stmt->fetch(PDO::FETCH_ASSOC)) {
            return $this->errorResponse($response, 'Game already has a result', 409);
        }

        try {
            $this->database->beginTransaction();

            $insert = $this->database->prepare(
                "INSERT INTO game_results (game_id, local_score, visit_score)
                 VALUES (:game_id, :local_score, :visit_score)"
            );

            $insert->execute([
                ':game_id' => $gameId,
                ':local_score' => (int)$localScore,
                ':visit_score' => (int)$visitScore,
            ]);

            $markAsPlayed = $this->database->prepare(
                "UPDATE games SET is_played = 1, updated_at = CURRENT_TIMESTAMP WHERE id = :id"
            );
            $markAsPlayed->execute([':id' => $gameId]);

            $this->database->commit();
        } catch (PDOException $e) {
            if ($this->database->inTransaction()) {
                $this->database->rollBack();
            }
            return $this->errorResponse($response, 'Unable to create game result', 500);
        }

        $resultId = $this->database->lastInsertId();

        $response->getBody()->write(json_encode([
            'id' => $resultId,
            'message' => 'Game result created successfully'
        ]));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(201);
    }

    public function update(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        $id = $args['id'] ?? null;

        if (!$id) {
            return $this->errorResponse($response, 'Game result id is required', 400);
        }

        $data = $request->getParsedBody();
        $localScore = $data['local_score'] ?? null;
        $visitScore = $data['visit_score'] ?? null;

        if ($localScore === null && $visitScore === null) {
            return $this->errorResponse($response, 'No fields to update', 400);
        }

        if (($localScore !== null && (!is_numeric($localScore) || $localScore < 0)) ||
            ($visitScore !== null && (!is_numeric($visitScore) || $visitScore < 0))
        ) {
            return $this->errorResponse($response, 'Scores must be non-negative numbers', 400);
        }

        $stmt = $this->database->prepare(
            "SELECT id, game_id, local_score, visit_score FROM game_results WHERE id = :id"
        );
        $stmt->execute([':id' => $id]);
        $gameResult = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$gameResult) {
            return $this->errorResponse($response, 'Game result not found', 404);
        }

        $localScore = $localScore ?? $gameResult['local_score'];
        $visitScore = $visitScore ?? $gameResult['visit_score'];

        $update = $this->database->prepare(
            "UPDATE game_results SET
                local_score = :local_score,
                visit_score = :visit_score,
                updated_at = CURRENT_TIMESTAMP
             WHERE id = :id"
        );

        try {
            $update->execute([
                ':local_score' => (int)$localScore,
                ':visit_score' => (int)$visitScore,
                ':id' => $id
            ]);
        } catch (PDOException $e) {
            return $this->errorResponse($response, 'Unable to update game result', 500);
        }

        $response->getBody()->write(json_encode([
            'message' => 'Game result updated successfully'
        ]));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    }

    public function index(
        ServerRequestInterface $request,
        ResponseInterface $response
    ): ResponseInterface {
        $stmt = $this->database->prepare(
            "SELECT
                gr.id,
                gr.game_id,
                gr.local_score,
                gr.visit_score,
                gr.created_at,
                gr.updated_at
             FROM game_results gr
             ORDER BY gr.id DESC"
        );

        $stmt->execute();
        $gameResults = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $response->getBody()->write(json_encode([
            'data' => $gameResults
        ]));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    }

    public function show(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        $id = $args['id'] ?? null;

        if (!$id) {
            return $this->errorResponse($response, 'Game result id is required', 400);
        }

        $stmt = $this->database->prepare(
            "SELECT
                gr.id,
                gr.game_id,
                gr.local_score,
                gr.visit_score,
                gr.created_at,
                gr.updated_at
             FROM game_results gr
             WHERE gr.id = :id"
        );
        $stmt->execute([':id' => $id]);

        $gameResult = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$gameResult) {
            return $this->errorResponse($response, 'Game result not found', 404);
        }

        $response->getBody()->write(json_encode([
            'data' => $gameResult
        ]));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    }

    private function errorResponse(
        ResponseInterface $response,
        string $message,
        int $status
    ): ResponseInterface {
        $response->getBody()->write(json_encode([
            'error' => $message
        ]));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($status);
    }
}
