<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_SARBIDEAK')]
class DefaultController extends BaseController
{

   #[Route(path: '/', name: 'app_home')]
   public function home() : Response
   {
       return $this->redirectToRoute('lock_index');
   }
}