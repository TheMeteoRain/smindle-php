<?php
namespace App\Enums;

enum MessagePriority: int {
    case Low      = 1;
    case Normal   = 2;
    case High     = 3;
    case Critical = 4;
    case Max      = 5;
}
