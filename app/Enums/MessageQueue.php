<?php
namespace App\Enums;

enum MessageQueue: string {
    case OrderProcess   = 'orders_process';
    case OrderResult    = 'orders_result';
    case DLXOrderFailed = 'dlx_orders_failed';
}
