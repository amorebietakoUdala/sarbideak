<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Repository\AuditRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
#[Route(path: '/{_locale}')]
class AuditController extends BaseController
{

    public function __construct(
        private readonly AuditRepository $repo, 
        private readonly TranslatorInterface $translator, 
        private $limit = 50
    )
    {
    }
    
    #[Route(path: '/audit', name: 'audit_index')]
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
