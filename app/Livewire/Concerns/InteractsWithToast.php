<?php

namespace App\Livewire\Concerns;

trait InteractsWithToast
{
    protected function toast(string $message, string $type = 'success', ?string $title = null, ?int $duration = null): void
    {
        $payload = array_filter([
            'message' => $message,
            'type' => $type,
            'title' => $title,
            'duration' => $duration,
        ], fn ($value) => $value !== null);

        $this->dispatch('toast', ...$payload);
    }

    protected function flashToast(string $message, string $type = 'success', ?string $title = null, ?int $duration = null): void
    {
        session()->flash('toast', array_filter([
            'message' => $message,
            'type' => $type,
            'title' => $title,
            'duration' => $duration,
        ], fn ($value) => $value !== null));
    }
}
