import pika
import time
import os

RABBITMQ_HOST = os.getenv("RABBITMQ_HOST", "rabbitmq")
RABBITMQ_USER = os.getenv("RABBITMQ_USER", "user")
RABBITMQ_PASS = os.getenv("RABBITMQ_PASS", "pass")
CREDENTIALS = pika.PlainCredentials(RABBITMQ_USER, RABBITMQ_PASS)

DLQ_NAME = "dlq_orders_failed"
PROCESS_QUEUE = "orders_process"

def promote_message():
    connection = pika.BlockingConnection(pika.ConnectionParameters(
        host=RABBITMQ_HOST,
        credentials=CREDENTIALS
    ))
    channel = connection.channel()

    print(f"[*] Starting promotion process from {DLQ_NAME} to {PROCESS_QUEUE}.")

    while True:
        method_frame, properties, body = channel.basic_get(queue=DLQ_NAME, auto_ack=False)

        if method_frame:
            print(f"[!] Found a message in {DLQ_NAME}. Promoting...")

            channel.basic_publish(
                exchange='',
                routing_key=PROCESS_QUEUE,
                body=body,
                properties=properties
            )
            print(f"[✓] Published message to {PROCESS_QUEUE}.")

            channel.basic_ack(delivery_tag=method_frame.delivery_tag)
            print("[✓] Acknowledged message in DLQ.")

            time.sleep(0.1)
        else:
            print(f"[!] No messages in {DLQ_NAME}.")
            break

    connection.close()

if __name__ == "__main__":
    promote_message()
