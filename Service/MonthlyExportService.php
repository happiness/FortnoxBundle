<?php

declare(strict_types=1);

namespace KimaiPlugin\FortnoxBundle\Service;

use App\Entity\User;
use App\Repository\ProjectRepository;
use KimaiPlugin\FortnoxBundle\Form\Model\TimeReportFilter;

/**
 * Builds one time report PDF per project that has time recorded in a month, packed into a zip file.
 */
final class MonthlyExportService
{
    public function __construct(
        private readonly TimeReportService $reports,
        private readonly PdfGenerator $pdfGenerator,
        private readonly ProjectRepository $projects,
    ) {
    }

    /**
     * Creates a zip archive in the temp directory, the caller has to delete the file.
     *
     * @return array{path: string, filename: string, count: int}|null null if there is nothing to export
     */
    public function createArchive(\DateTimeInterface $month, bool $billableOnly, bool $notExportedOnly, User $user): ?array
    {
        [$begin, $end] = $this->reports->monthRange($month);

        $files = [];
        foreach ($this->reports->summarizeMonth($begin, $billableOnly, $notExportedOnly) as $customer) {
            foreach ($customer['projects'] as $summary) {
                $project = $this->projects->find($summary['id']);
                if ($project === null) {
                    continue;
                }

                $filter = new TimeReportFilter();
                $filter->customer = $project->getCustomer();
                $filter->project = $project;
                $filter->begin = \DateTime::createFromInterface($begin);
                $filter->end = \DateTime::createFromInterface($end);
                $filter->billableOnly = $billableOnly;
                $filter->notExportedOnly = $notExportedOnly;

                // the summary ignores user permissions, the report does not
                $entries = $this->reports->find($filter, $user);
                if ($entries === []) {
                    continue;
                }

                $files[] = [
                    'name' => $this->pdfGenerator->filename($filter),
                    'content' => $this->pdfGenerator->generate($filter, $entries),
                    'id' => (int) $project->getId(),
                ];
            }
        }

        if ($files === []) {
            return null;
        }

        return [
            'path' => $this->buildZip($files),
            'filename' => \sprintf('fortnox_%s.zip', $begin->format('Y-m')),
            'count' => \count($files),
        ];
    }

    /**
     * @param list<array{name: string, content: string, id: int}> $files
     * @return string path of the created zip file
     */
    public function buildZip(array $files): string
    {
        $path = tempnam(sys_get_temp_dir(), 'fortnox_');
        if ($path === false) {
            throw new \RuntimeException('Could not create a temporary file.');
        }

        $zip = new \ZipArchive();
        if ($zip->open($path, \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Could not create the zip archive.');
        }

        $used = [];
        foreach ($files as $file) {
            $zip->addFromString($this->uniqueName($file['name'], $file['id'], $used), $file['content']);
        }

        if (!$zip->close()) {
            throw new \RuntimeException('Could not write the zip archive.');
        }

        return $path;
    }

    /**
     * Two projects can end up with the same file name, e.g. similar names with special characters.
     *
     * @param array<string, true> $used
     */
    private function uniqueName(string $name, int $projectId, array &$used): string
    {
        if (isset($used[$name])) {
            $name = preg_replace('/\.pdf$/', \sprintf('_%d.pdf', $projectId), $name) ?? $name;
        }
        $used[$name] = true;

        return $name;
    }
}
