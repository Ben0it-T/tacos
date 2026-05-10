<?php
declare(strict_types=1);

namespace App\Entity;

use DateTimeImmutable;

final class LoginAttempts
{
    private int $trackingId;
    private int $attempts;
    private DateTimeImmutable $firstAttemptAt;
    private ?DateTimeImmutable $blockedUntil = null;

    public function getTrackingId(): int {
        return $this->trackingId;
    }

    public function setTrackingId(int $trackingId): self {
        $this->trackingId = $trackingId;
        return $this;
    }


    public function getAttempts(): int {
        return $this->attempts;
    }

    public function setAttempts(int $attempts): self {
        $this->attempts = $attempts;
        return $this;
    }


    public function getFirstAttemptAt(): DateTimeImmutable {
        return $this->firstAttemptAt;
    }

    public function setFirstAttemptAt(DateTimeImmutable $firstAttemptAt): self {
        $this->firstAttemptAt = $firstAttemptAt;
        return $this;
    }


     public function getBlockedUntil(): ?DateTimeImmutable {
        return $this->blockedUntil;
    }

    public function setBlockedUntil(?DateTimeImmutable $blockedUntil) {
        $this->blockedUntil = $blockedUntil;
        return $this;
    }
}
