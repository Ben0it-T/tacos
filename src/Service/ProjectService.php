<?php
declare(strict_types=1);

namespace App\Service;

use App\Entity\Project;
use App\Helper\ValidationHelper;
use App\Repository\ActivityRepository;
use App\Repository\ProjectRepository;
use App\Repository\CustomerRepository;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

final class ProjectService
{
    private $container;
    private $activityRepository;
    private $projectRepository;
    private $customerRepository;
    private $validationHelper;
    private $logger;

    public function __construct(ContainerInterface $container, ActivityRepository $activityRepository, ProjectRepository $projectRepository, CustomerRepository $customerRepository, ValidationHelper $validationHelper, LoggerInterface $logger) {
        $this->container = $container;
        $this->activityRepository = $activityRepository;
        $this->projectRepository = $projectRepository;
        $this->customerRepository = $customerRepository;
        $this->validationHelper = $validationHelper;
        $this->logger = $logger;
    }

    /**
     * Find Project
     *
     * @param int $id
     * @return Project or false
     */
    public function findProject(int $id) {
        return $this->projectRepository->find($id);
    }

    /**
     * Find all Projects
     *
     * @return array of Projects
     */
    public function findAllProjects() {
        return $this->projectRepository->findAll();
    }

    /**
     * Find all Projects by customer
     *
     * @param int $customerId
     * @return array of Projects
     */
    public function findAllProjectsByCustomerId($customerId) {
        return $this->projectRepository->findAllProjectsByCustomerId($customerId);
    }

    /**
     * Find all Visible Projects by customer
     *
     * @param int $customerId
     * @return array of Projects
     */
    public function findAllVisibleProjectsByCustomerId($customerId) {
        return $this->projectRepository->findAllVisibleProjectsByCustomerId($customerId);
    }

    /**
     * Find All Projects have teams
     *
     * @return array of Projects
     */
    public function findAllProjectsHaveTeams() {
        return $this->projectRepository->findAllProjectsHaveTeams();
    }

    /**
     * Find All Visible Projects have teams
     *
     * @return array of Projects
     */
    public function findAllVisibleProjectsHaveTeams() {
        return $this->projectRepository->findAllVisibleProjectsHaveTeams();
    }

    /**
     * Find All Projects not in a team
     *
     * @return array of Projects
     */
    public function findAllProjectsNotInTeam() {
        return $this->projectRepository->findAllProjectsNotInTeam();
    }

    /**
     * Find All Visible Projects not in a team
     *
     * @return array of Projects
     */
    public function findAllVisibleProjectsNotInTeam() {
        return $this->projectRepository->findAllVisibleProjectsNotInTeam();
    }

    /**
     * Find All Projects by user Id
     *
     * @param int $userId
     * @return array of Projects
     */
    public function findAllProjectsByUserId(int $userId) {
        return $this->projectRepository->findAllProjectsByUserId($userId);
    }

    /**
     * Find All Visible Projects by user Id
     *
     * @param int $userId
     * @return array of Projects
     */
    public function findAllVisibleProjectsByUserId(int $userId) {
        return $this->projectRepository->findAllVisibleProjectsByUserId($userId);
    }

    /**
     * Find All Visible Projects by user Id and customer Id
     *
     * @param int $userId
     * @param int $customerId
     * @return array of Projects
     */
    public function findAllVisibleProjectsByUserIdAndCustomerId(int $userId, int $customerId) {
        return $this->projectRepository->findAllVisibleProjectsByUserIdAndCustomerId($userId, $customerId);
    }



    /**
     * Get number of projects for customer
     *
     * @param int $projectId
     * @return int number of teams
     */
    public function getNbOfProjectsForCustomer(int $customerId) {
        return $this->projectRepository->getNbOfProjectsForCustomer($customerId);
    }

    /**
     * Get number of teams for project
     *
     * @param int $projectId
     * @return int number of teams
     */
    public function getNbOfTeamsForProject(int $projectId) {
        return $this->projectRepository->getNbOfTeamsForProject($projectId);
    }

