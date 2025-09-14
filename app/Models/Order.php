<?php
namespace App\Models;

use App\Models\OrderClient;
use App\Models\OrderContent;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = [];

    public function client()
    {
        return $this->hasOne(OrderClient::class);
    }

    public function contents()
    {
        return $this->hasMany(OrderContent::class);
    }
}
