<?php
namespace App\Services;

use App\Enums\MessagePriority;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Exception\AMQPConnectionClosedException;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;

class RabbitMQService
{
    protected ?AMQPStreamConnection $connection = null;
    protected ?AMQPChannel $channel             = null;

    protected array $options = [
        'queue'       => null,
        'passive'     => false,
        'durable'     => true,
        'exclusive'   => false,
        'auto_delete' => false,
        'no_wait'     => false,
        'args'        => [
            'x-max-priority' => MessagePriority::Max->value,
        ],
    ];

    public function __construct(array $overrides = [])
    {
        $config        = config('rabbitmq');
        $this->options = collect($this->options)->replaceRecursive($overrides)->toArray();

        // if (is_null($options['queue'])) {
        //     throw new \InvalidArgumentException('A queue name must be specified in the constructor overrides.');
        // }

        $this->connect();
    }

    protected function connect(): void
    {
        $config = config('rabbitmq');

        logger()->info("Connecting to RabbitMQ... " . $config['host']);
        $this->connection = new AMQPStreamConnection(
            $config['host'],
            $config['port'],
            $config['user'],
            $config['password']
        );

        $this->channel = $this->connection->channel();

        $this->channel->queue_declare(
            $this->options['queue'],
            $this->options['passive'],
            $this->options['durable'],
            $this->options['exclusive'],
            $this->options['auto_delete'],
            $this->options['no_wait'],
            new AMQPTable($this->options['args'])
        );
    }

    public function publishWithConfirm(AMQPMessage $message, callable $successCallback, callable $rejectCallback): void
    {
        try {
            if (! $this->connection->isConnected()) {
                logger()->warning("RabbitMQ connection lost, reconnecting...");
                $this->connect();
            }

            $this->channel->confirm_select();

            $this->channel->set_ack_handler(function () use ($message, $successCallback) {
                $successCallback($message);
            });

            $this->channel->set_nack_handler(function () use ($message, $rejectCallback) {
                $rejectCallback($message);
            });

            $this->channel->basic_publish(msg: $message, exchange: '', routing_key: $this->options['queue']);

            $this->channel->wait_for_pending_acks_returns(5.0);
        } catch (\PhpAmqpLib\Exception\AMQPTimeoutException $e) {
            logger()->warning("Publisher confirm timeout for message: {$message->body}");
            $rejectCallback($message);
        } catch (AMQPConnectionClosedException $e) {
            logger()->error("RabbitMQ connection closed: {$e->getMessage()}");
            $rejectCallback($message);
        } catch (\Exception $e) {
            logger()->error("Unexpected RabbitMQ error: {$e->getMessage()}");
            $rejectCallback($message);
        }
    }

    public function channel(): AMQPChannel
    {
        return $this->channel;
    }

    public function close(): void
    {
        if ($this->channel && $this->channel->is_open()) {
            $this->channel->close();
        }

        if ($this->connection && $this->connection->isConnected()) {
            $this->connection->close();
        }
    }
}
