# Design: Task Entity + Repository + API Endpoint

## Overview

Create a Task entity backed by SQLite, a Doctrine repository for data access, and a JSON API controller — teaching the standard Symfony repository pattern end-to-end.

## Architecture

```
HTTP Request → Controller → Repository → Entity (ORM) → SQLite
     ↓              ↓            ↓            ↓
  JSON Response   Route      Query Layer   Data Model
```

## Components

### 1. Database — Switch to SQLite

Change `DATABASE_URL` in `.env` to `sqlite:///%kernel.project_dir%/var/data.db`. No server needed.

### 2. Entity — `App\Entity\Task`

| Field       | Type     | Notes                              |
|-------------|----------|------------------------------------|
| id          | int      | Auto-increment PK                  |
| title       | string   | Max 255, required                  |
| description | text     | Nullable                           |
| status      | string   | "pending", "in_progress", "completed" |
| priority    | int      | 1-5, default 3                     |
| dueDate     | datetime | Nullable                           |
| createdAt   | datetime | Auto-set on creation               |

Uses PHP 8 ORM attributes for mapping.

### 3. Repository — `App\Repository\TaskRepository`

Extends `ServiceEntityRepository<Task>`. Inherits `find()`, `findAll()`, `findOneBy()`, `findBy()`. Adds custom `findByStatus(string $status): array` using QueryBuilder.

### 4. Controller — `App\Controller\TaskApiController`

| Method | Route             | Action                                  |
|--------|-------------------|-----------------------------------------|
| GET    | /api/tasks        | List all tasks (optional ?status= filter) |
| GET    | /api/tasks/{id}   | Get a single task by ID                 |
| POST   | /api/tasks        | Create a new task                       |

Returns `JsonResponse`. Uses Symfony Serializer for entity-to-JSON conversion.

### 5. Migration

Auto-generate via `doctrine:migrations:diff`, apply via `doctrine:migrations:migrate`.
