<?php
declare(strict_types=1);

namespace App\Entity;

final class Role
{
    private int $id;
    private string $name;


    public function getId() {
        return $this->id;
    }

    public function setId($id) {
        $this->id = $id;
        return $this;
    }


    public function getName() {
        return $this->name;
    }

    public function setName($name) {
        $this->name = $name;
        return $this;
    }

}
