<?php

declare(strict_types=1);

namespace KimaiPlugin\FortnoxBundle\Controller;

use App\Controller\AbstractController;
use App\Entity\User;
use App\Repository\ProjectRepository;
use App\Utils\PageSetup;
use KimaiPlugin\FortnoxBundle\Form\Model\TimeReportFilter;
use KimaiPlugin\FortnoxBundle\Form\TimeReportType;
use KimaiPlugin\FortnoxBundle\Service\MonthlyExportService;
use KimaiPlugin\FortnoxBundle\Service\PdfGenerator;
use KimaiPlugin\FortnoxBundle\Service\TimeReportService;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route(path: '/admin/fortnox')]
#[IsGranted('fortnox_export')]
final class FortnoxController extends AbstractController
{
    public function __construct(
        private readonly TimeReportService $reports,
        private readonly PdfGenerator $pdfGenerator,
        private readonly ProjectRepository $projects,
        private readonly MonthlyExportService $monthlyExport,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Route(path: '', name: 'fortnox_overview', methods: ['GET'])]
    public function overview(Request $request): Response
    {
        $timezone = $this->getDateTimeFactory()->getTimezone();
        $month = \DateTime::createFromFormat('!Y-m', (string) $request->query->get('month', ''), $timezone);
        if ($month === false) {
            $month = new \DateTime('now', $timezone);
        }

        // the checkboxes default to checked, but must be possible to switch off once the form was submitted
        $submitted = $request->query->has('month');
        $billableOnly = $submitted ? $request->query->getBoolean('billableOnly') : true;
        $notExportedOnly = $submitted ? $request->query->getBoolean('notExportedOnly') : true;

        [$begin, $end] = $this->reports->monthRange($month);

        $page = new PageSetup('fortnox.title');
        $page->setActionName('fortnox_overview');

        return $this->render('@Fortnox/fortnox/overview.html.twig', [
            'page_setup' => $page,
            'month' => $begin,
            'previousMonth' => (clone $begin)->modify('-1 month'),
            'nextMonth' => (clone $begin)->modify('+1 month'),
            'begin' => $begin,
            'end' => $end,
            'billableOnly' => $billableOnly,
            'notExportedOnly' => $notExportedOnly,
            'customers' => $this->reports->summarizeMonth($begin, $billableOnly, $notExportedOnly),
        ]);
    }

    /**
     * One PDF per project with time in the month, delivered as a single zip file.
     */
    #[Route(path: '/export', name: 'fortnox_export_month', methods: ['GET'])]
    public function exportMonth(Request $request): Response
    {
        $timezone = $this->getDateTimeFactory()->getTimezone();
        $month = \DateTime::createFromFormat('!Y-m', (string) $request->query->get('month', ''), $timezone);
        $billableOnly = $request->query->getBoolean('billableOnly');
        $notExportedOnly = $request->query->getBoolean('notExportedOnly');

        if ($month !== false) {
            $archive = $this->monthlyExport->createArchive($month, $billableOnly, $notExportedOnly, $this->currentUser());
            if ($archive !== null) {
                $response = new BinaryFileResponse($archive['path']);
                $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $archive['filename']);
                $response->headers->set('Content-Type', 'application/zip');
                $response->deleteFileAfterSend(true);

                return $response;
            }
        }

        $this->addFlash('error', $this->translator->trans('fortnox.overview.export_nothing'));

        return new RedirectResponse($this->generateUrl('fortnox_overview', [
            'month' => $request->query->get('month'),
            'billableOnly' => $billableOnly ? 1 : 0,
            'notExportedOnly' => $notExportedOnly ? 1 : 0,
        ]));
    }

    #[Route(path: '/report', name: 'fortnox_report', methods: ['GET', 'POST'])]
    public function report(Request $request): Response
    {
        $filter = $this->defaultFilter();
        $fromOverview = $request->isMethod('GET') && $request->query->has('project');
        if ($fromOverview) {
            $this->applyQuery($filter, $request);
        }
        $form = $this->createForm(TimeReportType::class, $filter, [
            'method' => 'POST',
            'action' => $this->generateUrl('fortnox_report'),
        ]);
        $form->handleRequest($request);
        if ($fromOverview && $filter->project !== null) {
            $form->submit($this->formData($filter));
        }

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

    private function applyQuery(TimeReportFilter $filter, Request $request): void
    {
        $project = $this->projects->find((int) $request->query->get('project'));
        if ($project === null) {
            return;
        }

        $filter->project = $project;
        $filter->customer = $project->getCustomer();

        $timezone = $this->getDateTimeFactory()->getTimezone();
        foreach (['begin', 'end'] as $field) {
            $date = \DateTime::createFromFormat('!Y-m-d', (string) $request->query->get($field, ''), $timezone);
            if ($date !== false) {
                $filter->$field = $date;
            }
        }

        $filter->billableOnly = $request->query->getBoolean('billableOnly', true);
        $filter->notExportedOnly = $request->query->getBoolean('notExportedOnly', true);
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(TimeReportFilter $filter): array
    {
        $data = [
            'customer' => $filter->customer?->getId(),
            'project' => $filter->project?->getId(),
            'begin' => $filter->begin?->format('Y-m-d'),
            'end' => $filter->end?->format('Y-m-d'),
        ];
        if ($filter->billableOnly) {
            $data['billableOnly'] = '1';
        }
        if ($filter->notExportedOnly) {
            $data['notExportedOnly'] = '1';
        }

        return $data;
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
