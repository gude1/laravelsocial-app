<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'userid' => $this->userid,
            'name' => $this->name,
            'username' => $this->username,
            'gender' => $this->gender,
            'email' => $this->email,
            'phone' => $this->phone,
            'device_token' => $this->device_token,
            'token' => $this->token,
        ];
    }
}
