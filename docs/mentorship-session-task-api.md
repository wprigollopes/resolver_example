# Mentorship Session: Entity, Repository & API in Symfony

## Session Goal

Build a **Task management API** from scratch to understand how Symfony connects
these three layers:

```
┌──────────────┐     ┌──────────────────┐     ┌─────────────┐     ┌──────────┐
│  HTTP Client │────▶│  Controller      │────▶│  Repository │────▶│  SQLite  │
│  (curl/web)  │◀────│  (JSON response) │◀────│  (queries)  │◀────│  (data)  │
└──────────────┘     └──────────────────┘     └─────────────┘     └──────────┘
```

Each layer has a single responsibility:
- **Entity (Model):** Defines the data structure — what a "Task" looks like
- **Repository:** Encapsulates all database queries — how to find/save tasks
- **Controller:** Handles HTTP requests — receives input, calls the repository, returns JSON

---

## Part 1: Setting Up SQLite

### What is SQLite?

SQLite is a file-based database. Unlike MySQL or PostgreSQL, it doesn't need
a running server — the entire database lives in a single `.db` file. This makes
it perfect for development and learning.

### Step 1: Configure the database connection

Open `.env` and find the `DATABASE_URL` line. Change it to:

```env
DATABASE_URL="sqlite:///%kernel.project_dir%/var/data.db"
```

**What does this URL mean?**

| Part                    | Meaning                                        |
|-------------------------|-------------------------------------------------|
| `sqlite://`             | Use the SQLite driver                           |
| `%kernel.project_dir%`  | Symfony variable — resolves to your project root |
| `/var/data.db`          | The file where all data is stored               |

### Step 2: Create the database file

```bash
php bin/console doctrine:database:create
```

This creates an empty `var/data.db` file. You can verify it:

```bash
ls -la var/data.db
```

### Step 3: Verify Doctrine can connect

```bash
php bin/console doctrine:schema:validate
```

If the connection works, Doctrine will report on the schema status.
At this point it will say there are no mapped entities — that's expected.

---

## Part 2: Understanding the Entity (Model)

### What is an Entity?

An Entity is a PHP class where each instance represents **one row** in a
database table. Doctrine ORM reads the PHP attributes on the class and
automatically knows how to create the table, insert rows, query them, etc.

### File: `src/Entity/Task.php`

Open this file and study it from top to bottom.

#### The class-level attributes

```php
#[ORM\Entity(repositoryClass: TaskRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Task
```

