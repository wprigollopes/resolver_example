<?php

namespace App\Entity;

use App\Repository\TaskRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * SubTask Entity - Maps to the "sub_task" table in SQLite.
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

// --- #[ORM\Entity] ---
// This attribute registers the class as a Doctrine entity.
// repositoryClass: tells Doctrine which repository class handles queries for this entity.
// When you type-hint TaskRepository in a controller, Symfony knows to inject the right one.
#[ORM\Entity(repositoryClass: TaskRepository::class)]

// --- #[ORM\HasLifecycleCallbacks] ---
// Enables lifecycle hooks on this entity. Without this, methods annotated with
// #[ORM\PrePersist], #[ORM\PostUpdate], etc. would be silently ignored.
#[ORM\HasLifecycleCallbacks]
class SubTask
{
    // --- PRIMARY KEY ---
    // #[ORM\Id]: marks this property as the primary key
    // #[ORM\GeneratedValue]: tells the database to auto-increment (1, 2, 3, ...)
    // #[ORM\Column]: maps to a column; type is inferred from PHP type hint (?int → INTEGER)
    // Why ?int (nullable)? Because before the entity is saved, the ID doesn't exist yet.
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    // --- TITLE ---
    // #[ORM\Column(length: 255)]: creates a VARCHAR(255) column
    // Not nullable → Doctrine enforces NOT NULL at the database level
    #[ORM\Column(length: 255)]
    private string $title;

    // --- DESCRIPTION ---
    // type: Types::TEXT → creates a TEXT column instead of VARCHAR (no length limit)
    // nullable: true → this column allows NULL values in the database
    // The PHP type ?string mirrors this: the ? means "string or null"
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    // --- STATUS ---
    // Stores the task's current state as a simple string.
    // Valid values: "pending", "in_progress", "completed"
    // Default value is set in PHP (= 'pending'), NOT in the database schema.
    // length: 20 → VARCHAR(20), enough for our status strings
    #[ORM\Column(length: 20)]
    private string $status = 'pending';

    // --- PRIORITY ---
    // Range: 1 (lowest) to 5 (highest), defaults to 3.
    // #[ORM\Column] with no arguments → Doctrine infers INTEGER from the `int` type hint.
    #[ORM\Column]
    private int $priority = 3;

    // --- DUE DATE ---
    // type: Types::DATETIME_MUTABLE → stores date AND time (e.g., "2026-03-15 14:30:00")
    // nullable: true → tasks don't always have a due date
    // \DateTimeInterface is the parent interface for both \DateTime and \DateTimeImmutable
    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $dueDate = null;

    // --- CREATED AT ---
    // Automatically set when the entity is first persisted (saved to the database).
    // The #[ORM\PrePersist] lifecycle callback below handles this.
    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $createdAt;

    // --- CONSTRUCTOR ---
    // Sets createdAt immediately when the PHP object is created.
    // This is a safety net — the PrePersist callback also sets it,
    // but having it in the constructor means it's never null.
    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    // --- LIFECYCLE CALLBACK ---
    // #[ORM\PrePersist]: Doctrine calls this method automatically right BEFORE
    // inserting a new row into the database (when you call persist() + flush()).
    // This ensures createdAt is always set to the exact moment of database insertion.
    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->createdAt = new \DateTime();
    }

    // =========================================================================
    // GETTERS AND SETTERS
    // =========================================================================
    // Doctrine uses these to read/write property values.
    // Setters return `static` to enable method chaining:
    //   $task->setTitle('X')->setPriority(5)->setStatus('completed');
    // =========================================================================

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
