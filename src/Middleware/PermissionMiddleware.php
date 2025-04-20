<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Repository\UserRepository;
use App\Repository\TimesheetRepository;

use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

use Slim\Psr7\Response;
use Slim\Routing\RouteContext;
use Slim\Views\Twig;

use PDO;

final class PermissionMiddleware implements MiddlewareInterface
{
    private $container;
    private $userRepository;
    private $timesheetRepository;

    public function __construct(ContainerInterface $container, TimesheetRepository $timesheetRepository, UserRepository $userRepository)
    {
        $this->container = $container;
        $this->timesheetRepository = $timesheetRepository;
        $this->userRepository = $userRepository;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $routeContext = RouteContext::fromRequest($request);
        $routeParser = $routeContext->getRouteParser();
        $route = $routeContext->getRoute();
        $routeName = $route->getName();
        $groups = $route->getGroups();
        $methods = $route->getMethods();
        $arguments = $route->getArguments();

        $publicRoutes = array(
            'login', 'login_attempt',
            'logout',
            'forgot_password', 'forgot_password_attempt',
            'change_password', 'change_password_attempt',
            'redirect'
        );

        $userRoutes = array(
            'logout',
            'dashboard',
            'profile', 'profile_attempt',
            'timesheets', 'timesheets_create', 'timesheets_create_attempt',
            'timesheets_edit', 'timesheets_edit_attempt', 'timesheets_delete', 'timesheets_delete_attempt',
            'timesheets_restart', 'timesheets_stop',
            'timesheets_export',
            'xhr',
        );

        $teamLeadRoutes = array_merge($userRoutes, array(
            'activities', 'activities_create', 'activities_details', 'activities_edit', 'activities_edit_attempt',
            'customers', 'customers_details',
            'projects', 'projects_create', 'projects_details', 'projects_edit', 'projects_edit_attempt',
            'tags', 'tags_create', 'tags_edit', 'tags_edit_attempt',
            'teams', 'teams_edit', 'teams_edit_attempt',
            'timesheets_teams',
        ));

        $adminRoutes = array_merge($teamLeadRoutes, array(
            'customers_create', 'customers_edit', 'customers_edit_attempt',
            'teams_create',
            'users', 'users_create', 'users_edit', 'users_edit_attempt',
        ));

        $session = $request->getAttribute('session');
        $user = false;
        $isLoggedIn = false;
        $role = 0;
        if (isset($session['auth'])) {
            if ($session['auth']['isLoggedIn'] === true && $session['auth']['app'] === "tacos") {
                $user = $this->userRepository->find($session['auth']['userId']);
                if ($user) {
                    $isLoggedIn = ($user->getLastLogin() == $session['auth']['lastLogin'] ? true : false);
                }
            }
        }

        $hasPermissions = false; // has permissions to access
        if (in_array($routeName, $publicRoutes)) {
            $routeNames = $publicRoutes;
            $hasPermissions = true;
        }
        else if ($isLoggedIn) {
            $role = $user->getRole();
            // check if user is teamlead of one or more Teams (tacos_users_teams)
            if ($role == 1 && in_array($routeName, $userRoutes)) {
                $routeNames = $userRoutes;
                $hasPermissions = true;
            }
            else if ($role == 2 && in_array($routeName, $teamLeadRoutes)) {
                // role = TeamLead OR user is teamlead of one or more Teams (tacos_users_teams)
                $routeNames = $teamLeadRoutes;
                $hasPermissions = true;
            }
            else if ($role == 3 && in_array($routeName, $adminRoutes)) {
                $routeNames = $adminRoutes;
                $hasPermissions = true;
            }
        }

        // Redirect if has no permissions to access
        if (!$hasPermissions) {
            $response = new Response();
            if ($request->getHeaderLine('X-Requested-With') === 'XMLHttpRequest') {
                $response->getBody()->write("403");
                return $response->withStatus(403);
            }
            $url = $routeParser->urlFor('login');
            return $response->withStatus(302)->withHeader('Location', $url);
        }

        // Built links
        $navbarRoutes = array(
            'logout',
            'dashboard',
            'profile',
            'timesheets',
            'timesheets_create',
            'timesheets_export',
            'timesheets_teams',
            'users',
            'teams',
            'customers',
            'projects',
            'activities',
            'tags',

        );
        $navbarLinks = array(
            'active' => $routeName,
            'current' => $request->getUri()->getPath(),
        );
        foreach ($navbarRoutes as $name) {
            if (in_array($name, $routeNames)) {
                $navbarLinks[$name] = $routeParser->urlFor($name);
            }
        }

        // Get active timesheet
        $activeTimesheet = array();
        if ($user) {
            $activeTs = $this->timesheetRepository->findOneActiveTimesheetByUserId($user->getId());
            if ($activeTs) {
                $activeTimesheet = array(
                    'id' => $activeTs->getId(),
                    'start' => strtotime($activeTs->getStart()),
                    'stopLink' => $routeParser->urlFor('timesheets_stop', array('timesheetId' => $activeTs->getId())),
                );
            }
        }


        $twig = $this->container->get(Twig::class);
        $twig->getEnvironment()->addGlobal('navLinks', $navbarLinks);
        $twig->getEnvironment()->addGlobal('activeTimesheet', $activeTimesheet);
        $twig->getEnvironment()->addGlobal('currentUser', array(
            'isLoggedIn' => $isLoggedIn,
            'role' => $role,
        ));

        $response = $handler->handle($request);

        return $response;
    }
}
