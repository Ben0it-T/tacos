<?php
declare(strict_types=1);

namespace App\Controller;

use App\Service\CustomerService;
use App\Service\ProjectService;
use App\Service\TeamService;
use App\Service\UserService;

use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

use Slim\Flash\Messages;
use Slim\Routing\RouteContext;
use Slim\Views\Twig;

final class CustomersController
{
    private $container;
    private $customerService;
    private $projectService;
    private $teamService;
    private $userService;

    public function __construct(ContainerInterface $container, CustomerService $customerService, ProjectService $projectService, TeamService $teamService, UserService $userService)
    {
        $this->container = $container;
        $this->customerService = $customerService;
        $this->projectService = $projectService;
        $this->teamService = $teamService;
        $this->userService = $userService;
    }

    public function index(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $twig  = $this->container->get(Twig::class);
        $flash = $this->container->get('flash');
        $translations = $this->container->get('translations');

        $routeContext = RouteContext::fromRequest($request);
        $routeParser = $routeContext->getRouteParser();

        $session = $request->getAttribute('session');
        $currentUser = $this->userService->findUser($session['auth']['userId']);

        // Get customers
        if ($currentUser->getRole() === 3) {
            $customers = $this->customerService->findAllCustomerswithTeamsCountAndProjectsCount();
        }
        else {
            $customers = $this->customerService->findAllCustomersWithTeamsCountAndProjectsCountByTeamleaderId($currentUser->getId());
        }

        for ($i=0; $i < count($customers); $i++) {
            $customers[$i]['editLink'] = $routeParser->urlFor('customers_edit', array('customerId' => $customers[$i]['id']));
            $customers[$i]['viewLink'] = $routeParser->urlFor('customers_details', array('customerId' => $customers[$i]['id']));
        }

        // Get teams
        $teams = $this->teamService->findAllTeams();
        $teamsList = array();
        foreach ($teams as $team) {
            $teamsList[] = array(
                'id' => $team->getId(),
                'name' => $team->getName(),
            );
        }

        // Get colors
        $colorChoices = $this->container->get('settings')['theme']['colorChoices'];
        $colorsList = array();
        foreach (explode(',',$colorChoices) as $key => $value) {
            list($colorName, $colorValue) = explode('|', $value);
            //$colorsList[$colorName] = $colorValue;
            $colorsList[$key] = array(
                'name' => $colorName,
                'value' => $colorValue,
            );
        }

        $viewData = array();
        $viewData['userRole'] = $currentUser->getRole();
        $viewData['customers'] = $customers;
        $viewData['colors'] = $colorsList;
        $viewData['teams'] = $teamsList;

        $viewData['flashMsgSuccess'] = $flash->getFirstMessage('success');
        $viewData['flashMsgError'] = $flash->getFirstMessage('error');

        return $twig->render($response, 'customers.html.twig', $viewData);
    }

    public function createAction(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $flash = $this->container->get('flash');
        $translations = $this->container->get('translations');

        $data = $request->getParsedBody();
        $routeParser = RouteContext::fromRequest($request)->getRouteParser();

        $errors = $this->customerService->createCustomer($data);

        if (empty($errors)) {
            $flash->addMessage('success', $translations['form_success_create_customer']);
        }
        else {
            $flash->addMessage('error', $errors);
        }

        // redirect
        $url = $routeParser->urlFor('customers');
        return $response->withStatus(302)->withHeader('Location', $url);
    }