    /**
     * Get teams for project
     *
     * @param int $projectId
     * @return array list of teams
     */
    public function getTeamsForProject(int $projectId) {
        return $this->projectRepository->getTeamsForProject($projectId);
    }






    /**
     * Create new Project
     *
     * @param array $data
     * @return string $errorMsg
     */
    public function createProject($data) {
        $translations = $this->container->get('translations');
        $validation = true;
        $errorMsg = "";
        $dateFormat = $translations['dateFormats_date'];

        $name = $this->validationHelper->sanitizeString($data['project_edit_form_name']);
        $color = $this->validationHelper->sanitizeColor($data['project_edit_form_color']);
        $customerId = intval($data['project_edit_form_customer']);
        $number = $this->validationHelper->sanitizeString($data['project_edit_form_number']);
        $comment = $this->validationHelper->sanitizeString($data['project_edit_form_description']);
        $start = $this->validationHelper->sanitizeString($data['project_edit_form_start']);
        $start = !empty($start) ? date_create_from_format($dateFormat,$start) : "";
        $end = $this->validationHelper->sanitizeString($data['project_edit_form_end']);
        $end = !empty($end) ? date_create_from_format($dateFormat,$end) : "";
        $selectedTeams = isset($data['project_edit_form']['selectedTeams']) ? $data['project_edit_form']['selectedTeams'] : array();
        $visible = isset($data['project_edit_form_visible']) ? 1 : 0;
        $globalActivities = isset($data['project_edit_form_globalactivities']) ? 1 : 0;

        // Validate name
        if (!$this->validationHelper->validateName($name)) {
            $validation = false;
            $errorMsg .= str_replace("%fieldName%", $translations['form_label_name'], $translations['form_error_format']) . "\n";
        }

        // Validate color
        if (!$this->validationHelper->validateColor($color)) {
            $validation = false;
            $errorMsg .= str_replace("%fieldName%", $translations['form_label_color'], $translations['form_error_format']) . "\n";
        }

        // Validate customer
        if ($customerId === 0) {
            $validation = false;
            $errorMsg .= str_replace("%fieldName%", $translations['form_label_customer'], $translations['form_error_format']) . "\n";
        }

        // Validate number
        if (!$this->validationHelper->validateName($name, true)) {
            $validation = false;
            $errorMsg .= str_replace("%fieldName%", $translations['form_label_project_number'], $translations['form_error_format']) . "\n";
        }

        // Validate Date
        if (!$this->validationHelper->validateDate($start, true)) {
            $validation = false;
            $errorMsg .= str_replace("%fieldName%", $translations['form_label_project_start'], $translations['form_error_format']) . "\n";
        }
        if (!$this->validationHelper->validateDate($end, true)) {
            $validation = false;
            $errorMsg .= str_replace("%fieldName%", $translations['form_label_project_end'], $translations['form_error_format']) . "\n";
        }


        if ($validation) {
            $project = new Project;
            $project->setName($name);
            $project->setColor($color);
            $project->setCustomerId($customerId);
            $project->setNumber($number);
            $project->setComment($comment);
            $project->setStart((!empty($start) ? date_format($start,"Y-m-d") : NULL));
            $project->setEnd((!empty($end) ? date_format($end,"Y-m-d") : NULL));
            $project->setGlobalActivities($globalActivities);
            $project->setVisible($visible);
            $project->setCreatedAt(date("Y-m-d H:i:s"));
            $lastInsertId = $this->projectRepository->insert($project);
            $this->logger->info("ProjectService - Project '" . $lastInsertId . "' created.");
            if (count($selectedTeams) > 0) {
                $this->projectRepository->insertTeams(intval($lastInsertId), $selectedTeams);
                $this->logger->info("ProjectService - Project '" . $lastInsertId . "': teams created.");
            }
        }

        return $errorMsg;
    }

