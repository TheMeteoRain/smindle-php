<?php
namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'client'   => [
                'identity'      => $this->client_identity,
                'contact_point' => $this->client_contact_point,
            ],
            'contents' => $this->contents->map(function ($content) {
                return [
                    'label' => $content->label,
                    'kind'  => $content->kind,
                    'cost'  => $content->cost,
                    'meta'  => $content->meta ?? [],
                ];
            }),
        ];
    }
}
