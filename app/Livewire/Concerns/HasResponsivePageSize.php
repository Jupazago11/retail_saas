<?php

namespace App\Livewire\Concerns;

/**
 * @property int $perPage Declared by the consuming component (default varies per page).
 */
trait HasResponsivePageSize
{
    public function setPerPage(int $perPage): void
    {
        $clamped = max(4, min(50, $perPage));

        if ($clamped === $this->perPage) {
            return;
        }

        $this->perPage = $clamped;
        $this->resetPage();
    }
}
