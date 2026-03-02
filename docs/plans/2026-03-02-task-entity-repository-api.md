# Task Entity + Repository + API Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Create a Task entity backed by SQLite, a Doctrine repository for querying, and a JSON API controller — teaching the full repository pattern end-to-end.

**Architecture:** Entity (ORM mapping) → Repository (query layer) → Controller (HTTP/JSON). Doctrine handles the SQLite connection, the repository encapsulates all database queries, and the controller exposes them as REST endpoints.

**Tech Stack:** Symfony 8.0, Doctrine ORM 3.6, SQLite, PHP 8.4

---

### Task 1: Switch Database to SQLite

**Files:**
- Modify: `.env:36`

**Step 1: Update DATABASE_URL**

Change the `DATABASE_URL` line in `.env` from MySQL to SQLite:

```env
DATABASE_URL="sqlite:///%kernel.project_dir%/var/data.db"
```

**Step 2: Verify the connection works**

Run: `php bin/console doctrine:database:create`
Expected: Creates `var/data.db` file (or says it already exists)

**Step 3: Commit**

```bash
git add .env
git commit -m "chore: switch database from MySQL to SQLite"
```

---

### Task 2: Create the Task Entity

**Files:**
- Create: `src/Entity/Task.php`

**Step 1: Create the entity file**

```php
<?php

namespace App\Entity;

use App\Repository\TaskRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Task Entity - Maps to the "task" table in SQLite.
 *
 * This is a Doctrine Entity: a plain PHP class whose properties are mapped
 * to database columns using PHP 8 attributes (#[ORM\...]).
 *
 * Key concepts:
 * - #[ORM\Entity] tells Doctrine this class is a database table
 * - #[ORM\Column] maps a property to a column
 * - The repositoryClass parameter links this entity to its repository
 * - Doctrine reads these attributes to auto-generate SQL (CREATE TABLE, SELECT, etc.)
 */
#[ORM\Entity(repositoryClass: TaskRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Task
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $title;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    /**
     * Status field: one of "pending", "in_progress", "completed".
     * Stored as a simple string in the database.
     */
    #[ORM\Column(length: 20)]
    private string $status = 'pending';

    /**
     * Priority from 1 (lowest) to 5 (highest). Defaults to 3.
     */
    #[ORM\Column]
    private int $priority = 3;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $dueDate = null;

    /**
     * Automatically set when the entity is first persisted.
     * The #[ORM\PrePersist] lifecycle callback handles this.
     */
    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    /**
     * Lifecycle callback: Doctrine calls this automatically
     * right before inserting a new row (persist + flush).
     */
    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->createdAt = new \DateTime();
    }

    // --- Getters and Setters ---

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function getPriority(): int
    {
        return $this->priority;
    }

    public function setPriority(int $priority): static
    {
        $this->priority = $priority;
        return $this;
    }

    public function getDueDate(): ?\DateTimeInterface
    {
        return $this->dueDate;
    }

    public function setDueDate(?\DateTimeInterface $dueDate): static
    {
        $this->dueDate = $dueDate;
        return $this;
    }

    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->createdAt;
    }
}
```

**Step 2: Verify the entity is recognized**

Run: `php bin/console doctrine:mapping:info`
Expected: Shows `App\Entity\Task` as a mapped entity

**Step 3: Commit**

```bash
git add src/Entity/Task.php
git commit -m "feat: add Task entity with ORM mapping"
```

---

### Task 3: Create the TaskRepository

**Files:**
- Create: `src/Repository/TaskRepository.php`

**Step 1: Create the repository file**

