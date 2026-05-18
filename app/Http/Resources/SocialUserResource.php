<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SocialUserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nickname' => $this->nickname,
            'avatar_slug' => $this->avatar?->slug,
            'avatar_image_file' => $this->avatar?->image_file,
        ];
    }
}
