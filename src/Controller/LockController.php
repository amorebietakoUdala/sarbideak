<?php

namespace App\Controller;

use App\Entity\Audit;
use App\Service\SaltoIntegrationService;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;

/** 
* @IsGranted("ROLE_SALTO")
*/
class LockController extends BaseController
{

    private SaltoIntegrationService $salto;
    private string $siteId;
    private EntityManagerInterface $em;

    public function __construct(SaltoIntegrationService $salto, EntityManagerInterface $em, string $siteId) {
        $this->salto = $salto;
        $this->siteId = $siteId;
        $this->em = $em;
    }

    /**
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

    /**
    * @Route("/lock/{lockId}/unlock", name="lock_unlock")
    */
    public function unlock($lockId) {
        $lock = $this->salto->getLockById($this->siteId, $lockId);
        $result = $this->salto->unlock($this->siteId,$lockId);
        if ($result !== null && $result['status'] === 'success') {
            $this->addFlash('success', 'messages.successfullyUnlocked');
            $audit = Audit::createAudit(new \DateTime(), $this->getUser(),$lock['customer_reference'],'success');
        } else {
            $this->addFlash('error', $result['message']);
            $audit = Audit::createAudit(new \DateTime(), $this->getUser(),$lock['customer_reference'],$result['message']);
        }
        $this->em->persist($audit);
        $this->em->flush();
        return $this->redirectToRoute('lock_index');
    }
}
