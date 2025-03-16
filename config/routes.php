<?php

declare(strict_types=1);

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

// public routes
$app->get( '/login', \App\Controller\LoginController::class . ':loginForm')->setName('login');
$app->post('/login', \App\Controller\LoginController::class . ':loginAction')->setName('login_attempt');

$app->get('/logout', \App\Controller\LoginController::class . ':logoutAction')->setName('logout');

$app->get( '/forgot-password', \App\Controller\PasswordResetController::class . ':requestForm')->setName('forgot_password');
$app->post('/forgot-password', \App\Controller\PasswordResetController::class . ':requestAction')->setName('forgot_password_attempt');

$app->get( '/change-password/{key}', \App\Controller\PasswordResetController::class . ':changeForm')->setName('change_password');
$app->post('/change-password/{key}', \App\Controller\PasswordResetController::class . ':changeAction')->setName('change_password_attempt');

// has permissions to access
$app->get( '/activities', \App\Controller\ActivitiesController::class . ':index')->setName('activities');
$app->post('/activities', \App\Controller\ActivitiesController::class . ':createAction')->setName('activities_create');
$app->get( '/activities/{activityId}/details', \App\Controller\ActivitiesController::class . ':activityDetails')->setName('activities_details');
$app->get( '/activities/{activityId}/edit', \App\Controller\ActivitiesController::class . ':editForm')->setName('activities_edit');
$app->post('/activities/{activityId}/edit', \App\Controller\ActivitiesController::class . ':editAction')->setName('activities_edit_attempt');

$app->get( '/customers', \App\Controller\CustomersController::class . ':index')->setName('customers');
$app->post('/customers', \App\Controller\CustomersController::class . ':createAction')->setName('customers_create');
$app->get( '/customers/{customerId}/details', \App\Controller\CustomersController::class . ':customerDetails')->setName('customers_details');
$app->get( '/customers/{customerId}/edit', \App\Controller\CustomersController::class . ':editForm')->setName('customers_edit');
$app->post('/customers/{customerId}/edit', \App\Controller\CustomersController::class . ':editAction')->setName('customers_edit_attempt');

$app->get( '/dashboard', \App\Controller\DashboardController::class . ':index')->setName('dashboard');

$app->get( '/profile', \App\Controller\ProfileController::class . ':editForm')->setName('profile');
$app->post('/profile', \App\Controller\ProfileController::class . ':editAction')->setName('profile_attempt');

$app->get( '/projects', \App\Controller\ProjectsController::class . ':index')->setName('projects');
$app->post('/projects', \App\Controller\ProjectsController::class . ':createAction')->setName('projects_create');
$app->get( '/projects/{projectId}/details', \App\Controller\ProjectsController::class . ':projectDetails')->setName('projects_details');
$app->get( '/projects/{projectId}/edit', \App\Controller\ProjectsController::class . ':editForm')->setName('projects_edit');
$app->post('/projects/{projectId}/edit', \App\Controller\ProjectsController::class . ':editAction')->setName('projects_edit_attempt');

$app->get( '/tags', \App\Controller\TagsController::class . ':index')->setName('tags');
$app->post('/tags', \App\Controller\TagsController::class . ':createAction')->setName('tags_create');
$app->get( '/tags/{tagId}/edit', \App\Controller\TagsController::class . ':editForm')->setName('tags_edit');
$app->post('/tags/{tagId}/edit', \App\Controller\TagsController::class . ':editAction')->setName('tags_edit_attempt');

$app->get( '/timesheets', \App\Controller\TimesheetController::class . ':index')->setName('timesheets');
$app->get( '/timesheets/create', \App\Controller\TimesheetController::class . ':createForm')->setName('timesheets_create');
$app->post('/timesheets/create', \App\Controller\TimesheetController::class . ':createAction')->setName('timesheets_create_attempt');
$app->get( '/timesheets/{timesheetId}/delete', \App\Controller\TimesheetController::class . ':deleteForm')->setName('timesheets_delete');
$app->post('/timesheets/{timesheetId}/delete', \App\Controller\TimesheetController::class . ':deleteAction')->setName('timesheets_delete_attempt');
$app->get( '/timesheets/{timesheetId}/edit', \App\Controller\TimesheetController::class . ':editForm')->setName('timesheets_edit');
$app->post('/timesheets/{timesheetId}/edit', \App\Controller\TimesheetController::class . ':editAction')->setName('timesheets_edit_attempt');
$app->get( '/timesheets/{timesheetId}/stop', \App\Controller\TimesheetController::class . ':stopAction')->setName('timesheets_stop');
$app->get( '/timesheets/export', \App\Controller\TimesheetController::class . ':exportTimesheets')->setName('timesheets_export');

$app->get( '/teams', \App\Controller\TeamsController::class . ':index')->setName('teams');
$app->post('/teams', \App\Controller\TeamsController::class . ':createAction')->setName('teams_create');
$app->get( '/teams/{teamId}/edit', \App\Controller\TeamsController::class . ':editForm')->setName('teams_edit');
$app->post('/teams/{teamId}/edit', \App\Controller\TeamsController::class . ':editAction')->setName('teams_edit_attempt');

$app->get( '/users', \App\Controller\UsersController::class . ':index')->setName('users');
$app->post('/users', \App\Controller\UsersController::class . ':createAction')->setName('users_create');
$app->get( '/users/{username}/edit', \App\Controller\UsersController::class . ':editForm')->setName('users_edit');
$app->post('/users/{username}/edit', \App\Controller\UsersController::class . ':editAction')->setName('users_edit_attempt');

$app->get('/xhr/{action}/[{key}]', \App\Controller\XhrController::class . ':xhrAction')->setName('xhr');

// Redirect
$app->redirect('/', $app->getRouteCollector()->getRouteParser()->urlFor('timesheets'), 301)->setName('redirect');

