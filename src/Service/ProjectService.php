<?php
declare(strict_types=1);

namespace App\Service;

use App\Entity\Project;
use App\Helper\ValidationHelper;
use App\Repository\ActivityRepository;
use App\Repository\ProjectRepository;

use Psr\Log\LoggerInterface;

final class ProjectService
{
    private ActivityRepository $activityRepository;
    private ProjectRepository $projectRepository;
    private ValidationHelper $validationHelper;
    private LoggerInterface $logger;
    private array $translations;

    public function __construct(ActivityRepository $activityRepository, ProjectRepository $projectRepository, ValidationHelper $validationHelper, LoggerInterface $logger, array $translations) {
        $this->activityRepository = $activityRepository;
        $this->projectRepository = $projectRepository;
        $this->validationHelper = $validationHelper;
        $this->logger = $logger;
        $this->translations = $translations;
    }

    /**
     * Find One Project by id
     *
     * @param int $id
     * @return Project entity or false
     */
    public function findProject(int $id): Project|false {
        return $this->projectRepository->find($id);
    }

    /**
     * Find One Project by id and teamleader id
     * Note : accepts projects without a team
     *
     * @param int $projectId
     * @param int $teamleaderId
     * @return Project entity or false
     */
    public function findOneByIdAndTeamleaderId(int $projectId, int $teamleaderId): Project|false {
        return $this->projectRepository->findOneByIdAndTeamleaderId($projectId, $teamleaderId);
    }

    /**
     * Find One Project by id and user id is teamleader
     * Note : requires teamlead on at least one team
     *
     * @param int $projectId
     * @param int $teamleaderId
     * @return Project entity or false
     */
    public function findOneByIdAndTeamleaderIdStrict(int $projectId, int $teamleaderId): Project|false {
        return $this->projectRepository->findOneByIdAndTeamleaderIdStrict($projectId, $teamleaderId);
    }



    /**
     * Find All Projects
     *
     * @param ?int $visible
     * @return array of Project entities
     */
    public function findAll(?int $visible = null): array {
        return $this->projectRepository->findAll($visible);
    }

    /**
     * Find All Projects by Customer id
     *
     * @param int  $customerId
     * @param ?int $visible
     * @return array of Project entities
     */
    public function findAllByCustomerId(int $customerId, ?int $visible = null): array {
        return $this->projectRepository->findAllByCustomerId($customerId, $visible);
    }

    /**
     * Find All Projects by user Id
     *
     * @param int  $userId
     * @param ?int $visible
     * @return array of Project entities
     */
    public function findAllByUserId(int $userId, ?int $visible = null): array {
        return $this->projectRepository->findAllByUserId($userId, $visible);
    }

   /**
     * Find All Projects by user Id and customer id
     *
     * @param int  $userId
     * @param int  $customerId
     * @param ?int $visible
     * @return array of Project entities
     */
    public function findAllByUserIdAndCustomerId(int $userId, int $customerId, ?int $visible = null): array {
        return $this->projectRepository->findAllByUserIdAndCustomerId($userId, $customerId, $visible);
    }

    /**
     * Find All Projects by teamleader Id
     *
     * @param int $teamleaderId
     * @param int $visible
     * @return array of Project entities
     */
    public function findAllByTeamleaderId(int $teamleaderId, ?int $visible = null): array {
        return $this->projectRepository->findAllByTeamleaderId($teamleaderId, $visible);
    }



    /**
     * Find All Projects with Teams count and Customer
     *
     * @return array of Projects with Teams count and Customer
     */
    public function findAllProjectsWithTeamsCountAndCustomer(): array {
        return $this->projectRepository->findAllProjectsWithTeamsCountAndCustomer();
    }

    /**
     * Find Projects with Teams count and Customer by Teamleader id
     *
     * @param int $teamleaderId
     * @return array of Projects with Teams count and Customer
     */
    public function findAllProjectsWithTeamsCountAndCustomerByTeamleaderId(int $teamleaderId): array {
        return $this->projectRepository->findAllProjectsWithTeamsCountAndCustomerByTeamleaderId($teamleaderId);
    }

    /**
     * Find All Projects with Customer by User id
     *
     * @param int $userId
     * @return array of Projects with Customer
     */
    public function findAllProjectsWithCustomerByUserId(int $userId): array {
        return $this->projectRepository->findAllProjectsWithCustomerByUserId($userId);
    }

