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
     * Find Tag
     *
     * @param int $id
     * @return Tag or false
     */
    public function findTag(int $id) {
        return $this->tagRepository->find($id);
    }

    /**
     * Find all Tags
     *
     * @return array of Tag
     */
    public function findAllTags() {
        return $this->tagRepository->findAll();
    }

    /**
     * Find All Visible Tag
     *
     * @return array of Tag
     */
    public function findAllVisibleTags() {
        return $this->tagRepository->findAllVisibleTags();
    }


    /**
     * Find All Tags by timesheet id
     *
     * @param int $timesheetId
     * @return array of Tags
     */
    public function findAllTagsByTimesheetId(int $timesheetId) {
        return $this->tagRepository->findAllTagsByTimesheetId($timesheetId);
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
        $color = $this->validationHelper->sanitizeColor($data['tag_edit_form_color']);
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
        $color = $this->validationHelper->sanitizeColor($data['tag_edit_form_color']);
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
