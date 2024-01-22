<?php

namespace App\Entity;

use App\Repository\IqRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: IqRepository::class)]
class Iq
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;

    #[ORM\Column(type: 'string', length: 16, nullable: true)]
    private $secret;

    #[ORM\Column(type: 'string', length: 4, nullable: true)]
    private $pin;

    #[ORM\Column(type: 'string', length: 50, unique: true)]
    private $iqId;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private $customerReference;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSecret(): ?string
    {
        return $this->secret;
    }

    public function setSecret(?string $secret): self
    {
        $this->secret = $secret;

        return $this;
    }

    public function getPin(): ?string
    {
        return $this->pin;
    }

    public function setPin(?string $pin): self
    {
        $this->pin = $pin;

        return $this;
    }

    public function getIqId(): ?string
    {
        return $this->iqId;
    }

    public function setIqId(string $iqId): self
    {
        $this->iqId = $iqId;

        return $this;
    }

    public function getCustomerReference(): ?string
    {
        return $this->customerReference;
    }

    public function setCustomerReference(?string $customerReference): self
    {
        $this->customerReference = $customerReference;

        return $this;
    }

    public function updateIq($pin, $secret, $customerReference) {
        $this->setPin($pin);
        $this->setSecret($secret);
        $this->setCustomerReference($customerReference);
    }

    public static function createIq($iqId, $pin, $secret, $customerReference): Iq {
        $iq = new Iq();
        $iq->setIqId($iqId);
        $iq->setPin($pin);
        $iq->setSecret($secret);
        $iq->setCustomerReference($customerReference);
        return $iq;
    }
}
