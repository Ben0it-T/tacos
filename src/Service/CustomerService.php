<?php
declare(strict_types=1);

namespace App\Service;

use App\Entity\Customer;
use App\Helper\ValidationHelper;
use App\Repository\CustomerRepository;
use App\Repository\TeamRepository;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

final class CustomerService
{
    private $container;
    private $customerRepository;
    private $teamRepository;
    private $validationHelper;
    private $logger;

    public function __construct(ContainerInterface $container, CustomerRepository $customerRepository, TeamRepository $teamRepository, ValidationHelper $validationHelper, LoggerInterface $logger) {
        $this->container = $container;
        $this->customerRepository = $customerRepository;
        $this->teamRepository = $teamRepository;
        $this->validationHelper = $validationHelper;
        $this->logger = $logger;
    }

    /**
     * Find Customer by id
     *
     * @param int $id
     * @return Customer or false
     */
    public function findCustomer(int $id) {
        return $this->customerRepository->find($id);
    }

    /**
     * Find Customer by id and by Teamleader id
     *
     * @param int $id
     * @return Customer or false
     */
    public function findOneByCustomerIdAndTeamleaderId(int $customerId, int $teamleaderId) {
        return $this->customerRepository->findOneByCustomerIdAndTeamleaderId($customerId, $teamleaderId);
    }

    /**
     * Find Customers
     *
     * @return array of Customers
     */
    public function findAllCustomers() {
        return $this->customerRepository->findAll();
    }

    /**
     * Find Customers with Teams count and Projects count
     *
     * @return array of Customers with Teams count and Projects count
     */
    public function findAllCustomersWithTeamsCountAndProjectsCount() {
        return $this->customerRepository->findAllCustomersWithTeamsCountAndProjectsCount();
    }

    /**
     * Find Customers with Teams count and Projects count by User id
     *
     * @return array of Customers with Teams count and Projects count
     */
    public function findCustomersWithTeamsCountAndProjectsCountByUserId(int $userId) {
        return $this->customerRepository->findCustomersWithTeamsCountAndProjectsCountByUserId($userId);
    }

    /**
     * Find visible Customers
     *
     * @return array of Customers
     */
    public function findAllVisibleCustomers() {
        return $this->customerRepository->findAllVisibleCustomers();
    }

    /**
     * Find All Customers by user Id
     *
     * @param int $userId
     * @return array of Customers
     */
    public function findAllCustomersByUserId(int $userId) {
        return $this->customerRepository->findAllCustomersByUserId($userId);
    }

    /**
     * Find All visible Customers by user Id
     *
     * @param int $userId
     * @return array of Customers
     */
    public function findAllVisibleCustomersByUserId(int $userId) {
        return $this->customerRepository->findAllVisibleCustomersByUserId($userId);
    }

    /**
     * Find All Customers in Teams
     *
     * @param array $teamsIds
     * @return array of Customers
     */
    public function findAllCustomersInTeams($teamsIds) {
        return $this->customerRepository->findAllCustomersInTeams($teamsIds);
    }

    /**
     * Find All visible Customers in Teams
     *
     * @param array $teamsIds
     * @return array of Customers
     */
    public function findAllVisibleCustomersInTeams($teamsIds) {
        return $this->customerRepository->findAllVisibleCustomersInTeams($teamsIds);
    }

    /**
     * Find All Customers have a team
     *
     * @return array of Customers
     */
    public function findAllCustomersHaveTeams() {
        return $this->customerRepository->findAllCustomersHaveTeams();
    }

    /**
     * Find All visible Customers have a team
     *
     * @return array of Customers
     */
    public function findAllVisibleCustomersHaveTeams() {
        return $this->customerRepository->findAllVisibleCustomersHaveTeams();
    }

    /**
     * Find All Customers not in a team
     *
     * @return array of Customers
     */
    public function findAllCustomersNotInTeam() {
        return $this->customerRepository->findAllCustomersNotInTeam();
    }

    /**
     * Find All visible Customers not in a team
     *
     * @return array of Customers
     */
    public function findAllVisibleCustomersNotInTeam() {
        return $this->customerRepository->findAllVisibleCustomersNotInTeam();
    }

