<?php
declare(strict_types=1);

namespace App\Controller;

use App\Helper\ControllerHelper;
use App\Service\FlashMessageService;
use App\Service\TagService;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

use Slim\Views\Twig;

final class TagsController
{
    private Twig $twig;
    private FlashMessageService $flash;
    private TagService $tagService;
    private ControllerHelper $helper;
    private array $options;
    private array $translations;

    public function __construct(Twig $twig, FlashMessageService $flash, TagService $tagService, ControllerHelper $helper, array $options, array $translations)
    {
        $this->twig = $twig;
        $this->flash = $flash;
        $this->tagService = $tagService;
        $this->helper = $helper;
        $this->options = $options;
        $this->translations = $translations;
    }

    public function index(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $tags = $this->tagService->findAll();
        $tagsList = $this->mapTagsForList($request, $tags);

        $colors = $this->helper->parseColorChoices((string)($this->options['colorChoices'] ?? ''));

        return $this->twig->render($response, 'tags.html.twig', [
            'tags'            => $tagsList,
            'colors'          => $colors,
            'flashMsgSuccess' => $this->flash->getFirst('success'),
            'flashMsgError'   => $this->flash->getFirst('error'),
        ]);
    }

    public function createAction(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $data = (array) $request->getParsedBody();
        $errors = $this->tagService->createTag($data);

        if ($errors === '') {
            $this->flash->add('success', $this->translations['form_success_create_tag']);
        }
        else {
            $this->flash->add('error', $errors);
        }

        return $this->helper->redirect($request, $response, 'tags');
    }

    public function editForm(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $tagId = (int)($args['tagId'] ?? 0);
        $tag = $this->tagService->findTag($tagId);

        if (!$tag) {
            return $this->helper->redirect($request, $response, 'tags');
        }

        $colors = $this->helper->parseColorChoices((string)($this->options['colorChoices'] ?? ''));

        return $this->twig->render($response, 'tag-edit.html.twig', [
            'tag'            => $tag,
            'colors'          => $colors,
            'flashMsgSuccess' => $this->flash->getFirst('success'),
            'flashMsgError'   => $this->flash->getFirst('error'),
        ]);
    }

    public function editAction(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $tagId = (int)($args['tagId'] ?? 0);
        $tag = $this->tagService->findTag($tagId);

        if (!$tag) {
            return $this->helper->redirect($request, $response, 'tags');
        }

        $data = (array) $request->getParsedBody();
        $errors = $this->tagService->updateTag($tag, $data);

        if ($errors === '') {
            $this->flash->add('success', $this->translations['form_success_update']);
            return $this->helper->redirect($request, $response, 'tags');
        }

        $this->flash->add('error', $errors);
        return $this->helper->redirect($request, $response, 'tags_edit', ['tagId' => $tagId]);
    }

    // Helpers
    private function mapTagsForList(ServerRequestInterface $request, array $tags): array
    {
        $list = [];
        foreach ($tags as $tag) {
            $list[] = [
                'name'     => $tag->getName(),
                'color'    => $tag->getColor(),
                'visible'  => $tag->getVisible(),
                'editLink' => $this->helper->getUrlFor($request, 'tags_edit', ['tagId' => $tag->getId()]),
            ];
        }
        return $list;
    }
}
