<?php
declare(strict_types=1);

namespace App\Entity;

final class Project
{
    private int $id;
    private int $customer_id;
    private string $name;
    private ?string $color;
    private ?string $number;
    private ?string $comment;
    private ?string $start;
    private ?string $end;
    private ?string $last_activity;
    private int $global_activities;
    private int $visible;
    private ?string $created_at;


    public function getId(): int {
        return $this->id;
    }

    public function setId(int $id): self {
        $this->id = $id;
        return $this;
    }


    public function getCustomerId(): int {
        return $this->customer_id;
    }

    public function setCustomerId(int $customer_id): self {
        $this->customer_id = $customer_id;
        return $this;
    }


    public function getName(): string {
        return $this->name;
    }

    public function setName(string $name): self {
        $this->name = $name;
        return $this;
    }


    public function getColor(): ?string {
        return $this->color;
    }

    public function setColor(?string $color): self {
        $this->color = $color;
        return $this;
    }


    public function getNumber(): ?string {
        return $this->number;
    }

    public function setNumber(?string $number): self {
        $this->number = $number;
        return $this;
    }


    public function getComment(): ?string {
        return $this->comment;
    }

    public function setComment(?string $comment): self {
        $this->comment = $comment;
        return $this;
    }


    public function getStart(): ?string {
        return $this->start;
    }

    public function setStart(?string $start): self {
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


    public function getLastActivity(): ?string {
        return $this->last_activity;
    }

    public function setLastActivity(?string $last_activity): self {
        $this->last_activity = $last_activity;
        return $this;
    }


    public function getGlobalActivities(): int {
        return $this->global_activities;
    }

    public function setGlobalActivities(int $global_activities): self {
        $this->global_activities = $global_activities;
        return $this;
    }


    public function getVisible(): int {
        return $this->visible;
    }

    public function setVisible(int $visible): self {
        $this->visible = $visible;
        return $this;
    }


    public function getCreatedAt(): ?string {
        return $this->created_at;
    }

    public function setCreatedAt(?string $created_at): self {
        $this->created_at = $created_at;
        return $this;
    }

}
