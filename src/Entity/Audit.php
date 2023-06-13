<?php

namespace App\Entity;

use App\Repository\AuditRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=AuditRepository::class)
 */
class Audit
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="datetime")
     */
    private $date;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $lockDescription;

    /**
     * @ORM\ManyToOne(targetEntity=User::class, inversedBy="audits")
     */
    private $user;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $result;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDate(): ?\DateTimeInterface
    {
        return $this->date;
    }

    public function setDate(\DateTimeInterface $date): self
    {
        $this->date = $date;

        return $this;
    }

    public function getLockDescription(): ?string
    {
        return $this->lockDescription;
    }

    public function setLockDescription(?string $lockDescription): self
    {
        $this->lockDescription = $lockDescription;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;

        return $this;
    }

    public function getResult(): ?string
    {
        return $this->result;
    }

    public function setResult(string $result): self
    {
        $this->result = $result;

        return $this;
    }

    public static function createAudit(\DateTime $date, User $user, string $lockDescription, string $result) {
        $audit = new Audit();
        $audit->setDate($date);
        $audit->setLockDescription($lockDescription);
        $audit->setUser($user);
        $audit->setResult($result);
        return $audit;
    }
}
