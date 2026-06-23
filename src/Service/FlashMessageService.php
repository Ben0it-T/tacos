<?php
declare(strict_types=1);

namespace App\Service;

use App\Session\SessionStoreInterface;

final class FlashMessageService
{
    private const KEY = '_flash';

    public function __construct(private SessionStoreInterface $session)
    {
    }

    /**
     * Add flash message
     *
     * @param string $key
     * @param mixed $message
     */
    public function add(string $key, mixed $message): void
    {
        $messages = $this->session->get(self::KEY, []);

        if (!isset($messages[$key])) {
            $messages[$key] = [];
        }

        $messages[$key][] = $message;

        $this->session->set(self::KEY, $messages);
    }

    /**
     * Get Flash message
     *
     * @param  string $key
     * @return array<mixed>
     */
    public function get(string $key): array
    {
        $messages = $this->session->get(self::KEY, []);

        if (!isset($messages[$key])) {
            return [];
        }

        $value = $messages[$key];

        unset($messages[$key]);
        $this->session->set(self::KEY, $messages);

        return $value;
    }

    /**
     * Get the first Flash message
     *
     * @param  string $key
     * @param  ?string $default
     * @return mixed
     */
    public function getFirst(string $key, ?string $default = null): mixed
    {
        $messages = $this->session->get(self::KEY, []);

        if (!isset($messages[$key][0])) {
            return $default;
        }

        $value = array_shift($messages[$key]);

        if (empty($messages[$key])) {
            unset($messages[$key]);
        }
        $this->session->set(self::KEY, $messages);

        return $value;
    }

    public function all(): array
    {
        return $this->session->get(self::KEY, []);
    }

    public function clear(): void
    {
        $this->session->remove(self::KEY);
    }
}
