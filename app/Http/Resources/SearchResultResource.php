<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SearchResultResource extends JsonResource
{
    private ?array $timeline = null;
    private ?array $bounds = null;

    public function setTimeline(?array $timeline): static
    {
        $this->timeline = $timeline;
        return $this;
    }

    public function setBounds(?array $bounds): static
    {
        $this->bounds = $bounds;
        return $this;
    }

    public function toArray(Request $request): array
    {
        return [
            'data'     => $this->collection,
            'timeline' => $this->timeline,
            'bounds'   => $this->bounds,
        ];
    }
}
