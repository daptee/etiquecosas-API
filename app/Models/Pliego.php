<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Plancha grande de imprenta dividida en una grilla de lugares, cada uno con
 * una hoja de un PDF ya generado de una venta. Ver PLIEGOS.md.
 */
class Pliego extends Model
{
    use SoftDeletes;

    public const STATUS_BORRADOR = 'borrador';
    public const STATUS_FINALIZADO = 'finalizado';

    protected $fillable = [
        'name',
        'sheet_size_id',
        'width_cm',
        'height_cm',
        'columns',
        'rows',
        'margin_mm',
        'gap_mm',
        'cut_lines',
        'status',
        'finalized_at',
        'finalized_by',
        'created_by',
    ];

    protected $casts = [
        'width_cm' => 'float',
        'height_cm' => 'float',
        'columns' => 'integer',
        'rows' => 'integer',
        'margin_mm' => 'float',
        'gap_mm' => 'float',
        'finalized_at' => 'datetime',
        'cut_lines' => 'array',
    ];

    public function slots()
    {
        return $this->hasMany(PliegoSlot::class)->orderBy('position');
    }

    public function sheetSize()
    {
        return $this->belongsTo(PdfSheetSize::class, 'sheet_size_id');
    }

    /** Quien creó el pliego (created_by). Mismo nombre que en diseños (ProductPdfDesign::user). */
    public function user()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function finalizer()
    {
        return $this->belongsTo(User::class, 'finalized_by');
    }

    public function totalSlots(): int
    {
        return $this->columns * $this->rows;
    }

    /**
     * Geometría de cada lugar en mm (mismo cálculo que el front:
     * slotGeom() del prototipo), en orden izq→der, arriba→abajo.
     *
     * @return array{slot_w: float, slot_h: float, slots: array<int, array{x: float, y: float, w: float, h: float}>}
     */
    public function slotGeometry(): array
    {
        $w = $this->width_cm * 10;
        $h = $this->height_cm * 10;
        $m = (float) $this->margin_mm;
        $g = (float) $this->gap_mm;

        $sw = max(1, ($w - 2 * $m - ($this->columns - 1) * $g) / $this->columns);
        $sh = max(1, ($h - 2 * $m - ($this->rows - 1) * $g) / $this->rows);

        $slots = [];
        for ($r = 0; $r < $this->rows; $r++) {
            for ($c = 0; $c < $this->columns; $c++) {
                $slots[] = ['x' => $m + $c * ($sw + $g), 'y' => $m + $r * ($sh + $g), 'w' => $sw, 'h' => $sh];
            }
        }

        return ['slot_w' => $sw, 'slot_h' => $sh, 'slots' => $slots];
    }
}
