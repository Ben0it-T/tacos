<?php
declare(strict_types=1);

namespace App\Service;

use App\Entity\Activity;
use App\Helper\ValidationHelper;
use App\Repository\ActivityRepository;

use Psr\Log\LoggerInterface;

use \DateTimeImmutable;

final class ActivityService
{
    private ActivityRepository $activityRepository;
    private ValidationHelper $validationHelper;
    private LoggerInterface $logger;
    private array $translations;

    public function __construct(ActivityRepository $activityRepository, ValidationHelper $validationHelper, LoggerInterface $logger, array $translations) {
        $this->activityRepository = $activityRepository;
        $this->validationHelper = $validationHelper;
        $this->logger = $logger;
        $this->translations = $translations;
    }

    /**
     * Find Activity by id
     *
     * @param int $id
     * @return Activity entity or false
     */
    public function findActivity(int $id): Activity|false {
        return $this->activityRepository->find($id);
    }

    /**
     * Find One Activity by id and User id
     *
     * @param int $activityId
     * @param int $userId
     * @return Activity entity or false
     */
    public function findOneByIdAndUserId(int $activityId, int $userId): Activity|false {
        return $this->activityRepository->findOneByIdAndUserId($activityId, $userId);
    }

    /**
     * Find One Activity by id and teamleader id
     * Note : accepts activities without a team
     *
     * @param int $activityId
     * @param int $teamleaderId
     * @return Activity entity or false
     */
    public function findOneByIdAndTeamleaderId(int $activityId, int $teamleaderId): Activity|false {
        return $this->activityRepository->findOneByIdAndTeamleaderId($activityId, $teamleaderId);
    }

    /**
     * Find One Activity by id user id is teamleader
     * Note : requires teamlead on at least one team
     *
     * @param int $activityId
     * @param int $teamleaderId
     * @return Activity entity or false
     */
    public function findOneByIdAndTeamleaderIdStrict(int $activityId, int $teamleaderId): Activity|false {
        return $this->activityRepository->findOneByIdAndTeamleaderIdStrict($activityId, $teamleaderId);
    }



