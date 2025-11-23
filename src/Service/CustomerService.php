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
     * Find Customer by id and by User id
     *
     * @param int $id
     * @param int $userId
     * @return Customer or false
     */
    public function findOneByIdAndUserId(int $customerId, int $userId) {
        return $this->customerRepository->findOneByIdAndUserId($customerId, $userId);
    }

    /**
     * Find Customer by id and by Teamleader id
     * Note : accepts customers without a team
     *
     * @param int $id
     * @param int $teamleaderId
     * @return Customer or false
     */
    public function findOneByIdAndTeamleaderId(int $customerId, int $teamleaderId) {
        return $this->customerRepository->findOneByIdAndTeamleaderId($customerId, $teamleaderId);
    }

    /**
     * Find Customer by id by id user id is teamleader
     * Note : requires teamlead on at least one team
     *
     * @param int $id
     * @param int $teamleaderId
     * @return Customer or false
     */
    public function findOneByIdAndTeamleaderIdStrict(int $customerId, int $teamleaderId) {
        return $this->customerRepository->findOneByIdAndTeamleaderIdStrict($customerId, $teamleaderId);
    }



    /**
     * Find All Customers
     *
     * @param ?int $visible
     * @return array of Customer entities
     */
    public function findAll(?int $visible = null) {
        return $this->customerRepository->findAll($visible);
    }

    /**
     * Find All Customers by user Id
     *
     * @param int $userId
     * @param ?int $visible
     * @return array of Customer entities
     */
    public function findAllByUserId(int $userId, ?int $visible = null) {
        return $this->customerRepository->findAllByUserId($userId, $visible);
    }

    /**
     * Find All Customers by Teamleader Id
     *
     * @param int $teamleaderId
     * @param ?int $visible
     * @return array of Customer entities
     */
    public function findAllByTeamleaderId(int $teamleaderId, ?int $visible = null) {
        return $this->customerRepository->findAllByTeamleaderId($teamleaderId, $visible);
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
     * @param int $userId
     * @return array of Customers with Teams count and Projects count
     */
    public function findAllCustomersWithTeamsCountAndProjectsCountByUserId(int $userId) {
        return $this->customerRepository->findAllCustomersWithTeamsCountAndProjectsCountByUserId($userId);
    }

    /**
     * Find Customers with Teams count and Projects count by Teamleader id
     *
     * @param int $teamleaderId
     * @return array of Customers with Teams count and Projects count
     */
    public function findAllCustomersWithTeamsCountAndProjectsCountByTeamleaderId(int $teamleaderId) {
        return $this->customerRepository->findAllCustomersWithTeamsCountAndProjectsCountByTeamleaderId($teamleaderId);
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
