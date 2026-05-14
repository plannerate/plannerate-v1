# Docker Setup Guide

This file guides AI assistants (and humans) on how to adapt this project's Docker configuration for a new Laravel project.

## Files to Copy

Copy the entire `docker/` directory along with `docker-compose.yml`:

```
docker/
  nginx/default.conf
  php/Dockerfile
  DOCKER.md
docker-compose.yml
```

## Required Changes When Copying to a New Project

### 1. Project Name (Traefik Labels)

Replace all occurrences of `plannerate` with your new project name in `docker-compose.yml`:

| Service    | Label to update                                                      |
|------------|----------------------------------------------------------------------|
| nginx      | `traefik.http.routers.app.rule=Host(\`{name}.localhost\`)`           |
| reverb     | `traefik.http.routers.reverb.rule=Host(\`ws.{name}.localhost\`)`     |
| pgadmin    | `traefik.http.routers.pgadmin.rule=Host(\`pgadmin.{name}.localhost\`)` |
| phpmyadmin | `traefik.http.routers.phpmyadmin.rule=Host(\`phpmyadmin.{name}.localhost\`)` |

### 2. PostgreSQL Database Name

In the `postgres` service, change `POSTGRES_DB` to match the new project:

```yaml
postgres:
  environment:
    POSTGRES_DB: your_project_name   # was: landlord
    POSTGRES_USER: postgres
    POSTGRES_PASSWORD: postgres
```

### 3. Conflicting Ports

If running multiple projects simultaneously, each project must use different host ports. Change the left side of the mapping (`host:container`):

| Service    | Default port | Change to (example) |
|------------|--------------|---------------------|
| postgres   | `5432:5432`  | `5433:5432`         |
| pgadmin    | `5050:80`    | `5051:80`           |
| mysql      | `3306:3306`  | `3307:3306`         |
| phpmyadmin | `8081:80`    | `8082:80`           |
| redis      | `6379:6379`  | `6380:6379`         |
| mailpit    | `1025:1025`  | `1026:1025`         |
| mailpit    | `8025:8025`  | `8026:8025`         |

### 4. `.env` Variables

Ensure the new project's `.env` matches the database credentials defined in `docker-compose.yml`:

```env
DB_CONNECTION=pgsql
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=your_project_name
DB_USERNAME=postgres
DB_PASSWORD=postgres

REDIS_HOST=redis
REDIS_PORT=6379

MAIL_HOST=mailpit
MAIL_PORT=1025
```

## Calling External `.localhost` APIs from Inside Containers

PHP containers cannot resolve `*.localhost` domains by default — those hostnames are only known by the host machine via Traefik.

**Symptom:** `cURL error 7: Failed to connect to some-api.localhost port 80`

**Fix:** add the hostname to `extra_hosts` in the `php` and `horizon` services in `docker-compose.yml`:

```yaml
php:
  extra_hosts:
    - "some-api.localhost:host-gateway"

horizon:
  extra_hosts:
    - "some-api.localhost:host-gateway"
```

`host-gateway` is a Docker special value (Engine 20.10+) that resolves to the host machine's IP, allowing the container to reach Traefik which then routes the request normally.

> Add one line per external `.localhost` domain this project needs to call. After editing, run `docker compose up -d` to apply.

## External Network

This setup uses an external Traefik network called `web`. Before running `docker compose up`, ensure the network exists:

```bash
docker network create web
```

Traefik must also be running on this network to route `*.localhost` domains.

## Services Overview

| Service    | Purpose                              |
|------------|--------------------------------------|
| nginx      | Web server, proxies to PHP-FPM       |
| php        | Laravel app (PHP-FPM)                |
| horizon    | Laravel queue worker via Horizon     |
| reverb     | Laravel WebSocket server             |
| postgres   | Primary relational database          |
| pgadmin    | PostgreSQL GUI                       |
| mysql      | Legacy/secondary database            |
| phpmyadmin | MySQL GUI                            |
| redis      | Cache, queues, and sessions          |
| mailpit    | Local email catcher (SMTP trap)      |
