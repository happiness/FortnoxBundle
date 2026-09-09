<?php

declare(strict_types=1);

namespace KimaiPlugin\FortnoxBundle\Form\Model;

use App\Entity\Customer;
use App\Entity\Project;
use Symfony\Component\Validator\Constraints as Assert;

final class TimeReportFilter
{
    #[Assert\NotNull]
    public ?Customer $customer = null;

    #[Assert\NotNull]
    public ?Project $project = null;

    #[Assert\NotNull]
    public ?\DateTime $begin = null;

    #[Assert\NotNull]
    public ?\DateTime $end = null;

    public bool $billableOnly = true;

    public bool $notExportedOnly = true;
}
