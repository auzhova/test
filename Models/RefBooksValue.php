<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RefBooksValue extends Model
{
    protected $guarded = ['id'];

    /**
     * @return BelongsTo
     */
    public function rb(): BelongsTo {
        return $this->belongsTo(RefBook::class, 'rb_id', 'id');
    }

    public function scopeOfRb($query, $sRefBook){
        $nRbId = RefBook::whereSysname($sRefBook)->value('id');
        return $query->whereRbId($nRbId);
    }

    /**
     * Получает значения по sysname справочника
     * @param array $sysnames
     * @return Collection
     */
    public static function getRefBookValues(array $sysnames): Collection {
        return self::query()->join('ref_books', 'ref_books.id', '=', 'ref_books_values.rb_id')
            ->whereIn('ref_books.sysname', $sysnames)->get(['ref_books.*', 'ref_books_values.*', 'ref_books_values.id as id', 'ref_books.sysname as sysname']);
    }
}
