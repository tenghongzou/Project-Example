<?php

declare(strict_types=1);

namespace App\Notification\Application;

use App\Notification\Application\Channel\NotificationChannel;
use App\Notification\Domain\Notification;
use App\Notification\Domain\NotificationRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

final readonly class ChannelNotifier implements Notifier
{
    /**
     * @param iterable<NotificationChannel> $channels
     */
    public function __construct(
        private NotificationRepository $notificationRepository,
        #[AutowireIterator('app.notification_channel')]
        private iterable $channels,
        private LoggerInterface $logger,
    ) {
    }

    public function notify(string $channel, string $subject, string $body, array $context = []): Notification
    {
        $notification = new Notification($channel, $subject, $body, $context);
        // 先以 pending 落地：投遞中途當機時留有紀錄可對帳，不會無聲蒸發
        $this->notificationRepository->save($notification);

        try {
            $this->resolveChannel($channel)->send($notification);
            $notification->markSent();
        } catch (\Throwable $e) {
            $notification->markFailed($e->getMessage());
            $this->logger->error('Notification delivery failed', [
                'notification_id' => $notification->getId(),
                'channel' => $channel,
                'error' => $e->getMessage(),
            ]);
        }

        $this->notificationRepository->save($notification);

        return $notification;
    }

    private function resolveChannel(string $channel): NotificationChannel
    {
        foreach ($this->channels as $candidate) {
            if ($candidate->supports($channel)) {
                return $candidate;
            }
        }

        throw NotificationDeliveryFailed::withReason(\sprintf('No notification channel supports "%s".', $channel));
    }
}
