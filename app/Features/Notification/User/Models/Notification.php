<?php

namespace App\Features\Notification\User\Models;

use App\Features\Auth\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    use HasFactory;

    protected $fillable = [
        'category',
        'title',
        'description',
        'read_at',
        'user_id',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public $timestamps = true;

    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
        ];
    }
}
