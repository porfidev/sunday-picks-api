# Sunday Picks API

API REST construida con PHP + Slim 4 para gestionar usuarios, temporadas, semanas, equipos, juegos, picks y resultados.

## Requerimientos Minimos

- PHP 8.1 o superior
- Composer 2.x
- Extension `pdo` habilitada
- SQLite 3 (el proyecto usa `storage/database.sqlite`)

## Instalacion

1. Instalar dependencias:

```bash
composer install
```

2. Crear archivo de entorno:

```bash
cp .env.example .env
```

3. Levantar servidor local:

```bash
php -S localhost:8080 -t public
```

4. Inicializar base de datos y usuario administrador:

```bash
curl http://localhost:8080/init-db
```

Esto crea las tablas necesarias y un usuario admin con los valores definidos en `.env` (`ADMIN_NAME`, `ADMIN_EMAIL`, `ADMIN_PASSWORD`, etc.).

## Variables de Entorno

Variables principales (ver `.env.example`):

- `ADMIN_NAME`
- `ADMIN_PHONE`
- `ADMIN_EMAIL`
- `ADMIN_PASSWORD`
- `JWT_SECRET`
- `JWT_ISSUER`
- `JWT_EXPIRES_IN`
- `REFRESH_TOKEN_EXPIRES_IN`
- `CORS_ALLOWED_ORIGINS`
- `CORS_ALLOW_CREDENTIALS`

## Autenticacion

La mayoria de endpoints requieren token JWT:

1. Hacer login en `/auth/login`
2. Tomar `access_token`
3. Enviar header:

```http
Authorization: Bearer <access_token>
```

## Endpoints

Base URL local: `http://localhost:8080`

### Health / Setup

- `GET /ping` (publico)
- `GET /version` (publico)
- `GET /init-db` (publico)

### Auth

- `POST /auth/login` (publico)
- `POST /auth/refresh` (publico)
- `POST /auth/logout` (requiere token)
- `PATCH /auth/password` (requiere token)

Ejemplo login:

```bash
curl -X POST http://localhost:8080/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "admin@example.com",
    "password": "DummyPass123!"
  }'
```

Ejemplo refresh:

```bash
curl -X POST http://localhost:8080/auth/refresh \
  -H "Content-Type: application/json" \
  -d '{
    "refresh_token": "TU_REFRESH_TOKEN"
  }'
```

### Users (requiere token)

- `POST /users`
- `GET /users`
- `GET /users/{id}`
- `PUT /users/{id}`
- `DELETE /users/{id}` (soft delete)

Crear usuario:

```bash
curl -X POST http://localhost:8080/users \
  -H "Authorization: Bearer TU_ACCESS_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Jane Doe",
    "phone": "5551112233",
    "email": "jane@example.com",
    "password": "Secret123!",
    "is_admin": 0
  }'
```

### Weeks (requiere token)

- `POST /weeks`
- `GET /weeks`
- `GET /weeks/{id}`
- `PUT /weeks/{id}`
- `DELETE /weeks/{id}` (soft delete)

Payload para crear/actualizar:

```json
{
  "name": "Week 1"
}
```

### Seasons (requiere token)

- `POST /seasons`
- `GET /seasons`
- `GET /seasons/{id}`
- `PUT /seasons/{id}`
- `DELETE /seasons/{id}` (soft delete)

Payload para crear/actualizar:

```json
{
  "name": "2026"
}
```

### Teams (requiere token)

- `POST /teams` (multipart/form-data, requiere `name` + `logo`)
- `GET /teams`
- `GET /teams/{id}`
- `POST /teams/{id}` (actualizacion via multipart/form-data)
- `DELETE /teams/{id}` (soft delete)

Crear equipo:

```bash
curl -X POST http://localhost:8080/teams \
  -H "Authorization: Bearer TU_ACCESS_TOKEN" \
  -F "name=Patriots" \
  -F "logo=@/ruta/a/logo.png"
```

### Games (requiere token)

- `POST /games`
- `GET /games`
- `GET /games/{id}`
- `PUT /games/{id}`

Crear juego:

```bash
curl -X POST http://localhost:8080/games \
  -H "Authorization: Bearer TU_ACCESS_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "game_datetime": "2026-09-10 20:20:00",
    "season_id": 1,
    "week_id": 1,
    "local_team_id": 1,
    "visit_team_id": 2
  }'
```

### Picks (requiere token)

- `POST /picks`
- `PUT /picks/{id}`

Crear pick:

```bash
curl -X POST http://localhost:8080/picks \
  -H "Authorization: Bearer TU_ACCESS_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "user_id": 2,
    "game_id": 1,
    "prediction": "local"
  }'
```

`prediction` acepta: `local`, `visit`, `draw`.

### Game Results (requiere token)

- `POST /game-results`
- `GET /game-results`
- `GET /game-results/{id}`
- `PUT /game-results/{id}`

Crear resultado:

```bash
curl -X POST http://localhost:8080/game-results \
  -H "Authorization: Bearer TU_ACCESS_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "game_id": 1,
    "local_score": 24,
    "visit_score": 17
  }'
```

Crear resultados en lote:

```bash
curl -X POST http://localhost:8080/game-results \
  -H "Authorization: Bearer TU_ACCESS_TOKEN" \
  -H "Content-Type: application/json" \
  -d '[
    {
      "game_id": 1,
      "local_score": 24,
      "visit_score": 17
    },
    {
      "game_id": 2,
      "local_score": 10,
      "visit_score": 7
    }
  ]'
```

## Notas

- Al registrar un `game_result`, el juego se marca automaticamente con `is_played = 1`.
- No se puede crear/actualizar pick si el juego ya inicio o si ya tiene resultado.
- Los recursos `users`, `weeks`, `seasons` y `teams` usan soft delete.
