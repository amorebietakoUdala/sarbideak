<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use AMREU\UserBundle\Model\UserInterface as AMREUserInterface;
use AMREU\UserBundle\Model\User as BaseUser;

#[ORM\Table(name: 'user')]
#[ORM\Entity(repositoryClass: \App\Repository\UserRepository::class)]
class User extends BaseUser implements AMREUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    protected $id;

    #[ORM\Column(type: 'string', length: 180, unique: true)]
    protected $username;

    #[ORM\Column(type: 'json')]
    protected $roles = [];

    /**
     * @var string The hashed password
     */
    #[ORM\Column(type: 'string')]
    protected $password;

    #[ORM\Column(type: 'string', length: 255)]
    protected $firstName;

    #[ORM\Column(type: 'string', length: 255)]
    protected $email;

    #[ORM\Column(type: 'boolean', options: ['default' => '1'], nullable: true)]
    protected $activated;

    #[ORM\Column(type: 'datetime', nullable: true)]
    protected $lastLogin;
}
