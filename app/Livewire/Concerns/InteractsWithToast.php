<?php

namespace App\Livewire\Concerns;

use Illuminate\Validation\ValidationException;

trait InteractsWithToast
{
    /**
     * Sobrescribe la validacion nativa de Livewire para que los errores se
     * avisen como notificacion (toast) en vez de debajo de cada input. El
     * bag real de errores no se toca (sigue igual de intacto para PHPUnit
     * via assertHasErrors/assertHasNoErrors y para cualquier logica interna
     * que lo consulte); lo unico nuevo es el toast adicional. La supresion
     * visual de los bloques @error/<x-input-error> en las vistas es aparte
     * (ver barrido de vistas), asi que aqui no hace falta tocar el bag.
     */
    public function validate($rules = null, $messages = [], $attributes = [])
    {
        try {
            return parent::validate($rules, $messages, $attributes);
        } catch (ValidationException $e) {
            $this->toast(
                collect($e->errors())->flatten()->unique()->implode("\n"),
                'error'
            );

            throw $e;
        }
    }

    /**
     * Mismo criterio que validate(): un error de negocio agregado a mano
     * (ej. tras capturar InvalidArgumentException de una Action) tambien
     * debe avisarse como notificacion, no solo quedar en el error bag.
     */
    public function addError($name, $message)
    {
        $this->toast($message, 'error');

        return parent::addError($name, $message);
    }

    protected function toast(
        string $message,
        string $type = 'success',
        ?string $title = null,
        ?int $duration = null,
        ?string $actionUrl = null,
        ?string $actionLabel = null,
    ): void {
        $payload = array_filter([
            'message' => $message,
            'type' => $type,
            'title' => $title,
            'duration' => $duration,
            'actionUrl' => $actionUrl,
            'actionLabel' => $actionLabel,
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
