<?php

namespace Nawasara\Registry\Search;

use Nawasara\Registry\Models\Asset;
use Nawasara\Search\Contracts\SearchProvider;

class AssetSearchProvider implements SearchProvider
{
    public function key(): string
    {
        return 'asset';
    }

    public function label(): string
    {
        return 'Aset';
    }

    public function permission(): ?string
    {
        return 'registry.asset.view';
    }

    public function search(string $term, int $limit): array
    {
        return Asset::query()
            ->with('opd:id,name')
            ->search($term)
            ->orderBy('identifier')
            ->limit($limit)
            ->get()
            ->map(fn (Asset $asset) => [
                'label' => $asset->identifier,
                'sublabel' => trim(($asset->type ? ucfirst($asset->type).' · ' : '').($asset->opd->name ?? ''), ' ·'),
                'url' => url('nawasara-registry/assets?search='.urlencode($term)),
            ])
            ->all();
    }
}
