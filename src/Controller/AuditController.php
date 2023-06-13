<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Repository\AuditRepository;
use Symfony\Component\HttpFoundation\Request;

class AuditController extends BaseController
{

    private AuditRepository $repo;

    public function __construct(AuditRepository $repo) 
    {
        $this->repo = $repo;    
    }
    /**
     * @Route("/audit", name="audit_index")
     */
    public function index(Request $request): Response
    {
        $this->loadQueryParameters($request);
        $audits = $this->repo->findAll();
        return $this->render('audit/index.html.twig', [
            'audits' => $audits,
        ]);
    }
}
