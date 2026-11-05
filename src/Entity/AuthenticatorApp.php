<?php

declare(strict_types=1);

namespace FwsDoctrineAuth\AppTwoFactor\Entity;

use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * A user's paired authenticator app. Users have at most one.
 */
#[ORM\Entity]
#[ORM\Table(name: 'two_factor_app')]
class AuthenticatorApp
{
    public function __construct(
        #[ORM\Id, ORM\Column(name: 'user_id', type: Types::STRING, length: 64)]
        private string            $userId,
        #[ORM\Column(type: Types::STRING, length: 255)]
        private string            $secret,
        /** The 30 second step of the last code used, so no code can be used twice */
        #[ORM\Column(name: 'last_timestep', type: Types::INTEGER, nullable: true)]
        private ?int              $lastTimestep,
        #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
        private DateTimeImmutable $createdAt,
    )
    {
    }

    public function getUserId(): string
    {
        return $this->userId;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
