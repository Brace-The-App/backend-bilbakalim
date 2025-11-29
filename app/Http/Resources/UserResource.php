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
        // Elmas bakiyesini al (eğer yoksa 0)
        $diamondBalance = 0;
        if ($this->relationLoaded('diamond')) {
            $diamondBalance = $this->diamond ? $this->diamond->balance : 0;
        } else {
            $diamond = $this->diamond;
            $diamondBalance = $diamond ? $diamond->balance : 0;
        }

        return [
            'id' => $this->id,
            'name' => $this->name,
            'coins' => $this->coins,
            'diamonds' => $diamondBalance,
            'role_id' => $this->role_id,
            'phone' => $this->phone,
            'email' => $this->email,
            'is_premium' => $this->is_premium,
            'account_status' => $this->account_status,
            'created_at' => $this->created_at,
            'profile_image' => 'https://bilbakalim.online/storage/' . $this->profile_image,
            'profile_completed' => ($this->name && $this->email && $this->phone && $this->password) ? true : false,
        ];
    }
}
