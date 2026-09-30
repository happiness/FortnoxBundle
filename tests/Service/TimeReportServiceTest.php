<?php

declare(strict_types=1);

namespace KimaiPlugin\FortnoxBundle\Tests\Service;

use App\Repository\TimesheetRepository;
use Doctrine\ORM\EntityManagerInterface;
use KimaiPlugin\FortnoxBundle\Service\TimeReportService;
use PHPUnit\Framework\TestCase;

class TimeReportServiceTest extends TestCase
{
    private function createService(): TimeReportService
    {
        return new TimeReportService(
            $this->createMock(TimesheetRepository::class),
            $this->createMock(EntityManagerInterface::class)
        );
    }

    public function testMonthRange(): void
    {
        [$begin, $end] = $this->createService()->monthRange(new \DateTime('2026-02-17 13:45:00'));

        self::assertSame('2026-02-01 00:00:00', $begin->format('Y-m-d H:i:s'));
        self::assertSame('2026-02-28 23:59:59', $end->format('Y-m-d H:i:s'));
    }

    public function testMonthRangeKeepsTimezone(): void
    {
        $month = new \DateTimeImmutable('2026-12-31 23:00:00', new \DateTimeZone('Europe/Stockholm'));
        [$begin, $end] = $this->createService()->monthRange($month);

        self::assertSame('Europe/Stockholm', $begin->getTimezone()->getName());
        self::assertSame('2026-12-01', $begin->format('Y-m-d'));
        self::assertSame('2026-12-31 23:59:59', $end->format('Y-m-d H:i:s'));
    }

    public function testGroupByCustomerWithNoRows(): void
    {
        self::assertSame([], $this->createService()->groupByCustomer([]));
    }

    public function testGroupByCustomerGroupsProjectsAndSumsTotals(): void
    {
        $rows = [
            ['customerId' => 1, 'customerName' => 'Acme', 'projectId' => 10, 'projectName' => 'Alpha', 'entries' => '2', 'duration' => '3600', 'billable' => '3600', 'notExported' => '1800'],
            ['customerId' => 1, 'customerName' => 'Acme', 'projectId' => 11, 'projectName' => 'Beta', 'entries' => '1', 'duration' => '600', 'billable' => '0', 'notExported' => '600'],
            ['customerId' => 2, 'customerName' => 'Zeta', 'projectId' => 20, 'projectName' => 'Gamma', 'entries' => '4', 'duration' => null, 'billable' => null, 'notExported' => null],
        ];

        $result = $this->createService()->groupByCustomer($rows);

        self::assertCount(2, $result);

        self::assertSame(1, $result[0]['id']);
        self::assertSame('Acme', $result[0]['name']);
        self::assertSame(3, $result[0]['entries']);
        self::assertSame(4200, $result[0]['duration']);
        self::assertSame(3600, $result[0]['billable']);
        self::assertSame(2400, $result[0]['notExported']);
        self::assertCount(2, $result[0]['projects']);
        self::assertSame('Alpha', $result[0]['projects'][0]['name']);
        self::assertSame(3600, $result[0]['projects'][0]['duration']);
        self::assertSame('Beta', $result[0]['projects'][1]['name']);

        self::assertSame(2, $result[1]['id']);
        self::assertSame(4, $result[1]['entries']);
        self::assertSame(0, $result[1]['duration']);
        self::assertCount(1, $result[1]['projects']);
    }
}
