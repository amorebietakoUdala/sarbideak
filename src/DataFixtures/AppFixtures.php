<?php

namespace App\DataFixtures;

use App\Entity\GraveType;
use App\Factory\UserFactory;
use App\Factory\CemeteryFactory;
use App\Factory\GraveFactory;
use App\Factory\GraveTypeFactory;
use App\Factory\OwnerFactory;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class AppFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        //UserFactory::createMany(5);
        $manager->flush();
    }
}
