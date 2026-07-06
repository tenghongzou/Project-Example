<?php

declare(strict_types=1);

namespace App\Demo\Presentation;

use App\Demo\Application\Message\PingMessage;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

final class PingController extends AbstractController
{
    #[Route('/ping', name: 'app_ping', methods: ['GET'])]
    public function __invoke(MessageBusInterface $bus): JsonResponse
    {
        $note = sprintf('ping at %s', date(DATE_ATOM));
        $bus->dispatch(new PingMessage($note));

        return $this->json(['queued' => true, 'note' => $note]);
    }
}
