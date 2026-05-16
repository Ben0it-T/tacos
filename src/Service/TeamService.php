<?php
declare(strict_types=1);

namespace App\Service;

use App\Entity\Team;
use App\Helper\ValidationHelper;
use App\Repository\TeamRepository;
use Psr\Log\LoggerInterface;

final class TeamService
{
    private TeamRepository $teamRepository;
    private ValidationHelper $validationHelper;
    private LoggerInterface $logger;
    private array $translations;

    public function __construct(TeamRepository $teamRepository, ValidationHelper $validationHelper, LoggerInterface $logger, array $translations) {
        $this->teamRepository = $teamRepository;
        $this->validationHelper = $validationHelper;
        $this->logger = $logger;
        $this->translations = $translations;
    }

    /**
     * Find Team
     *
     * @param int $id
     * @return Team or false
     */
    public function findTeam(int $id): Team|false {
        return $this->teamRepository->find($id);
    }

    /**
     * Find Team
     *
     * @param int $teamId
     * @param int $teamleaderId
     * @return Team or false
     */
    public function findTeamByIdAndTeamleader(int $teamId, int $teamleaderId): Team|false {
        return $this->teamRepository->findOneByIdAndTeamleaderId($teamId, $teamleaderId);
    }

    /**
     * Find Teams
     *
     * @return array of Teams
     */
    public function findAllTeams(): array {
        return $this->teamRepository->findAll();
    }

    /**
     * Find all Teams by activity
     *
     * @param int $activityId
     * @return array of Teams
     */
    public function findAllTeamsByActivityId(int $activityId): array {
        return $this->teamRepository->findAllByActivityId($activityId);
    }

    /**
     * Find all Teams by customer
     *
     * @param int $customerId
     * @return array of Teams
     */
    public function findAllTeamsByCustomerId(int $customerId): array {
        return $this->teamRepository->findAllByCustomerId($customerId);
    }

    /**
     * Find all Teams by project
     *
     * @param int $projectId
     * @return array of Teams
     */
    public function findAllTeamsByProjectId(int $projectId): array {
        return $this->teamRepository->findAllByProjectId($projectId);
    }

    /**
     * Find all Teams by user
     *
     * @param int $userId
     * @return array of Teams
     */
    public function findAllTeamsByUserId(int $userId): array {
        return $this->teamRepository->findAllByUserId($userId);
    }

    /**
     * Find Teamleader's Teams
     *
     * @param int $teamleaderId
     * @return array of Teams
     */
    public function findAllTeamsByTeamleaderId(int $teamleaderId): array {
        return $this->teamRepository->findAllByTeamleaderId($teamleaderId);
    }



    /**
     * Find all Teams with teamlead by user id
     *
     * @param int $userId
     * @return array of Team entities
     */
    public function findAllTeamsWithTeamleadByUserId(int $userId): array {
        return $this->teamRepository->findAllTeamsWithTeamleadByUserId($userId);
    }

    /**
     * Find all Teams with Users count and Teamleaders
     *
     * @return array of Teams with Users count and Teamleaders
     */
    public function findAllTeamsWithUserCountAndTeamleads(): array {
        return $this->teamRepository->findAllTeamsWithUserCountAndTeamleads();
    }

    /**
     * Find all Teams with Users count and Teamleaders by Teamleader id
     *
     * @return array of Teams with Users count and Teamleaders
     */
    public function findAllTeamsWithUserCountAndTeamleadsByTeamleaderId(int $teamleaderId): array {
        return $this->teamRepository->findAllTeamsWithUserCountAndTeamleadsByTeamleaderId($teamleaderId);
    }



    /**
     * Create new Team
     *
     * @param array $data
     * @return string $errorMsg
     */
    public function createTeam(array $data): string {
        $errorMsg = "";
        $name = $this->validationHelper->sanitizeName($data['team_edit_form_name']);
        $color = $this->validationHelper->sanitizeColor($data['team_edit_form_color'] ?? '#ffffff');
        $members = $data['team_edit_form']['members'] ?? [];

        foreach ($members as $key => $member) {
            $members[$key]['user'] = isset($member['user']) ? intval($member['user']) : 0;
            $members[$key]['teamlead'] = isset($member['teamlead']) ? intval($member['teamlead']) : 0;
        }

        // Validate name
        if (!$this->validationHelper->validateName($name)) {
            $errorMsg .= $this->translations['form_error_name'] . "\n";
        }
        else if ($this->teamRepository->isTeamNameExists($name)) {
            $errorMsg .= $this->translations['form_error_team_name'] . "\n";
        }

        // Validate color
        if (!$this->validationHelper->validateColor($color)) {
            $errorMsg .= str_replace("%fieldName%", $this->translations['form_label_color'], $this->translations['form_error_format']) . "\n";
        }

        if ($errorMsg !== '') {
            return $errorMsg;
        }

        $team = new Team();
        $team->setName($name);
        $team->setColor($color);

        $lastInsertId = $this->teamRepository->insert($team);

        if (!$lastInsertId) {
            return $this->translations['error_occurred'];
        }

        $this->logger->info(
            "[TeamService] Team '".$team->getName()."' created",
            [
                'id'   => $lastInsertId,
                'name' => $team->getName(),
            ]
        );

        if (count($members) > 0) {
            if (!$this->teamRepository->insertMembers(intval($lastInsertId), $members)) {
                return $this->translations['error_occurred'];
            }
            $this->logger->info(
                "[TeamService] Team '".$team->getName()."': users link created",
                [
                    'id'      =>  $lastInsertId,
                    'name'    =>  $team->getName(),
                    'userIds' =>  $members,
                ]
            );
        }

        return '';
    }

    /**
     * Update Team
     *
     * @param Team $team
     * @param array $data
     * @return string $errorMsg
     */
    public function updateTeam(Team $team, array $data): string {
        $errorMsg = "";
        $name = $this->validationHelper->sanitizeName($data['team_edit_form_name']);
        $color = $this->validationHelper->sanitizeColor($data['team_edit_form_color'] ?? '#ffffff');
        $members = $data['team_edit_form']['members'] ?? [];

        foreach ($members as $key => $member) {
            $members[$key]['user'] = isset($member['user']) ? intval($member['user']) : 0;
            $members[$key]['teamlead'] = isset($member['teamlead']) ? intval($member['teamlead']) : 0;
        }

        // Validate name
        if (!$this->validationHelper->validateName($name)) {
            $errorMsg .= $this->translations['form_error_name'] . "\n";
        }
        else if ($this->teamRepository->isTeamNameExists($name, $team->getId())) {
            $errorMsg .= $this->translations['form_error_team_name'] . "\n";
        }

        // Validate color
        if (!$this->validationHelper->validateColor($color)) {
            $errorMsg .= str_replace("%fieldName%", $this->translations['form_label_color'], $this->translations['form_error_format']) . "\n";
        }

        if ($errorMsg !== '') {
            return $errorMsg;
        }

        $team->setName($name);
        $team->setColor($color);

        if (!$this->teamRepository->updateTeam($team)) {
            return $this->translations['error_occurred'];
        }

        $this->logger->info(
            "[TeamService] Team '".$team->getName()."' updated",
            [
                'id'   => $team->getId(),
                'name' => $team->getName(),
            ]
        );

        if (!$this->teamRepository->updateMembers($team->getId(), $members)) {
            return $this->translations['error_occurred'];
        }
        $this->logger->info(
            "[TeamService] Team '".$team->getName()."': users link updated",
            [
                'id'      =>  $team->getId(),
                'name'    =>  $team->getName(),
                'userIds' =>  $members,
            ]
        );

        return '';
    }

}