    /**
     * Create new Customer
     *
     * @param array $data
     * @return string $errorMsg
     */
    public function createCustomer($data) {
        $translations = $this->container->get('translations');
        $validation = true;
        $errorMsg = "";

        $name = $this->validationHelper->sanitizeName($data['customer_edit_form_name']);
        $color = isset($data['customer_edit_form_color']) ? $this->validationHelper->sanitizeColor($data['customer_edit_form_color']) : "#ffffff";
        $number = $this->validationHelper->sanitizeString($data['customer_edit_form_number']);
        $comment = $this->validationHelper->sanitizeString($data['customer_edit_form_description']);
        $selectedTeams = isset($data['customer_edit_form']['selectedTeams']) ? $data['customer_edit_form']['selectedTeams'] : array();
        $visible = isset($data['customer_edit_form_visible']) ? 1 : 0;

        // Validate name
        if (!$this->validationHelper->validateName($name)) {
            $validation = false;
            $errorMsg .= $translations['form_error_name'] . "\n";
        }

        // Validate color
        if (!$this->validationHelper->validateColor($color)) {
            $validation = false;
            $errorMsg .= str_replace("%fieldName%", $translations['form_label_color'], $translations['form_error_format']) . "\n";
        }

        // Validate number
        if (!$this->validationHelper->validateNumber($number, true)) {
            $validation = false;
            $errorMsg .= str_replace("%fieldName%", $translations['form_label_project_number'], $translations['form_error_format']) . "\n";
        }

        if ($validation) {
            $customer = new Customer;
            $customer->setName($name);
            $customer->setColor($color);
            $customer->setNumber($number);
            $customer->setComment($comment);
            $customer->setVisible($visible);
            $customer->setCreatedAt(date("Y-m-d H:i:s"));
            $lastInsertId = $this->customerRepository->insert($customer);
            $this->logger->info("CustomerService - Customer '" . $lastInsertId . "' created.");
            if (count($selectedTeams) > 0) {
                $this->customerRepository->insertTeams(intval($lastInsertId), $selectedTeams);
                $this->logger->info("CustomerService - Customer '" . $lastInsertId . "': teams created.");
            }
        }

        return $errorMsg;
    }

    /**
     * Update Customer
     *
     * @param Customer $customer
     * @param array $data
     * @return string $errorMsg
     */
    public function updateCustomer(Customer $customer, $data) {
        $translations = $this->container->get('translations');
        $validation = true;
        $errorMsg = "";

        $name = $this->validationHelper->sanitizeName($data['customer_edit_form_name']);
        $color = isset($data['customer_edit_form_color']) ? $this->validationHelper->sanitizeColor($data['customer_edit_form_color']) : "#ffffff";
        $number = $this->validationHelper->sanitizeString($data['customer_edit_form_number']);
        $comment = $this->validationHelper->sanitizeString($data['customer_edit_form_description']);
        $selectedTeams = isset($data['customer_edit_form']['selectedTeams']) ? $data['customer_edit_form']['selectedTeams'] : array();
        $visible = isset($data['customer_edit_form_visible']) ? 1 : 0;

        // Validate name
        if (!$this->validationHelper->validateName($name)) {
            $validation = false;
            $errorMsg .= $translations['form_error_name'] . "\n";
        }

        // Validate color
        if (!$this->validationHelper->validateColor($color)) {
            $validation = false;
            $errorMsg .= str_replace("%fieldName%", $translations['form_label_color'], $translations['form_error_format']) . "\n";
        }

        // Validate number
        if (!$this->validationHelper->validateNumber($number, true)) {
            $validation = false;
            $errorMsg .= str_replace("%fieldName%", $translations['form_label_project_number'], $translations['form_error_format']) . "\n";
        }

        if ($validation) {
            $customer->setName($name);
            $customer->setColor($color);
            $customer->setNumber($number);
            $customer->setComment($comment);
            $customer->setVisible($visible);
            $this->customerRepository->updateCustomer($customer);
            $this->logger->info("CustomerService - Customer '" . $customer->getId() . "' updated.");
            $this->customerRepository->updateTeams($customer->getId(), $selectedTeams);
            $this->logger->info("CustomerService - Customer '" . $customer->getId() . "': Teams updated.");
        }

        return $errorMsg;
    }
}
