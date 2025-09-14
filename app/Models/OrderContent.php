<?php
namespace App\Models;

use App\Models\Order;
use Illuminate\Database\Eloquent\Model;

class OrderContent extends Model
{
    protected $fillable = ['order_id', 'label', 'kind', 'cost', 'status', 'meta'];
    protected $casts    = ['meta' => 'array'];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
