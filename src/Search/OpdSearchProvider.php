<?php

namespace Nawasara\Registry\Search;

use Nawasara\Registry\Models\Opd;
use Nawasara\Search\Contracts\SearchProvider;

class OpdSearchProvider implements SearchProvider
{
    public function key(): string
    {
        return 'opd';
    }

    public function label(): string
    {
        return 'OPD';
    }

    public function permission(): ?string
    {
        return 'registry.opd.view';
    }

    public function search(string $term, int $limit): array
    {
        return Opd::query()
            ->search($term)
            ->orderBy('name')
            ->limit($limit)
            ->get(['id', 'code', 'name'])
            ->map(fn (Opd $opd) => [
                'label' => $opd->name,
                'sublabel' => $opd->code,
                // Deep-link to the OPD list pre-filtered to this record.
                'url' => url('nawasara-registry/opd?search='.urlencode($term)),
            ])
            ->all();
    }
}
