<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use App\Service\SaltoIntegrationService;

/** 
* @IsGranted("ROLE_SALTO")
*/
class DefaultController extends BaseController
{

   private SaltoIntegrationService $salto;

   public function __construct(SaltoIntegrationService $salto)
   {
      $this->salto = $salto;
   }

   /**
    * @Route("/", name="app_home")
    */
   public function home(Request $request): Response
   {
      return $this->redirectToRoute('lock_index');
//      return $this->scenario1($request);
      return $this->scenario2($request);
   }

   private function scenario1(Request $request) {
      $siteId = 'bc32b74a-c3e6-4ed6-84ba-dca555f6d309';
      $iqId = '8a1f8adc-fae7-11ed-83e8-000d3a46a880';
      // get Secret
      $secret = $this->salto->getSecretFromIqFromSite($siteId, $iqId);
//      dd($secret);
      $secret = 'E875C9C0F7E00DE3';
      $pin = 1111;
      $newPin = 5518;
      // get Pin By SMS one you receive not need send it every time.
      //$this->salto->sendPinBySMSFromIqFromSite($siteId, $iqId);
      //$this->salto->activateIq($siteId, $iqId, $secret, $pin);
      // $otp = $this->salto->calculateOTP($secret, $pin);
      // dump($otp);
      $this->salto->activateIq($siteId, $iqId, $newPin);
      //dd();
   }

   private function scenario2(Request $request) {
      $siteId = 'bc32b74a-c3e6-4ed6-84ba-dca555f6d309';
      $lockId = '75e0a140-5602-4495-9cbb-53763918ece8';
      $this->salto->unlock($siteId, $lockId);
      dd();
   }

}