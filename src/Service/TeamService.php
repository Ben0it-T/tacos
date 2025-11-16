<?php
declare(strict_types=1);

namespace App\Service;

use App\Entity\Team;
use App\Helper\ValidationHelper;
use App\Repository\TeamRepository;
use App\Repository\UserRepository;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

final class TeamService
{
    private $container;
    private $teamRepository;
    private $userRepository;
    private $validationHelper;
    private $logger;

    public function __construct(ContainerInterface $container, TeamRepository $teamRepository, UserRepository $userRepository, ValidationHelper $validationHelper, LoggerInterface $logger) {
        $this->container = $container;
        $this->teamRepository = $teamRepository;
        $this->userRepository = $userRepository;
        $this->validationHelper = $validationHelper;
        $this->logger = $logger;
    }

    /**
     * Find Team
     *
     * @param int $id
     * @return Team or false
     */
    public function findTeam(int $id) {
        return $this->teamRepository->find($id);
    }

    /**
     * Find Team
     *
     * @param int $teamId
     * @param int $teamleaderId
     * @return Team or false
     */
    public function findTeamByIdAndTeamleader(int $teamId, int $teamleaderId) {
        return $this->teamRepository->findOneByIdAndTeamleader($teamId, $teamleaderId);
    }

    /**
     * Find Teams
     *
     * @return array of Teams
     */
    public function findAllTeams() {
        return $this->teamRepository->findAll();
    }

    /**
     * Find all Teams by activity
     *
     * @param int $activityId
     * @return array of Teams
     */
    public function findAllTeamsByActivityId(int $activityId) {
        return $this->teamRepository->findAllTeamsByActivityId($activityId);
    }

    /**
     * Find all Teams by customer
     *
     * @param int $customerId
     * @return array of Teams
     */
    public function findAllTeamsByCustomerId(int $customerId) {
        return $this->teamRepository->findAllTeamsByCustomerId($customerId);
    }

    /**
     * Find all Teams by project
     *
     * @param int $projectId
     * @return array of Teams
     */
    public function findAllTeamsByProjectId(int $projectId) {
        return $this->teamRepository->findAllTeamsByProjectId($projectId);
    }

    /**
     * Find all Teams by user
     *
     * @param int $userId
     * @return array of Teams
     */
    public function findAllTeamsByUserId(int $userId) {
        return $this->teamRepository->findAllByUserId($userId);
    }

    /**
     * Find Teamleader's Teams
     *
     * @param int $teamleaderId
     * @return array of of Teams
     */
    public function findAllTeamsByTeamleaderId(int $teamleaderId) {
        return $this->teamRepository->findAllByTeamleaderId($teamleaderId);
    }

    /**
     * Find all Teams with Users count and Teamleaders
     *
     * @return array of Teams with Users count and Teamleaders
     */
    public function findAllTeamsWithUserCountAndTeamleads() {
        return $this->teamRepository->findAllTeamsWithUserCountAndTeamleads();
    }

    /**
     * Find all Teams with Users count and Teamleaders by Teamleader id
     *
     * @return array of Teams with Users count and Teamleaders
     */
    public function findAllTeamsWithUserCountAndTeamleadsByTeamleaderId(int $teamleaderId) {
        return $this->teamRepository->findAllTeamsWithUserCountAndTeamleadsByTeamleaderId($teamleaderId);
    }

    /**
     * Get Team members
     *
     * @param int teamId
     * @return array list of Members
     */
    public function getTeamMembers(int $teamId) {
        return $this->teamRepository->getTeamMembers($teamId);
    }

    /**
     * Get Team Teamleaders
     *
     * @param int teamId
     * @return array list of Members
     */
    public function getTeamTeamleaders(int $teamId) {
        return $this->teamRepository->getTeamTeamleaders($teamId);
    }



    /**
     * Create new Team
     *
     * @param array $data
     * @return string $errorMsg
     */
    public function createTeam($data) {
        $translations = $this->container->get('translations');
        $validation = true;
        $errorMsg = "";

        $name = $this->validationHelper->sanitizeName($data['team_edit_form_name']);
        $color = isset($data['team_edit_form_color']) ? $this->validationHelper->sanitizeColor($data['team_edit_form_color']) : "#ffffff";
        $members = isset($data['team_edit_form']['members']) ? $data['team_edit_form']['members'] : array();
        foreach ($members as $key => $member) {
            $members[$key]['user'] = intval($member['user']);
            $members[$key]['teamlead'] = isset($member['teamlead']) ? intval($member['teamlead']) : 0;
        }

        // Validate name
        if (!$this->validationHelper->validateName($name)) {
            $validation = false;
            $errorMsg .= $translations['form_error_name'] . "\n";
        }
        else if ($this->teamRepository->isTeamNameExists($name)) {
            $validation = false;
            $errorMsg .= $translations['form_error_team_name'] . "\n";
        }

        // Validate color
        if (!$this->validationHelper->validateColor($color)) {
            $validation = false;
            $errorMsg .= str_replace("%fieldName%", $translations['form_label_color'], $translations['form_error_format']) . "\n";
        }

        if ($validation) {
            $team = new Team();
            $team->setName($name);
            $team->setColor($color);
            $lastInsertId = $this->teamRepository->insert($team);
            $this->logger->info("TeamService - Team '" . $lastInsertId . "' created.");
            if (count($members) > 0) {
                $this->teamRepository->insertMembers(intval($lastInsertId), $members);
                $this->logger->info("TeamService - Team '" . $lastInsertId . "': members created.");
            }
        }

        return $errorMsg;
    }

    /**
     * Update Team
     *
     * @param Team $team
     * @param array $data
     * @return string $errorMsg
     */
    public function updateTeam(Team $team, $data) {
        $translations = $this->container->get('translations');
        $validation = true;
        $errorMsg = "";

        $name = $this->validationHelper->sanitizeName($data['team_edit_form_name']);
        $color = isset($data['team_edit_form_color']) ? $this->validationHelper->sanitizeColor($data['team_edit_form_color']) : "#ffffff";
        $members = isset($data['team_edit_form']['members']) ? $data['team_edit_form']['members'] : array();
        foreach ($members as $key => $member) {
            $members[$key]['user'] = intval($member['user']);
            $members[$key]['teamlead'] = isset($member['teamlead']) ? intval($member['teamlead']) : 0;
        }

        // Validate name
        if (!$this->validationHelper->validateName($name)) {
            $validation = false;
            $errorMsg .= $translations['form_error_name'] . "\n";
        }
        else if ($this->teamRepository->isTeamNameExists($name, $team->getId())) {
            $validation = false;
            $errorMsg .= $translations['form_error_team_name'] . "\n";
        }

        // Validate color
        if (!$this->validationHelper->validateColor($color)) {
            $validation = false;
            $errorMsg .= str_replace("%fieldName%", $translations['form_label_color'], $translations['form_error_format']) . "\n";
        }

        if ($validation) {
            $team->setName($name);
            $team->setColor($color);
            $this->teamRepository->updateTeam($team);
            $this->logger->info("TeamService - Team '" . $team->getId() . "' updated.");
            $this->teamRepository->updateMembers($team->getId(), $members);
            $this->logger->info("TeamService - Team '" . $team->getId() . "': members updated.");
        }

        return $errorMsg;
    }

}
