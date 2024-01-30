<?php

namespace App\Controller;

use App\Service\SaltoIntegrationService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_SARBIDEAK')]
#[Route(path: '/{_locale}')]
class LockController extends BaseController
{

    final public const STATUS_LOCKED='locked';
    final public const STATUS_OFFICE_MODE='office_mode';
    final public const STATUS_UNLOCKED='unlocked';

    public function __construct(private readonly SaltoIntegrationService $salto, private readonly string $siteId)
    {
    }

    /**
     * Show lock index page.
     */
    #[Route(path: '/lock', name: 'lock_index')]
    public function index(Request $request): Response
    {
        $this->loadQueryParameters($request);
        $locks = $this->salto->getLocksFromSite($this->siteId); 
        if ( !array_key_exists('items',$locks) ) {
            $this->addFlash('error', 'message.cantConnectToApi');
            $locks = [];
        } else {
            $locks = $locks['items'];
        }
        return $this->render('lock/index.html.twig', [
            'locks' => $locks,

        ]);
    }
}
