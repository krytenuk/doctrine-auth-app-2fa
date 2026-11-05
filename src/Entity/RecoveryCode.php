<?php

declare(strict_types=1);

namespace FwsDoctrineAuth\AppTwoFactor\Entity;

use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'two_factor_recovery_code')]
#[ORM\UniqueConstraint(name: 'two_factor_recovery_code_hash', columns: ['user_id', 'code_hash'])]
class RecoveryCode
{
    #[ORM\Id, ORM\Column(type: Types::INTEGER), ORM\GeneratedValue]
    private ?int $id = null;

    #[ORM\Column(name: 'used_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $usedAt = null;

    public function __construct(
        #[ORM\Column(name: 'user_id', type: Types::STRING, length: 64)]
        private string            $userId,
        #[ORM\Column(name: 'code_hash', type: Types::STRING, length: 64)]
        private string            $codeHash,
        #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
        private DateTimeImmutable $createdAt,
    )
    {
    }

    public function getUserId(): string
    {
        return $this->userId;
    }

    public function getUsedAt(): ?DateTimeImmutable
    {
        return $this->usedAt;
    }
}