    /**
     * Find All Projects with Customer by Team id
     *
     * @param int $teamId
     * @return array of Projects with Customer
     */
    public function findAllProjectsWithCustomerByTeamId(int $teamId): array {
        return $this->projectRepository->findAllProjectsWithCustomerByTeamId($teamId);
    }



    /**
     * Create new Project
     *
     * @param array $data
     * @return string $errorMsg
     */
    public function createProject(array $data): string {
        $errorMsg = "";
        $dateFormat = $this->translations['dateFormats_date'];
        $name = $this->validationHelper->sanitizeString($data['project_edit_form_name']);
        $color = $this->validationHelper->sanitizeColor($data['project_edit_form_color'] ?? '#ffffff');
        $customerId = intval($data['project_edit_form_customer']);
        $number = $this->validationHelper->sanitizeString($data['project_edit_form_number']);
        $comment = $this->validationHelper->sanitizeString($data['project_edit_form_description']);
        $start = $this->validationHelper->sanitizeString($data['project_edit_form_start']);
        $start = !empty($start) ? date_create_from_format($dateFormat, $start) : null;
        $end = $this->validationHelper->sanitizeString($data['project_edit_form_end']);
        $end = !empty($end) ? date_create_from_format($dateFormat, $end) : null;
        $selectedTeams = $data['project_edit_form']['selectedTeams'] ?? [];
        $visible = isset($data['project_edit_form_visible']) ? 1 : 0;
        $globalActivities = isset($data['project_edit_form_globalactivities']) ? 1 : 0;

        // Validate name
        if (!$this->validationHelper->validateName($name)) {
            $errorMsg .= str_replace("%fieldName%", $this->translations['form_label_name'], $this->translations['form_error_format']) . "\n";
        }

        // Validate color
        if (!$this->validationHelper->validateColor($color)) {
            $errorMsg .= str_replace("%fieldName%", $this->translations['form_label_color'], $this->translations['form_error_format']) . "\n";
        }

        // Validate customer
        if ($customerId === 0) {
            $errorMsg .= str_replace("%fieldName%", $this->translations['form_label_customer'], $this->translations['form_error_format']) . "\n";
        }

        // Validate number
        if (!$this->validationHelper->validateNumber($number, true)) {
            $errorMsg .= str_replace("%fieldName%", $this->translations['form_label_project_number'], $this->translations['form_error_format']) . "\n";
        }

        // Validate Date
        if (!$this->validationHelper->validateDate($start, true)) {
            $errorMsg .= str_replace("%fieldName%", $this->translations['form_label_project_start'], $this->translations['form_error_format']) . "\n";
        }
        if (!$this->validationHelper->validateDate($end, true)) {
            $errorMsg .= str_replace("%fieldName%", $this->translations['form_label_project_end'], $this->translations['form_error_format']) . "\n";
        }

        if ($errorMsg !== '') {
            return $errorMsg;
        }

        $project = new Project;
        $project->setName($name);
        $project->setColor($color);
        $project->setCustomerId($customerId);
        $project->setNumber($number);
        $project->setComment($comment);
        $project->setStart($start ? $start->format('Y-m-d') : null);
        $project->setEnd($end ? $end->format('Y-m-d') : null);
        $project->setGlobalActivities($globalActivities);
        $project->setVisible($visible);
        $project->setCreatedAt((new \DateTimeImmutable())->format('Y-m-d H:i:s'));

        $lastInsertId = $this->projectRepository->insert($project);

        if (!$lastInsertId) {
            return $this->translations['error_occurred'];
        }

        $this->logger->info(
            "[ProjectService] Project '".$project->getName()."' created",
            [
                'id'   => $lastInsertId,
                'name' => $project->getName(),
            ]
        );

        if (count($selectedTeams) > 0) {
            if (!$this->projectRepository->insertTeams(intval($lastInsertId), $selectedTeams)) {
                return $this->translations['error_occurred'];
            }

            $this->logger->info(
                "[ProjectService] Project '".$project->getName()."': teams link created",
                [
                    'id'      => $lastInsertId,
                    'name'    => $project->getName(),
                    'teamIds' => $selectedTeams,
                ]
            );
        }

        return '';
    }

