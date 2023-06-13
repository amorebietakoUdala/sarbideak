<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use AMREU\UserBundle\Model\UserInterface as AMREUserInterface;
use AMREU\UserBundle\Model\User as BaseUser;

/**
 * @ORM\Table(name="user")
 * @ORM\Entity(repositoryClass="App\Repository\UserRepository")
 */
class User extends BaseUser implements AMREUserInterface
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    protected $id;

    /**
     * @ORM\Column(type="string", length=180, unique=true)
     */
    protected $username;

    /**
     * @ORM\Column(type="json")
     */
    protected $roles = [];

    /**
     * @var string The hashed password
     * @ORM\Column(type="string")
     */
    protected $password;

    /**
     * @ORM\Column(type="string", length=255)
     */
    protected $firstName;

    /**
     * @ORM\Column(type="string", length=255)
     */
    protected $email;

    /**
     * @ORM\Column(type="boolean", options={"default":"1"}, nullable=true)
     */
    protected $activated;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    protected $lastLogin;

    /**
     * @ORM\OneToMany(targetEntity=Audit::class, mappedBy="user")
     */
    private $audits;

    public function __construct()
    {
        $this->audits = new ArrayCollection();
    }

    /**
     * @return Collection<int, Audit>
     */
    public function getAudits(): Collection
    {
        return $this->audits;
    }

    public function addAudit(Audit $audit): self
    {
        if (!$this->audits->contains($audit)) {
            $this->audits[] = $audit;
            $audit->setUser($this);
        }

        return $this;
    }

    public function removeAudit(Audit $audit): self
    {
        if ($this->audits->removeElement($audit)) {
            // set the owning side to null (unless already changed)
            if ($audit->getUser() === $this) {
                $audit->setUser(null);
            }
        }

        return $this;
    }
}
