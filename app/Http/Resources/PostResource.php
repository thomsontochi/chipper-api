<?php

namespace App\Http\Resources;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class PostResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $imageUrl = null;

        if ($this->image_path) {
            /** @var FilesystemAdapter $disk */
            $disk = Storage::disk('public');
            $imageUrl = $disk->url($this->image_path);
        }

        return [
            'id' => $this->id,
            'title' => $this->title,
            'body' => $this->body,
            'image_url' => $imageUrl,
            'user' => new UserResource($this->user),
        ];
    }
}
