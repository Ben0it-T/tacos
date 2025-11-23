<?php
declare(strict_types=1);

namespace App\Service;

use App\Entity\Tag;
use App\Helper\ValidationHelper;
use App\Repository\TagRepository;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

final class TagService
{
    private $container;
    private $tagRepository;
    private $validationHelper;
    private $logger;

    public function __construct(ContainerInterface $container, TagRepository $tagRepository, ValidationHelper $validationHelper, LoggerInterface $logger) {
        $this->container = $container;
        $this->tagRepository = $tagRepository;
        $this->validationHelper = $validationHelper;
        $this->logger = $logger;
    }

    /**
     * Find Tag by id
     *
     * @param int $id
     * @return Tag entity or false
     */
    public function findTag(int $id) {
        return $this->tagRepository->find($id);
    }

    /**
     * Find All Tag
     *
     * @param ?int $visible
     * @return array of Tag entities
     */
    public function findAll(?int $visible = null) {
        return $this->tagRepository->findAll($visible);
    }

    /**
     * Find All Visible Tag
     *
     * @return array of Tag
     */
    public function findAllVisible() {
        return $this->tagRepository->findAll(1);
    }


    /**
     * Find All Tags by timesheet id
     *
     * @param int  $timesheetId
     * @param ?int $visible
     * @return array of Tag entities
     */
    public function findAllByTimesheetId(int $timesheetId, ?int $visible = null) {
        return $this->tagRepository->findAllByTimesheetId($timesheetId, $visible);
    }

    /**
     * Create new Tag
     *
     * @param array $data
     * @return string $errorMsg
     */
    public function createTag($data) {
        $translations = $this->container->get('translations');
        $validation = true;
        $errorMsg = "";

        $name = $this->validationHelper->sanitizeString($data['tag_edit_form_name']);
        $color = isset($data['tag_edit_form_color']) ? $this->validationHelper->sanitizeColor($data['tag_edit_form_color']) : "#ffffff";
        $visible = isset($data['tag_edit_form_visible']) ? 1 : 0;

        // Validate name
        if (!$this->validationHelper->validateName($name)) {
            $validation = false;
            $errorMsg .= str_replace("%fieldName%", $translations['form_label_name'], $translations['form_error_format']) . "\n";
        }
        else if ($this->tagRepository->isNameExists($name)) {
            $validation = false;
            $errorMsg .= $translations['form_error_tag_name'] . "\n";
        }

        // Validate color
        if (!$this->validationHelper->validateColor($color)) {
            $validation = false;
            $errorMsg .= str_replace("%fieldName%", $translations['form_label_color'], $translations['form_error_format']) . "\n";
        }

        if ($validation) {
            $tag = new Tag;
            $tag->setName($name);
            $tag->setColor($color);
            $tag->setVisible($visible);
            $lastInsertId = $this->tagRepository->insert($tag);
            $this->logger->info("TagService - Tag '" . $lastInsertId . "' created.");
        }

        return $errorMsg;
    }

    /**
     * Update Tag
     *
     * @param Tag $tag
     * @param array $data
     * @return string $errorMsg
     */
    public function updateTag($tag, $data) {
        $translations = $this->container->get('translations');
        $validation = true;
        $errorMsg = "";

        $name = $this->validationHelper->sanitizeString($data['tag_edit_form_name']);
        $color = isset($data['tag_edit_form_color']) ? $this->validationHelper->sanitizeColor($data['tag_edit_form_color']) : "#ffffff";
        $visible = isset($data['tag_edit_form_visible']) ? 1 : 0;

        // Validate name
        if (!$this->validationHelper->validateName($name)) {
            $validation = false;
            $errorMsg .= str_replace("%fieldName%", $translations['form_label_name'], $translations['form_error_format']) . "\n";
        }
        else if ($this->tagRepository->isNameExists($name, $tag->getId())) {
            $validation = false;
            $errorMsg .= $translations['form_error_tag_name'] . "\n";
        }

        // Validate color
        if (!$this->validationHelper->validateColor($color)) {
            $validation = false;
            $errorMsg .= str_replace("%fieldName%", $translations['form_label_color'], $translations['form_error_format']) . "\n";
        }

        if ($validation) {
            $tag->setName($name);
            $tag->setColor($color);
            $tag->setVisible($visible);
            $this->tagRepository->update($tag);
            $this->logger->info("TagService - Tag '" . $tag->getId() . "' updated.");
        }

        return $errorMsg;
    }

}
