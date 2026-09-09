<?php

declare(strict_types=1);

namespace KimaiPlugin\FortnoxBundle\Service;

use App\Entity\Timesheet;
use KimaiPlugin\FortnoxBundle\Form\Model\TimeReportFilter;
use Mpdf\Mpdf;
use Twig\Environment;

final class PdfGenerator
{
    public function __construct(private readonly Environment $twig)
    {
    }

    /** @param array<Timesheet> $entries */
    public function generate(TimeReportFilter $filter, array $entries): string
    {
        $html = $this->twig->render('@Fortnox/fortnox/pdf.html.twig', [
            'filter' => $filter,
            'entries' => $entries,
        ]);

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'margin_left' => 12,
            'margin_right' => 12,
            'margin_top' => 14,
            'margin_bottom' => 14,
        ]);
        $mpdf->WriteHTML($html);

        return $mpdf->Output('', 'S');
    }

    public function filename(TimeReportFilter $filter): string
    {
        $customer = $this->slug($filter->customer?->getName() ?? 'customer');
        $project = $this->slug($filter->project?->getName() ?? 'project');
        $from = $filter->begin?->format('Y-m-d') ?? 'from';
        $to = $filter->end?->format('Y-m-d') ?? 'to';

        return sprintf('%s_%s_%s_%s.pdf', $customer, $project, $from, $to);
    }

    private function slug(string $value): string
    {
        $value = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value;
        $value = preg_replace('/[^A-Za-z0-9]+/', '_', $value) ?? $value;

        return trim($value, '_');
    }
}
