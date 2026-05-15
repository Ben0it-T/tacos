<?php
declare(strict_types=1);

namespace App\Service;

use App\Entity\Customer;
use App\Helper\ValidationHelper;
use App\Repository\CustomerRepository;

use Psr\Log\LoggerInterface;

final class CustomerService
{
    private CustomerRepository $customerRepository;
    private ValidationHelper $validationHelper;
    private LoggerInterface $logger;
    private array $translations;

    public function __construct(CustomerRepository $customerRepository, ValidationHelper $validationHelper, LoggerInterface $logger, array $translations) {
        $this->customerRepository = $customerRepository;
        $this->validationHelper = $validationHelper;
        $this->logger = $logger;
        $this->translations = $translations;
    }

    /**
     * Find Customer by id
     *
     * @param int $id
     * @return Customer or false
     */
    public function findCustomer(int $id): Customer|false {
        return $this->customerRepository->find($id);
    }

    /**
     * Find Customer by id and by User id
     *
     * @param int $id
     * @param int $userId
     * @return Customer or false
     */
    public function findOneByIdAndUserId(int $customerId, int $userId): Customer|false {
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
    public function findOneByIdAndTeamleaderId(int $customerId, int $teamleaderId): Customer|false {
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
    public function findOneByIdAndTeamleaderIdStrict(int $customerId, int $teamleaderId): Customer|false {
        return $this->customerRepository->findOneByIdAndTeamleaderIdStrict($customerId, $teamleaderId);
    }



    /**
     * Find All Customers
     *
     * @param ?int $visible
     * @return array of Customer entities
     */
    public function findAll(?int $visible = null): array {
        return $this->customerRepository->findAll($visible);
    }

    /**
     * Find All Customers by user Id
     *
     * @param int $userId
     * @param ?int $visible
     * @return array of Customer entities
     */
    public function findAllByUserId(int $userId, ?int $visible = null): array {
        return $this->customerRepository->findAllByUserId($userId, $visible);
    }

    /**
     * Find All Customers by Teamleader Id
     *
     * @param int $teamleaderId
     * @param ?int $visible
     * @return array of Customer entities
     */
    public function findAllByTeamleaderId(int $teamleaderId, ?int $visible = null): array {
        return $this->customerRepository->findAllByTeamleaderId($teamleaderId, $visible);
    }

    /**
     * Find All Customers by team Id
     *
     * @param int $teamId
     * @param ?int $visible
     * @return array of Customer entities
     */
    public function findAllByTeamId(int $teamId, ?int $visible = null): array {
        return $this->customerRepository->findAllByTeamId($teamId, $visible);
    }



    /**
     * Find Customers with Teams count and Projects count
     *
     * @return array of Customers with Teams count and Projects count
     */
    public function findAllCustomersWithTeamsCountAndProjectsCount(): array {
        return $this->customerRepository->findAllCustomersWithTeamsCountAndProjectsCount();
    }

    /**
     * Find Customers with Teams count and Projects count by User id
     *
     * @param int $userId
     * @return array of Customers with Teams count and Projects count
     */
    public function findAllCustomersWithTeamsCountAndProjectsCountByUserId(int $userId): array {
        return $this->customerRepository->findAllCustomersWithTeamsCountAndProjectsCountByUserId($userId);
    }

    /**
     * Find Customers with Teams count and Projects count by Teamleader id
     *
     * @param int $teamleaderId
     * @return array of Customers with Teams count and Projects count
     */
    public function findAllCustomersWithTeamsCountAndProjectsCountByTeamleaderId(int $teamleaderId): array {
        return $this->customerRepository->findAllCustomersWithTeamsCountAndProjectsCountByTeamleaderId($teamleaderId);
    }



    /**
     * Create new Customer
     *
     * @param array $data
     * @return string $errorMsg
     */
    public function createCustomer(array $data): string {
        $errorMsg = "";
        $name = $this->validationHelper->sanitizeName($data['customer_edit_form_name']);
        $color = $this->validationHelper->sanitizeColor($data['customer_edit_form_color'] ?? '#ffffff');
        $number = $this->validationHelper->sanitizeString($data['customer_edit_form_number']);
        $comment = $this->validationHelper->sanitizeString($data['customer_edit_form_description']);
        $selectedTeams = $data['customer_edit_form']['selectedTeams'] ?? [];
        $visible = isset($data['customer_edit_form_visible']) ? 1 : 0;

        // Validate name
        if (!$this->validationHelper->validateName($name)) {
            $errorMsg .= $this->translations['form_error_name'] . "\n";
        }

        // Validate color
        if (!$this->validationHelper->validateColor($color)) {
            $errorMsg .= str_replace("%fieldName%", $this->translations['form_label_color'], $this->translations['form_error_format']) . "\n";
        }

        // Validate number
        if (!$this->validationHelper->validateNumber($number, true)) {
            $errorMsg .= str_replace("%fieldName%", $this->translations['form_label_project_number'], $this->translations['form_error_format']) . "\n";
        }

        if ($errorMsg !== '') {
            return $errorMsg;
        }

        $customer = new Customer;
        $customer->setName($name);
        $customer->setColor($color);
        $customer->setNumber($number);
        $customer->setComment($comment);
        $customer->setVisible($visible);
        $customer->setCreatedAt((new \DateTimeImmutable())->format('Y-m-d H:i:s'));

        $lastInsertId = $this->customerRepository->insert($customer);

        if (!$lastInsertId) {
            return $this->translations['error_occurred'];
        }

        $this->logger->info(
            "[CustomerService] Customer '".$customer->getName()."' created",
            [
                'id'   => $lastInsertId,
                'name' => $customer->getName(),
            ]
        );

        if (count($selectedTeams) > 0) {
            if (!$this->customerRepository->insertTeams(intval($lastInsertId), $selectedTeams)) {
                return $this->translations['error_occurred'];
            }

            $this->logger->info(
                "[CustomerService] Customer '".$customer->getName()."': teams link created",
                [
                    'id'      =>  $lastInsertId,
                    'name'    =>  $customer->getName(),
                    'teamIds' =>  $selectedTeams,
                ]
            );
        }


        return '';
    }

    /**
     * Update Customer
     *
     * @param Customer $customer
     * @param array $data
     * @return string $errorMsg
     */
    public function updateCustomer(Customer $customer, array $data): string {
        $errorMsg = "";
        $name = $this->validationHelper->sanitizeName($data['customer_edit_form_name']);
        $color = $this->validationHelper->sanitizeColor($data['customer_edit_form_color'] ?? '#ffffff');
        $number = $this->validationHelper->sanitizeString($data['customer_edit_form_number']);
        $comment = $this->validationHelper->sanitizeString($data['customer_edit_form_description']);
        $selectedTeams = $data['customer_edit_form']['selectedTeams'] ?? [];
        $visible = isset($data['customer_edit_form_visible']) ? 1 : 0;

        // Validate name
        if (!$this->validationHelper->validateName($name)) {
            $errorMsg .= $this->translations['form_error_name'] . "\n";
        }

        // Validate color
        if (!$this->validationHelper->validateColor($color)) {
            $errorMsg .= str_replace("%fieldName%", $this->translations['form_label_color'], $this->translations['form_error_format']) . "\n";
        }

        // Validate number
        if (!$this->validationHelper->validateNumber($number, true)) {
            $errorMsg .= str_replace("%fieldName%", $this->translations['form_label_project_number'], $this->translations['form_error_format']) . "\n";
        }

        if ($errorMsg !== '') {
            return $errorMsg;
        }

        $customer->setName($name);
        $customer->setColor($color);
        $customer->setNumber($number);
        $customer->setComment($comment);
        $customer->setVisible($visible);

        if (!$this->customerRepository->updateCustomer($customer)) {
            return $this->translations['error_occurred'];
        }

        $this->logger->info(
            "[CustomerService] Customer '".$customer->getName()."' updated",
            [
                'id'   => $customer->getId(),
                'name' => $customer->getName(),
            ]
        );

        if (!$this->customerRepository->updateTeams($customer->getId(), $selectedTeams)) {
            return $this->translations['error_occurred'];
        }
        $this->logger->info(
            "[CustomerService] Customer '".$customer->getName()."': teams link updated",
            [
                'id'      =>  $customer->getId(),
                'name'    =>  $customer->getName(),
                'teamIds' =>  $selectedTeams,
            ]
        );

        return '';
    }
}
