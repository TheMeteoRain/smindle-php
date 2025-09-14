<?php
namespace App\Http\Controllers;

use App\Http\Requests\StoreOrderRequest;
use App\Jobs\PublishRecurringOrderToRabbitMQ;
use App\Models\Order;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreOrderRequest $request)
    {
        $payload = $request->validated();

        $order = DB::transaction(function () use ($payload) {
            $order = Order::create();

            $order->client()->create([
                'identity'      => $payload['client']['identity'],
                'contact_point' => $payload['client']['contact_point'],
            ]);

            $order->contents()->createMany(
                collect($payload['contents'])->map(function ($item) {
                    $kind   = $item['kind'] ?? 'single';
                    $status = $kind === 'single' ? 'completed' : 'received';

                    return [
                        'label'  => $item['label'],
                        'kind'   => $kind,
                        'cost'   => $item['cost'],
                        'status' => $status,
                        'meta'   => $item['meta'] ?? [],
                    ];
                })->toArray()
            );

            return $order;
        });

        $recurringContents = $order->contents->where('kind', 'recurring');
        foreach ($recurringContents as $item) {
            PublishRecurringOrderToRabbitMQ::dispatch(
                $item->toArray()
            );
        }

        return response()->json([
            'message' => 'Order stored successfully',
            'order'   => $order->load('client', 'contents'),
        ], 201);
    }
}