    /**
     * Update Project
     *
     * @param Project $project
     * @param array $data
     * @return string $errorMsg
     */
    public function updateProject(Project $project, array $data): string {
        $errorMsg = "";
        $dateFormat = $this->translations['dateFormats_date'];
        $name = $this->validationHelper->sanitizeString($data['project_edit_form_name']);
        $color = $this->validationHelper->sanitizeColor($data['project_edit_form_color'] ?? '#ffffff');
        $customerId = intval($data['project_edit_form_customer']);
        $number = $this->validationHelper->sanitizeString($data['project_edit_form_number']);
        $comment = $this->validationHelper->sanitizeString($data['project_edit_form_description']);
        $start = $this->validationHelper->sanitizeString($data['project_edit_form_start']);
        $start = !empty($start) ? date_create_from_format($dateFormat, $start) : null;
        $end = $this->validationHelper->sanitizeString($data['project_edit_form_end']);
        $end = !empty($end) ? date_create_from_format($dateFormat, $end) : null;
        $selectedTeams = $data['project_edit_form']['selectedTeams'] ?? [];
        $selectedActivities = $data['project_edit_form']['selectedActivities'] ?? [];
        $visible = isset($data['project_edit_form_visible']) ? 1 : 0;
        $globalActivities = isset($data['project_edit_form_globalactivities']) ? 1 : 0;

        // Validate name
        if (!$this->validationHelper->validateName($name)) {
            $errorMsg .= str_replace("%fieldName%", $this->translations['form_label_name'], $this->translations['form_error_format']) . "\n";
        }

        // Validate color
        if (!$this->validationHelper->validateColor($color)) {
            $errorMsg .= str_replace("%fieldName%", $this->translations['form_label_color'], $this->translations['form_error_format']) . "\n";
        }

        // Validate customer
        if ($customerId === 0) {
            $errorMsg .= str_replace("%fieldName%", $this->translations['form_label_customer'], $this->translations['form_error_format']) . "\n";
        }

        // Validate number
        if (!$this->validationHelper->validateNumber($number, true)) {
            $errorMsg .= str_replace("%fieldName%", $this->translations['form_label_project_number'], $this->translations['form_error_format']) . "\n";
        }

        // Validate Date
        if (!$this->validationHelper->validateDate($start, true)) {
            $errorMsg .= str_replace("%fieldName%", $this->translations['form_label_project_start'], $this->translations['form_error_format']) . "\n";
        }
        if (!$this->validationHelper->validateDate($end, true)) {
            $errorMsg .= str_replace("%fieldName%", $this->translations['form_label_project_end'], $this->translations['form_error_format']) . "\n";
        }

        if ($errorMsg !== '') {
            return $errorMsg;
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

        $project->setName($name);
        $project->setColor($color);
        $project->setCustomerId($customerId);
        $project->setNumber($number);
        $project->setComment($comment);
        $project->setStart($start ? $start->format('Y-m-d') : null);
        $project->setEnd($end ? $end->format('Y-m-d') : null);
        $project->setGlobalActivities($globalActivities);
        $project->setVisible($visible);

        if (!$this->projectRepository->updateProject($project)) {
            return $this->translations['error_occurred'];
        }
        $this->logger->info(
            "[ProjectService] Project '".$project->getName()."' updated",
            [
                'id'   => $project->getId(),
                'name' => $project->getName(),
            ]
        );


        if (!$this->projectRepository->updateTeams($project->getId(), $selectedTeams)) {
            return $this->translations['error_occurred'];
        }
        $this->logger->info(
            "[ProjectService] Project '".$project->getName()."': teams link updated",
            [
                'id'      => $project->getId(),
                'name'    => $project->getName(),
                'teamIds' => $selectedTeams,
            ]
        );

        // update autorised > selectedActivities
        if (!$this->projectRepository->updateActivities($project->getId(), $selectedActivities)) {
            return $this->translations['error_occurred'];
        }
        $this->logger->info(
            "[ProjectService] Project '".$project->getName()."': activities link updated",
            [
                'id'          => $project->getId(),
                'name'        => $project->getName(),
                'activityIds' => $selectedActivities,
            ]
        );

        return $errorMsg;
    }

}
