<?php

declare(strict_types=1);

namespace App\Notification\Domain;

interface NotificationRepository
{
    public function save(Notification $notification): void;

    /**
     * @throws NotificationNotFound
     */
    public function get(string $id): Notification;

    /**
     * @return list<Notification> 依 createdAt 由新到舊排序
     */
    public function list(): array;
}
