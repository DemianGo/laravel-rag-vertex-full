<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Chunk extends Model
{
    protected $fillable = ['document_id','chunk_index','content','meta'];
    protected $casts = ['meta'=>'array'];
    public function document(){ return $this->belongsTo(Document::class); }
}
