<?php
declare(strict_types=1);

namespace App\Controller;

use App\Service\TagService;

use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

use Slim\Flash\Messages;
use Slim\Routing\RouteContext;
use Slim\Views\Twig;

final class TagsController
{
    private $container;
    private $tagService;

    public function __construct(ContainerInterface $container, TagService $tagService)
    {
        $this->container = $container;
        $this->tagService = $tagService;
    }

    public function index(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $twig  = $this->container->get(Twig::class);
        $flash = $this->container->get('flash');
        $translations = $this->container->get('translations');

        $routeContext = RouteContext::fromRequest($request);
        $routeParser = $routeContext->getRouteParser();

        // Get tags
        $tags = $this->tagService->findAllTags();
        $tagsList = array();
        foreach ($tags as $tag) {
            $tagsList[] = array(
                'name' => $tag->getName(),
                'color' => $tag->getColor(),
                'visible' => $tag->getVisible(),
                'editLink' => $routeParser->urlFor('tags_edit', array('tagId' => $tag->getId())),
            );
        }

        // Get colors
        $colorChoices = $this->container->get('settings')['theme']['colorChoices'];
        $colorsList = array();
        foreach (explode(',',$colorChoices) as $key => $value) {
            list($colorName, $colorValue) = explode('|', $value);
            //$colorsList[$colorName] = $colorValue;
            $colorsList[$key] = array(
                'name' => $colorName,
                'value' => $colorValue,
            );
        }

        $viewData = array();
        $viewData['tags'] = $tagsList;
        $viewData['colors'] = $colorsList;

        $viewData['flashMsgSuccess'] = $flash->getFirstMessage('success');
        $viewData['flashMsgError'] = $flash->getFirstMessage('error');

        return $twig->render($response, 'tags.html.twig', $viewData);
    }

    public function createAction(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $flash = $this->container->get('flash');
        $translations = $this->container->get('translations');

        $data = $request->getParsedBody();
        $routeParser = RouteContext::fromRequest($request)->getRouteParser();

        $errors = $this->tagService->createTag($data);

        if (empty($errors)) {
            $flash->addMessage('success', $translations['form_success_create_activity']);
        }
        else {
            $flash->addMessage('error', $errors);
        }

        // redirect
        $url = $routeParser->urlFor('tags');
        return $response->withStatus(302)->withHeader('Location', $url);
    }

    public function editForm(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $twig  = $this->container->get(Twig::class);
        $flash = $this->container->get('flash');
        $translations = $this->container->get('translations');

        $routeContext = RouteContext::fromRequest($request);
        $routeParser = $routeContext->getRouteParser();

        $tag = $this->tagService->findTag(intval($args['tagId']));
        if ($tag) {
            // Get colors
            $colorChoices = $this->container->get('settings')['theme']['colorChoices'];
            $colorsList = array();
            foreach (explode(',',$colorChoices) as $key => $value) {
                list($colorName, $colorValue) = explode('|', $value);
                //$colorsList[$colorName] = $colorValue;
                $colorsList[$key] = array(
                    'name' => $colorName,
                    'value' => $colorValue,
                );
            }

            $viewData = array();
            $viewData['tag'] = $tag;
            $viewData['colors'] = $colorsList;

            $viewData['flashMsgSuccess'] = $flash->getFirstMessage('success');
            $viewData['flashMsgError'] = $flash->getFirstMessage('error');

            return $twig->render($response, 'tag-edit.html.twig', $viewData);
        }

        // redirect
        $url = $routeParser->urlFor('tags');
        return $response->withStatus(302)->withHeader('Location', $url);
    }

    public function editAction(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $flash = $this->container->get('flash');
        $translations = $this->container->get('translations');

        $data = $request->getParsedBody();
        $routeParser = RouteContext::fromRequest($request)->getRouteParser();

        $tag = $this->tagService->findTag(intval($args['tagId']));
        if ($tag) {
            $errors = $this->tagService->updateTag($tag, $data);

            if (empty($errors)) {
                $flash->addMessage('success', $translations['form_success_update']);
            }
            else {
                $flash->addMessage('error', $errors);
            }

            // redirect
            $url = $routeParser->urlFor('tags_edit', array('tagId' => $args['tagId']));
            return $response->withStatus(302)->withHeader('Location', $url);

        }

        // redirect
        $url = $routeParser->urlFor('tags');
        return $response->withStatus(302)->withHeader('Location', $url);
    }

}
