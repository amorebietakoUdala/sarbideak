<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;

/** 
* @IsGranted("ROLE_SARBIDEAK")
*/
class DefaultController extends BaseController
{

   /**
    * @Route("/", name="app_home")
    */
   public function home(Request $request): Response
   {
      return $this->redirectToRoute('lock_index');
   }
}