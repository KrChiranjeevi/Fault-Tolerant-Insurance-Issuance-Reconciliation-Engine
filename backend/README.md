# PolicyFlow — Backend (Laravel 11 API)

Enterprise policy lifecycle management and workflow automation API service.

## 🚀 Tech Stack

- **Framework**: Laravel 11.x (PHP 8.2+)
- **Authentication**: Laravel Sanctum (Token-based API authentication)
- **Database**: MySQL 8.0+ (InnoDB, UTF-8 Multilingual `utf8mb4_unicode_ci`)
- **Testing**: PHPUnit 10.x with SQLite in-memory and MySQL integration support

---

## 🗄️ Database Setup (XAMPP MySQL)

Follow these steps to configure your local MySQL database using XAMPP:

### 1. Start MySQL in XAMPP
1. Open the **XAMPP Control Panel**.
2. Click **Start** next to **MySQL** (and **Apache** if using phpMyAdmin).
3. Verify MySQL is running on port `3306`.

### 2. Create the Database
Create the `policyflow` database using either phpMyAdmin or the MySQL CLI:

- **Via phpMyAdmin**:
  1. Navigate to [http://localhost/phpmyadmin](http://localhost/phpmyadmin) in your browser.
  2. Click **New** in the left sidebar.
  3. Enter database name: `policyflow`.
  4. Select collation: `utf8mb4_unicode_ci`.
  5. Click **Create**.

- **Via MySQL CLI**:
  ```bash
  mysql -u root -p -e "CREATE DATABASE IF NOT EXISTS policyflow CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
  ```
  *(If your XAMPP root user has no password, omit `-p`)*.

### 3. Configure Environment Variables
Copy `.env.example` to `.env` (if not already present) and update the database settings:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=policyflow
DB_USERNAME=root
DB_PASSWORD=your_mysql_password
```

> **Note**: Never commit the real `.env` file to version control. The repository `.gitignore` automatically excludes `.env`.

### 4. Run Database Migrations
Execute the initial schema migrations to establish all system tables:

```bash
php artisan migrate
```

To rollback or re-run all migrations from scratch:
```bash
php artisan migrate:fresh
```

---

## 📡 API Endpoints

| Method | Endpoint | Description | Auth Required |
| :--- | :--- | :--- | :--- |
| `GET` | `/api/health` | Service and database operational status check | No |
| `GET` | `/api/status` | Lightweight online status and version info | No |
| `GET` | `/api/user` | Authenticated user profile | Yes (`auth:sanctum`) |
| `GET` | `/sanctum/csrf-cookie` | CSRF cookie initialization for SPA client | No |

### Health Check Response Example (`GET /api/health`):
```json
{
  "status": "ok",
  "app": "PolicyFlow",
  "environment": "local",
  "timestamp": "2026-10-23T16:52:58+00:00",
  "services": {
    "database": {
      "status": "connected",
      "driver": "mysql",
      "error": null
    }
  }
}
```

---

## 🧪 Testing

Run the automated test suite using PHPUnit / Artisan:

```bash
php artisan test
```

Tests run in-memory using SQLite for fast, isolated verification of endpoints, authentication, and CORS policies.

---

## 💻 Running the Local Server

Start the Laravel development API server on port 8000:

```bash
php artisan serve
```

The API will be available at `http://127.0.0.1:8000`.
Cross-Origin Resource Sharing (CORS) is configured to accept requests from the local React dev origin (`http://localhost:5173`).
