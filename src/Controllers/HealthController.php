<?php

namespace App\Controllers;

use App\Database;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

readonly class HealthController
{
    public function __construct(private Database $database)
    {
    }

    public function version(
        ServerRequestInterface $request,
        ResponseInterface      $response
    ): ResponseInterface
    {
        $version = $this->database
            ->query('SELECT sqlite_version()')
            ->fetchColumn();

        $response->getBody()->write(json_encode([
            'version' => $version,
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    }

    public function ping(
        ServerRequestInterface $request,
        ResponseInterface      $response
    ): ResponseInterface
    {
        $response->getBody()->write('pong');
        return $response;
    }
}
