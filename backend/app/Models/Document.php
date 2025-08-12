<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Document extends Model
{
    protected $fillable = [
        'title',
        'content', 
        'etat',        // ✅ Correct
        'category_id',
        'user_id'
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function files()
    {
        return $this->hasMany(File::class);
    }

    public function audits()
    {
        return $this->hasMany(Audit::class);
    }

    public function logs()
    {
        return $this->hasMany(Log::class);
    }
}