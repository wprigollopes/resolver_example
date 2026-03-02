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
 *   1. Browser/Client sends an HTTP request (e.g., GET /api/tasks)
 *        ↓
 *   2. Symfony Router reads #[Route] attributes and matches the URL to a method
 *        ↓
 *   3. Dependency Injection: Symfony automatically creates and passes
 *      TaskRepository and EntityManagerInterface to the method parameters
 *        ↓
 *   4. Controller calls Repository methods to read data from SQLite
 *        ↓
 *   5. Repository uses Doctrine ORM to build SQL, execute it, and return Task objects
 *        ↓
 *   6. Controller converts Task objects to arrays and returns a JsonResponse
 *        ↓
 *   7. Client receives JSON with Content-Type: application/json
 *
 * KEY SYMFONY CONCEPTS USED HERE:
 *
 * - #[Route] attribute: maps a URL path + HTTP method to a controller method
 * - Dependency Injection: Symfony reads type hints and passes the right services
 * - JsonResponse: automatically sets Content-Type: application/json
 * - AbstractController: base class providing helper methods like $this->json()
 */

// --- CLASS-LEVEL ROUTE ---
// This #[Route] sets a PREFIX for all methods in this controller.
// Every method's own route is appended to '/api/tasks'.
// The 'name' parameter is a prefix for route names (used to generate URLs).
#[Route('/api/tasks', name: 'api_tasks_')]
class TaskApiController extends AbstractController
{
    // =========================================================================
    // GET /api/tasks — List all tasks (optionally filtered by status)
    // =========================================================================
    //
    // This endpoint demonstrates the Repository as the query layer:
    // - The Controller doesn't know SQL or how the database works
    // - It just calls repository methods and returns the results as JSON
    // - The ?status= query parameter triggers the custom findByStatus() method
    //
    // Route breakdown:
    //   path: ''          → appended to class prefix → final URL: /api/tasks
    //   name: 'list'      → full route name: api_tasks_list
    //   methods: ['GET']  → only responds to GET requests (not POST, PUT, etc.)

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(Request $request, TaskRepository $taskRepository): JsonResponse
    {
        // --- READING QUERY PARAMETERS ---
        // $request->query is Symfony's wrapper for $_GET parameters.
        // For URL: /api/tasks?status=pending → $status = "pending"
        // For URL: /api/tasks               → $status = null
        $status = $request->query->get('status');

        if ($status) {
            // --- CUSTOM REPOSITORY METHOD ---
            // When a filter is provided, use our custom findByStatus() method.
            // This calls the QueryBuilder code we wrote in TaskRepository.
            // The repository handles the SQL; the controller just asks for data.
            $tasks = $taskRepository->findByStatus($status);
        } else {
            // --- INHERITED REPOSITORY METHOD ---
            // findAll() comes from ServiceEntityRepository for free.
            // It executes: SELECT * FROM task
            // No custom code needed for basic queries!
            $tasks = $taskRepository->findAll();
        }

        // --- SERIALIZATION ---
        // Convert each Task entity object into an associative array,
        // then $this->json() encodes it as JSON and sets the Content-Type header.
        // array_map applies serializeTask() to every element in $tasks.
        return $this->json(
            array_map(fn(Task $task) => $this->serializeTask($task), $tasks)
        );
    }

    // =========================================================================
    // GET /api/tasks/{id} — Get a single task by its database ID
    // =========================================================================
    //
    // Route parameters:
    //   {id} is a URL placeholder. Symfony extracts it and passes it to the method.
    //   URL: /api/tasks/42 → $id = 42
    //
    // We use the inherited find($id) method — no custom code needed.
    // If the task doesn't exist, we return a 404 JSON error.

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(int $id, TaskRepository $taskRepository): JsonResponse
    {
        // --- FIND BY PRIMARY KEY ---
        // find($id) is inherited from ServiceEntityRepository.
        // It executes: SELECT * FROM task WHERE id = ?
        // Returns a Task object, or null if not found.
        $task = $taskRepository->find($id);

        // --- ERROR HANDLING ---
        // If find() returns null, the task doesn't exist in the database.
        // We return a JSON error with HTTP 404 (Not Found) status code.
        // Response::HTTP_NOT_FOUND is a constant = 404 (more readable than a magic number).
        if (!$task) {
            return $this->json(
                ['error' => 'Task not found'],
                Response::HTTP_NOT_FOUND
            );
        }

        return $this->json($this->serializeTask($task));
    }

    // =========================================================================
    // POST /api/tasks — Create a new task
    // =========================================================================
    //
    // This endpoint demonstrates the EntityManager (Doctrine's "unit of work"):
    //
    // - persist($task): tells Doctrine "start tracking this NEW object"
    // - flush():        tells Doctrine "execute all pending SQL NOW"
    //
    // These two steps are ALWAYS needed when creating or updating entities.
    // Why two steps? Performance. You can persist() 100 objects and flush() once —
    // Doctrine batches all INSERTs into a single database transaction.
    //
    // Note: we inject EntityManagerInterface here (not the Repository) because
    // we're WRITING data. The Repository is for READING; the EntityManager is for WRITING.

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        // --- PARSE REQUEST BODY ---
        // $request->getContent() returns the raw body string.
        // json_decode with `true` converts JSON to an associative array.
        // Example body: {"title": "My Task", "priority": 5}
        // Result: ['title' => 'My Task', 'priority' => 5]
        $data = json_decode($request->getContent(), true);

        // --- VALIDATION ---
        // Basic check: the title field is required.
        // In a production app, you'd use Symfony's Validator component for this.
        // Response::HTTP_BAD_REQUEST = 400 (client sent invalid data).
        if (!$data || !isset($data['title'])) {
            return $this->json(
                ['error' => 'Title is required'],
                Response::HTTP_BAD_REQUEST
            );
        }

        // --- CREATE THE ENTITY ---
        // Instantiate a new Task object and populate it with the request data.
        // The constructor sets createdAt automatically.
        // We use setters for each field, checking if it was provided in the JSON.
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

        // --- PERSIST + FLUSH (the two-step save) ---
        //
        // persist($task):
        //   Tells Doctrine: "I have a NEW object. Start tracking it."
        //   At this point, NO SQL has been executed yet.
        //   The entity has no ID yet ($task->getId() === null).
        //
        // flush():
        //   Tells Doctrine: "Execute all pending operations NOW."
        //   Doctrine generates and runs: INSERT INTO task (title, ...) VALUES (?, ...)
        //   AFTER flush(), the entity has an ID ($task->getId() === 1).
        //
        $entityManager->persist($task);
        $entityManager->flush();

        // --- RETURN CREATED RESOURCE ---
        // Response::HTTP_CREATED = 201 (standard HTTP code for "resource created").
        // The response includes the full task with its newly assigned ID.
        return $this->json(
            $this->serializeTask($task),
            Response::HTTP_CREATED
        );
    }

    // =========================================================================
    // SERIALIZATION HELPER
    // =========================================================================
    //
    // Converts a Task entity into an associative array for JSON output.
    //
    // This is a simple manual approach. For larger projects, you'd use:
    // - Symfony's Serializer component (automatic, configurable)
    // - API Platform (full REST/GraphQL framework)
    //
    // But manual serialization makes the process VISIBLE for learning.
    // You control exactly what fields appear in the API response.
    //
    // Note the ?->format() null-safe operator on dueDate:
    //   - If dueDate is null → returns null (no error)
    //   - If dueDate is a DateTime → returns the formatted string

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