```php
<?php

namespace App\Repository;

use App\Entity\Task;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * TaskRepository - The query layer for Task entities.
 *
 * HOW THE REPOSITORY PATTERN WORKS:
 *
 * 1. The Repository sits BETWEEN the Controller and the Database.
 *    Controller → Repository → Database
 *
 * 2. ServiceEntityRepository (the parent class) gives you these methods for free:
 *    - find($id)       → SELECT * FROM task WHERE id = ?
 *    - findAll()       → SELECT * FROM task
 *    - findOneBy([])   → SELECT * FROM task WHERE ... LIMIT 1
 *    - findBy([])      → SELECT * FROM task WHERE ...
 *
 * 3. For complex queries, you write custom methods using QueryBuilder
 *    (a PHP API that generates SQL for you).
 *
 * 4. WHY use a repository instead of querying directly?
 *    - Encapsulation: Query logic lives in one place, not scattered in controllers
 *    - Reusability: Multiple controllers can use the same query methods
 *    - Testability: You can mock the repository in unit tests
 *    - Abstraction: If you switch databases, only the repository changes
 *
 * @extends ServiceEntityRepository<Task>
 */
class TaskRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Task::class);
    }

    /**
     * Find tasks filtered by status.
     *
     * This uses Doctrine's QueryBuilder — a fluent PHP API that generates SQL.
     * Instead of writing raw SQL like:
     *   SELECT * FROM task WHERE status = 'pending' ORDER BY created_at DESC
     *
     * You write:
     *   ->andWhere('t.status = :status')
     *   ->setParameter('status', $status)
     *
     * Benefits:
     * - SQL injection protection (parameterized queries)
     * - Database-agnostic (works with SQLite, MySQL, PostgreSQL)
     * - IDE autocompletion and refactoring support
     *
     * @return Task[]
     */
    public function findByStatus(string $status): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.status = :status')
            ->setParameter('status', $status)
            ->orderBy('t.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
```

**Step 2: Verify autowiring recognizes the repository**

Run: `php bin/console debug:container TaskRepository`
Expected: Shows `App\Repository\TaskRepository` as a registered service

**Step 3: Commit**

```bash
git add src/Repository/TaskRepository.php
git commit -m "feat: add TaskRepository with findByStatus query"
```

---

### Task 4: Create the Database Migration

**Step 1: Generate the migration**

Run: `php bin/console doctrine:migrations:diff`
Expected: Creates a new file in `migrations/` with `CREATE TABLE task` SQL

**Step 2: Run the migration**

Run: `php bin/console doctrine:migrations:migrate --no-interaction`
Expected: Creates the `task` table in `var/data.db`

**Step 3: Verify the table exists**

Run: `php bin/console doctrine:schema:validate`
Expected: Shows schema is in sync

**Step 4: Commit**

```bash
git add migrations/
git commit -m "feat: add migration for task table"
```

---

### Task 5: Create the TaskApiController

**Files:**
- Create: `src/Controller/TaskApiController.php`

**Step 1: Create the controller file**

