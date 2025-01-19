<?php
declare(strict_types=1);

namespace App\Entity;

final class Activity
{
    private int $id;
    private ?int $project_id;
    private string $name;
    private ?string $color;
    private ?string $number;
    private ?string $comment;
    private int $visible;
    private ?string $created_at;


    public function getId() {
        return $this->id;
    }

    public function setId($id) {
        $this->id = $id;
        return $this;
    }


    public function getProjectId() {
        return $this->project_id;
    }

    public function setProjectId($project_id) {
        $this->project_id = $project_id;
        return $this;
    }


    public function getName() {
        return $this->name;
    }

    public function setName($name) {
        $this->name = $name;
        return $this;
    }


    public function getColor() {
        return $this->color;
    }

    public function setColor($color) {
        $this->color = $color;
        return $this;
    }


    public function getNumber() {
        return $this->number;
    }

    public function setNumber($number) {
        $this->number = $number;
        return $this;
    }


    public function getComment() {
        return $this->comment;
    }

    public function setComment($comment) {
        $this->comment = $comment;
        return $this;
    }


    public function getVisible() {
        return $this->visible;
    }

    public function setVisible($visible) {
        $this->visible = $visible;
        return $this;
    }


    public function getCreatedAt() {
        return $this->created_at;
    }

    public function setCreatedAt($created_at) {
        $this->created_at = $created_at;
        return $this;
    }

}
