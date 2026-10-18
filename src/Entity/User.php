<?php
declare(strict_types=1);

namespace App\Entity;

final class User
{
    private int $id;
    private string $username;
    private string $name;
    private string $email;
    private string $password;
    private int $enabled;
    private ?string $registrationDate = null;
    private int $roleId;
    private ?string $lastLogin = null;
    private ?string $requestToken = null;
    private ?string $requestDate = null;



    public function getId(): int {
        return $this->id;
    }

    public function setId(int $id): self {
        $this->id = $id;
        return $this;
    }


    public function getUsername(): string {
        return $this->username;
    }

    public function setUsername(string $username): self {
        $this->username = $username;
        return $this;
    }


    public function getName(): string {
        return $this->name;
    }

    public function setName(string $name): self {
        $this->name = $name;
        return $this;
    }


    public function getEmail(): string {
        return $this->email;
    }

    public function setEmail(string $email): self {
        $this->email = $email;
        return $this;
    }


    public function getPassword(): string {
        return $this->password;
    }

    public function setPassword(string $password): self {
        $this->password = $password;
        return $this;
    }


    public function getEnabled(): int {
        return $this->enabled;
    }

    public function setEnabled(int $enabled): self {
        $this->enabled = $enabled;
        return $this;
    }


    public function getRegistrationDate(): ?string {
        return $this->registrationDate;
    }

    public function setRegistrationDate(?string $registrationDate): self {
        $this->registrationDate = $registrationDate;
        return $this;
    }


    public function getRole(): int {
        return $this->roleId;
    }

    public function setRole(int $roleId): self {
        $this->roleId = $roleId;
        return $this;
    }


    public function getLastLogin(): ?string {
        return $this->lastLogin;
    }

    public function setLastLogin(?string $lastLogin): self {
        $this->lastLogin = $lastLogin;
        return $this;
    }


    public function getRequestToken(): ?string {
        return $this->requestToken;
    }

    public function setRequestToken(?string $requestToken): self {
        $this->requestToken = $requestToken;
        return $this;
    }


    public function getRequestDate(): ?string {
        return $this->requestDate;
    }

    public function setRequestDate(?string $requestDate): self {
        $this->requestDate = $requestDate;
        return $this;
    }

}
