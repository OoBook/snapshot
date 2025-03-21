<?php

namespace Oobook\Snapshot\Tests\TestModels;

use Illuminate\Database\Eloquent\Model;
use Oobook\Database\Eloquent\Concerns\ManageEloquent;

class UserType extends Model
{
    use ManageEloquent;

    protected $fillable = ['title', 'description'];

    public function users(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(User::class);
    }

}
