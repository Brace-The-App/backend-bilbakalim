<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'coins' => $this->coins,
            'role_id' => $this->role_id,
            'phone' => $this->phone,
            'email' => $this->email,
            'is_premium' => $this->is_premium,
            'account_status' => $this->account_status,
            'created_at' => $this->created_at,
        ];
    }
}
