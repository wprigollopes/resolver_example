<?php

namespace App\Repository;

use App\Entity\SubTask;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * SubTaskRepository - The query layer for SubTask entities.
 *
 * THE REPOSITORY PATTERN:
 *
 * The Repository sits BETWEEN the Controller and the Database:
 *
 *   Controller  →  Repository  →  Database (SQLite)
 *   (HTTP layer)   (query layer)   (storage layer)
 *
 * WHY use a repository instead of querying the database directly?
 *
 * 1. ENCAPSULATION: All query logic lives here, not scattered across controllers.
 *    If you need to change a query, you change it in ONE place.
 *
 * 2. REUSABILITY: Multiple controllers/services can call the same query methods.
 *    Example: both a web controller and a CLI command can use findByStatus().
 *
 * 3. TESTABILITY: In unit tests, you can mock the repository to return fake data
 *    without needing a real database connection.
 *
 * 4. ABSTRACTION: If you switch from SQLite to PostgreSQL, only the repository
 *    (and config) changes — controllers stay untouched.
 *
 * INHERITED METHODS (from ServiceEntityRepository):
 *
 *   find($id)            → SELECT * FROM task WHERE id = ?
 *   findAll()            → SELECT * FROM task
 *   findOneBy(['k'=>'v'])→ SELECT * FROM task WHERE k = 'v' LIMIT 1
 *   findBy(['k'=>'v'])   → SELECT * FROM task WHERE k = 'v'
 *
 * You get these for FREE — no code needed. They cover most basic queries.
 *
 * @extends ServiceEntityRepository<SubTask>
 */
class SubTaskRepository extends ServiceEntityRepository
{
    /**
     * Constructor — tells Doctrine which entity this repository manages.
     *
     * ManagerRegistry is Doctrine's service locator. It knows about all
     * database connections and entity managers in your app.
     *
     * parent::__construct($registry, SubTask::class) says:
     * "This repository is responsible for the SubTask entity."
     */
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SubTask::class);
    }

    /**
     * Find all subtasks with a specific status, ordered by creation date (newest first).
     *
     * QUERYBUILDER EXPLAINED:
     *
     * QueryBuilder is a fluent PHP API that generates SQL for you.
     * Instead of writing raw SQL like:
     *
     *   SELECT * FROM task WHERE status = 'pending' ORDER BY created_at DESC
     *
     * You write PHP code that builds the query step by step:
     *
     *   createQueryBuilder('t')         → Start building. 't' is an alias for the task table
     *   ->andWhere('t.status = :status')→ Add a WHERE clause with a named parameter
     *   ->setParameter('status', $val)  → Bind the value to the parameter (prevents SQL injection!)
     *   ->orderBy('t.createdAt', 'DESC')→ ORDER BY created_at DESC
     *   ->getQuery()                    → Compile the QueryBuilder into a Query object
     *   ->getResult()                   → Execute the SQL and return an array of Task objects
     *
     * WHY QueryBuilder instead of raw SQL?
     *
     * - SQL injection protection: parameters are always escaped by Doctrine
     * - Database portability: same PHP code works on SQLite, MySQL, PostgreSQL
     * - Object mapping: returns Task[] objects, not raw associative arrays
     * - IDE support: autocompletion and refactoring work on PHP method calls
     *
     * @param string $status One of: "pending", "in_progress", "completed"
     * @return Task[] Array of Task entity objects matching the status
     */
    public function findByStatus(string $status): array
    {
        // Step 1: Create a QueryBuilder. 't' is the alias for the Task table.
        //         Think of it as: SELECT t.* FROM task AS t
        return $this->createQueryBuilder('t')

            // Step 2: Add a WHERE condition.
            //         :status is a named parameter placeholder (like ? in prepared statements).
            //         Using andWhere() instead of where() allows safe chaining of multiple conditions.
            ->andWhere('t.status = :status')

            // Step 3: Bind the actual value to the :status placeholder.
            //         Doctrine escapes this value — no SQL injection possible.
            ->setParameter('status', $status)

            // Step 4: Sort results by creation date, newest first.
            //         Note: we use the PHP property name (createdAt), NOT the column name (created_at).
            //         Doctrine translates property names to column names automatically.
            ->orderBy('t.createdAt', 'DESC')

            // Step 5: Compile the QueryBuilder into a Doctrine Query object.
            ->getQuery()

            // Step 6: Execute the query and return the results as Task[] objects.
            //         Doctrine hydrates each database row into a Task entity automatically.
            ->getResult();
    }
}
