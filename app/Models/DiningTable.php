<?php

namespace App\Models;

use App\Enums\RecordStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class DiningTable extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'branch_id',
        'name',
        'capacity',
        'status',
        'occupancy_status',
        'pos_x',
        'pos_y',
        'shape',
        'size',
        'height',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function frozenSales(): HasMany
    {
        return $this->hasMany(FrozenSale::class);
    }

    public function openFrozenSale(): ?FrozenSale
    {
        return $this->frozenSales()
            ->where('status', 'open')
            ->latest('id')
            ->first();
    }

    // Numeracion automatica de mesas: el negocio no permite huecos, asi que
    // las mesas activas de una sucursal son siempre exactamente 1..N sin
    // saltos (garantizado por renumberActiveTables(), que corre cada vez que
    // una mesa se archiva). Por eso "el siguiente numero" es simplemente
    // cuantas mesas activas hay mas uno — no hace falta buscar huecos ni
    // considerar archivadas.
    public static function nextNumberFor(int $companyId, int $branchId): string
    {
        $count = static::query()
            ->where('company_id', $companyId)
            ->where('branch_id', $branchId)
            ->where('status', RecordStatus::Active->value)
            ->count();

        return (string) ($count + 1);
    }

    // Reordena las mesas activas de una sucursal para que queden 1..N sin
    // huecos, preservando su orden relativo actual (por numero). Se llama
    // despues de archivar una mesa — si se borra la "3" de un grupo de 8, la
    // "4" pasa a ser "3", la "5" a "4", etc. Recorrer en orden ascendente
    // evita colisiones con el indice unico (company_id, branch_id, name):
    // el numero destino de cada mesa siempre ya quedo libre por el paso
    // anterior.
    public static function renumberActiveTables(int $companyId, int $branchId): void
    {
        static::query()
            ->where('company_id', $companyId)
            ->where('branch_id', $branchId)
            ->where('status', RecordStatus::Active->value)
            ->get()
            ->sortBy(fn (self $table) => is_numeric($table->name) ? (int) $table->name : PHP_INT_MAX)
            ->values()
            ->each(function (self $table, int $index) {
                $newName = (string) ($index + 1);

                if ($table->name !== $newName) {
                    $table->update(['name' => $newName]);
                }
            });
    }
}
