<?php

namespace Oobook\Snapshot\Tests\TestModels;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Oobook\Database\Eloquent\Concerns\ManageEloquent;

class User extends Model
{
    use ManageEloquent;

    protected $fillable = ['name', 'email', 'user_type_id'];

    protected $table = 'users';

    public function posts(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Post::class);
    }

    public function fileNames(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->morphMany(File::class, 'fileable');
    }

    public function userType(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(UserType::class);
    }

}
