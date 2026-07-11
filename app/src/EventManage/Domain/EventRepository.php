<?php

declare(strict_types=1);

namespace App\EventManage\Domain;

interface EventRepository
{
    public function save(Event $event): void;

    /**
     * @throws EventNotFound
     */
    public function get(string $id): Event;

    /**
     * @return list<Event> 依 createdAt 由新到舊排序
     */
    public function list(): array;
}
