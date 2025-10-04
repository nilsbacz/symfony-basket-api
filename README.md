# Symfony Basket API — Docker (MySQL) Starter

A minimal Docker setup for a Symfony 7+ REST API (basket service) using **PHP-FPM**, **nginx**, and **MySQL 8** — without Make. This README shows how to run everything with plain `docker compose`.

---

## Prerequisites

- **Docker Engine** + **Docker Compose v2**
- **Git** (for version control)

> Linux users: ensure your user is in the `docker` group.
>
> ```bash
> sudo usermod -aG docker $USER
> # log out/in or: newgrp docker
> ```

---

## Project layout

```
.docker/
  app/
    Dockerfile       # PHP 8.3 FPM image with required extensions
    php.ini          # Dev-friendly PHP settings + opcache
  nginx/
    default.conf     # nginx vhost (front controller pattern)
docker-compose.yml   # services: app, web, db
.gitignore           # starter ignore file
```

---

## 1) Start the stack

Build and start containers in the background:

```bash
# from repo root
docker compose up -d --build
```

Check that all services are healthy:

```bash
docker compose ps
```

- `web` should be `Up`
- `app` should be `Up`
- `db` should be `healthy`

Logs (follow):

```bash
docker compose logs -f --no-log-prefix web
```

Stop everything:

```bash
docker compose down
```

Remove containers **and** named volumes (DB data, vendor cache):

```bash
docker compose down -v
```

---

## 2) Bootstrap Symfony inside the container

> Skip this if you already have a Symfony app in the repo.

Open a shell in the **app** container:

```bash
docker compose exec app bash
```

Create the Symfony skeleton in the mounted project root:

```bash
composer create-project symfony/skeleton .
```

Install Doctrine and friends (you'll need these soon):

```bash
composer require symfony/orm-pack
composer require symfony/maker-bundle --dev
```

Ensure the environment variable is set for Doctrine (already in `docker-compose.yml`):

```
DATABASE_URL="mysql://symfony:secret@db:3306/basket?serverVersion=8.0&charset=utf8mb4"
```

Create the database:

```bash
php bin/console doctrine:database:create --if-not-exists
```

> Later, when you add entities/migrations:
>
> ```bash
> php bin/console make:migration
> php bin/console doctrine:migrations:migrate --no-interaction
> ```

Exit the container shell when done:

```bash
exit
```

---

## 3) Verify nginx ↔ PHP ↔ Symfony

With the stack running, open:

- http://localhost:8080 — you should see a Symfony response (404 until you add a route, which is fine).

Create a quick health route to be sure:

```bash
docker compose exec app bash -lc "printf '%s\n' '<?php\n// public/health.php\nhttp_response_code(200);\necho \"OK\";\n' > public/health.php"
```

Now visit http://localhost:8080/health — it should print `OK`.

> Remove `public/health.php` later; your real app will route everything via `public/index.php`.

---

## 4) Useful `docker compose` commands (no Make)

```bash
# Build & start
docker compose up -d --build

# Stop
docker compose down

# Stop and remove volumes (DB data, vendor cache)
docker compose down -v

# Shell into PHP app container
docker compose exec app bash

# Tail logs for nginx
docker compose logs -f web

# Run Composer inside the container
docker compose exec app composer install

# Run Symfony CLI inside the container
docker compose exec app php bin/console
```

---

## 5) Database access (MySQL)

- Host (from app container): `db`
- Host (from your machine): `127.0.0.1`
- Port: `3306`
- DB: `basket`
- User: `symfony`
- Password: `secret`

From the host, you can test with:

```bash
mysql -h 127.0.0.1 -P 3306 -u symfony -psecret basket -e "SELECT 1;"
```

---

## 6) Curl examples (once you add routes)

```bash
# Ping health (temporary file example above)
curl -i http://localhost:8080/health

# Symfony front controller example (will 404 until controllers exist)
curl -i http://localhost:8080/
```

---

## 7) Troubleshooting

**`usermod: group 'docker' does not exist`**
- Install Docker first, then add your user to the `docker` group:
  ```bash
  sudo apt-get install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
  getent group docker || sudo groupadd docker
  sudo usermod -aG docker $USER
  # log out/in or: newgrp docker
  ```

**`db` not healthy / connection refused**
- Give MySQL a moment to initialize; check logs:
  ```bash
  docker compose logs -f db
  ```
- Ensure your `DATABASE_URL` matches `docker-compose.yml`.

**Port already in use (8080 or 3306)**
- Change the host port mapping in `docker-compose.yml`:
  ```yaml
  web:
    ports:
      - "8081:80"
  db:
    ports:
      - "3307:3306"
  ```
- Then `docker compose up -d` again and update your client URLs/ports.

**PHP extensions missing (pdo_mysql, intl, mbstring, zip)**
- They’re installed by the Dockerfile. Rebuild if you changed the file:
  ```bash
  docker compose build --no-cache app
  docker compose up -d
  ```

**Permission issues on vendor/**
- `app_vendor` is a named volume. Recreate containers/volumes:
  ```bash
  docker compose down -v && docker compose up -d --build
  ```

---
