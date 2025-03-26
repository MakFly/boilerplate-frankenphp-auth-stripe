<?php

declare(strict_types=1);

namespace App\Service\Notifier;

use App\Interface\NotifierInterface;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;

final readonly class NotifierFactory
{
    /**
     * @param iterable<NotifierInterface> $notifiers
     */
    public function __construct(
        #[TaggedIterator('app.notifier')]
        private iterable $notifiers
    ) {
    }

    public function create(string $type): NotifierInterface
    {
        foreach ($this->notifiers as $notifier) {
            if ($this->getNotifierType($notifier) === $type) {
                return $notifier;
            }
        }
        
        throw new \InvalidArgumentException(sprintf('Notificateur "%s" non trouvé', $type));
    }

    private function getNotifierType(NotifierInterface $notifier): string
    {
        $className = get_class($notifier);
        $shortName = (new \ReflectionClass($className))->getShortName();
        return strtolower(str_replace('Notifier', '', $shortName));
    }
}