    /**
     * Update Project
     *
     * @param Project $project
     * @param array $data
     * @return string $errorMsg
     */
    public function updateProject($project, $data) {
        $translations = $this->container->get('translations');
        $validation = true;
        $errorMsg = "";
        $dateFormat = $translations['dateFormats_date'];

        $name = $this->validationHelper->sanitizeString($data['project_edit_form_name']);
        $color = $this->validationHelper->sanitizeColor($data['project_edit_form_color']);
        $customerId = intval($data['project_edit_form_customer']);
        $number = $this->validationHelper->sanitizeString($data['project_edit_form_number']);
        $comment = $this->validationHelper->sanitizeString($data['project_edit_form_description']);
        $start = $this->validationHelper->sanitizeString($data['project_edit_form_start']);
        $start = !empty($start) ? date_create_from_format($dateFormat,$start) : "";
        $end = $this->validationHelper->sanitizeString($data['project_edit_form_end']);
        $end = !empty($end) ? date_create_from_format($dateFormat,$end) : "";
        $selectedTeams = isset($data['project_edit_form']['selectedTeams']) ? $data['project_edit_form']['selectedTeams'] : array();
        $selectedActivities = isset($data['project_edit_form']['selectedActivities']) ? $data['project_edit_form']['selectedActivities'] : array();
        $visible = isset($data['project_edit_form_visible']) ? 1 : 0;
        $globalActivities = isset($data['project_edit_form_globalactivities']) ? 1 : 0;

        // Validate name
        if (!$this->validationHelper->validateName($name)) {
            $validation = false;
            $errorMsg .= str_replace("%fieldName%", $translations['form_label_name'], $translations['form_error_format']) . "\n";
        }

        // Validate color
        if (!$this->validationHelper->validateColor($color)) {
            $validation = false;
            $errorMsg .= str_replace("%fieldName%", $translations['form_label_color'], $translations['form_error_format']) . "\n";
        }

        // Validate customer
        if ($customerId === 0) {
            $validation = false;
            $errorMsg .= str_replace("%fieldName%", $translations['form_label_customer'], $translations['form_error_format']) . "\n";
        }

        // Validate number
        if (!$this->validationHelper->validateName($name, true)) {
            $validation = false;
            $errorMsg .= str_replace("%fieldName%", $translations['form_label_project_number'], $translations['form_error_format']) . "\n";
        }

        // Validate Date
        if (!$this->validationHelper->validateDate($start, true)) {
            $validation = false;
            $errorMsg .= str_replace("%fieldName%", $translations['form_label_project_start'], $translations['form_error_format']) . "\n";
        }
        if (!$this->validationHelper->validateDate($end, true)) {
            $validation = false;
            $errorMsg .= str_replace("%fieldName%", $translations['form_label_project_end'], $translations['form_error_format']) . "\n";
        }

        // Validate activities
        if ($globalActivities == 0) {
            $globalActivitiesRes = $this->activityRepository->findAllGlobalActivities();
            $globalActivitiesIds = array();
            foreach ($globalActivitiesRes as $entry) {
                $globalActivitiesIds[] = $entry->getId();
            }

            foreach ($selectedActivities as $key => $val) {
                if (in_array($val, $globalActivitiesIds)) {
                    unset($selectedActivities[$key]);
                }
            }

        }

        if ($validation) {
            $project->setName($name);
            $project->setColor($color);
            $project->setCustomerId($customerId);
            $project->setNumber($number);
            $project->setComment($comment);
            $project->setStart((!empty($start) ? date_format($start,"Y-m-d") : NULL));
            $project->setEnd((!empty($end) ? date_format($end,"Y-m-d") : NULL));
            $project->setGlobalActivities($globalActivities);
            $project->setVisible($visible);
            $this->projectRepository->updateProject($project);
            $this->logger->info("ProjectService - Project '" . $project->getId() . "' updated.");

            $this->projectRepository->updateTeams($project->getId(), $selectedTeams);
            $this->logger->info("ProjectService - Project '" . $project->getId() . "': Teams updated.");

            // update autorised > selectedActivities
            $this->projectRepository->updateActivities($project->getId(), $selectedActivities);
            $this->logger->info("ProjectService - Project '" . $project->getId() . "': Activities updated.");

        }

        return $errorMsg;
    }

}
