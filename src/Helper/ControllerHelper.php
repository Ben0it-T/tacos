<?php
declare(strict_types=1);

namespace App\Helper;

use App\Entity\User;
use App\Service\UserService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Routing\RouteContext;

final class ControllerHelper
{
    private const ROLE_ADMIN = 3;
    private const ROLE_TEAMLEAD = 2;
    private const ROLE_USER = 1;

    public function __construct(private UserService $userService)
    {
    }

    // User
    public function getCurrentUser(ServerRequestInterface $request): ?User
    {
        $session = $request->getAttribute('session');

        $userId = is_array($session)
            ? (int)($session['auth']['userId'] ?? 0)
            : 0;

        if ($userId <= 0) {
            return null;
        }

        $user = $this->userService->findUser($userId);
        return $user ?: null;
    }

    public function isAdmin(User $user): bool
    {
        return $user->getRole() === self::ROLE_ADMIN;
    }

    public function isTeamlead(User $user): bool
    {
        return $user->getRole() === self::ROLE_TEAMLEAD;
    }

    public function isUser(User $user): bool
    {
        return $user->getRole() === self::ROLE_USER;
    }

    // url & redirect
    public function getUrlFor(ServerRequestInterface $request, string $routeName, array $data = []): string
    {
        return RouteContext::fromRequest($request)->getRouteParser()->urlFor($routeName, $data);
    }

    public function fullUrlFor(ServerRequestInterface $request, string $routeName, array $data = []): string
    {
        return RouteContext::fromRequest($request)->getRouteParser()->fullUrlFor($request->getUri(), $routeName, $data);
    }

    public function redirect(ServerRequestInterface $request, ResponseInterface $response, string $routeName, array $data = []): ResponseInterface
    {
        $url = $this->getUrlFor($request, $routeName, $data);
        return $response->withStatus(302)->withHeader('Location', $url);
    }

    /**
     * Map entities (having getId() / getName()) to [{id,name}, ...]
     *
     * @param array<object> $entities
     * @return array<int, array{id:int, name:string}>
     */
    public function mapIdNameList(array $entities): array
    {
        return array_map(static function ($e): array {
            return [
                'id'   => $e->getId(),
                'name' => $e->getName(),
            ];
        }, $entities);
    }

    /**
     * Parse "colorChoices" format: "Red|#ff0000,Green|#00ff00,..."
     *
     * @param string $colorChoices
     * @return array<int, array{name:string, value:string}>
     */
    public function parseColorChoices(string $colorChoices): array
    {
        $colorChoices = trim($colorChoices);
        if ($colorChoices === '') {
            return [];
        }

        $colors = [];
        foreach (explode(',', $colorChoices) as $key => $value) {
            if ($value === '') continue;

            $parts = explode('|', $value, 2);
            if (count($parts) !== 2) {
                continue;
            }
            [$name, $hex] = $parts;
            $colors[$key] = [
                'name'  => $name,
                'value' => $hex,
            ];
        }

        return $colors;
    }
}
