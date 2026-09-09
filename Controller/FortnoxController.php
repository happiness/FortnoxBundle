<?php

declare(strict_types=1);

namespace KimaiPlugin\FortnoxBundle\Controller;

use App\Controller\AbstractController;
use App\Entity\User;
use App\Utils\PageSetup;
use KimaiPlugin\FortnoxBundle\Form\Model\TimeReportFilter;
use KimaiPlugin\FortnoxBundle\Form\TimeReportType;
use KimaiPlugin\FortnoxBundle\Service\PdfGenerator;
use KimaiPlugin\FortnoxBundle\Service\TimeReportService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route(path: '/admin/fortnox')]
#[IsGranted('fortnox_export')]
final class FortnoxController extends AbstractController
{
    public function __construct(
        private readonly TimeReportService $reports,
        private readonly PdfGenerator $pdfGenerator,
    ) {
    }

    #[Route(path: '', name: 'fortnox_report', methods: ['GET', 'POST'])]
    public function report(Request $request): Response
    {
        $filter = $this->defaultFilter();
        $form = $this->createForm(TimeReportType::class, $filter, [
            'method' => 'POST',
            'action' => $this->generateUrl('fortnox_report'),
        ]);
        $form->handleRequest($request);

        $entries = [];
        $error = null;
        $searched = false;

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $entries = $this->reports->find($filter, $this->currentUser());
                $searched = true;

                if ($request->request->has('download') && $entries !== []) {
                    $pdf = $this->pdfGenerator->generate($filter, $entries);
                    $filename = $this->pdfGenerator->filename($filter);

                    return new Response($pdf, Response::HTTP_OK, [
                        'Content-Type' => 'application/pdf',
                        'Content-Disposition' => sprintf('attachment; filename="%s"', $filename),
                        'Content-Length' => (string) strlen($pdf),
                    ]);
                }
            } catch (\InvalidArgumentException $exception) {
                $error = $exception->getMessage();
            }
        }

        $page = new PageSetup('fortnox.title');
        $page->setActionName('fortnox_report');

        return $this->render('@Fortnox/fortnox/index.html.twig', [
            'page_setup' => $page,
            'form' => $form->createView(),
            'filter' => $filter,
            'entries' => $entries,
            'totalDuration' => $this->reports->totalDuration($entries),
            'error' => $error,
            'searched' => $searched,
        ]);
    }

    private function currentUser(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }

    private function defaultFilter(): TimeReportFilter
    {
        $filter = new TimeReportFilter();
        $now = new \DateTime('now', $this->getDateTimeFactory()->getTimezone());
        $filter->begin = (clone $now)->modify('first day of this month')->setTime(0, 0, 0);
        $filter->end = (clone $now)->modify('last day of this month')->setTime(0, 0, 0);

        return $filter;
    }
}
