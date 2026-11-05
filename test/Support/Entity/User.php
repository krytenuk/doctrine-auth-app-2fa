<?php

declare(strict_types=1);

namespace FwsDoctrineAuthTest\AppTwoFactor\Support\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use FwsDoctrineAuth\Mapping as Auth;

#[ORM\Entity]
#[ORM\Table(name: 'app_user')]
class User
{
    #[ORM\Id, ORM\Column(type: Types::INTEGER), ORM\GeneratedValue]
    public ?int $id = null;

    public function __construct(
        #[ORM\Column(type: Types::STRING)]
        #[Auth\IdentityProperty]
        private string $email,
        #[ORM\Column(type: Types::STRING, nullable: true)]
        #[Auth\CredentialProperty]
        private ?string $password,
    ) {
    }
}
