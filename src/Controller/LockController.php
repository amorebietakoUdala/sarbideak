<?php

namespace App\Controller;

use App\Service\SaltoIntegrationService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;

/** 
* @IsGranted("ROLE_SALTO")
* @Route("/{_locale}")
*/
class LockController extends BaseController
{

    const STATUS_LOCKED='locked';
    const STATUS_OFFICE_MODE='office_mode';
    const STATUS_UNLOCKED='unlocked';

    private SaltoIntegrationService $salto;
    private string $siteId;

    public function __construct(SaltoIntegrationService $salto, string $siteId) {
        $this->salto = $salto;
        $this->siteId = $siteId;
    }

    /**
    * Show lock index page.
    * @Route("/lock", name="lock_index")
    */
    public function index(Request $request): Response
    {
        $this->loadQueryParameters($request);
        $locks = $this->salto->getLocksFromSite($this->siteId); 
        return $this->render('lock/index.html.twig', [
            'locks' => $locks['items'],

        ]);
    }
}
