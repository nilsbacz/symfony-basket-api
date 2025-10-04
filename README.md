# 🧺 Symfony Basket API

A RESTful Basket API built with **Symfony 7**, running in **Docker** using **PHP-FPM**, **nginx**, and **MySQL 8** — no API Platform.

---

## 🚀 Overview

This project is a coding task demonstrating:

- A clean, RESTful API design using Symfony
- Proper Docker-based environment setup
- Usage of Doctrine ORM with MySQL
- Unit and integration testing
- Atomic Git commits and structured project organization

---

## 🧩 Tech Stack

| Component | Purpose |
|------------|----------|
| **Symfony 7** | PHP framework (main application) |
| **PHP 8.3-FPM** | Executes Symfony code |
| **nginx (1.27)** | Serves HTTP requests and static assets |
| **MySQL 8.0** | Database for basket and product data |
| **Docker Compose** | Orchestration |
| **Composer** | Dependency management |
| **PHPUnit** | Testing |

---

## 🧰 Prerequisites

- **Docker Engine** + **Docker Compose Plugin**
- **Git**

On Linux, ensure your user is in the `docker` group:

```bash
sudo usermod -aG docker $USER
# Log out and back in (or run: newgrp docker)
```

---

## 🧱 Project structure

```
.
├── .docker/
│   ├── app/Dockerfile           # PHP-FPM image
│   ├── app/php.ini              # Custom PHP config
│   └── nginx/default.conf       # nginx vhost
├── bin/                         # Symfony console
├── config/                      # Framework configuration
├── public/                      # Document root (index.php entry)
├── src/                         # Application code (controllers, entities, etc.)
├── tests/                       # PHPUnit tests
├── docker-compose.yml           # Docker services definition
├── .env                         # Symfony environment vars
├── composer.json
├── .gitignore
└── README.md
```

---

## ⚙️ Setup & Run

Clone the repository:

```bash
git clone https://github.com/YOUR_USERNAME/symfony-basket-api.git
cd symfony-basket-api
```

### 1️⃣ Start the stack

```bash
docker compose up -d --build
```

### 2️⃣ Install PHP dependencies

```bash
docker compose exec app composer install
```

### 3️⃣ Create the database

```bash
docker compose exec app php bin/console doctrine:database:create --if-not-exists
```

(Once you add entities, you can later run migrations:
```bash
docker compose exec app php bin/console doctrine:migrations:migrate --no-interaction
```)

---

## 🧪 Testing the setup

- Visit: [http://localhost:8080](http://localhost:8080)  
  You should see Symfony’s welcome page or a 404.

**Quick health check** (temporary):
```bash
docker compose exec app bash -lc "printf '%s\n' '<?php echo \"OK\";' > public/health.php"
curl http://localhost:8080/health
```

You should get:
```
HTTP/1.1 200 OK
OK
```

---

## 🧩 Database credentials

| Key | Value |
|-----|--------|
| **Host (from app)** | `db` |
| **Host (from host machine)** | `127.0.0.1` |
| **Port** | `3306` |
| **Database** | `basket` |
| **User** | `symfony` |
| **Password** | `secret` |
| **Root password** | `rootsecret` |

You can connect from your host for debugging:

```bash
mysql -h 127.0.0.1 -P 3306 -u symfony -psecret basket
```

---

## 🧰 Common Docker commands

```bash
# Build & start
docker compose up -d --build

# Stop
docker compose down

# Stop & remove volumes (DB data, vendor cache)
docker compose down -v

# Rebuild PHP app only
docker compose build app

# Shell into PHP container
docker compose exec app bash

# Logs
docker compose logs -f web
```

---

## 🧠 Development notes

- **Autocompletion**: PhpStorm will index your `vendor/` because it’s mounted from the host.
- **Atomic commits**:
    - `chore:` → environment/config updates
    - `feat:` → new features or endpoints
    - `fix:` → bug fixes
    - `test:` → tests and test setup
- **Tests**:  
  Run inside the container:
  ```bash
  docker compose exec app php bin/phpunit
  ```

---

## 🔮 Future improvements

- Add Basket and Product entities
- Implement CRUD REST endpoints
- Add request validation and exception handling
- Write PHPUnit integration tests
- Use DataFixtures for seeding demo data

---

## 🧾 License

MIT (or specify your chosen license)

---

**Author:** Your Name  
**Repository:** [https://github.com/YOUR_USERNAME/symfony-basket-api](https://github.com/YOUR_USERNAME/symfony-basket-api)