    /**
     * Find All Activities
     *
     * @param ?int $visible
     * @return array of Activity entities
     */
    public function findAll(?int $visible = null): array {
        return $this->activityRepository->findAll($visible);
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
    public function findAllByProjectId(int $projectId, ?int $visible = null): array {
        return $this->activityRepository->findAllByProjectId($projectId, $visible);
    }

    /**
     * Find All 'Project Activities' by projectId
     *
     * @param int  $projectId
     * @param ?int $visible
     * @return array of Activity entities
     */
    public function findAllProjectActivitiesByProjectId(int $projectId, ?int $visible = null): array {
        return $this->activityRepository->findAllProjectActivitiesByProjectId($projectId, $visible);
    }

    /**
     * Find All 'Global Activities'
     *
     * @param ?int $visible
     * @return array of Activity entities
     */
    public function findAllGlobalActivities(?int $visible = null): array {
        return $this->activityRepository->findAllGlobalActivities($visible);
    }

    /**
     * Find All Activities by User Id
     *
     * @param int  $userId
     * @param ?int $visible
     * @return array of Activity entities
     */
    public function findAllByUserId(int $userId, ?int $visible = null): array {
        return $this->activityRepository->findAllByUserId($userId, $visible);
    }

    /**
     * Find All Activities by Teamleader Id
     *
     * @param int  $teamleaderId
     * @param ?int $visible
     * @return array of Activity entities
     */
    public function findAllByTeamleaderId(int $teamleaderId, ?int $visible = null): array {
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
    public function findAllByUserIdAndProjectId(int $userId, int $projectId, ?int $visible = null): array {
        return $this->activityRepository->findAllByUserIdAndProjectId($userId, $projectId, $visible);
    }



    /**
     * Find All Activities with Teams count and Project
     *
     * @return array of Activities with Teams count and Project
     */
    public function findAllActivitiesWithTeamsCountAndProject(): array {
        return $this->activityRepository->findAllActivitiesWithTeamsCountAndProject();
    }

    /**
     * Find All Activities with Teams count and Project by Teamleader id
     *
     * @return array of Activities with Teams count and Project
     */
    public function findAllActivitiesWithTeamsCountAndProjectByTeamleaderId(int $teamleaderId): array {
        return $this->activityRepository->findAllActivitiesWithTeamsCountAndProjectByTeamleaderId($teamleaderId);
    }



    /**
     * Create new Activity
     *
     * @param array $data
     * @return string $errorMsg
     */
    public function createActivity(array $data): string {
        $errorMsg = "";
        $name = $this->validationHelper->sanitizeString($data['activity_edit_form_name']);
        $color = $this->validationHelper->sanitizeColor($data['activity_edit_form_color'] ?? '#ffffff');
        $projectId = intval($data['activity_edit_form_project']);
        $number = $this->validationHelper->sanitizeString($data['activity_edit_form_number']);
        $comment = $this->validationHelper->sanitizeString($data['activity_edit_form_description']);
        $selectedTeams = $data['activity_edit_form']['selectedTeams'] ?? [];
        $visible = isset($data['activity_edit_form_visible']) ? 1 : 0;

        // Validate name
        if (!$this->validationHelper->validateName($name)) {
            $errorMsg .= str_replace("%fieldName%", $this->translations['form_label_name'], $this->translations['form_error_format']) . "\n";
        }

        // Validate color
        if (!$this->validationHelper->validateColor($color)) {
            $errorMsg .= str_replace("%fieldName%", $this->translations['form_label_color'], $this->translations['form_error_format']) . "\n";
        }

        // Validate project
        if ($projectId === 0) {
            $projectId = null;
        }

        // Validate number
        if (!$this->validationHelper->validateNumber($number, true)) {
            $errorMsg .= str_replace("%fieldName%", $this->translations['form_label_project_number'], $this->translations['form_error_format']) . "\n";
        }

        if ($errorMsg !== '') {
            return $errorMsg;
        }

        $activity = new Activity;
        $activity->setName($name);
        $activity->setColor($color);
        $activity->setProjectId($projectId);
        $activity->setNumber($number);
        $activity->setComment($comment);
        $activity->setVisible($visible);
        $activity->setCreatedAt((new \DateTimeImmutable())->format('Y-m-d H:i:s'));

        $lastInsertId = $this->activityRepository->insert($activity);

        if (!$lastInsertId) {
            return $this->translations['error_occurred'];
        }

        $this->logger->info(
            "[ActivityService] Activity '".$activity->getName()."' created",
            [
                'id'   => $lastInsertId,
                'name' => $activity->getName(),
            ]
        );
        if (count($selectedTeams) > 0) {
            if (!$this->activityRepository->insertTeams(intval($lastInsertId), $selectedTeams)) {
                return $this->translations['error_occurred'];
            }

            $this->logger->info(
                "[ActivityService] Activity '".$activity->getName()."': teams link created",
                [
                    'id'      =>  $lastInsertId,
                    'name'    =>  $activity->getName(),
                    'teamIds' =>  $selectedTeams,
                ]
            );
        }

        return '';
    }

    /**
     * Update Activity
     *
     * @param Activity $activity
     * @param array $data
     * @return string $errorMsg
     */
    public function updateActivity(Activity $activity, array $data): string {
        $errorMsg = "";
        $name = $this->validationHelper->sanitizeString($data['activity_edit_form_name']);
        $color = $this->validationHelper->sanitizeColor($data['activity_edit_form_color'] ?? '#ffffff');
        $number = $this->validationHelper->sanitizeString($data['activity_edit_form_number']);
        $comment = $this->validationHelper->sanitizeString($data['activity_edit_form_description']);
        $selectedTeams = $data['activity_edit_form']['selectedTeams'] ?? [];
        $visible = isset($data['activity_edit_form_visible']) ? 1 : 0;

        // Validate name
        if (!$this->validationHelper->validateName($name)) {
            $errorMsg .= str_replace("%fieldName%", $this->translations['form_label_name'], $this->translations['form_error_format']) . "\n";
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

        $activity->setName($name);
        $activity->setColor($color);
        $activity->setNumber($number);
        $activity->setComment($comment);
        $activity->setVisible($visible);

        if (!$this->activityRepository->updateActivity($activity)) {
            return $this->translations['error_occurred'];
        }

        $this->logger->info(
            "[ActivityService] Activity '".$activity->getName()."' updated",
            [
                'id'   => $activity->getId(),
                'name' => $activity->getName(),
            ]
        );

        if (!$this->activityRepository->updateTeams($activity->getId(), $selectedTeams)) {
            return $this->translations['error_occurred'];
        }
        $this->logger->info(
            "[ActivityService] Activity '".$activity->getName()."': teams link updated",
            [
                'id'      =>  $activity->getId(),
                'name'    =>  $activity->getName(),
                'teamIds' =>  $selectedTeams,
            ]
        );

        return '';
    }

}
