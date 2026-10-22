<?php

namespace App\Support;

use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\JobReleasedAfterException;
use Illuminate\Support\Facades\Event;
use Sentry\Event as SentryEvent;
use Sentry\EventHint;
use Sentry\State\Scope;
use Throwable;
use WeakMap;

use function Sentry\configureScope;

/**
 * Remontée Sentry des jobs en queue : seul l'échec définitif d'un job est envoyé.
 * Une exception suivie d'une nouvelle tentative (job relâché dans la queue) est ignorée,
 * pour ne pas alerter sur une erreur passagère que le retry rattrape.
 */
final class SentryQueueReporting
{
    /** @var WeakMap<Throwable, true>|null */
    private static ?WeakMap $retried = null;

    public static function register(): void
    {
        // Le scope Sentry du job reste actif jusqu'au report de l'exception : les tags
        // permettent de filtrer les alertes par job ou par queue.
        Event::listen(JobProcessing::class, static function (JobProcessing $event): void {
            configureScope(static function (Scope $scope) use ($event): void {
                $scope->setTags([
                    'queue.job' => $event->job->resolveName(),
                    'queue.name' => (string) $event->job->getQueue(),
                    'queue.attempt' => (string) $event->job->attempts(),
                ]);
            });
        });

        Event::listen(JobReleasedAfterException::class, static function (JobReleasedAfterException $event): void {
            if ($event->exception !== null) {
                self::retried()[$event->exception] = true;
            }
        });
    }

    /**
     * Callback `before_send` de config/sentry.php.
     */
    public static function beforeSend(SentryEvent $event, ?EventHint $hint): ?SentryEvent
    {
        $exception = $hint?->exception;

        if ($exception !== null && isset(self::retried()[$exception])) {
            return null;
        }

        return $event;
    }

    /** @return WeakMap<Throwable, true> */
    private static function retried(): WeakMap
    {
        return self::$retried ??= new WeakMap;
    }
}
