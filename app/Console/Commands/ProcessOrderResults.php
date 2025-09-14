<?php
namespace App\Console\Commands;

use App\Enums\MessageQueue;
use App\Models\OrderContent;
use App\Services\RabbitMQService;
use Illuminate\Console\Command;
use PhpAmqpLib\Message\AMQPMessage;

class ProcessOrderResults extends Command
{
    protected $signature          = 'orders:process-results';
    protected $description        = 'Consume orders_result queue and update orders';
    protected int $reconnectDelay = 10;

    public function handle()
    {
        while (true) {
            try {
                $rabbit  = new RabbitMQService(['queue' => MessageQueue::OrderResult->value]);
                $channel = $rabbit->channel();

                $callback = function (AMQPMessage $msg) {
                    $data    = json_decode($msg->body, true);
                    $headers = $msg->get_properties()['application_headers'] ?? null;

                    $traceId = null;
                    if ($headers) {
                        $table   = $headers->getNativeData(); // convert to plain PHP array
                        $traceId = $table['traceid'] ?? null;
                    }
                    logger()->info(message: "Processing result for content #{$data['id']} in order #{$data['order_id']} [traceid={$traceId}]");

                    $order = OrderContent::find($data['id']);
                    if ($order) {
                        $order->status  = 'completed';
                        $order->details = $data['details'];
                        $order->save();

                        logger()->info(message: "Completed processing content #{$data['id']} in order #{$data['order_id']} [traceid={$traceId}]");
                    }

                    $msg->ack();
                };

                $channel->basic_qos(prefetch_size: 0, prefetch_count: 1, a_global: null);
                $channel->basic_consume(
                    queue: MessageQueue::OrderResult->value,
                    consumer_tag: '',
                    no_local: false,
                    no_ack: false,
                    exclusive: false,
                    nowait: false,
                    callback: $callback
                );

                logger()->info(message: sprintf(
                    "Waiting for messages in %s queue...",
                    MessageQueue::OrderResult->value
                ));

                while ($channel->is_consuming()) {
                    $channel->wait();
                }

                $rabbit->close();
            } catch (\Exception $e) {
                logger()->error(message: "Unexpected error: {$e->getMessage()}. Reconnecting in {$this->reconnectDelay}s...");
                sleep($this->reconnectDelay);
            }
        }
    }
}
