<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExpenseCategory extends Model
{
    protected $table = 'expense_categories';

    protected $guarded = ['id'];

    protected $casts = [
        'is_fixed' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * Relación con los egresos asociados a esta categoría.
     */
    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class, 'expense_category_id');
    }
}