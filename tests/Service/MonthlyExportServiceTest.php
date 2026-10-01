<?php

declare(strict_types=1);

namespace KimaiPlugin\FortnoxBundle\Tests\Service;

use App\Repository\ProjectRepository;
use App\Repository\TimesheetRepository;
use Doctrine\ORM\EntityManagerInterface;
use KimaiPlugin\FortnoxBundle\Service\MonthlyExportService;
use KimaiPlugin\FortnoxBundle\Service\PdfGenerator;
use KimaiPlugin\FortnoxBundle\Service\TimeReportService;
use PHPUnit\Framework\TestCase;
use Twig\Environment;

class MonthlyExportServiceTest extends TestCase
{
    private function createService(): MonthlyExportService
    {
        return new MonthlyExportService(
            new TimeReportService($this->createMock(TimesheetRepository::class), $this->createMock(EntityManagerInterface::class)),
            new PdfGenerator($this->createMock(Environment::class)),
            $this->createMock(ProjectRepository::class),
        );
    }

    public function testBuildZipContainsAllFiles(): void
    {
        $path = $this->createService()->buildZip([
            ['name' => 'a_b_2026-10-01_2026-10-31.pdf', 'content' => 'one', 'id' => 1],
            ['name' => 'c_d_2026-10-01_2026-10-31.pdf', 'content' => 'two', 'id' => 2],
        ]);

        try {
            $zip = new \ZipArchive();
            self::assertTrue($zip->open($path));
            self::assertSame(2, $zip->numFiles);
            self::assertSame('one', $zip->getFromName('a_b_2026-10-01_2026-10-31.pdf'));
            self::assertSame('two', $zip->getFromName('c_d_2026-10-01_2026-10-31.pdf'));
            $zip->close();
        } finally {
            unlink($path);
        }
    }

    public function testBuildZipKeepsFilesWithIdenticalNames(): void
    {
        $path = $this->createService()->buildZip([
            ['name' => 'same.pdf', 'content' => 'one', 'id' => 7],
            ['name' => 'same.pdf', 'content' => 'two', 'id' => 8],
        ]);

        try {
            $zip = new \ZipArchive();
            self::assertTrue($zip->open($path));
            self::assertSame(2, $zip->numFiles);
            self::assertSame('one', $zip->getFromName('same.pdf'));
            self::assertSame('two', $zip->getFromName('same_8.pdf'));
            $zip->close();
        } finally {
            unlink($path);
        }
    }
}
