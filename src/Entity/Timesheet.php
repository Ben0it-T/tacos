<?php
declare(strict_types=1);

namespace App\Entity;

final class Timesheet
{
    private int $id;
    private int $user_id;
    private int $activity_id;
    private int $project_id;
    private string $start;
    private ?string $end;
    private ?int $duration;
    private ?string $comment;
    private ?string $modified_at;


    public function getId(): int {
        return $this->id;
    }

    public function setId(int $id): self {
        $this->id = $id;
        return $this;
    }


    public function getUserId(): int {
        return $this->user_id;
    }

    public function setUserId(int $user_id): self {
        $this->user_id = $user_id;
        return $this;
    }


    public function getActivityId(): int {
        return $this->activity_id;
    }

    public function setActivityId(int $activity_id): self {
        $this->activity_id = $activity_id;
        return $this;
    }


    public function getProjectId(): int {
        return $this->project_id;
    }

    public function setProjectId(int $project_id): self {
        $this->project_id = $project_id;
        return $this;
    }


    public function getStart(): string {
        return $this->start;
    }

    public function setStart(string $start): self {
        $this->start = $start;
        return $this;
    }


    public function getEnd(): ?string {
        return $this->end;
    }

    public function setEnd(?string $end): self {
        $this->end = $end;
        return $this;
    }


    public function getDuration(): ?int {
        return $this->duration;
    }

    public function setDuration(?int $duration): self {
        $this->duration = $duration;
        return $this;
    }


    public function getComment(): ?string {
        return $this->comment;
    }

    public function setComment(?string $comment): self {
        $this->comment = $comment;
        return $this;
    }


    public function getModifiedAt(): ?string {
        return $this->modified_at;
    }

    public function setModifiedAt(?string $modified_at): self {
        $this->modified_at = $modified_at;
        return $this;
    }

}
