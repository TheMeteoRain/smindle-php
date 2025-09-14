import pika
import json
import time
import random
import logging
import os

# Env
RABBITMQ_HOST = os.getenv("RABBITMQ_HOST", "rabbitmq")
RABBITMQ_USER = os.getenv("RABBITMQ_USER", "user")
RABBITMQ_PASS = os.getenv("RABBITMQ_PASS", "pass")
PROCESS_QUEUE = os.getenv("PROCESS_QUEUE", "orders_process")
RESULT_QUEUE = os.getenv("RESULT_QUEUE", "orders_result")
DLX_EXCHANGE = os.getenv("DLX_EXCHANGE", "dlx_orders_failed")
DLQ_NAME = os.getenv("DLQ_NAME", "dlq_orders_failed")
MAX_RETRIES = int(os.getenv("MAX_RETRIES", 5))
DELIVERY_MODE_PERSISTENT = int(os.getenv("DELIVERY_MODE_PERSISTENT", 2))
RECONNECT_DELAY_SECONDS = int(os.getenv("RECONNECT_DELAY_SECONDS", 10))
MAX_PRIORITY = int(os.getenv("MAX_PRIORITY", 5))

CREDENTIALS = pika.PlainCredentials(RABBITMQ_USER, RABBITMQ_PASS)

# Log
BASE_DIR = os.path.dirname(os.path.abspath(__file__))
LOG_DIR = os.path.join(BASE_DIR, "logs")
os.makedirs(LOG_DIR, exist_ok=True)

LOG_FILE = os.path.join(LOG_DIR, "app.log")

logger = logging.getLogger("worker")
logger.setLevel(logging.INFO)

file_handler = logging.FileHandler(LOG_FILE)
file_handler.setLevel(logging.INFO)

formatter = logging.Formatter(
    "%(asctime)s [%(levelname)s] %(name)s - %(message)s"
)
file_handler.setFormatter(formatter)
logger.addHandler(file_handler)

def process_order(data):
    """
    Simulate processing the order with third-party API.
    """
    # simulate API call
    time.sleep(random.randint(5, 30))
    # simulate API call failing
    if random.random() < 0.2:
        raise Exception("HTTP 500 (simulated error)")

    return {
        "id": data['id'],
        "order_id": data['order_id'],
        "status": "success",
        "details": {"processed_at": time.time()}
    }

def publish_result(channel, result, properties):
    """
    Publish the result to the results queue.
    """
    try:
        original_priority = properties.priority
        original_headers = getattr(properties, "headers", {}) or {}

        new_properties = pika.BasicProperties(
            delivery_mode=DELIVERY_MODE_PERSISTENT,
            priority=original_priority,
            headers=original_headers
        )
        channel.basic_publish(
            exchange='',
            routing_key=RESULT_QUEUE,
            body=json.dumps(result),
            properties=new_properties
        )
    except Exception as e:
        raise e

def callback(channel, method, properties, body):
    """
    Called for each channel message in the queue.
    """
    data = json.loads(body)
    traceId = properties.headers.get("traceid")
    logger.info(f"Processing content #{data['id']} for order #{data['order_id']} [traceid={traceId}]")

    result = None
    retry_count = 0
    while retry_count < MAX_RETRIES:
        try:
            result = process_order(data)
            break
        except Exception as e:
            retry_count = retry_count + 1
            logger.warning(f"API processing failed for content #{data['id']}: {e}.")

            if retry_count >= MAX_RETRIES:
                logger.error(f"Max API retries ({MAX_RETRIES}) exceeded. Moving to DLQ.")
                channel.basic_nack(delivery_tag=method.delivery_tag, requeue=False)
            else:
                logger.warning(f"Retrying API call (retry_count {retry_count}).")
                time.sleep(2 ** retry_count)

    if result is None:
        return

    try:
        publish_result(channel, result, properties)
        logger.info(f"Published result for content #{result['id']} in order #{result['order_id']}")
        channel.basic_ack(delivery_tag=method.delivery_tag)
    except:
        logger.error(f"Could not publish result for content #{data['id']} in order #{data['order_id']}. Moving to DLQ.")
        channel.basic_nack(delivery_tag=method.delivery_tag, requeue=False)

def main():
    while True:
        try:
            logger.info("Connecting to broker...")
            connection = pika.BlockingConnection(
                pika.ConnectionParameters(host=RABBITMQ_HOST, credentials=CREDENTIALS)
            )
            channel = connection.channel()

            # declare exchanges and queues
            channel.exchange_declare(exchange=DLX_EXCHANGE, exchange_type='fanout', durable=True)
            channel.queue_declare(queue=DLQ_NAME, durable=True)
            channel.queue_bind(exchange=DLX_EXCHANGE, queue=DLQ_NAME)
            channel.queue_declare(queue=PROCESS_QUEUE, durable=True, arguments={
                'x-max-priority': 5,
                'x-dead-letter-exchange': DLX_EXCHANGE
            })
            channel.queue_declare(queue=RESULT_QUEUE, durable=True, arguments={
                'x-max-priority': 5,
            })

            channel.basic_qos(prefetch_count=1)
            channel.basic_consume(queue=PROCESS_QUEUE, on_message_callback=callback)

            logger.info("Connected to broker. Waiting for messages.")
            channel.start_consuming()

        except pika.exceptions.AMQPConnectionError as e:
            logger.warning(f"Connection lost: {e}. Reconnectin in {RECONNECT_DELAY_SECONDS}s...")
            time.sleep(RECONNECT_DELAY_SECONDS)
        except Exception as e:
            logger.warning(f"Unexpected error: {e}. Reconnectin in {RECONNECT_DELAY_SECONDS}s...")
            time.sleep(RECONNECT_DELAY_SECONDS)

if __name__ == "__main__":
    main()
