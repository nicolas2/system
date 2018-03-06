<?php

namespace Nova\Notification;

use Nova\Bus\DispatcherInterface as Bus;
use Nova\Database\ORM\Collection as ModelCollection;
use Nova\Events\Dispatcher;
use Nova\Foundation\Application;
use Nova\Queue\ShouldQueueInterface;
use Nova\Support\Collection;
use Nova\Support\Manager;

use Nova\Notification\Channels\BroadcastChannel;
use Nova\Notification\Channels\DatabaseChannel;
use Nova\Notification\Channels\MailChannel;
use Nova\Notification\Events\NotificationSending;
use Nova\Notification\Events\NotificationSent;
use Nova\Notification\DispatcherInterface;
use Nova\Notification\SendQueuedNotifications;

use Ramsey\Uuid\Uuid;

use InvalidArgumentException;


class ChannelManager extends Manager implements DispatcherInterface
{
    /**
     * The events dispatcher instance.
     *
     * @var \Nova\Events\Dispatcher
     */
    protected $events;

    /**
     * The default channels used to deliver messages.
     *
     * @var array
     */
    protected $defaultChannel = 'mail';


    /**
     * Create a new manager instance.
     *
     * @param  \Nova\Foundation\Application  $app
     * @return void
     */
    public function __construct(Application $app, Dispatcher $events)
    {
        $this->app = $app;

        $this->events = $events;
    }

    /**
     * Send the given notification to the given notifiable entities.
     *
     * @param  \Nova\Support\Collection|array|mixed  $notifiables
     * @param  mixed  $notification
     * @return void
     */
    public function send($notifiables, $notification)
    {
        $notifiables = $this->formatNotifiables($notifiables);

        if ($notification instanceof ShouldQueueInterface) {
            return $this->queueNotification($notifiables, $notification);
        }

        return $this->sendNow($notifiables, $notification);
    }

    /**
     * Send the given notification to the given notifiable entities.
     *
     * @param  \Nova\Support\Collection|array|mixed  $notifiables
     * @param  mixed  $notification
     * @param  array|null  $channels
     * @return void
     */
    public function sendNow($notifiables, $notification, array $channels = null)
    {
        $notifiables = $this->formatNotifiables($notifiables);

        $original = clone $notification;

        foreach ($notifiables as $notifiable) {
            $notificationId = Uuid::uuid4()->toString();

            $channels = $channels ?: $notification->via($notifiable);

            if (empty($channels)) {
                continue;
            }

            foreach ($channels as $channel) {
                $notification = clone $original;

                if (is_null($notification->id)) {
                    $notification->id = $notificationId;
                }

                if (! $this->shouldSendNotification($notifiable, $notification, $channel)) {
                    continue;
                }

                $response = $this->driver($channel)->send($notifiable, $notification);

                $this->events->fire(
                    new NotificationSent($notifiable, $notification, $channel, $response)
                );
            }
        }
    }

    /**
     * Determines if the notification can be sent.
     *
     * @param  mixed  $notifiable
     * @param  mixed  $notification
     * @param  string  $channel
     * @return bool
     */
    protected function shouldSendNotification($notifiable, $notification, $channel)
    {
        $result = $this->events->until(
            new NotificationSending($notifiable, $notification, $channel)
        );

        return $result !== false;
    }

    /**
     * Queue the given notification instances.
     *
     * @param  mixed  $notifiables
     * @param  array  $notification
     * @return void
     */
    protected function queueNotification($notifiables, $notification)
    {
        $commandBus = $this->app->make(Bus::class);

        //
        $notifiables = $this->formatNotifiables($notifiables);

        $original = clone $notification;

        foreach ($notifiables as $notifiable) {
            $notificationId = Uuid::uuid4()->toString();

            foreach ($notification->via($notifiable) as $channel) {
                $notification = clone $original;

                $notification->id = $notificationId;

                //
                $notifiable = $this->formatNotifiables($notifiable);

                $job = with(new SendQueuedNotifications($notifiable, $notification, array($channel)))
                    ->onConnection($notification->connection)
                    ->onQueue($notification->queue)
                    ->delay($notification->delay);

                $commandBus->dispatch($job);
            }
        }
    }

    /**
     * Format the notifiables into a Collection / array if necessary.
     *
     * @param  mixed  $notifiables
     * @return ModelCollection|array
     */
    protected function formatNotifiables($notifiables)
    {
        if ((! $notifiables instanceof Collection) && ! is_array($notifiables)) {
            $items = array($notifiables);

            if ($notifiables instanceof Model) {
                return new ModelCollection($items);
            }

            return $items;
        }

        return $notifiables;
    }

    /**
     * Get a channel instance.
     *
     * @param  string|null  $name
     * @return mixed
     */
    public function channel($name = null)
    {
        return $this->driver($name);
    }

    /**
     * Create an instance of the database driver.
     *
     * @return \Nova\Notification\Channels\DatabaseChannel
     */
    protected function createDatabaseDriver()
    {
        return $this->app->make(DatabaseChannel::class);
    }

    /**
     * Create an instance of the broadcast driver.
     *
     * @return \Nova\Notification\Channels\BroadcastChannel
     */
    protected function createBroadcastDriver()
    {
        return $this->app->make(BroadcastChannel::class);
    }

    /**
     * Create an instance of the mail driver.
     *
     * @return \Nova\Notification\Channels\MailChannel
     */
    protected function createMailDriver()
    {
        return $this->app->make(MailChannel::class);
    }

    /**
     * Create a new driver instance.
     *
     * @param  string  $driver
     * @return mixed
     *
     * @throws \InvalidArgumentException
     */
    protected function createDriver($driver)
    {
        try {
            return parent::createDriver($driver);
        }
        catch (InvalidArgumentException $e) {
            if (class_exists($driver)) {
                return $this->app->make($driver);
            }

            throw $e;
        }
    }

    /**
     * Get the default channel driver name.
     *
     * @return string
     */
    public function getDefaultDriver()
    {
        return $this->defaultChannel;
    }

    /**
     * Get the default channel driver name.
     *
     * @return string
     */
    public function deliversVia()
    {
        return $this->defaultChannel;
    }

    /**
     * Set the default channel driver name.
     *
     * @param  string  $channel
     * @return void
     */
    public function deliverVia($channel)
    {
        $this->defaultChannel = $channel;
    }
}
