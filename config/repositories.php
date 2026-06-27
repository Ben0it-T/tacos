<?php
declare(strict_types=1);

use App\Helper\SqlHelper;

use App\Repository\ActivityRepository;
use App\Repository\CustomerRepository;
use App\Repository\LoginAttemptsRepository;
use App\Repository\ProjectRepository;
use App\Repository\RoleRepository;
use App\Repository\TagRepository;
use App\Repository\TeamRepository;
use App\Repository\TimesheetRepository;
use App\Repository\UserRepository;

use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

return [
    ActivityRepository::class => function (ContainerInterface $c) {
        return new ActivityRepository(
            $c->get(PDO::class),
            $c->get(LoggerInterface::class)
        );
    },

    CustomerRepository::class => function (ContainerInterface $c) {
        return new CustomerRepository(
            $c->get(PDO::class),
            $c->get(LoggerInterface::class)
        );
    },

    LoginAttemptsRepository::class => function (ContainerInterface $c) {
        return new LoginAttemptsRepository(
            $c->get(PDO::class),
            $c->get(LoggerInterface::class)
        );
    },

    ProjectRepository::class => function (ContainerInterface $c) {
        return new ProjectRepository(
            $c->get(PDO::class),
            $c->get(LoggerInterface::class)
        );
    },

    RoleRepository::class => function (ContainerInterface $c) {
        return new RoleRepository(
            $c->get(PDO::class)
        );
    },

    TagRepository::class => function (ContainerInterface $c) {
        return new TagRepository(
            $c->get(PDO::class),
            $c->get(LoggerInterface::class)
        );
    },

    TeamRepository::class => function (ContainerInterface $c) {
        return new TeamRepository(
            $c->get(PDO::class),
            $c->get(LoggerInterface::class)
        );
    },

    TimesheetRepository::class => function (ContainerInterface $c) {
        return new TimesheetRepository(
            $c->get(PDO::class),
            $c->get(SqlHelper::class),
            $c->get(LoggerInterface::class)
        );
    },

    UserRepository::class => function (ContainerInterface $c) {
        return new UserRepository(
            $c->get(PDO::class),
            $c->get(SqlHelper::class),
            $c->get(LoggerInterface::class)
        );
    },

];
