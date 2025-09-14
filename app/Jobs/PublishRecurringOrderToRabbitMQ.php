<?php
namespace App\Jobs;

use App\Enums\MessagePriority;
use App\Enums\MessageQueue;
use App\Models\OrderContent;
use App\Services\RabbitMQService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Keepsuit\LaravelOpenTelemetry\Facades\Tracer;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;

class PublishRecurringOrderToRabbitMQ implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries   = 5;
    public $timeout = 30;
    public $backoff = 3;

    protected $order;

    public function __construct($order)
    {
        $this->order = $order;
    }

    public function handle()
    {
        $priority = match ($this->order['meta']['priority'] ?? null) {
            'low'      => MessagePriority::Low->value,
            'high'     => MessagePriority::High->value,
            'critical' => MessagePriority::Critical->value,
            default    => MessagePriority::Normal->value,
        };

        $rabbit = new RabbitMQService([
            'queue' => MessageQueue::OrderProcess->value,
            'args'  => [
                'x-dead-letter-exchange' => MessageQueue::DLXOrderFailed->value,
            ],
        ]);

        $traceId = Tracer::traceId();
        $msg     = new AMQPMessage(
            body: json_encode($this->order),
            properties: [
                'content_type'        => 'application/json',
                'delivery_mode'       => AMQPMessage::DELIVERY_MODE_PERSISTENT,
                'priority'            => $priority,
                'application_headers' => new AMQPTable([
                    'traceid' => $traceId,
                ])]
        );
        $rabbit->publishWithConfirm($msg, function ($msg) {
            logger()->info("Message published: {$msg->body}");

            $order = OrderContent::find($this->order['id']);
            if ($order) {
                $order->status = 'processing';
                $order->save();
            }
        }, function ($msg) {
            logger()->error("Failed to publish: {$msg->body}");
            throw new \RuntimeException('Failed to publish message to RabbitMQ');
        });
        $rabbit->close();
    }

    public function failed( ? \Throwable $exception) : void
    {
        $order = OrderContent::find($this->order['id']);
        if ($order) {
            $order->status = 'failed';
            $order->save();
        }

        logger()->error("Job failed for order #{$this->order['id']}: {$exception->getMessage()}");
    }

}
