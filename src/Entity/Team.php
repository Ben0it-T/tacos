<?php
declare(strict_types=1);

namespace App\Entity;

final class Team
{
    private int $id;
    private string $name;
    private ?string $color;


    public function getId(): int {
        return $this->id;
    }

    public function setId(int $id): self {
        $this->id = $id;
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

}