- `#[ORM\Entity]` — Tells Doctrine: "this class is a database table."
- `repositoryClass: TaskRepository::class` — Links this entity to its repository
  (we'll create that next). When you ask Doctrine for the Task repository,
  it knows to return a `TaskRepository` instance.
- `#[ORM\HasLifecycleCallbacks]` — Enables lifecycle hooks (like auto-setting
  `createdAt` before the first save).

#### Column mapping

Each property maps to a database column:

```php
#[ORM\Id]                        // This is the primary key
#[ORM\GeneratedValue]            // Auto-increment (1, 2, 3, ...)
#[ORM\Column]                    // Map to a column (type inferred from PHP type)
private ?int $id = null;

#[ORM\Column(length: 255)]       // VARCHAR(255)
private string $title;

#[ORM\Column(type: Types::TEXT, nullable: true)]  // TEXT, allows NULL
private ?string $description = null;

#[ORM\Column(length: 20)]        // VARCHAR(20)
private string $status = 'pending';

#[ORM\Column]                    // INTEGER (inferred from int type)
private int $priority = 3;

#[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
private ?\DateTimeInterface $dueDate = null;

#[ORM\Column(type: Types::DATETIME_MUTABLE)]
private \DateTimeInterface $createdAt;
```

**Key insight:** Doctrine reads the PHP type hints (`string`, `int`, `?int`)
to infer column types. You only need to specify `type:` explicitly for
special types like `TEXT` or `DATETIME_MUTABLE`.

#### Lifecycle callbacks

```php
#[ORM\PrePersist]
public function onPrePersist(): void
{
    $this->createdAt = new \DateTime();
}
```

This method runs automatically **right before** Doctrine inserts a new row.
It ensures `createdAt` is always set without the controller having to worry
about it.

#### Getters and Setters

The rest of the entity is getters/setters. Doctrine uses them to read and
write property values. Note the fluent `return $this` pattern on setters:

```php
public function setTitle(string $title): static
{
    $this->title = $title;
    return $this;    // allows chaining: $task->setTitle('X')->setPriority(5)
}
```

### Verify the entity is recognized

After creating the entity, check that Doctrine sees it:

```bash
php bin/console doctrine:mapping:info
```

Expected output:

```
Found 1 mapped entity:
 [OK] App\Entity\Task
```

---

## Part 3: Creating the Database Table (Migration)

### What is a Migration?

A migration is a versioned SQL script. Instead of writing `CREATE TABLE`
manually, Doctrine compares your entity to the current database schema and
generates the SQL diff automatically.

### Step 1: Generate the migration

```bash
php bin/console doctrine:migrations:diff
```

This creates a file in `migrations/` like `Version20260302XXXXXX.php`.
Open it and look at the `up()` method — you'll see the `CREATE TABLE task`
SQL that Doctrine generated from your entity attributes.

### Step 2: Run the migration

```bash
php bin/console doctrine:migrations:migrate --no-interaction
```

This executes the SQL against your SQLite database. The table now exists.

### Step 3: Verify the schema

```bash
php bin/console doctrine:schema:validate
```

Expected output:

```
[OK] The mapping files are correct.
[OK] The database schema is in sync with the mapping files.
```

### Optional: Inspect the SQLite database directly

```bash
php bin/console dbal:run-sql "SELECT * FROM task"
```

This runs raw SQL against the database. The table is empty for now, but
you can verify it exists.

---

## Part 4: Understanding the Repository

### What is the Repository Pattern?

The Repository pattern creates a **dedicated class** for all database queries
related to an entity. Think of it as a specialized "librarian" for Tasks:

```
Without Repository:                 With Repository:
┌────────────┐                      ┌────────────┐
│ Controller │──SQL──▶ Database     │ Controller │──findByStatus()──▶┌────────────┐
│            │                      │            │                   │ Repository │──▶ DB
│ Service A  │──SQL──▶ Database     │ Service A  │──findByStatus()──▶│            │
│            │                      │            │                   │ (one place │
│ Command    │──SQL──▶ Database     │ Command    │──findByStatus()──▶│  for all   │
└────────────┘                      └────────────┘                   │  queries)  │
 ❌ Query logic scattered            ✅ Query logic centralized       └────────────┘
 ❌ Duplicated SQL                   ✅ Reusable methods
 ❌ Hard to test                     ✅ Easy to mock in tests
```

### File: `src/Repository/TaskRepository.php`

#### The class declaration

```php
class TaskRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Task::class);
    }
```

- `ServiceEntityRepository` is Doctrine's base repository class.
- The constructor tells Doctrine: "this repository manages `Task` entities."
- By extending this class, you **inherit** these methods for free:

| Method                          | Equivalent SQL                                    |
|---------------------------------|---------------------------------------------------|
| `find(1)`                       | `SELECT * FROM task WHERE id = 1`                 |
| `findAll()`                     | `SELECT * FROM task`                              |
| `findOneBy(['status' => 'x'])` | `SELECT * FROM task WHERE status = 'x' LIMIT 1`  |
| `findBy(['status' => 'x'])`    | `SELECT * FROM task WHERE status = 'x'`           |

**You never write SQL for basic queries.** Doctrine handles it.

#### Custom queries with QueryBuilder

For more complex queries, you use the QueryBuilder:

```php
public function findByStatus(string $status): array
{
    return $this->createQueryBuilder('t')    // 't' is an alias for the Task table
        ->andWhere('t.status = :status')     // WHERE clause with a parameter placeholder
        ->setParameter('status', $status)    // Bind the value (prevents SQL injection)
        ->orderBy('t.createdAt', 'DESC')     // ORDER BY created_at DESC
        ->getQuery()                         // Build the final query object
        ->getResult();                       // Execute and return Task[] array
    ;
}
```

**Why QueryBuilder instead of raw SQL?**

1. **SQL injection protection** — Parameters are always escaped
2. **Database portability** — Same code works on SQLite, MySQL, PostgreSQL
3. **Object mapping** — Returns `Task` objects, not raw arrays
4. **Composability** — You can add conditions dynamically

### Verify the repository is registered

```bash
php bin/console debug:container TaskRepository
```

Symfony auto-registers it as a service thanks to the `autowire: true`
setting in `config/services.yaml`.

---

## Part 5: Understanding the Controller (API Endpoint)

### How does Symfony route a request to your code?

```
1. Client sends:  GET /api/tasks?status=pending
                     │
2. Symfony Router:   matches URL to TaskApiController::list()
                     │
3. Dependency Injection: passes TaskRepository to the method
                     │
4. Controller:       calls $taskRepository->findByStatus('pending')
                     │
5. Repository:       builds SQL, queries SQLite, returns Task[] objects
                     │
6. Controller:       converts Task[] to JSON, returns JsonResponse
                     │
7. Client receives:  [{"id":1,"title":"...","status":"pending",...}]
```

### File: `src/Controller/TaskApiController.php`

#### Route prefix

```php
#[Route('/api/tasks', name: 'api_tasks_')]
class TaskApiController extends AbstractController
```

The class-level `#[Route]` sets a **prefix** for all methods in this controller.
Every method's route is appended to `/api/tasks`.

#### GET /api/tasks — List endpoint

```php
#[Route('', name: 'list', methods: ['GET'])]
public function list(Request $request, TaskRepository $taskRepository): JsonResponse
```

- The route is `''` (empty), so the final URL is `/api/tasks` (just the prefix).
- `methods: ['GET']` — Only responds to GET requests.
- `TaskRepository $taskRepository` — Symfony **injects** the repository automatically.
  You don't create it with `new`. This is called **Dependency Injection**.

#### GET /api/tasks/{id} — Show endpoint

```php
#[Route('/{id}', name: 'show', methods: ['GET'])]
public function show(int $id, TaskRepository $taskRepository): JsonResponse
```

- `{id}` is a **route parameter** — Symfony extracts it from the URL.
- If someone requests `/api/tasks/42`, Symfony passes `$id = 42`.
- We use `$taskRepository->find($id)` — the inherited method.

#### POST /api/tasks — Create endpoint

```php
#[Route('', name: 'create', methods: ['POST'])]
public function create(Request $request, EntityManagerInterface $entityManager): JsonResponse
```

- Uses `EntityManagerInterface` (not the repository) because we're **writing** data.
- `persist()` + `flush()` is the two-step pattern for saving:

```php
$entityManager->persist($task);   // Step 1: "Track this new object"
$entityManager->flush();          // Step 2: "Execute the INSERT SQL now"
```

**Why two steps?** Doctrine batches operations for performance. You can
persist 100 objects and flush once — Doctrine runs all INSERTs in a single
transaction.

#### Manual serialization

```php
private function serializeTask(Task $task): array
{
    return [
        'id'          => $task->getId(),
        'title'       => $task->getTitle(),
        'description' => $task->getDescription(),
        'status'      => $task->getStatus(),
        'priority'    => $task->getPriority(),
        'dueDate'     => $task->getDueDate()?->format('Y-m-d H:i:s'),
        'createdAt'   => $task->getCreatedAt()->format('Y-m-d H:i:s'),
    ];
}
```

This manually converts a `Task` object to a PHP array, which `$this->json()`
then encodes as JSON. For larger projects, you'd use Symfony's Serializer
component or API Platform, but manual serialization makes the process visible.

### Verify routes are registered

```bash
php bin/console debug:router | grep api_tasks
```

Expected:

```
api_tasks_list    GET    /api/tasks
api_tasks_show    GET    /api/tasks/{id}
api_tasks_create  POST   /api/tasks
```

---

## Part 6: Testing the API

### Start the development server

```bash
# Option A: Symfony CLI (if installed)
symfony server:start -d

# Option B: PHP built-in server
php -S localhost:8000 -t public/
```

### Create a task

```bash
curl -X POST http://localhost:8000/api/tasks \
  -H "Content-Type: application/json" \
  -d '{
    "title": "Learn Repository Pattern",
    "description": "Understand how repositories encapsulate database queries",
    "priority": 5
  }'
```

Expected response:

```json
{
  "id": 1,
  "title": "Learn Repository Pattern",
  "description": "Understand how repositories encapsulate database queries",
  "status": "pending",
  "priority": 5,
  "dueDate": null,
  "createdAt": "2026-03-02 10:30:00"
}
```

### Create a second task with different status

```bash
curl -X POST http://localhost:8000/api/tasks \
  -H "Content-Type: application/json" \
  -d '{
    "title": "Build the API Controller",
    "status": "completed",
    "priority": 3
  }'
```

### List all tasks

```bash
curl http://localhost:8000/api/tasks
```

Returns an array with both tasks.

### Filter tasks by status

```bash
curl "http://localhost:8000/api/tasks?status=pending"
```

Returns only tasks with `"status": "pending"`.

### Get a specific task

```bash
curl http://localhost:8000/api/tasks/1
```

### Test error handling — task not found

```bash
curl http://localhost:8000/api/tasks/999
```

Expected: `{"error": "Task not found"}` with HTTP status 404.

### Test error handling — missing title

```bash
curl -X POST http://localhost:8000/api/tasks \
  -H "Content-Type: application/json" \
  -d '{"description": "no title provided"}'
```

Expected: `{"error": "Title is required"}` with HTTP status 400.

---

## Summary: How Everything Connects

```
┌──────────────────────────────────────────────────────────────────────────┐
│                        SYMFONY APPLICATION                              │
│                                                                         │
│  .env                                                                   │
│  └─ DATABASE_URL="sqlite:///var/data.db"                                │
│       │                                                                 │
│       ▼                                                                 │
│  config/packages/doctrine.yaml                                          │
│  └─ Reads DATABASE_URL, configures Doctrine DBAL + ORM                  │
│       │                                                                 │
│       ▼                                                                 │
│  src/Entity/Task.php                    ◀── THE DATA MODEL              │
│  └─ PHP class with ORM attributes                                       │
│  └─ Each property = one database column                                 │
│  └─ Doctrine generates CREATE TABLE from this                           │
│       │                                                                 │
│       ▼                                                                 │
│  src/Repository/TaskRepository.php      ◀── THE QUERY LAYER            │
│  └─ Extends ServiceEntityRepository                                     │
│  └─ Inherits find(), findAll(), findOneBy(), findBy()                   │
│  └─ Custom method: findByStatus() with QueryBuilder                     │
│  └─ The ONLY place where database queries live                          │
│       │                                                                 │
│       ▼                                                                 │
│  src/Controller/TaskApiController.php   ◀── THE HTTP LAYER             │
│  └─ #[Route] maps URLs to methods                                       │
│  └─ Receives Repository via dependency injection                        │
│  └─ Calls repository methods, returns JsonResponse                      │
│  └─ Never touches the database directly                                 │
│                                                                         │
└──────────────────────────────────────────────────────────────────────────┘
```

### Key Takeaways

1. **Separation of Concerns:** Each layer does one thing. The controller
   doesn't know SQL. The repository doesn't know about HTTP. The entity
   doesn't know about either.

2. **Dependency Injection:** You never write `new TaskRepository()`. Symfony
   creates it for you and passes it where needed. This is configured
   automatically by `autowire: true` in `config/services.yaml`.

3. **The Repository is the bridge** between your PHP objects (Entity) and
   the database (SQLite). It translates method calls like `findByStatus('pending')`
   into SQL queries and returns Entity objects back.

4. **persist + flush:** Creating/updating entities is always a two-step
   process. `persist()` says "track this", `flush()` says "save now."

5. **SQLite is just a file.** The entire database is `var/data.db`. You can
   delete it and re-run migrations to start fresh at any time.
