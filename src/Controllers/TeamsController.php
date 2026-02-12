<?php

namespace App\Controllers;

use App\Database;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Psr7\UploadedFile;
use PDOException;

class TeamsController
{
    public function __construct(private Database $database)
    {
    }

    public function register(
        ServerRequestInterface $request,
        ResponseInterface $response
    ): ResponseInterface {

        $data = $request->getParsedBody();
        $uploadedFiles = $request->getUploadedFiles();

        $name = $data['name'] ?? null;

        if (!$name) {
            $response->getBody()->write(json_encode([
                'error' => 'Team name is required'
            ]));

            return $response->withStatus(400)
                ->withHeader('Content-Type', 'application/json');
        }

        if (!isset($uploadedFiles['logo'])) {
            $response->getBody()->write(json_encode([
                'error' => 'Logo image is required'
            ]));

            return $response->withStatus(400)
                ->withHeader('Content-Type', 'application/json');
        }

        /** @var UploadedFile $logo */
        $logo = $uploadedFiles['logo'];

        if ($logo->getError() !== UPLOAD_ERR_OK) {
            $response->getBody()->write(json_encode([
                'error' => 'Error uploading file'
            ]));

            return $response->withStatus(400)
                ->withHeader('Content-Type', 'application/json');
        }

        $uploadDirectory = __DIR__ . '/../../public/uploads/teams';

        if (!is_dir($uploadDirectory)) {
            mkdir($uploadDirectory, 0777, true);
        }

        $extension = pathinfo($logo->getClientFilename(), PATHINFO_EXTENSION);
        $filename = uniqid('team_', true) . '.' . $extension;

        $logo->moveTo($uploadDirectory . DIRECTORY_SEPARATOR . $filename);

        $logoUri = '/uploads/teams/' . $filename;

        $stmt = $this->database->prepare("INSERT INTO teams (name, logo_uri) VALUES (:name, :logo_uri)");

        try {
            $stmt->execute([
                ':name' => $name,
                ':logo_uri' => $logoUri
            ]);
        } catch (PDOException $e) {
            $response->getBody()->write(json_encode([
                'error' => 'Unable to create team'
            ]));

            return $response->withStatus(500)
                ->withHeader('Content-Type', 'application/json');
        }

        $teamId = $this->database->lastInsertId();

        $response->getBody()->write(json_encode([
            'id' => $teamId,
            'name' => $name,
            'logo_uri' => $logoUri
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
            $response->getBody()->write(json_encode([
                'error' => 'Team id is required'
            ]));

            return $response->withStatus(400)
                ->withHeader('Content-Type', 'application/json');
        }

        $data = $request->getParsedBody();
        $uploadedFiles = $request->getUploadedFiles();

        $name = $data['name'] ?? null;

        $stmt = $this->database->prepare("SELECT * FROM teams WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $team = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$team) {
            $response->getBody()->write(json_encode([
                'error' => 'Team not found'
            ]));

            return $response->withStatus(404)
                ->withHeader('Content-Type', 'application/json');
        }

        $logoUri = $team['logo_uri'];

        if (isset($uploadedFiles['logo'])) {
            /** @var UploadedFile $logo */
            $logo = $uploadedFiles['logo'];

            if ($logo->getError() === UPLOAD_ERR_OK) {
                $uploadDirectory = __DIR__ . '/../../public/uploads/teams';

                if (!is_dir($uploadDirectory)) {
                    mkdir($uploadDirectory, 0777, true);
                }

                $extension = pathinfo($logo->getClientFilename(), PATHINFO_EXTENSION);
                $filename = uniqid('team_', true) . '.' . $extension;

                $logo->moveTo($uploadDirectory . DIRECTORY_SEPARATOR . $filename);

                $logoUri = '/uploads/teams/' . $filename;
            }
        }

        $updateStmt = $this->database->prepare(
            "UPDATE teams SET name = :name, logo_uri = :logo_uri, updated_at = CURRENT_TIMESTAMP WHERE id = :id"
        );

        $updateStmt->execute([
            ':name' => $name ?? $team['name'],
            ':logo_uri' => $logoUri,
            ':id' => $id
        ]);

        $response->getBody()->write(json_encode([
            'name' => $name ?? $team['name'],
            'logo_uri' => $logoUri,
            'message' => 'Team updated successfully'
        ]));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    }

    public function delete(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {

        $id = $args['id'] ?? null;

        if (!$id) {
            $response->getBody()->write(json_encode([
                'error' => 'Team id is required'
            ]));

            return $response->withStatus(400)
                ->withHeader('Content-Type', 'application/json');
        }

        $stmt = $this->database->prepare("SELECT id FROM teams WHERE id = :id AND is_deleted = 0");
        $stmt->execute([':id' => $id]);

        if (!$stmt->fetch()) {
            $response->getBody()->write(json_encode([
                'error' => 'Team not found'
            ]));

            return $response->withStatus(404)
                ->withHeader('Content-Type', 'application/json');
        }

        $deleteStmt = $this->database->prepare(
            "UPDATE teams SET is_deleted = 1, updated_at = CURRENT_TIMESTAMP WHERE id = :id"
        );

        $deleteStmt->execute([':id' => $id]);

        $response->getBody()->write(json_encode([
            'message' => 'Team deleted successfully'
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
            "SELECT id, name, logo_uri, created_at, updated_at
             FROM teams
             WHERE is_deleted = 0
             ORDER BY id ASC"
        );

        $stmt->execute();

        $teams = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $response->getBody()->write(json_encode([
            'data' => $teams
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
            $response->getBody()->write(json_encode([
                'error' => 'Team id is required'
            ]));

            return $response->withStatus(400)
                ->withHeader('Content-Type', 'application/json');
        }

        $stmt = $this->database->prepare(
            "SELECT id, name, logo_uri, created_at, updated_at
             FROM teams
             WHERE id = :id AND is_deleted = 0"
        );

        $stmt->execute([':id' => $id]);

        $team = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$team) {
            $response->getBody()->write(json_encode([
                'error' => 'Team not found'
            ]));

            return $response->withStatus(404)
                ->withHeader('Content-Type', 'application/json');
        }

        $response->getBody()->write(json_encode([
            'data' => $team
        ]));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    }
}
