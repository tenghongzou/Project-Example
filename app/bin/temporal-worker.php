<?php

declare(strict_types=1);

use App\Kernel;
use App\Orchestration\Infrastructure\Temporal\EventReminderWorkflow;
use App\Orchestration\Infrastructure\Temporal\ReminderActivity;
use Symfony\Component\Dotenv\Dotenv;
use Temporal\WorkerFactory;

require dirname(__DIR__).'/vendor/autoload.php';

// Temporal worker（RoadRunner 宿主）：boot Symfony kernel 讓 activity 拿得到 DI 服務
(new Dotenv())->bootEnv(dirname(__DIR__).'/.env');

$env = $_SERVER['APP_ENV'] ?? 'dev';
$debug = filter_var($_SERVER['APP_DEBUG'] ?? ('prod' !== $env), FILTER_VALIDATE_BOOL);
$kernel = new Kernel($env, $debug);
$kernel->boot();
$container = $kernel->getContainer();

$factory = WorkerFactory::create();
$worker = $factory->newWorker('default');

$worker->registerWorkflowTypes(EventReminderWorkflow::class);
$worker->registerActivity(
    ReminderActivity::class,
    static fn (): ReminderActivity => $container->get(ReminderActivity::class),
);

$factory->run();
