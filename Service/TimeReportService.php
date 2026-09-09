<?php

declare(strict_types=1);

namespace KimaiPlugin\FortnoxBundle\Service;

use App\Entity\Timesheet;
use App\Entity\User;
use App\Repository\Query\BaseQuery;
use App\Repository\Query\TimesheetQuery;
use App\Repository\TimesheetRepository;
use KimaiPlugin\FortnoxBundle\Form\Model\TimeReportFilter;

final class TimeReportService
{
    public function __construct(private readonly TimesheetRepository $timesheets)
    {
    }

    /**
     * @return array<Timesheet>
     */
    public function find(TimeReportFilter $filter, User $currentUser): array
    {
        if ($filter->customer === null || $filter->project === null || $filter->begin === null || $filter->end === null) {
            return [];
        }

        if ($filter->project->getCustomer()?->getId() !== $filter->customer->getId()) {
            throw new \InvalidArgumentException('The selected project does not belong to the selected customer.');
        }

        $begin = (clone $filter->begin)->setTime(0, 0, 0);
        $end = (clone $filter->end)->setTime(23, 59, 59);

        if ($end < $begin) {
            throw new \InvalidArgumentException('The end date must not be before the start date.');
        }

        $query = new TimesheetQuery();
        $query->setCurrentUser($currentUser);
        $query->setCustomers([$filter->customer]);
        $query->setProjects([$filter->project]);
        $query->setBegin($begin);
        $query->setEnd($end);
        $query->setState(TimesheetQuery::STATE_STOPPED);
        $query->setOrderBy('begin');
        $query->setOrder(BaseQuery::ORDER_ASC);

        if ($filter->billableOnly) {
            $query->setBillable(true);
        }

        if ($filter->notExportedOnly) {
            $query->setExported(TimesheetQuery::STATE_NOT_EXPORTED);
        }

        return $this->timesheets->getTimesheetResult($query)->getResults();
    }

    /** @param array<Timesheet> $entries */
    public function totalDuration(array $entries): int
    {
        return array_reduce(
            $entries,
            static fn (int $total, Timesheet $entry): int => $total + (int) $entry->getDuration(),
            0
        );
    }
}
