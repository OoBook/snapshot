<?php

namespace Oobook\Snapshot\Tests\TestModels;

use Illuminate\Database\Eloquent\Model;
use Oobook\Database\Eloquent\Concerns\ManageEloquent;

class File extends Model
{
    use ManageEloquent;

    protected $fillable = ['fileable_id', 'fileable_type', 'name'];

    public $timestamps = false;

    protected $table = 'files';

    public function fileable(): \Illuminate\Database\Eloquent\Relations\MorphTo
    {
        return $this->morphTo();
    }
}
