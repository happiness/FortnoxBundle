<?php

declare(strict_types=1);

namespace KimaiPlugin\FortnoxBundle\EventSubscriber;

use App\Event\ConfigureMainMenuEvent;
use App\Utils\MenuItemModel;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

final class MenuSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly AuthorizationCheckerInterface $security)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ConfigureMainMenuEvent::class => ['onMenuConfigure', 100],
        ];
    }

    public function onMenuConfigure(ConfigureMainMenuEvent $event): void
    {
        if (!$this->security->isGranted('IS_AUTHENTICATED_REMEMBERED')) {
            return;
        }

        if (!$this->security->isGranted('fortnox_export')) {
            return;
        }

        $event->getMenu()->addChild(
            new MenuItemModel('fortnox_export', 'fortnox.menu', 'fortnox_report', [], 'fas fa-file-pdf')
        );
    }
}
