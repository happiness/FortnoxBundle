<?php

declare(strict_types=1);

namespace KimaiPlugin\FortnoxBundle\Service;

use App\Entity\Timesheet;
use App\Entity\User;
use App\Repository\Query\BaseQuery;
use App\Repository\Query\TimesheetQuery;
use App\Repository\TimesheetRepository;
use Doctrine\ORM\EntityManagerInterface;
use KimaiPlugin\FortnoxBundle\Form\Model\TimeReportFilter;

final class TimeReportService
{
    public function __construct(
        private readonly TimesheetRepository $timesheets,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Returns the first and last moment of the month containing the given date.
     *
     * @return array{0: \DateTime, 1: \DateTime}
     */
    public function monthRange(\DateTimeInterface $month): array
    {
        $begin = \DateTime::createFromInterface($month)->modify('first day of this month')->setTime(0, 0, 0);
        $end = (clone $begin)->modify('last day of this month')->setTime(23, 59, 59);

        return [$begin, $end];
    }

    /**
     * Summarizes all stopped time entries of a month per customer and project.
     *
     * @return array<int, array{id: int, name: string, entries: int, duration: int, billable: int, notExported: int, projects: array<int, array{id: int, name: string, entries: int, duration: int, billable: int, notExported: int}>}>
     */
    public function summarizeMonth(\DateTimeInterface $month, bool $billableOnly, bool $notExportedOnly): array
    {
        [$begin, $end] = $this->monthRange($month);

        $qb = $this->entityManager->createQueryBuilder();
        $qb
            ->select('c.id AS customerId', 'c.name AS customerName', 'p.id AS projectId', 'p.name AS projectName')
            ->addSelect('COUNT(t.id) AS entries')
            ->addSelect('SUM(t.duration) AS duration')
            ->addSelect('SUM(CASE WHEN t.billable = true THEN t.duration ELSE 0 END) AS billable')
            ->addSelect('SUM(CASE WHEN t.exported = false THEN t.duration ELSE 0 END) AS notExported')
            ->from(Timesheet::class, 't')
            ->join('t.project', 'p')
            ->join('p.customer', 'c')
            ->where($qb->expr()->isNotNull('t.end'))
            ->andWhere($qb->expr()->gte('t.begin', ':begin'))
            ->andWhere($qb->expr()->lte('t.begin', ':end'))
            ->setParameter('begin', $begin)
            ->setParameter('end', $end)
            ->groupBy('c.id', 'c.name', 'p.id', 'p.name')
            ->orderBy('c.name', 'ASC')
            ->addOrderBy('p.name', 'ASC');

        if ($billableOnly) {
            $qb->andWhere('t.billable = true');
        }

        if ($notExportedOnly) {
            $qb->andWhere('t.exported = false');
        }

        /** @var array<int, array<string, mixed>> $rows */
        $rows = $qb->getQuery()->getArrayResult();

        return $this->groupByCustomer($rows);
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array{id: int, name: string, entries: int, duration: int, billable: int, notExported: int, projects: array<int, array{id: int, name: string, entries: int, duration: int, billable: int, notExported: int}>}>
     */
    public function groupByCustomer(array $rows): array
    {
        $customers = [];

        foreach ($rows as $row) {
            $customerId = (int) $row['customerId'];
            $project = [
                'id' => (int) $row['projectId'],
                'name' => (string) $row['projectName'],
                'entries' => (int) $row['entries'],
                'duration' => (int) $row['duration'],
                'billable' => (int) $row['billable'],
                'notExported' => (int) $row['notExported'],
            ];

            if (!isset($customers[$customerId])) {
                $customers[$customerId] = [
                    'id' => $customerId,
                    'name' => (string) $row['customerName'],
                    'entries' => 0,
                    'duration' => 0,
                    'billable' => 0,
                    'notExported' => 0,
                    'projects' => [],
                ];
            }

            $customers[$customerId]['entries'] += $project['entries'];
            $customers[$customerId]['duration'] += $project['duration'];
            $customers[$customerId]['billable'] += $project['billable'];
            $customers[$customerId]['notExported'] += $project['notExported'];
            $customers[$customerId]['projects'][] = $project;
        }

        return array_values($customers);
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
