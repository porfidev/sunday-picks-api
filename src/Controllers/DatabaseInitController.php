<?php

namespace App\Controllers;

use App\Database;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class DatabaseInitController
{
    public function __construct(private Database $database)
    {
    }

    public function init(
        ServerRequestInterface $request,
        ResponseInterface      $response
    ): ResponseInterface
    {
        // Activar Foreign Keys en SQLite
        $this->database->exec("PRAGMA foreign_keys = ON;");

        // Crear tabla users si no existe
        $this->database->exec("
            CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                phone TEXT NOT NULL,
                email TEXT NOT NULL UNIQUE,
                password TEXT NOT NULL,
                is_admin INTEGER NOT NULL DEFAULT 0,
                is_deleted INTEGER NOT NULL DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");

        // Crear usuario administrador inicial (si no existe)
        $adminName = $this->env('ADMIN_NAME', 'Admin');
        $adminPhone = $this->env('ADMIN_PHONE', '0000000000');
        $adminEmail = $this->env('ADMIN_EMAIL', 'admin@sundaypicks.local');
        $adminPassword = $this->env('ADMIN_PASSWORD', 'ChangeMe123!');

        $checkAdmin = $this->database->prepare("
    SELECT id FROM users WHERE email = :email LIMIT 1
");
        $checkAdmin->execute([':email' => $adminEmail]);

        if (!$checkAdmin->fetch()) {
            $hashedPassword = password_hash($adminPassword, PASSWORD_DEFAULT);

            $createAdmin = $this->database->prepare("
        INSERT INTO users (name, phone, email, password, is_admin)
        VALUES (:name, :phone, :email, :password, :is_admin)
    ");

            $createAdmin->execute([
                ':name' => $adminName,
                ':phone' => $adminPhone,
                ':email' => $adminEmail,
                ':password' => $hashedPassword,
                ':is_admin' => 1
            ]);
        }


        // Crear tabla weeks si no existe
        $this->database->exec("
            CREATE TABLE IF NOT EXISTS weeks (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                is_deleted INTEGER NOT NULL DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");

        // Crear tabla seasons si no existe
        $this->database->exec("
            CREATE TABLE IF NOT EXISTS seasons (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                is_deleted INTEGER NOT NULL DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");

        // Crear tabla teams si no existe
        $this->database->exec("
            CREATE TABLE IF NOT EXISTS teams (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                logo_uri TEXT NOT NULL,
                is_deleted INTEGER NOT NULL DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");

        // Crear tabla games si no existe
        $this->database->exec("
            CREATE TABLE IF NOT EXISTS games (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                game_datetime DATETIME NOT NULL,
                is_played INTEGER NOT NULL DEFAULT 0,
                season_id INTEGER NOT NULL,
                week_id INTEGER NOT NULL,
                local_team_id INTEGER NOT NULL,
                visit_team_id INTEGER NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (season_id) REFERENCES seasons(id),
                FOREIGN KEY (week_id) REFERENCES weeks(id),
                FOREIGN KEY (local_team_id) REFERENCES teams(id),
                FOREIGN KEY (visit_team_id) REFERENCES teams(id)
            )
        ");

        // Crear tabla game_results si no existe
        $this->database->exec("
            CREATE TABLE IF NOT EXISTS game_results (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                game_id INTEGER NOT NULL UNIQUE,
                local_score INTEGER NOT NULL,
                visit_score INTEGER NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (game_id) REFERENCES games(id)
            )
        ");

        // Crear tabla picks si no existe
        $this->database->exec("
            CREATE TABLE IF NOT EXISTS picks (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                game_id INTEGER NOT NULL,
                prediction TEXT NOT NULL CHECK (prediction IN ('local', 'visit', 'draw')),
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE(user_id, game_id),
                FOREIGN KEY (user_id) REFERENCES users(id),
                FOREIGN KEY (game_id) REFERENCES games(id)
            )
        ");

        $response->getBody()->write(json_encode([
            'status' => 'ok',
            'message' => 'Database initialized (if not exists)'
        ]));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    }

    private function env(string $key, string $default): string
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

        if ($value === false || $value === null || $value === '') {
            return $default;
        }

        return (string)$value;
    }

}
