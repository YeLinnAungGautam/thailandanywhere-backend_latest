<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class PostResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {

        $imagesArr = [];

        if ($this->images) {
            $postImage = json_decode($this->images);
            foreach ($postImage as $image) {
                array_push($imagesArr, Storage::url('images/' . $image));
            }
        }

        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'title' => $this->title,
            'content' => $this->content,
            'views' => $this->views,
            'featured_image' => Storage::url('images/' . $this->featured_image),
            'images' => $imagesArr,
            'category' => new CategoryResource($this->category),
            'tags' => json_decode($this->tags, true),
            'comments' => CommentResource::collection($this->comments),
            'reacts' => count($this->reacts),
            'published_by' => $this->publishedBy,
            'published_at' => $this->created_at->format('Y-m-d H:i:s'),
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at->format('Y-m-d H:i:s'),
        ];
    }
}
