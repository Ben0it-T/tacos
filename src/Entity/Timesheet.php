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


    public function getId() {
        return $this->id;
    }

    public function setId($id) {
        $this->id = $id;
        return $this;
    }


    public function getUserId() {
        return $this->user_id;
    }

    public function setUserId($user_id) {
        $this->user_id = $user_id;
        return $this;
    }


    public function getActivityId() {
        return $this->activity_id;
    }

    public function setActivityId($activity_id) {
        $this->activity_id = $activity_id;
        return $this;
    }


    public function getProjectId() {
        return $this->project_id;
    }

    public function setProjectId($project_id) {
        $this->project_id = $project_id;
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


    public function getDuration() {
        return $this->duration;
    }

    public function setDuration($duration) {
        $this->duration = $duration;
        return $this;
    }


    public function getComment() {
        return $this->comment;
    }

    public function setComment($comment) {
        $this->comment = $comment;
        return $this;
    }


    public function getModifiedAt() {
        return $this->modified_at;
    }

    public function setModifiedAt($modified_at) {
        $this->modified_at = $modified_at;
        return $this;
    }

}
