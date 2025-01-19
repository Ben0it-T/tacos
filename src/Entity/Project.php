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


    public function getId() {
        return $this->id;
    }

    public function setId($id) {
        $this->id = $id;
        return $this;
    }


    public function getCustomerId() {
        return $this->customer_id;
    }

    public function setCustomerId($customer_id) {
        $this->customer_id = $customer_id;
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


    public function getStart() {
        return $this->start;
    }

    public function setStart($start) {
        $this->start = $start;
        return $this;
    }


    public function getEnd() {
        return $this->end;
    }

    public function setEnd($end) {
        $this->end = $end;
        return $this;
    }


    public function getLastActivity() {
        return $this->last_activity;
    }

    public function setLastActivity($last_activity) {
        $this->last_activity = $last_activity;
        return $this;
    }


    public function getGlobalActivities() {
        return $this->global_activities;
    }

    public function setGlobalActivities($global_activities) {
        $this->global_activities = $global_activities;
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
