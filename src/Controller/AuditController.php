<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Repository\AuditRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Translation\TranslatorInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;

/** 
* @IsGranted("ROLE_ADMIN")
* @Route("/{_locale}")
*/
class AuditController extends BaseController
{

    private AuditRepository $repo;
    private TranslatorInterface $translator;
    private $limit;


    public function __construct(AuditRepository $repo, TranslatorInterface $translator, $limit = 50) 
    {
        $this->repo = $repo;
        $this->limit = $limit;
        $this->translator = $translator;
    }
    /**
     * @Route("/audit", name="audit_index")
     */
    public function index(Request $request): Response
    {
        $this->loadQueryParameters($request);
        $audits = $this->repo->findBy([],[
            'date' => 'desc',
        ], $this->limit);
        if (count($audits) === $this->limit) {
            $this->addFlash('warning', $this->translator->trans('messages.resultLimitReached',[
                '{limit}' => $this->limit,
            ]));
        }
        return $this->render('audit/index.html.twig', [
            'audits' => $audits,
        ]);
    }
}
