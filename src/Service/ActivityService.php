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
     * Find Activity by id
     *
     * @param int $id
     * @return Activity entity or false
     */
    public function findActivity(int $id) {
        return $this->activityRepository->find($id);
    }

    /**
     * Find One Activity by id and User id
     *
     * @param int $activityId
     * @param int $userId
     * @return Activity entity or false
     */
    public function findOneByIdAndUserId(int $activityId, int $userId) {
        return $this->activityRepository->findOneByIdAndUserId($activityId, $userId);
    }

    /**
     * Find One Activity by id and teamleader id
     *
     * @param int $activityId
     * @param int $teamleaderId
     * @return Activity entity or false
     */
    public function findOneByIdAndTeamleaderId(int $activityId, int $teamleaderId) {
        return $this->activityRepository->findOneByIdAndTeamleaderId($activityId, $teamleaderId);
    }



    /**
     * Find All Activities
     *
     * @return array of Activity entities
     */
    public function findAll() {
        return $this->activityRepository->findAll();
    }

    /**
     * Find All Activities by Project id
     * Activities linked to a project
     * = projet "global activities" + project "project activities"
     *
     * @param int  $projectId
     * @param ?int $visible
     * @return array of Activity entities
     */
    public function findAllByProjectId(int $projectId, ?int $visible = null) {
        return $this->activityRepository->findAllByProjectId($projectId, $visible);
    }

    /**
     * Find All 'Project Activities' by projectId
     *
     * @param int  $projectId
     * @param ?int $visible
     * @return array of Activity entities
     */
    public function findAllProjectActivitiesByProjectId(int $projectId, ?int $visible = null) {
        return $this->activityRepository->findAllProjectActivitiesByProjectId($projectId, $visible);
    }

    /**
     * Find All 'Global Activities'
     *
     * @param ?int $visible
     * @return array of Activity entities
     */
    public function findAllGlobalActivities(?int $visible = null) {
        return $this->activityRepository->findAllGlobalActivities($visible);
    }

    /**
     * Find All Activities by User Id
     *
     * @param int  $userId
     * @param ?int $visible
     * @return array of Activity entities
     */
    public function findAllByUserId(int $userId, ?int $visible = null) {
        return $this->activityRepository->findAllByUserId($userId, $visible);
    }

    /**
     * Find All Activities by Teamleader Id
     *
     * @param int  $teamleaderId
     * @param ?int $visible
     * @return array of Activity entities
     */
    public function findAllByTeamleaderId(int $teamleaderId, ?int $visible = null) {
        return $this->activityRepository->findAllByTeamleaderId($teamleaderId, $visible);
    }

    /**
     * Find All Activities by Project Id and by User Id
     *
     * @param int  $projectId
     * @param int  $userId
     * @param ?int $visible
     * @return array of Activity entities
     */
    public function findAllByUserIdAndProjectId(int $userId, int $projectId, ?int $visible = null) {
        return $this->activityRepository->findAllByUserIdAndProjectId($userId, $projectId, $visible);
    }



    /**
     * Find All Activities with Teams count and Project
     *
     * @return array of Activities with Teams count and Project
     */
    public function findAllActivitiesWithTeamsCountAndProject() {
        return $this->activityRepository->findAllActivitiesWithTeamsCountAndProject();
    }

    /**
     * Find All Activities with Teams count and Project by Teamleader id
     *
     * @return array of Activities with Teams count and Project
     */
    public function findAllActivitiesWithTeamsCountAndProjectByTeamleaderId(int $teamleaderId) {
        return $this->activityRepository->findAllActivitiesWithTeamsCountAndProjectByTeamleaderId($teamleaderId);
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
        $color = isset($data['activity_edit_form_color']) ? $this->validationHelper->sanitizeColor($data['activity_edit_form_color']) : "#ffffff";
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
        if (!$this->validationHelper->validateNumber($number, true)) {
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
        $color = isset($data['activity_edit_form_color']) ? $this->validationHelper->sanitizeColor($data['activity_edit_form_color']) : "#ffffff";
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
        if (!$this->validationHelper->validateNumber($number, true)) {
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
