<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Document extends Model
{
    protected $fillable = ['tenant_slug','title','source','metadata'];
    public function chunks(){ return $this->hasMany(Chunk::class); }
}
