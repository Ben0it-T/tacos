<?php
declare(strict_types=1);

namespace App\Controller;

use App\Entity\Customer;
use App\Entity\User;
use App\Helper\ControllerHelper;
use App\Service\CustomerService;
use App\Service\ProjectService;
use App\Service\TeamService;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

use Slim\Flash\Messages;
use Slim\Views\Twig;

final class CustomersController
{
    private Twig $twig;
    private Messages $flash;
    private CustomerService $customerService;
    private ProjectService $projectService;
    private TeamService $teamService;
    private ControllerHelper $helper;
    private array $options;
    private array $translations;

    public function __construct(Twig $twig, Messages $flash, CustomerService $customerService, ProjectService $projectService, TeamService $teamService, ControllerHelper $helper, array $options, array $translations)
    {
        $this->twig = $twig;
        $this->flash = $flash;
        $this->customerService = $customerService;
        $this->projectService = $projectService;
        $this->teamService = $teamService;
        $this->helper = $helper;
        $this->options = $options;
        $this->translations = $translations;
    }

    public function index(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $currentUser = $this->helper->getCurrentUser();
        if (!$currentUser) {
            return $this->helper->redirect($request, $response, 'login');
        }

        $customers = $this->helper->isAdmin($currentUser)
            ? $this->customerService->findAllCustomersWithTeamsCountAndProjectsCount()
            : $this->customerService->findAllCustomersWithTeamsCountAndProjectsCountByTeamleaderId($currentUser->getId());

        $customers = $this->addCustomerLinks($request, $customers);

        $teams = $this->teamService->findAllTeams();

        $colors = $this->helper->parseColorChoices((string)($this->options['colorChoices'] ?? ''));

        return $this->twig->render($response, 'customers.html.twig', [
            'userRole'        => $currentUser->getRole(),
            'colors'          => $colors,
            'teams'           => $this->helper->mapIdNameList($teams),
            'customers'       => $customers,
            'flashMsgSuccess' => $this->flash->getFirstMessage('success'),
            'flashMsgError'   => $this->flash->getFirstMessage('error'),
        ]);
    }

    public function createAction(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $data = (array) $request->getParsedBody();
        $errors = $this->customerService->createCustomer($data);

        if ($errors === '') {
            $this->flash->addMessage('success', $this->translations['form_success_create_customer']);
        }
        else {
            $this->flash->addMessage('error', $errors);
        }

        return $this->helper->redirect($request, $response, 'customers');
    }

    public function customerDetails(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $currentUser = $this->helper->getCurrentUser();
        if (!$currentUser) {
            return $this->helper->redirect($request, $response, 'login');
        }

        $customerId = (int)($args['customerId'] ?? 0);
        $customer = $this->getAccessibleCustomer($currentUser, $customerId);

        if (!$customer) {
            return $this->helper->redirect($request, $response, 'customers');
        }

        $colors = $this->helper->parseColorChoices((string)($this->options['colorChoices'] ?? ''));

        $selectedTeams = $this->teamService->findAllTeamsByCustomerId($customer->getId());

        $projects = $this->projectService->findAllByCustomerId($customer->getId());

        return $this->twig->render($response, 'customer-details.html.twig', [
            'customer'      => $customer,
            'colors'        => $colors,
            'selectedTeams' => $selectedTeams,
            'projects'      => $projects,
        ]);
    }

    public function editForm(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $currentUser = $this->helper->getCurrentUser();
        if (!$currentUser) {
            return $this->helper->redirect($request, $response, 'login');
        }

        $customerId = (int)($args['customerId'] ?? 0);
        $customer = $this->getAccessibleCustomer($currentUser, $customerId);

        if (!$customer) {
            return $this->helper->redirect($request, $response, 'customers');
        }

        $teams = $this->teamService->findAllTeams();

        $colors = $this->helper->parseColorChoices((string)($this->options['colorChoices'] ?? ''));

        $selectedTeams = $this->teamService->findAllTeamsByCustomerId($customer->getId());
        $selectedTeamsIds = array_map(static fn($t) => $t->getId(), $selectedTeams);

        return $this->twig->render($response, 'customer-edit.html.twig', [
            'customer'         => $customer,
            'colors'           => $colors,
            'teams'            => $this->helper->mapIdNameList($teams),
            'selectedTeams'    => $selectedTeams,
            'selectedTeamsIds' => $selectedTeamsIds,
            'flashMsgSuccess'  => $this->flash->getFirstMessage('success'),
            'flashMsgError'    => $this->flash->getFirstMessage('error'),
        ]);
    }

    public function editAction(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $currentUser = $this->helper->getCurrentUser();
        if (!$currentUser) {
            return $this->helper->redirect($request, $response, 'login');
        }

        $customerId = (int)($args['customerId'] ?? 0);
        $customer = $this->getAccessibleCustomer($currentUser, $customerId);

        if (!$customer) {
            return $this->helper->redirect($request, $response, 'customers');
        }

        $data = (array) $request->getParsedBody();

        $errors = $this->customerService->updateCustomer($customer, $data);
        if ($errors === '') {
            $this->flash->addMessage('success', $this->translations['form_success_update']);
            return $this->helper->redirect($request, $response, 'customers');
        }

        $this->flash->addMessage('error', $errors);
        return $this->helper->redirect($request, $response, 'customers_edit', ['customerId' => $customerId]);
    }

    // Helpers
    private function addCustomerLinks(ServerRequestInterface $request, array $customers): array
    {
        foreach ($customers as &$customer) {
            if (!isset($customer['id'])) {
                continue;
            }

            $id = (int)$customer['id'];

            $customer['editLink'] = $this->helper->getUrlFor($request, 'customers_edit', ['customerId' => $id]);
            $customer['viewLink'] = $this->helper->getUrlFor($request, 'customers_details', ['customerId' => $id]);
        }
        unset($customer);

        return $customers;
    }

    private function getAccessibleCustomer(User $user, int $customerId): Customer|false
    {
        if ($customerId <= 0) {
            return false;
        }

        if ($this->helper->isAdmin($user)) {
            return $this->customerService->findCustomer($customerId);
        }

        return $this->customerService->findOneByIdAndTeamleaderId($customerId, $user->getId());
    }
}