    public function customerDetails(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $twig  = $this->container->get(Twig::class);
        $translations = $this->container->get('translations');

        $routeContext = RouteContext::fromRequest($request);
        $routeParser = $routeContext->getRouteParser();

        $session = $request->getAttribute('session');
        $currentUser = $this->userService->findUser($session['auth']['userId']);

        // Get customers
        if ($currentUser->getRole() === 3) {
            $customer = $this->customerService->findCustomer(intval($args['customerId']));
        }
        else {
            $customer = $this->customerService->findOneByIdAndTeamleaderId(intval($args['customerId']), intval($currentUser->getId()));
        }

        if ($customer) {
            // Get colors
            $colorChoices = $this->container->get('settings')['theme']['colorChoices'];
            $colorsList = array();
            foreach (explode(',',$colorChoices) as $key => $value) {
                list($colorName, $colorValue) = explode('|', $value);
                //$colorsList[$colorName] = $colorValue;
                $colorsList[$key] = array(
                    'name' => $colorName,
                    'value' => $colorValue,
                );
            }

            // Get selected Teams
            $selectedTeams = $this->teamService->findAllTeamsByCustomerId($customer->getId());

            // Get projects
            $projects = $this->projectService->findAllByCustomerId($customer->getId());

            $viewData = array();
            $viewData['customer'] = $customer;
            $viewData['colors'] = $colorsList;
            $viewData['selectedTeams'] = $selectedTeams;
            $viewData['projects'] = $projects;

            return $twig->render($response, 'customer-details.html.twig', $viewData);
        }

        // redirect
        $url = $routeParser->urlFor('customers');
        return $response->withStatus(302)->withHeader('Location', $url);
    }

    public function editForm(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $twig  = $this->container->get(Twig::class);
        $flash = $this->container->get('flash');
        $translations = $this->container->get('translations');

        $routeContext = RouteContext::fromRequest($request);
        $routeParser = $routeContext->getRouteParser();

        $customer = $this->customerService->findCustomer(intval($args['customerId']));
        if ($customer) {
            // Get teams
            $teams = $this->teamService->findAllTeams();
            $teamsList = array();
            foreach ($teams as $team) {
                $teamsList[] = array(
                    'id' => $team->getId(),
                    'name' => $team->getName(),
                );
            }

            // Get colors
            $colorChoices = $this->container->get('settings')['theme']['colorChoices'];
            $colorsList = array();
            foreach (explode(',',$colorChoices) as $key => $value) {
                list($colorName, $colorValue) = explode('|', $value);
                //$colorsList[$colorName] = $colorValue;
                $colorsList[$key] = array(
                    'name' => $colorName,
                    'value' => $colorValue,
                );
            }

            // Get selected Teams
            $selectedTeams = $this->teamService->findAllTeamsByCustomerId($customer->getId());
            $selectedTeamsIds = array();
            foreach ($selectedTeams as $selectedTeam) {
                $selectedTeamsIds[] = $selectedTeam->getId();
            }

            $viewData = array();
            $viewData['customer'] = $customer;
            $viewData['colors'] = $colorsList;
            $viewData['teams'] = $teams;
            $viewData['selectedTeams'] = $selectedTeams;
            $viewData['selectedTeamsIds'] = $selectedTeamsIds;

            $viewData['flashMsgSuccess'] = $flash->getFirstMessage('success');
            $viewData['flashMsgError'] = $flash->getFirstMessage('error');

            return $twig->render($response, 'customer-edit.html.twig', $viewData);
        }

        // redirect
        $url = $routeParser->urlFor('customers');
        return $response->withStatus(302)->withHeader('Location', $url);
    }

    public function editAction(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $flash = $this->container->get('flash');
        $translations = $this->container->get('translations');

        $data = $request->getParsedBody();
        $routeParser = RouteContext::fromRequest($request)->getRouteParser();

        $customer = $this->customerService->findCustomer(intval($args['customerId']));
        if ($customer) {
            $errors = $this->customerService->updateCustomer($customer, $data);

            if (empty($errors)) {
                $flash->addMessage('success', $translations['form_success_update']);
            }
            else {
                $flash->addMessage('error', $errors);
                $url = $routeParser->urlFor('customers_edit', array('customerId' => $args['customerId']));
                return $response->withStatus(302)->withHeader('Location', $url);
            }
        }

        // redirect
        $url = $routeParser->urlFor('customers');
        return $response->withStatus(302)->withHeader('Location', $url);
    }
}
