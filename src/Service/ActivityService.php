<?php
declare(strict_types=1);

namespace App\Service;

use App\Entity\Activity;
use App\Helper\ValidationHelper;
use App\Repository\ActivityRepository;
use App\Repository\CustomerRepository;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

final class ActivityService
{
    private $container;
    private $activityRepository;
    private $customerRepository;
    private $validationHelper;
    private $logger;

    public function __construct(ContainerInterface $container, ActivityRepository $activityRepository, CustomerRepository $customerRepository, ValidationHelper $validationHelper, LoggerInterface $logger) {
        $this->container = $container;
        $this->activityRepository = $activityRepository;
        $this->customerRepository = $customerRepository;
        $this->validationHelper = $validationHelper;
        $this->logger = $logger;
    }

    /**
     * Find Activity
     *
     * @param int $id
     * @return Activity or false
     */
    public function findActivity(int $id) {
        return $this->activityRepository->find($id);
    }

    /**
     * Find all Activities
     *
     * @return array of Activity
     */
    public function findAllActivities() {
        return $this->activityRepository->findAll();
    }

    /**
     * Find (all) project activities
     *
     * @param int $projectId
     * @return array of Activity
     */
    public function findProjectAllowedActivities(int $projectId) {
        return $this->activityRepository->findProjectAllowedActivities($projectId);
    }

    /**
     * Find All Activities by projectId
     *
     * @param int $projectId
     * @return array of Activity
     */
    public function findAllActivitiesByProjectId(int $projectId) {
        return $this->activityRepository->findAllActivitiesByProjectId($projectId);
    }

    /**
     * Find All Visible Activities by projectId
     *
     * @param int $projectId
     * @return array of Activity
     */
    public function findAllVisibleActivitiesByProjectId(int $projectId) {
        return $this->activityRepository->findAllVisibleActivitiesByProjectId($projectId);
    }

    /**
     * Find All Global Activities
     *
     * @return array of Activity
     */
    public function findAllGlobalActivities() {
        return $this->activityRepository->findAllGlobalActivities();
    }

    /**
     * Find All Visible Global Activities
     *
     * @return array of Activity
     */
    public function findAllVisibleGlobalActivities() {
        return $this->activityRepository->findAllVisibleGlobalActivities();
    }

    /**
     * Find All Activities have teams
     *
     * @return array of Activity
     */
    public function findAllActivitiesHaveTeams() {
        return $this->activityRepository->findAllActivitiesHaveTeams();
    }

    /**
     * Find All visible Activities have teams
     *
     * @return array of Activity
     */
    public function findAllVisibleActivitiesHaveTeams() {
        return $this->activityRepository->findAllVisibleActivitiesHaveTeams();
    }

    /**
     * Find All Activities not in a team
     *
     * @return array of Activity
     */
    public function findAllActivitiesNotInTeam() {
        return $this->activityRepository->findAllActivitiesNotInTeam();
    }

    /**
     * Find All Visible Activities not in a team
     *
     * @return array of Activity
     */
    public function findAllVisibleActivitiesNotInTeam() {
        return $this->activityRepository->findAllVisibleActivitiesNotInTeam();
    }

    /**
     * Find All Activities by user Id
     *
     * @return array of Activity
     */
    public function findAllActivitiesByUserId(int $userId) {
        return $this->activityRepository->findAllActivitiesByUserId($userId);
    }

    /**
     * Find All Visible Activities by user Id
     *
     * @return array of Activity
     */
    public function findAllVisibleActivitiesByUserId(int $userId) {
        return $this->activityRepository->findAllVisibleActivitiesByUserId($userId);
    }



    /**
     * Get number of teams for activity
     *
     * @param int $activityId
     * @return int number of teams for activity
     */
    public function getNbOfTeamsForActivity(int $activityId) {
        return $this->activityRepository->getNbOfTeamsForActivity($activityId);
    }

    /**
     * Get teams for project
     *
     * @param int $activityId
     * @return array list of Teams
     */
    public function getTeamsForactivity(int $activityId) {
        return $this->activityRepository->getTeamsForactivity($activityId);
    }



    /**
     * Create new Activity
     *
     * @param array $data
     * @return string $errorMsg
     */
    public function createActivity($data) {
        $translations = $this->container->get('translations');
        $validation = true;
        $errorMsg = "";

        $name = $this->validationHelper->sanitizeString($data['activity_edit_form_name']);
        $color = $this->validationHelper->sanitizeColor($data['activity_edit_form_color']);
        $projectId = intval($data['activity_edit_form_project']);
        $number = $this->validationHelper->sanitizeString($data['activity_edit_form_number']);
        $comment = $this->validationHelper->sanitizeString($data['activity_edit_form_description']);
        $selectedTeams = isset($data['activity_edit_form']['selectedTeams']) ? $data['activity_edit_form']['selectedTeams'] : array();
        $visible = isset($data['activity_edit_form_visible']) ? 1 : 0;

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

        // Validate project
        if ($projectId === 0) {
            $projectId = NULL;
        }

        // Validate number
        if (!$this->validationHelper->validateName($name, true)) {
            $validation = false;
            $errorMsg .= str_replace("%fieldName%", $translations['form_label_project_number'], $translations['form_error_format']) . "\n";
        }

        if ($validation) {
            $activity = new Activity;
            $activity->setName($name);
            $activity->setColor($color);
            $activity->setProjectId($projectId);
            $activity->setNumber($number);
            $activity->setComment($comment);
            $activity->setVisible($visible);
            $activity->setCreatedAt(date("Y-m-d H:i:s"));
            $lastInsertId = $this->activityRepository->insert($activity);
            $this->logger->info("ActivityService - Activity '" . $lastInsertId . "' created.");
            if (count($selectedTeams) > 0) {
                $this->activityRepository->insertTeams(intval($lastInsertId), $selectedTeams);
                $this->logger->info("ActivityService - Activity '" . $lastInsertId . "': teams created.");
            }
        }

        return $errorMsg;
    }

    /**
     * Update Activity
     *
     * @param Activity $activity
     * @param array $data
     * @return string $errorMsg
     */
    public function updateActivity($activity, $data) {
        $translations = $this->container->get('translations');
        $validation = true;
        $errorMsg = "";

        $name = $this->validationHelper->sanitizeString($data['activity_edit_form_name']);
        $color = $this->validationHelper->sanitizeColor($data['activity_edit_form_color']);
        $number = $this->validationHelper->sanitizeString($data['activity_edit_form_number']);
        $comment = $this->validationHelper->sanitizeString($data['activity_edit_form_description']);
        $selectedTeams = isset($data['activity_edit_form']['selectedTeams']) ? $data['activity_edit_form']['selectedTeams'] : array();
        $visible = isset($data['activity_edit_form_visible']) ? 1 : 0;

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

        // Validate number
        if (!$this->validationHelper->validateName($name, true)) {
            $validation = false;
            $errorMsg .= str_replace("%fieldName%", $translations['form_label_project_number'], $translations['form_error_format']) . "\n";
        }

        if ($validation) {
            $activity->setName($name);
            $activity->setColor($color);
            $activity->setNumber($number);
            $activity->setComment($comment);
            $activity->setVisible($visible);
            $this->activityRepository->updateActivity($activity);
            $this->logger->info("ActivityService - Activity '" . $activity->getId() . "' updated.");

            $this->activityRepository->updateTeams($activity->getId(), $selectedTeams);
            $this->logger->info("ActivityService - Activity '" . $activity->getId() . "': teams updated.");
        }

        return $errorMsg;
    }

}
