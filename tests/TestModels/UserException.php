<?php

namespace Oobook\Snapshot\Tests\TestModels;

use Illuminate\Database\Eloquent\Model;
use Oobook\Snapshot\Traits\HasSnapshot;

class UserException extends Model
{
    use HasSnapshot;

    protected $fillable = ['name'];

    protected $table = 'user_snapshots';

}