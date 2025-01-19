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



    public function getId() {
        return $this->id;
    }

    public function setId($id) {
        $this->id = $id;
        return $this;
    }


    public function getUsername() {
        return $this->username;
    }

    public function setUsername($username) {
        $this->username = $username;
        return $this;
    }


    public function getName() {
        return $this->name;
    }

    public function setName($name) {
        $this->name = $name;
        return $this;
    }


    public function getEmail() {
        return $this->email;
    }

    public function setEmail($email) {
        $this->email = $email;
        return $this;
    }


    public function getPassword() {
        return $this->password;
    }

    public function setPassword($password) {
        $this->password = $password;
        return $this;
    }


    public function getEnabled() {
        return $this->enabled;
    }

    public function setEnabled($enabled) {
        $this->enabled = $enabled;
        return $this;
    }


    public function getRegistrationDate() {
        return $this->registrationDate;
    }

    public function setRegistrationDate($registrationDate) {
        $this->registrationDate = $registrationDate;
        return $this;
    }


    public function getRole() {
        return $this->roleId;
    }

    public function setRole($roleId) {
        $this->roleId = $roleId;
        return $this;
    }


    public function getLastLogin() {
        return $this->lastLogin;
    }

    public function setLastLogin($lastLogin) {
        $this->lastLogin = $lastLogin;
        return $this;
    }


    public function getRequestToken() {
        return $this->requestToken;
    }

    public function setRequestToken($requestToken) {
        $this->requestToken = $requestToken;
        return $this;
    }


    public function getRequestDate() {
        return $this->requestDate;
    }

    public function setRequestDate($requestDate) {
        $this->requestDate = $requestDate;
        return $this;
    }

}
