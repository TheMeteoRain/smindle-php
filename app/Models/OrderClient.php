<?php
namespace App\Models;

use App\Models\Order;
use Illuminate\Database\Eloquent\Model;

class OrderClient extends Model
{
    protected $fillable = ['order_id', 'identity', 'contact_point'];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
