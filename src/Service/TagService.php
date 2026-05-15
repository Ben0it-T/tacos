<?php
declare(strict_types=1);

namespace App\Service;

use App\Entity\Tag;
use App\Helper\ValidationHelper;
use App\Repository\TagRepository;

use Psr\Log\LoggerInterface;

final class TagService
{
    private TagRepository $tagRepository;
    private ValidationHelper $validationHelper;
    private LoggerInterface $logger;
    private array $translations;

    public function __construct(TagRepository $tagRepository, ValidationHelper $validationHelper, LoggerInterface $logger, array $translations) {
        $this->tagRepository = $tagRepository;
        $this->validationHelper = $validationHelper;
        $this->logger = $logger;
        $this->translations = $translations;
    }

    /**
     * Find Tag by id
     *
     * @param int $id
     * @return Tag entity or false
     */
    public function findTag(int $id): Tag|false {
        return $this->tagRepository->find($id);
    }

    /**
     * Find All Tag
     *
     * @param ?int $visible
     * @return array of Tag entities
     */
    public function findAll(?int $visible = null): array {
        return $this->tagRepository->findAll($visible);
    }

    /**
     * Find All Visible Tag
     *
     * @return array of Tag
     */
    public function findAllVisible(): array {
        return $this->tagRepository->findAll(1);
    }


    /**
     * Find All Tags by timesheet id
     *
     * @param int  $timesheetId
     * @param ?int $visible
     * @return array of Tag entities
     */
    public function findAllByTimesheetId(int $timesheetId, ?int $visible = null): array {
        return $this->tagRepository->findAllByTimesheetId($timesheetId, $visible);
    }

    /**
     * Create new Tag
     *
     * @param array $data
     * @return string $errorMsg
     */
    public function createTag(array $data): string {
        $errorMsg = "";
        $name = $this->validationHelper->sanitizeString($data['tag_edit_form_name']);
        $color = $this->validationHelper->sanitizeColor($data['tag_edit_form_color'] ?? '#ffffff');
        $visible = isset($data['tag_edit_form_visible']) ? 1 : 0;

        // Validate name
        if (!$this->validationHelper->validateName($name)) {
            $errorMsg .= str_replace("%fieldName%", $this->translations['form_label_name'], $this->translations['form_error_format']) . "\n";
        }
        else if ($this->tagRepository->isNameExists($name)) {
            $errorMsg .= $this->translations['form_error_tag_name'] . "\n";
        }

        // Validate color
        if (!$this->validationHelper->validateColor($color)) {
            $errorMsg .= str_replace("%fieldName%", $this->translations['form_label_color'], $this->translations['form_error_format']) . "\n";
        }

        if ($errorMsg !== '') {
            return $errorMsg;
        }

        $tag = new Tag;
        $tag->setName($name);
        $tag->setColor($color);
        $tag->setVisible($visible);

        $lastInsertId = $this->tagRepository->insert($tag);

        if (!$lastInsertId) {
            return $this->translations['error_occurred'];
        }
        $this->logger->info(
            "[TagService] Tag '".$tag->getName()."' created",
            [
                'id'   => $lastInsertId,
                'name' => $tag->getName(),
            ]
        );

        return '';
    }

    /**
     * Update Tag
     *
     * @param Tag $tag
     * @param array $data
     * @return string $errorMsg
     */
    public function updateTag(Tag $tag, array $data): string {
        $errorMsg = "";
        $name = $this->validationHelper->sanitizeString($data['tag_edit_form_name']);
        $color = $this->validationHelper->sanitizeColor($data['tag_edit_form_color'] ?? '#ffffff');
        $visible = isset($data['tag_edit_form_visible']) ? 1 : 0;

        // Validate name
        if (!$this->validationHelper->validateName($name)) {
            $errorMsg .= str_replace("%fieldName%", $this->translations['form_label_name'], $this->translations['form_error_format']) . "\n";
        }
        else if ($this->tagRepository->isNameExists($name, $tag->getId())) {
            $errorMsg .= $this->translations['form_error_tag_name'] . "\n";
        }

        // Validate color
        if (!$this->validationHelper->validateColor($color)) {
            $errorMsg .= str_replace("%fieldName%", $this->translations['form_label_color'], $this->translations['form_error_format']) . "\n";
        }

        if ($errorMsg !== '') {
            return $errorMsg;
        }

        $tag->setName($name);
        $tag->setColor($color);
        $tag->setVisible($visible);

        if (!$this->tagRepository->update($tag)) {
            return $this->translations['error_occurred'];
        }
        $this->logger->info(
            "[TagService] Tag '".$tag->getName()."' updated",
            [
                'id'   => $tag->getId(),
                'name' => $tag->getName(),
            ]
        );

        return '';
    }

}
