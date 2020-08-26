<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RefBook extends Model
{
    public const REF_BOOK_REALTY_TYPE = 'realty_type';
    public const REF_BOOK_DEAL_TYPE = 'deal_type';
    public const REF_BOOK_EXTERNAL_ADS_SOURCE = 'external_ads_source';

    protected $guarded = ['id'];
    
    public function values()
    {
        return $this->hasMany('App\Models\RefBooksValue', 'rb_id');
    }
    
    public function groups()
    {
        return $this->hasMany('App\Models\RefBooksGroup', 'ref_books_id');
    }
}
