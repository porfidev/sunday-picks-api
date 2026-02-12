<?php

namespace App\Controllers;

use App\Database;
use PDO;
use PDOException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class GamesController
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

        $gameDatetime = $data['game_datetime'] ?? null;
        $seasonId = $data['season_id'] ?? null;
        $weekId = $data['week_id'] ?? null;
        $localTeamId = $data['local_team_id'] ?? null;
        $visitTeamId = $data['visit_team_id'] ?? null;

        if (!$gameDatetime || !$seasonId || !$weekId || !$localTeamId || !$visitTeamId) {
            return $this->errorResponse($response, 'All fields are required', 400);
        }

        // Regla de dominio: un equipo no puede jugar contra sí mismo
        if ($localTeamId == $visitTeamId) {
            return $this->errorResponse($response, 'A team cannot play against itself', 400);
        }

        // Validar existencia de season
        $stmt = $this->database->prepare("SELECT id FROM seasons WHERE id = :id");
        $stmt->execute([':id' => $seasonId]);
        if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
            return $this->errorResponse($response, 'Season not found', 404);
        }

        // Validar existencia de week
        $stmt = $this->database->prepare("SELECT id FROM weeks WHERE id = :id");
        $stmt->execute([':id' => $weekId]);
        if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
            return $this->errorResponse($response, 'Week not found', 404);
        }

        // Validar existencia de equipos (solo activos)
        $stmt = $this->database->prepare(
            "SELECT id FROM teams WHERE id = :id AND is_deleted = 0"
        );

        $stmt->execute([':id' => $localTeamId]);
        if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
            return $this->errorResponse($response, 'Local team not found', 404);
        }

        $stmt->execute([':id' => $visitTeamId]);
        if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
            return $this->errorResponse($response, 'Visit team not found', 404);
        }

        $insert = $this->database->prepare(
            "INSERT INTO games (
                game_datetime,
                season_id,
                week_id,
                local_team_id,
                visit_team_id
            ) VALUES (
                :game_datetime,
                :season_id,
                :week_id,
                :local_team_id,
                :visit_team_id
            )"
        );

        try {
            $insert->execute([
                ':game_datetime' => $gameDatetime,
                ':season_id' => $seasonId,
                ':week_id' => $weekId,
                ':local_team_id' => $localTeamId,
                ':visit_team_id' => $visitTeamId
            ]);
        } catch (PDOException $e) {
            return $this->errorResponse($response, 'Unable to create game', 500);
        }

        $gameId = $this->database->lastInsertId();

        $response->getBody()->write(json_encode([
            'id' => $gameId,
            'message' => 'Game created successfully'
        ]));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(201);
    }

    private function errorResponse(
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
        ResponseInterface      $response,
        array                  $args
    ): ResponseInterface
    {

        $id = $args['id'] ?? null;

        if (!$id) {
            return $this->errorResponse($response, 'Game id is required', 400);
        }

        $data = $request->getParsedBody();

        $gameDatetime = $data['game_datetime'] ?? null;
        $seasonId = $data['season_id'] ?? null;
        $weekId = $data['week_id'] ?? null;
        $localTeamId = $data['local_team_id'] ?? null;
        $visitTeamId = $data['visit_team_id'] ?? null;
        $isPlayed = $data['is_played'] ?? null;

        $stmt = $this->database->prepare("SELECT * FROM games WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $game = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$game) {
            return $this->errorResponse($response, 'Game not found', 404);
        }

        $gameDatetime = $gameDatetime ?? $game['game_datetime'];
        $seasonId = $seasonId ?? $game['season_id'];
        $weekId = $weekId ?? $game['week_id'];
        $localTeamId = $localTeamId ?? $game['local_team_id'];
        $visitTeamId = $visitTeamId ?? $game['visit_team_id'];
        $isPlayed = $isPlayed ?? $game['is_played'];

        if ($localTeamId == $visitTeamId) {
            return $this->errorResponse($response, 'A team cannot play against itself', 400);
        }

        $update = $this->database->prepare(
            "UPDATE games SET
                game_datetime = :game_datetime,
                season_id = :season_id,
                week_id = :week_id,
                local_team_id = :local_team_id,
                visit_team_id = :visit_team_id,
                is_played = :is_played,
                updated_at = CURRENT_TIMESTAMP
             WHERE id = :id"
        );

        try {
            $update->execute([
                ':game_datetime' => $gameDatetime,
                ':season_id' => $seasonId,
                ':week_id' => $weekId,
                ':local_team_id' => $localTeamId,
                ':visit_team_id' => $visitTeamId,
                ':is_played' => $isPlayed,
                ':id' => $id
            ]);
        } catch (PDOException $e) {
            return $this->errorResponse($response, 'Unable to update game', 500);
        }

        $response->getBody()->write(json_encode([
            'message' => 'Game updated successfully'
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
            "SELECT
                g.id,
                g.game_datetime,
                g.is_played,
                g.season_id,
                g.week_id,
                g.local_team_id,
                g.visit_team_id,
                g.created_at,
                g.updated_at
             FROM games g
             ORDER BY g.game_datetime ASC"
        );

        $stmt->execute();

        $games = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $response->getBody()->write(json_encode([
            'data' => $games
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
            return $this->errorResponse($response, 'Game id is required', 400);
        }

        $stmt = $this->database->prepare(
            "SELECT
                g.id,
                g.game_datetime,
                g.is_played,
                g.season_id,
                g.week_id,
                g.local_team_id,
                g.visit_team_id,
                g.created_at,
                g.updated_at
             FROM games g
             WHERE g.id = :id"
        );

        $stmt->execute([':id' => $id]);

        $game = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$game) {
            return $this->errorResponse($response, 'Game not found', 404);
        }

        $response->getBody()->write(json_encode([
            'data' => $game
        ]));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    }

}