```php
<?php

namespace App\Controller;

use App\Entity\Task;
use App\Repository\TaskRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * TaskApiController - JSON API endpoints for Task management.
 *
 * HOW THIS CONNECTS EVERYTHING:
 *
 *   Browser/Client sends HTTP request
 *        ↓
 *   Symfony Router matches URL to a controller method
 *        ↓
 *   Controller receives the request + injected dependencies (Repository, EntityManager)
 *        ↓
 *   Controller calls Repository methods to read/write data
 *        ↓
 *   Repository uses Doctrine ORM to translate to SQL and query SQLite
 *        ↓
 *   Controller converts the result to JSON and sends the response
 *
 * KEY SYMFONY CONCEPTS:
 * - #[Route] attribute: maps a URL path to this method
 * - Dependency Injection: Symfony automatically passes TaskRepository and
 *   EntityManagerInterface to the constructor (configured in services.yaml)
 * - JsonResponse: sets Content-Type: application/json automatically
 */
#[Route('/api/tasks', name: 'api_tasks_')]
class TaskApiController extends AbstractController
{
    /**
     * GET /api/tasks — List all tasks, optionally filtered by status.
     *
     * The Repository handles the actual database query.
     * The Controller just orchestrates: receive request → call repo → return response.
     *
     * Query parameter example: GET /api/tasks?status=pending
     */
    #[Route('', name: 'list', methods: ['GET'])]
    public function list(Request $request, TaskRepository $taskRepository): JsonResponse
    {
        // Check if a ?status= query parameter was sent
        $status = $request->query->get('status');

        if ($status) {
            // Use our custom repository method for filtered queries
            $tasks = $taskRepository->findByStatus($status);
        } else {
            // Use the inherited findAll() — no custom code needed
            $tasks = $taskRepository->findAll();
        }

        // Convert each Task entity to an associative array for JSON
        return $this->json(
            array_map(fn(Task $task) => $this->serializeTask($task), $tasks)
        );
    }

    /**
     * GET /api/tasks/{id} — Get a single task by its ID.
     *
     * Symfony automatically converts the {id} URL parameter to an integer.
     * We use Repository::find($id) which is inherited from ServiceEntityRepository.
     */
    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(int $id, TaskRepository $taskRepository): JsonResponse
    {
        $task = $taskRepository->find($id);

        if (!$task) {
            return $this->json(
                ['error' => 'Task not found'],
                Response::HTTP_NOT_FOUND
            );
        }

        return $this->json($this->serializeTask($task));
    }

    /**
     * POST /api/tasks — Create a new task.
     *
     * EntityManager is Doctrine's "unit of work":
     * - persist($task) tells Doctrine "track this new object"
     * - flush() executes the actual INSERT INTO SQL
     *
     * These two steps are always needed when creating or updating entities.
     */
    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        // Decode the JSON body from the request
        $data = json_decode($request->getContent(), true);

        if (!$data || !isset($data['title'])) {
            return $this->json(
                ['error' => 'Title is required'],
                Response::HTTP_BAD_REQUEST
            );
        }

        // Create and populate the entity
        $task = new Task();
        $task->setTitle($data['title']);

        if (isset($data['description'])) {
            $task->setDescription($data['description']);
        }
        if (isset($data['status'])) {
            $task->setStatus($data['status']);
        }
        if (isset($data['priority'])) {
            $task->setPriority((int) $data['priority']);
        }
        if (isset($data['dueDate'])) {
            $task->setDueDate(new \DateTime($data['dueDate']));
        }

        // persist() = "Doctrine, start tracking this object"
        // flush()   = "Doctrine, execute all pending SQL (INSERT in this case)"
        $entityManager->persist($task);
        $entityManager->flush();

        return $this->json(
            $this->serializeTask($task),
            Response::HTTP_CREATED
        );
    }

    /**
     * Convert a Task entity to an array for JSON serialization.
     *
     * This is a simple manual approach. For larger projects,
     * you'd use Symfony's Serializer component or API Platform.
     */
    private function serializeTask(Task $task): array
    {
        return [
            'id' => $task->getId(),
            'title' => $task->getTitle(),
            'description' => $task->getDescription(),
            'status' => $task->getStatus(),
            'priority' => $task->getPriority(),
            'dueDate' => $task->getDueDate()?->format('Y-m-d H:i:s'),
            'createdAt' => $task->getCreatedAt()->format('Y-m-d H:i:s'),
        ];
    }
}
```

**Step 2: Verify routes are registered**

Run: `php bin/console debug:router | grep api_tasks`
Expected output (3 routes):
```
api_tasks_list    GET    /api/tasks
api_tasks_show    GET    /api/tasks/{id}
api_tasks_create  POST   /api/tasks
```

**Step 3: Commit**

```bash
git add src/Controller/TaskApiController.php
git commit -m "feat: add TaskApiController with list, show, create endpoints"
```

---

### Task 6: Test the API Manually

**Step 1: Start the Symfony dev server**

Run: `symfony server:start -d` (or `php -S localhost:8000 -t public/`)

**Step 2: Create a task**

Run:
```bash
curl -X POST http://localhost:8000/api/tasks \
  -H "Content-Type: application/json" \
  -d '{"title": "Learn Repository Pattern", "description": "Understand how repositories work in Symfony", "priority": 5}'
```
Expected: JSON response with the created task and `id: 1`

**Step 3: List all tasks**

Run: `curl http://localhost:8000/api/tasks`
Expected: JSON array with the task created in step 2

**Step 4: Get a single task**

Run: `curl http://localhost:8000/api/tasks/1`
Expected: JSON object for task with id 1

**Step 5: Filter by status**

Run: `curl "http://localhost:8000/api/tasks?status=pending"`
Expected: JSON array with only pending tasks

**Step 6: Test 404 handling**

Run: `curl http://localhost:8000/api/tasks/999`
Expected: `{"error": "Task not found"}` with 404 status

**Step 7: Commit all work**

```bash
git add -A
git commit -m "feat: complete Task entity, repository, and API endpoint"
```
