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

        // Joker sayılarını al
        $fiftyFiftyJokers = $this->fifty_fifty_jokers ?? 0;
        $doubleAnswerJokers = $this->double_answer_jokers ?? 0;
        $hintJokers = $this->hint_jokers ?? 0;
        $totalJokers = $fiftyFiftyJokers + $doubleAnswerJokers + $hintJokers;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'coins' => $this->coins,
            'duel_earned_coins' => (int) ($this->duel_earned_coins ?? 0),
            'gift_claim_min_coins' => \App\Services\FinanceService::giftClaimMinCoins(),
            'gift_claim_min_games' => (int) config('app.gift_claim_min_games', 3),
            'gift_claim_daily_limit' => (int) config('app.gift_claim_daily_limit', 1),
            'diamonds' => $diamondBalance,
            'role_id' => $this->role_id,
            'phone' => $this->phone,
            'email' => $this->email,
            'is_premium' => $this->is_premium,
            'account_status' => $this->account_status,
            'platform' => $this->platform,
            'created_at' => $this->created_at,
            'profile_image' => $this->profile_image
                ? \App\Models\User::toStorageUrl((string) $this->profile_image)
                : null,
            'profile_completed' => ($this->name && $this->email && $this->phone && $this->password) ? true : false,
            'jokers' => [
                'fifty_fifty' => $fiftyFiftyJokers,
                'double_answer' => $doubleAnswerJokers,
                'hint' => $hintJokers,
                'total' => $totalJokers
            ],
            'referral_code' => $this->referral_code,
            'has_used_referral' => $this->has_used_referral ?? false,
        ];
    }
}
