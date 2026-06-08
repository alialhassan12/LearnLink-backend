<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['title','description','type','features','is_free','duration_days','price','status'])]
class Plan extends Model
{
    // relations
    public function subscriptions(){
        return $this->hasMany(Subscription::class);
    }

    // casts
    protected $casts=[
        'features'=>'array',
        'price' => 'decimal:2'
    ];
}
